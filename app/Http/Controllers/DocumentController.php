<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Download;
use App\Models\Summary;
use App\Models\User;
use App\Services\AiSummaryService;
use App\Services\AnalysisProgressService;
use App\Services\LegalAnalysisSupportService;
use App\Services\NlpLearningDatasetService;
use App\Services\SemanticSearchService;
use App\Services\SummaryReportDeliveryService;
use App\Services\SummaryStorageService;
use App\Services\SummaryReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    private const ADMIN_EMAIL = 'mafusec@protonmail.com';

    public function __construct(
        private AnalysisProgressService $progress,
        private LegalAnalysisSupportService $support,
        private SemanticSearchService $semanticSearch,
        private SummaryReportPdfService $pdfReports
    ) {}

    /* ── Dashboard stats ─────────────────────────── */

    public function dashboard()
    {
        $user      = Auth::user();
        $docCount  = Document::where('user_id', $user->id)->where('status', 'done')->count();
        $dlCount   = Download::where('user_id', $user->id)->count();
        $recent    = Summary::with('document')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();
        $isAdmin = $this->isAdminUser($user);
        $adminAnalytics = $isAdmin ? $this->adminAnalytics() : null;

        return view('dashboard.index', compact('user', 'docCount', 'dlCount', 'recent', 'isAdmin', 'adminAnalytics'));
    }

    /* ── Upload & extract ────────────────────────── */

    private function isAdminUser(?User $user): bool
    {
        return strtolower((string) $user?->email) === self::ADMIN_EMAIL;
    }

    private function ensureAdminUser(): void
    {
        abort_unless($this->isAdminUser(Auth::user()), 403);
    }

    private function adminAnalytics(): array
    {
        $documentsByStatus = Document::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $providerMix = Summary::query()
            ->select('ai_provider', DB::raw('COUNT(*) as total'))
            ->groupBy('ai_provider')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn(Summary $summary) => [
                'label' => $this->nlpBartProviderLabel((string) $summary->ai_provider),
                'total' => (int) $summary->total,
            ]);

        return [
            'totals' => [
                'users' => User::count(),
                'legal_professionals' => User::where('role', 'Legal Professional')->count(),
                'documents' => Document::count(),
                'summaries' => Summary::count(),
                'downloads' => Download::count(),
                'documents_today' => Document::whereDate('created_at', today())->count(),
                'summaries_today' => Summary::whereDate('created_at', today())->count(),
                'documents_7d' => Document::where('created_at', '>=', now()->subDays(7))->count(),
                'summaries_7d' => Summary::where('created_at', '>=', now()->subDays(7))->count(),
                'pages' => (int) Document::sum('page_count'),
                'words' => (int) Document::sum('word_count'),
                'avg_processing_ms' => (int) round((float) Summary::whereNotNull('processing_ms')->avg('processing_ms')),
            ],
            'documents_by_status' => [
                'done' => (int) ($documentsByStatus['done'] ?? 0),
                'processing' => (int) ($documentsByStatus['processing'] ?? 0),
                'pending' => (int) ($documentsByStatus['pending'] ?? 0),
                'failed' => (int) ($documentsByStatus['failed'] ?? 0),
            ],
            'roles' => $this->topCounts(User::query(), 'role', 'Member'),
            'document_types' => $this->topCounts(Summary::query(), 'document_type', 'Unknown document'),
            'courts' => $this->topCounts(Summary::query(), 'court', 'Not stated'),
            'summary_modes' => $this->topCounts(Summary::query(), 'summary_type', 'general_user'),
            'provider_mix' => $providerMix,
            'rouge_scores' => $this->rougeScores(),
            'recent_documents' => Document::with('user')->latest()->limit(5)->get(),
            'recent_summaries' => Summary::with(['document', 'user'])->latest()->limit(5)->get(),
        ];
    }

    private function rougeScores(int $limit = 30): array
    {
        $summaries = Summary::with('document')
            ->latest()
            ->limit($limit)
            ->get();

        $totals = ['rouge_1' => 0.0, 'rouge_2' => 0.0, 'rouge_l' => 0.0];
        $count = 0;

        foreach ($summaries as $summary) {
            $source = (string) ($summary->document?->extracted_text ?? '');
            $generated = trim((string) ($summary->professional_summary ?: $summary->executive_summary ?: $summary->citizen_summary));
            if (mb_strlen($source) < 40 || mb_strlen($generated) < 20) {
                continue;
            }

            $scores = $this->rougeOverlapScores($source, $generated);
            foreach ($totals as $key => $value) {
                $totals[$key] += $scores[$key];
            }
            $count++;
        }

        return [
            'sample_size' => $count,
            'rouge_1' => $count ? round($totals['rouge_1'] / $count, 1) : null,
            'rouge_2' => $count ? round($totals['rouge_2'] / $count, 1) : null,
            'rouge_l' => $count ? round($totals['rouge_l'] / $count, 1) : null,
        ];
    }

    private function rougeOverlapScores(string $reference, string $candidate): array
    {
        $referenceTokens = $this->rougeTokens($reference);
        $candidateTokens = $this->rougeTokens($candidate);

        return [
            'rouge_1' => $this->ngramOverlapPrecision($referenceTokens, $candidateTokens, 1),
            'rouge_2' => $this->ngramOverlapPrecision($referenceTokens, $candidateTokens, 2),
            'rouge_l' => $this->rougeLPrecision($referenceTokens, $candidateTokens),
        ];
    }

    private function rougeTokens(string $text): array
    {
        preg_match_all('/[\pL\pN]+/u', mb_strtolower($text), $matches);
        return $matches[0] ?? [];
    }

    private function ngramOverlapPrecision(array $reference, array $candidate, int $n): float
    {
        $referenceNgrams = $this->ngramCounts($reference, $n);
        $candidateNgrams = $this->ngramCounts($candidate, $n);
        $candidateTotal = array_sum($candidateNgrams);
        if ($candidateTotal === 0) {
            return 0.0;
        }

        $overlap = 0;
        foreach ($candidateNgrams as $ngram => $count) {
            $overlap += min($count, $referenceNgrams[$ngram] ?? 0);
        }

        return ($overlap / $candidateTotal) * 100;
    }

    private function ngramCounts(array $tokens, int $n): array
    {
        $counts = [];
        $limit = count($tokens) - $n;
        for ($i = 0; $i <= $limit; $i++) {
            $key = implode(' ', array_slice($tokens, $i, $n));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    private function rougeLPrecision(array $reference, array $candidate): float
    {
        if (!$reference || !$candidate) {
            return 0.0;
        }

        $reference = array_slice($reference, 0, 1500);
        $candidate = array_slice($candidate, 0, 350);
        $previous = array_fill(0, count($candidate) + 1, 0);

        foreach ($reference as $referenceToken) {
            $current = [0];
            foreach ($candidate as $index => $candidateToken) {
                $current[$index + 1] = $referenceToken === $candidateToken
                    ? $previous[$index] + 1
                    : max($previous[$index + 1], $current[$index]);
            }
            $previous = $current;
        }

        return (end($previous) / count($candidate)) * 100;
    }

    private function topCounts($query, string $column, string $fallback, int $limit = 5)
    {
        return $query
            ->select($column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'label' => (string) ($row->{$column} ?: $fallback),
                'total' => (int) $row->total,
            ]);
    }

    private function nlpBartProviderLabel(string $provider): string
    {
        return match (strtolower($provider)) {
            'gemini' => 'NLP_BART',
            'nlp_local', 'nlp' => 'NLP_BART Local',
            'openai' => 'NLP_BART Assisted',
            default => 'NLP_BART',
        };
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480', // 20 MB, PDF only
        ]);

        $file        = $request->file('file');
        $storedName  = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('uploads', $storedName, 'local');

        $doc = Document::create([
            'user_id'       => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => $storedName,
            'mime_type'     => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size'     => $file->getSize(),
            'status'        => 'pending',
        ]);

        return response()->json(['success' => true, 'document_id' => $doc->id]);
    }

    public function extract(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Only PDF uploads are supported. Please use the PDF upload area.',
        ], 422);
    }

    /* ── Summarise (NLP + AI) ────────────────────── */

    public function summarise(
        Request $request,
        AiSummaryService $ai,
        SummaryStorageService $storage
    )
    {
        $request->validate([
            'document_id' => 'required|integer|exists:documents,id',
            'text'        => 'required|string|min:20',
            'word_count'  => 'nullable|integer',
            'page_count'  => 'nullable|integer',
        ]);

        $doc = Document::where('id', $request->document_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $summaryType = $this->summaryTypeForRole((string) Auth::user()?->role);

        $documentPayload = [
            'status'         => 'processing',
            'extracted_text' => $request->text,
            'word_count'     => $request->word_count,
            'page_count'     => $request->page_count,
        ];

        if (Schema::hasColumn('documents', 'summary_type')) {
            $documentPayload['summary_type'] = $summaryType;
        }

        $doc->update($documentPayload);

        try {
            $this->progress->start($doc->id);
            $this->progress->update($doc->id, 'processing', 15, 'Preparing extracted text...');

            if (!$doc->extracted_text || mb_strlen(trim($doc->extracted_text)) < 20) {
                $doc->update(['status' => 'failed']);
                $this->progress->update($doc->id, 'failed', 100, 'Analysis failed.', 'No extracted text was available for analysis.');

                return response()->json([
                    'success' => false,
                    'message' => 'No extracted text was available for analysis.',
                ], 422);
            }

            $this->progress->update($doc->id, 'processing', 40, 'Running legal NLP and summary analysis...');
            $result = $ai->analyse($doc->extracted_text, $doc->original_name, $summaryType);

            $this->progress->update($doc->id, 'processing', 80, 'Saving legal insight summary...');
            $summary = $storage->store($doc, $result);

            $this->progress->update($doc->id, 'processing', 90, 'Finalising summary...');

            $doc->update(['status' => 'done']);
            $this->progress->update($doc->id, 'done', 100, 'Analysis complete.');

            return response()->json([
                'success' => true,
                'queued' => false,
                'document_id' => $doc->id,
                'summary_id' => $summary->id,
                'summary' => $this->formatSummary($summary, $doc),
                'message' => 'Analysis completed without queueing.',
            ]);
        } catch (\Throwable $e) {
            $doc->update(['status' => 'failed']);
            $this->progress->update($doc->id, 'failed', 100, 'Analysis failed.', 'The document could not be analysed directly.');
            Log::error('Direct summarise error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Analysis could not be completed directly.',
            ], 500);
        }
    }

    public function analysisStatus(int $id)
    {
        $doc = Document::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $payload = [
            'success' => true,
            'status' => $doc->status,
            'progress' => $this->progress->get($doc->id),
        ];

        if ($doc->status === 'done') {
            $summary = Summary::with('document')
                ->where('document_id', $doc->id)
                ->where('user_id', Auth::id())
                ->first();

            if ($summary) {
                $payload['summary'] = $this->formatSummary($summary, $doc);
                $payload['summary_id'] = $summary->id;
            }
        }

        return response()->json($payload);
    }

    /* ── Records list ────────────────────────────── */

    public function records(Request $request)
    {
        $query = Summary::with('document')
            ->where('summaries.user_id', Auth::id());

        if ($type = $request->get('type')) {
            $query->where('document_type', 'like', "%{$type}%");
        }
        if ($court = $request->get('court')) {
            $query->where('court', 'like', "%{$court}%");
        }

        $records = $query->latest('summaries.created_at')->get();
        $formatted = $records->map(fn($summary) => $this->formatSummary($summary, $summary->document))->values()->all();

        if ($issue = trim((string) $request->get('issue'))) {
            $formatted = array_values(array_filter($formatted, function (array $summary) use ($issue): bool {
                $issues = array_map('mb_strtolower', $summary['structured_panels']['key_legal_issues'] ?? []);

                return in_array(mb_strtolower($issue), $issues, true)
                    || str_contains(mb_strtolower(implode(' ', $issues)), mb_strtolower($issue));
            }));
        }

        if ($search = trim((string) $request->get('search'))) {
            $formatted = $this->semanticSearch->rank($search, $formatted);
        }

        if ($request->expectsJson()) {
            return response()->json($formatted);
        }

        $records = collect($formatted);

        return view('dashboard.records', compact('records'));
    }

    /* ── Single summary detail ───────────────────── */

    public function show(int $id)
    {
        $summary = Summary::with('document')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json($this->formatSummary($summary, $summary->document));
    }

    public function export(int $id, string $format)
    {
        $summary = Summary::with('document')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $format = strtolower($format);
        if ($format !== 'pdf') {
            abort(404);
        }

        return view('downloads.started', [
            'summary' => $summary,
            'downloadUrl' => route('documents.export.file', ['id' => $summary->id, 'format' => $format]),
            'dashboardUrl' => route('dashboard'),
        ]);
    }

    public function downloadExport(int $id, string $format)
    {
        $summary = Summary::with('document')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $format = strtolower($format);
        if ($format !== 'pdf') {
            abort(404);
        }

        $path = $this->pdfReports->generate($summary, $summary->document);

        Download::create([
            'user_id' => Auth::id(),
            'summary_id' => $summary->id,
        ]);

        $absolutePath = Storage::disk('local')->path($path);
        $downloadName = Str::slug(pathinfo($summary->document?->original_name ?? ('summary_' . $summary->id), PATHINFO_FILENAME))
            . '_justconnect.' . $format;

        return response()->download($absolutePath, $downloadName);
    }

    /* ── Downloads list ──────────────────────────── */

    public function emailSummary(int $id, SummaryReportDeliveryService $reports)
    {
        $summary = Summary::with('document')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $user = Auth::user();

        try {
            $sent = $reports->deliver($summary, $summary->document, $user);
        } catch (\Throwable $e) {
            Log::warning('JustConnect: requested summary email delivery failed.', [
                'summary_id' => $summary->id,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'We could not send the summary email right now. Please try again shortly.',
            ], 500);
        }

        if (!$sent) {
            return response()->json([
                'success' => false,
                'message' => 'Email delivery is not configured yet. Your PDF report was prepared, but it could not be sent.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Summary sent to ' . $user->email . '.',
            'email' => $user->email,
        ]);
    }

    public function downloads()
    {
        $downloads = Download::with(['summary.document'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        if (request()->expectsJson()) {
            return response()->json($downloads->map(fn($d) => [
                'id'            => $d->id,
                'document_name' => $d->summary?->document?->original_name,
                'document_type' => $d->summary?->document_type,
                'downloaded_at' => $d->downloaded_at?->format('d M Y H:i'),
                'summary_id'    => $d->summary_id,
            ]));
        }

        return view('dashboard.downloads', compact('downloads'));
    }

    /* ── Log a download ──────────────────────────── */

    public function adminDataset(Request $request, NlpLearningDatasetService $dataset)
    {
        $this->ensureAdminUser();

        $limit = (int) $request->integer('limit', 100);

        return response()->json([
            'total' => $dataset->totalRows(),
            'rows' => $dataset->tableRows($limit),
        ]);
    }

    public function exportAdminDataset(NlpLearningDatasetService $dataset)
    {
        $this->ensureAdminUser();

        $filename = 'justconnect_dataset_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($dataset): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            $dataset->writeCsv($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function logDownload(Request $request)
    {
        $request->validate(['summary_id' => 'required|integer|exists:summaries,id']);

        Summary::where('id', $request->summary_id)->where('user_id', Auth::id())->firstOrFail();

        Download::create([
            'user_id'    => Auth::id(),
            'summary_id' => $request->summary_id,
        ]);

        return response()->json(['success' => true]);
    }

    /* ── Delete document ─────────────────────────── */

    public function destroy(int $id)
    {
        $doc = Document::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        Storage::disk('local')->delete('uploads/' . $doc->stored_name);
        $this->progress->clear($doc->id);
        $doc->delete();
        return response()->json(['success' => true]);
    }

    /* ── Profile update ──────────────────────────── */

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name'   => 'required|string|max:80',
            'last_name'    => 'required|string|max:80',
            'organisation' => 'nullable|string|max:191',
            'role'         => 'nullable|string|in:Legal Professional,Law Student,Researcher,Business Owner,Other',
        ]);
        Auth::user()->update($data);
        return response()->json(['success' => true]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        Auth::user()->update(['password' => \Illuminate\Support\Facades\Hash::make($request->password)]);
        return response()->json(['success' => true]);
    }

    public function updateMfa(Request $request)
    {
        $data = $request->validate([
            'mfa_enabled' => 'required|boolean',
            'mfa_channel' => 'required|string|in:email',
            'mfa_security_question' => 'nullable|string|max:191',
            'mfa_security_answer' => 'nullable|string|min:3|max:191',
        ]);

        $payload = [
            'mfa_enabled' => $data['mfa_enabled'],
            'mfa_channel' => $data['mfa_channel'],
        ];

        if (array_key_exists('mfa_security_question', $data)) {
            $payload['mfa_security_question'] = trim((string) $data['mfa_security_question']) ?: null;
        }

        if (!empty($data['mfa_security_answer'])) {
            $payload['mfa_security_answer_hash'] = Hash::make($this->normaliseSecurityAnswer($data['mfa_security_answer']));
        }

        Auth::user()->update($payload);

        return response()->json([
            'success' => true,
            'message' => $data['mfa_enabled']
                ? 'Multifactor authentication is enabled for email verification codes.'
                : 'Multifactor authentication is turned off.',
        ]);
    }

    private function normaliseSecurityAnswer(string $answer): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $answer)));
    }

    /* ── Internal helper ─────────────────────────── */

    private function summaryTypeForRole(string $role): string
    {
        return in_array($role, ['Legal Professional', 'Law Student', 'Researcher'], true)
            ? 'legal_professional'
            : 'general_user';
    }

    private function formatSummary(Summary $s, ?Document $doc): array
    {
        $executiveSummary = trim((string) $s->executive_summary);
        $professionalSummary = trim((string) ($s->professional_summary ?: $s->executive_summary));
        $citizenSummary = trim((string) $s->citizen_summary);
        $summaryText = $executiveSummary !== '' ? $executiveSummary : $professionalSummary;
        $entities = $this->decodeJson($s->nlp_entities);
        $structuredPanels = $this->decodeJson($s->structured_panels);
        $structuredExtraction = is_array($structuredPanels['structured_extraction'] ?? null)
            ? $structuredPanels['structured_extraction']
            : [];
        $clauses = is_array($structuredExtraction['clauses'] ?? null)
            ? $structuredExtraction['clauses']
            : (is_array($structuredPanels['key_clauses'] ?? null) ? $structuredPanels['key_clauses'] : []);
        $resultCards = $this->decodeJson($s->result_cards);
        $supportingPassages = $this->decodeJson($s->supporting_passages);
        $sourceMap = $this->decodeJson($s->source_map);
        $semanticProfile = $this->decodeJson($s->semantic_profile);
        $parties = $this->decodeJson($s->parties);
        $obligations = $this->decodeJson($s->key_obligations);
        $keywords = $this->decodeJson($s->nlp_keywords);
        $categories = $this->decodeJson($s->nlp_legal_categories);

        $supportPayload = $this->support->build((string) ($doc?->extracted_text ?? ''), [
            'document_type' => $s->document_type,
            'case_number' => $s->case_number,
            'parties' => $parties,
            'date_of_document' => $s->date_of_document,
            'court' => $s->court,
            'judge' => $s->judge,
            'executive_summary' => $s->executive_summary,
            'professional_summary' => $s->professional_summary,
            'citizen_summary' => $s->citizen_summary,
            'key_findings' => $s->key_findings,
            'key_obligations' => $obligations,
            'outcome' => $s->outcome,
            'practical_implications' => $s->practical_implications,
            'nlp_entities' => $entities,
        ]);

        $payload = [
            'id'               => $s->id,
            'document_id'      => $doc?->id,
            'summary_type'     => $s->summary_type ?? $doc?->summary_type ?? 'general_user',
            'document_name'    => $doc?->original_name,
            'file_size'        => $doc?->file_size,
            'word_count'       => $doc?->word_count,
            'page_count'       => $doc?->page_count,
            'document_type'    => $s->document_type,
            'case_number'      => $s->case_number,
            'parties'          => $parties,
            'date_of_document' => $s->date_of_document,
            'court'            => $s->court,
            'judge'            => $s->judge,
            'entities_involved'=> $this->entitiesInvolved($s),
            'legal_entities'   => $entities['labels'] ?? [],
            'nlp_entities'     => $entities,
            'nlp_keywords'     => $keywords,
            'nlp_legal_categories' => $categories,
            'summary'          => $summaryText,
            'executive_summary'=> $executiveSummary !== '' ? $executiveSummary : $professionalSummary,
            'professional_summary' => $professionalSummary,
            'citizen_summary'  => $citizenSummary,
            'key_findings'     => $s->key_findings,
            'key_obligations'  => $obligations,
            'legal_principles' => $s->legal_principles,
            'outcome'          => $s->outcome,
            'practical_implications' => $s->practical_implications,
            'result_cards'     => $resultCards,
            'structured_panels'=> $structuredPanels,
            'structured_extraction' => $structuredExtraction,
            'clauses'          => $clauses,
            'supporting_passages' => $supportingPassages,
            'source_map'       => $sourceMap,
            'semantic_profile' => $semanticProfile,
            'supporting_evidence' => $supportPayload,
            'original_text'    => (string) ($doc?->extracted_text ?? ''),
            'pdf_available'    => $s->pdf_path !== null,
            'created_at'       => $s->created_at?->format('d M Y H:i'),
        ];

        return $payload;
    }

    private function entitiesInvolved(Summary $summary): array
    {
        $entities = $this->decodeJson($summary->nlp_entities);
        $people = is_array($entities['persons'] ?? null) ? $entities['persons'] : [];
        $organisations = is_array($entities['organisations'] ?? null) ? $entities['organisations'] : [];
        $labelledPeople = is_array($entities['labels']['PERSON'] ?? null) ? $entities['labels']['PERSON'] : [];
        $labelledOrganisations = is_array($entities['labels']['ORGANISATION'] ?? null) ? $entities['labels']['ORGANISATION'] : [];
        $fallbackParties = $this->decodeJson($summary->parties);

        $values = array_merge(
            $people,
            $organisations,
            $labelledPeople,
            $labelledOrganisations,
            is_array($fallbackParties) ? $fallbackParties : []
        );
        $seen = [];
        $cleaned = [];

        foreach ($values as $value) {
            $name = preg_replace('/\s+/u', ' ', trim((string) $value));
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $cleaned[] = $name;
        }

        return array_slice($cleaned, 0, 8);
    }

    private function decodeJson(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
