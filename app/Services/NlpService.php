<?php

namespace App\Services;

/**
 * NlpService - Pure-PHP NLP pipeline for Zimbabwean legal documents.
 *
 * Capabilities:
 *   - Named-entity recognition (persons, organisations, dates, amounts, courts)
 *   - Sentence-aware keyword extraction
 *   - Document-type classification
 *   - Extractive summarisation with legal cue weighting
 *   - Obligation / provision detection
 *   - Readability scoring (Flesch-Kincaid)
 *   - Sentiment heuristic
 *   - Key metadata extraction (case no., judge, parties, date)
 */
class NlpService
{
    private const SENTENCE_PLACEHOLDER = '<prd>';

    private const STOP_WORDS = [
        'the','a','an','and','or','but','in','on','at','to','for','of','with',
        'by','from','as','is','was','are','were','be','been','being','have',
        'has','had','do','does','did','will','would','could','should','may',
        'might','shall','this','that','these','those','it','its','he','she',
        'they','we','you','i','his','her','their','our','your','my','not',
        'no','nor','so','yet','both','either','neither','each','every','any',
        'all','few','more','most','other','some','such','than','then','there',
        'when','where','which','who','whom','whose','why','how','if','unless',
        'until','while','since','after','before','between','into','through',
        'during','upon','about','above','below','over','under',
        'applicant','respondent','respondents','plaintiff','defendant','court',
        'judgment','judgement','case','matter','section','sections','subsection',
        'subsections','chapter','paragraph','paragraphs','order','ordered',
        'application','appeal','affidavit','annexure','thereof','therein',
        'whereof','hereby','said','justice','judge','honourable',
    ];

    private ?NlpAdaptiveLearningService $adaptiveLearning;

    public function __construct(?NlpAdaptiveLearningService $adaptiveLearning = null)
    {
        $this->adaptiveLearning = $adaptiveLearning;
    }

    public function analyse(string $text, string $filename = ''): array
    {
        $startMs = (int) round(microtime(true) * 1000);

        $clean = $this->clean($text);
        $sentences = $this->sentences($clean);
        $tokens = $this->tokenise($clean);
        $wordCount = count($tokens);

        $docType = $this->classifyDocType($clean, $filename);
        $entities = $this->extractEntities($clean);
        $keywords = $this->tfidfKeywords($clean, $tokens, $docType);
        $metaFields = $this->extractMeta($clean, $docType);
        $legalReferences = $this->extractLegalReferences($clean);
        $clauses = $this->extractClauses($clean, $docType);
        $obligations = $this->extractObligations($sentences);
        $topSents = $this->extractiveSummary($sentences, $keywords, 3);
        $findings = $this->extractiveSummary($sentences, $keywords, 5);
        $outcome = $this->detectOutcome($clean, $sentences, $docType);
        $principles = $this->extractLegalPrinciples($sentences);
        $implications = $this->practicalImplications($clean, $docType);
        $readability = $this->readability($tokens, $sentences);
        $sentiment = $this->sentiment($tokens);
        $categories = $this->legalCategories($clean);
        $language = $this->detectLanguage($tokens);

        $execSummary = $this->buildExecutiveSummary(
            $clean,
            $docType,
            $topSents,
            $metaFields,
            $entities,
            $outcome,
            $obligations,
            $legalReferences,
            $categories,
            $implications
        );
        $keyFindings = $this->buildKeyFindings($clean, $docType, $findings);
        if ($execSummary === '') {
            $execSummary = 'Legal document analysed: ' . $docType . '. '
                . number_format($wordCount) . ' words processed via NLP pipeline.';
        }

        $endMs = (int) round(microtime(true) * 1000);

        return [
            'document_type' => $docType,
            'case_number' => $metaFields['case_number'] ?? null,
            'parties' => $metaFields['parties'] ?? [],
            'date_of_document' => $metaFields['date'] ?? null,
            'court' => $metaFields['court'] ?? null,
            'judge' => $metaFields['judge'] ?? null,
            'executive_summary' => $execSummary,
            'key_findings' => $keyFindings,
            'key_obligations' => $obligations,
            'legal_principles' => $principles ?: null,
            'outcome' => $outcome,
            'practical_implications' => $implications,
            'legal_references' => $legalReferences,
            'clauses' => $clauses,
            'structured_extraction' => [
                'document_type' => $docType,
                'entities' => [
                    'court' => $metaFields['court'] ?? '',
                    'judge' => $metaFields['judge'] ?? '',
                    'parties' => $metaFields['parties'] ?? [],
                    'dates' => $entities['dates'] ?? [],
                    'money_amounts' => $entities['amounts'] ?? [],
                    'laws_cited' => $legalReferences,
                ],
                'clauses' => $clauses,
            ],
            'nlp_entities' => $entities,
            'nlp_keywords' => array_keys($keywords),
            'nlp_sentiment' => $sentiment,
            'nlp_readability' => $readability,
            'nlp_language' => $language,
            'nlp_legal_categories' => $categories,
            'ai_provider' => 'nlp_local',
            'processing_ms' => $endMs - $startMs,
        ];
    }

    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\u{00AD}", '', $text);
        $text = str_replace(
            ['Ã¢â‚¬â€', 'Ã¢â‚¬â€œ', 'Ã¢â‚¬Ëœ', 'Ã¢â‚¬â„¢', 'Ã¢â‚¬Å“', 'Ã¢â‚¬Â', 'Ã¢â‚¬Â¦', "\t", '“', '”', '‘', '’', '–', '—', '•'],
            ['-', '-', "'", "'", '"', '"', '...', ' ', '"', '"', "'", "'", '-', '-', ' '],
            $text
        );
        $text = preg_replace('/(?<=\p{L})-\s*\n(?=\p{L})/u', '', $text);
        $text = preg_replace('/\[(?:Chapter\s*)?([0-9]{1,2}:[0-9]{2})\]/iu', ' Chapter $1 ', (string) $text);
        $text = preg_replace('/,([^\s])/u', ', $1', (string) $text);
        $text = preg_replace('/(?:^|\n)\s*[-]{2,}\s*PAGE\s+\d+\s*[-]{2,}\s*(?=\n|$)/iu', "\n\n", $text);
        $text = preg_replace('/[ \x{00A0}]{2,}/u', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim((string) $text);
    }

    private function sentences(string $text): array
    {
        $protected = preg_replace(
            '/\b(Mr|Mrs|Ms|Dr|Adv|Prof|Hon|Rev|Jr|Sr|No|Nos|Sec|Art|Chap|Para|Paras|pp|vs|v|J)\./iu',
            '$1' . self::SENTENCE_PLACEHOLDER,
            $text
        );
        $chunks = preg_split('/(?<=[.!?])(?:["\')\]]+)?\s+|(?:\n){2,}/u', (string) $protected, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map(
            fn($sentence) => trim((string) preg_replace('/\s+/u', ' ', str_replace(self::SENTENCE_PLACEHOLDER, '.', (string) $sentence))),
            $chunks ?: []
        ), static fn($sentence) => mb_strlen($sentence) > 25));
    }

    private function tokenise(string $text): array
    {
        preg_match_all("/\p{L}[\p{L}\p{Mn}'’-]{1,}/u", mb_strtolower($text), $matches);

        return array_values(array_filter(array_map(function ($token) {
            return trim((string) $token, "-'’");
        }, $matches[0] ?? [])));
    }

    private function classifyDocType(string $text, string $filename): string
    {
        $titleSignal = mb_strtolower($this->documentTitleSignal($text, $filename));
        $haystack = mb_strtolower($text . "\n" . $titleSignal);
        $patterns = [
            'Bill' => [
                '/\b(this bill seeks to|bill is intended to|be it enacted|parliament of zimbabwe|gazetted as a bill)\b/u' => 2.4,
                '/\b(?:constitution(?: of zimbabwe)? amendment|finance|electoral|criminal|labour|appropriation)[\w\s(),.-]{0,80}\bbill\b/u' => 3.2,
                '/\b(?:amendment of section|insertion of new section|repeal of section|substitution of section)\b/u' => 1.4,
                '/\bthis act may be cited as[\w\s(),.-]{0,120}\bbill\b/u' => 4.0,
            ],
            'Court Judgment' => [
                '/(?:^|\n)\s*(?:in\s+the\s+)?(?:high|supreme|constitutional|labour|magistrates?|administrative|commercial)\s+court(?:\s+of\s+zimbabwe)?\b/u' => 3.2,
                '/\b(?:hch|hcc|hh|hc|sc|ccz?|zwcc|zwsc|zwhhc)\s*\d{1,6}(?:\/\d{2,4}|-\d{2,4})?\b/u' => 2.8,
                '/\b(held that|court finds|it is ordered|court orders|judgment|judgement|ruling|appeal dismissed|application is dismissed|application is granted)\b/u' => 2.0,
                '/\b(applicant|respondent|plaintiff|defendant|appellant|coram|before:)\b/u' => 0.7,
            ],
            'Act' => [
                '/\b(this act may be cited as[\w\s(),.-]{0,120}\bact|act no\.?\s*\d+|arrangement of sections|date of commencement|enacted by the parliament|assented to|commencement)\b/u' => 3.0,
                '/\b(any person who|shall be guilty|subject to this act|minister may make regulations|first schedule|chapter\s+\d{1,2}:\d{2})\b/u' => 1.8,
                '/\b(shall be liable|convicted|fined|imprisonment|offence|offense|penalt(?:y|ies))\b/u' => 1.4,
            ],
            'Statutory Instrument' => [
                '/\b(statutory instrument|s\.?\s*i\.?\s*\d+\s+of\s+\d{4}|si\s+\d+\s+of\s+\d{4})\b/u' => 3.4,
                '/\b(it is hereby notified|gazetted|regulations,\s*\d{4}|by-laws,\s*\d{4}|in terms of section[\w\s(),.-]{0,80}\bact)\b/u' => 2.0,
            ],
            'Lease Agreement' => [
                '/\b(lease agreement|deed of lease|rental agreement|tenancy agreement)\b/u' => 3.2,
                '/\b(tenant|landlord|premises|rent|lease term|security deposit|monthly rental|lessee|lessor|property let|permitted use)\b/u' => 1.7,
            ],
            'Employment Contract' => [
                '/\b(employment contract|contract of employment|employment agreement)\b/u' => 3.4,
                '/\b(employee|employer|gross monthly salary|salary|wages|probation|leave entitlement|notice period|disciplinary|grievance|nssa|report for duty)\b/u' => 1.6,
            ],
            'Sale Agreement' => [
                '/\b(sale agreement|agreement of sale|deed of sale|sale of shares|sale of immovable property)\b/u' => 3.3,
                '/\b(seller|buyer|vendor|purchaser|purchase price|transfer of ownership|delivery of goods|warranties|voetstoots)\b/u' => 1.5,
            ],
            'Loan Agreement' => [
                '/\b(loan agreement|credit agreement|facility agreement|promissory note)\b/u' => 3.3,
                '/\b(borrower|lender|principal amount|interest rate|repayment schedule|instalments|collateral|security for the loan|event of default)\b/u' => 1.6,
            ],
            'Shareholder Agreement' => [
                '/\b(shareholders? agreement|subscription agreement|share purchase agreement)\b/u' => 3.2,
                '/\b(shareholder|share capital|ordinary shares|board of directors|voting rights|dividends|transfer of shares|reserved matters|pre-emptive rights)\b/u' => 1.5,
            ],
            'Power of Attorney' => [
                '/\b(power of attorney|special power of attorney|general power of attorney)\b/u' => 4.0,
                '/\b(donor|donee|principal|appoints?[\w\s]{0,40}attorney|act on (?:my|his|her|their) behalf|sign documents on (?:my|his|her|their) behalf)\b/u' => 1.7,
            ],
            'Will and Testament' => [
                '/\b(last will and testament|will and testament|codicil)\b/u' => 4.0,
                '/\b(testator|testatrix|executor|executrix|beneficiar(?:y|ies)|bequeath|devise|estate|residue of (?:my|the) estate|heirs)\b/u' => 1.7,
            ],
            'Contract Agreement' => [
                '/\b(the parties agree|parties agree|confidentiality|breach|indemnity|liability|termination|agreement|contract|whereas|consideration|governing law)\b/u' => 1.5,
                '/\b(obligations of the parties|payment terms|dispute resolution|force majeure|entire agreement|execution by the parties)\b/u' => 1.4,
            ],
        ];

        $scores = [];

        foreach ($patterns as $label => $weightedPatterns) {
            $scores[$label] = $this->scoreWeightedPatterns($haystack, $weightedPatterns);
        }

        $titleScores = [
            'Bill' => $this->scoreWeightedPatterns($titleSignal, [
                '/(?:^|\n)\s*[\p{L}\d ,&()\'\/.-]{3,180}\bbill\b(?:\s*(?:no\.?\s*\d+|,?\s*\d{4}|\([^)]*\)))?\s*(?=$|\n)/u' => 5.0,
            ]),
            'Act' => $this->scoreWeightedPatterns($titleSignal, [
                '/(?:^|\n)\s*(?:the\s+)?[\p{L}\d ,&()\'\/.-]{3,180}\bact\b(?:\s*(?:\[chapter\s+\d{1,2}:\d{2}\]|chapter\s+\d{1,2}:\d{2}|no\.?\s*\d+|,?\s*\d{4}|\([^)]*\)))?\s*(?=$|\n)/u' => 5.0,
            ]),
            'Statutory Instrument' => $this->scoreWeightedPatterns($titleSignal, [
                '/(?:^|\n)\s*(?:statutory instrument|s\.?\s*i\.?)\s*\d+\s+of\s+\d{4}\b.*(?=$|\n)/u' => 5.0,
            ]),
            'Court Judgment' => $this->scoreWeightedPatterns($titleSignal, [
                '/(?:^|\n)\s*(?:in\s+the\s+)?(?:high|supreme|constitutional|labour|magistrates?|administrative|commercial)\s+court(?:\s+of\s+zimbabwe)?\s*(?=$|\n)/u' => 4.0,
                '/(?:^|\n)\s*[\p{L}\d ,&()\'\/.-]{3,180}\b(?:judgment|judgement|ruling)\b.*(?=$|\n)/u' => 4.0,
                '/\b(?:hch|hcc|hh|hc|sc|ccz?|zwcc|zwsc|zwhhc)\s*\d{1,6}(?:\/\d{2,4}|-\d{2,4})?\b/u' => 3.5,
            ]),
        ];

        foreach ($titleScores as $label => $score) {
            $scores[$label] = ($scores[$label] ?? 0.0) + $score;
        }

        if (($scores['Bill'] ?? 0) >= 4.0 && ($scores['Bill'] ?? 0) >= max($scores['Act'] ?? 0, $scores['Statutory Instrument'] ?? 0) - 0.4) {
            return 'Bill';
        }

        if (($scores['Statutory Instrument'] ?? 0) >= 4.0 && ($scores['Statutory Instrument'] ?? 0) >= (($scores['Act'] ?? 0) - 0.4)) {
            return 'Statutory Instrument';
        }

        if (($titleScores['Act'] ?? 0) >= 5.0 && ($titleScores['Bill'] ?? 0) < 5.0 && ($titleScores['Statutory Instrument'] ?? 0) < 5.0) {
            return 'Act';
        }

        if (($scores['Court Judgment'] ?? 0) >= 4.0) {
            return 'Court Judgment';
        }

        $specificContracts = array_intersect_key($scores, array_flip([
            'Lease Agreement',
            'Employment Contract',
            'Sale Agreement',
            'Loan Agreement',
            'Shareholder Agreement',
            'Power of Attorney',
            'Will and Testament',
        ]));
        arsort($specificContracts);
        $specificLabel = array_key_first($specificContracts);
        $specificScore = $specificLabel ? (float) $specificContracts[$specificLabel] : 0.0;
        if ($specificScore >= 3.2 && $specificScore >= (($scores['Contract Agreement'] ?? 0) - 1.0)) {
            return $specificLabel;
        }

        $bestLabel = 'Legal Document';
        $bestScore = 0.0;
        foreach ($scores as $label => $score) {
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLabel = $label;
            }
        }

        return $bestScore >= 2.4 ? $bestLabel : 'Legal Document';
    }

    private function documentTitleSignal(string $text, string $filename): string
    {
        $signals = [];
        $filenameTitle = $this->normaliseTitleLine($filename, true);
        if ($filenameTitle !== '') {
            $signals[] = $filenameTitle;
        }

        $header = mb_substr($text, 0, 3000);
        $lines = preg_split('/\R/u', $header, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (array_slice($lines, 0, 18) as $line) {
            $title = $this->normaliseTitleLine($line);
            if ($title === '' || $this->shouldIgnoreTitleLine($title)) {
                continue;
            }

            if (
                preg_match('/\b(act|bill|statutory instrument|s\.?\s*i\.?|agreement|contract|lease|power of attorney|will and testament|judgment|judgement|ruling|court)\b/iu', $title)
                || $title === mb_strtoupper($title)
            ) {
                $signals[] = $title;
            }
        }

        return implode("\n", array_values(array_unique($signals)));
    }

    private function normaliseTitleLine(string $value, bool $isFilename = false): string
    {
        $value = trim($value);
        if ($isFilename) {
            $value = preg_replace('/\.[a-z0-9]{2,6}$/iu', '', $value);
            $value = preg_replace('/[_-]+/u', ' ', (string) $value);
        }

        $value = preg_replace('/\s+/u', ' ', (string) $value);
        $value = trim((string) $value, " \t\n\r\0\x0B,.;:-");

        return mb_strlen($value) <= 180 ? $value : '';
    }

    private function shouldIgnoreTitleLine(string $line): bool
    {
        return preg_match('/^(?:page\s+\d+|\d+|harare|bulawayo|mutare|gweru|masvingo|between|and)$/iu', $line) === 1;
    }

    private function scoreWeightedPatterns(string $haystack, array $weightedPatterns): float
    {
        $score = 0.0;

        foreach ($weightedPatterns as $regex => $weight) {
            $matches = preg_match_all($regex, $haystack);
            if ($matches !== false && $matches > 0) {
                $score += min(4, $matches) * (float) $weight;
            }
        }

        return $score;
    }

    private function extractEntities(string $text): array
    {
        $entities = [
            'persons' => [],
            'organisations' => [],
            'dates' => [],
            'amounts' => [],
            'courts' => [],
        ];

        $dates = [];
        $datePatterns = [
            '/\b(\d{1,2}(?:st|nd|rd|th)?\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})\b/iu',
            '/\b((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})\b/iu',
            '/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\b/u',
        ];
        foreach ($datePatterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $dates = array_merge($dates, $matches[1] ?? []);
        }
        $entities['dates'] = array_values(array_unique($dates));

        preg_match_all('/\b(USD|US\$|ZWL|ZWL\$|\$)\s*([0-9]{1,3}(?:\s*,\s*[0-9]{3})+|[0-9]+)(?:\.\d{2})?\b/iu', $text, $amounts, PREG_SET_ORDER);
        $entities['amounts'] = array_values(array_unique(array_map(
            fn ($match) => trim($match[1] . ' ' . preg_replace('/\s*,\s*/u', ',', $match[2])),
            $amounts
        )));

        if ($court = $this->extractCourtName($text)) {
            $entities['courts'][] = $court;
        }
        $entities['courts'] = array_values(array_unique($entities['courts']));

        preg_match_all('/([A-Z][A-Za-z0-9,&\'\-\s()]{3,120}(?:Limited|Ltd|Pvt(?:\s+Ltd)?|Holdings|Corporation|Inc|Group|Trust|Foundation|Authority|Services|Agency|Commission|Committee|Council|University|Bank|Ministry|Department))/u', $text, $orgs1);
        preg_match_all('/\b(Mining Commissioner(?:\s+[A-Z][A-Za-z]+){0,4}|Environmental Management Agency|Minister of [A-Z][A-Za-z\s]+|Registrar of Deeds|Master of the High Court|Zimbabwe Revenue Authority|Zimbabwe Electoral Commission|Zimbabwe Electoral Delimitation Commission|Zimbabwe Republic Police|Reserve Bank of Zimbabwe|National Prosecuting Authority|Judicial Service Commission|JustConnect Legal Services)\b/u', $text, $orgs2);
        preg_match_all('/(?:^|\n)([A-Z][A-Z\s,&\'\-\(\)]{5,120}(?:LIMITED|LTD|PVT(?:\s+LTD)?|GROUP|TRUST|AUTHORITY|AGENCY|COMMISSION|SERVICES|COUNCIL|UNIVERSITY|BANK|MINISTRY|DEPARTMENT))(?:\n|$)/u', $text, $orgs3);

        $entities['organisations'] = $this->uniqueTop(array_map(
            fn($name) => $this->normaliseEntityName($name),
            array_merge($orgs1[1] ?? [], $orgs2[1] ?? [], $orgs3[1] ?? [])
        ), 12);

        preg_match_all('/(?:Justice|Judge|Honourable|Mr|Mrs|Ms|Dr|Adv|Prof)\s+([A-Z][A-Za-z\-]+(?:\s+[A-Z][A-Za-z\-]+){0,3})/u', $text, $people1);
        preg_match_all('/\b([A-Z][A-Za-z\-]+(?:\s+[A-Z][A-Za-z\-]+){0,3})\s+J(?:A|P)?\b/u', $text, $people2);
        preg_match_all('/(?:^|\n)\s*([A-Z][A-Z\-]+(?:\s+[A-Z][A-Z\-]+){0,3})\s+J(?:A|P)?(?:\s|\n|$)/u', $text, $people3);
        preg_match_all('/\b(?:before|coram)\s*[:\-]?\s*([A-Z][A-Za-z\-]+(?:\s+[A-Z][A-Za-z\-]+){0,3})/iu', $text, $people4);
        $entities['persons'] = $this->uniqueTop(array_map(
            fn($name) => $this->normalisePersonName($name),
            array_merge($people1[1] ?? [], $people2[1] ?? [], $people3[1] ?? [], $people4[1] ?? [])
        ), 10);

        return $entities;
    }

    private function tfidfKeywords(string $text, array $tokens, string $docType = ''): array
    {
        $stopWords = array_flip(self::STOP_WORDS);
        $termFreq = [];

        foreach ($tokens as $token) {
            if (!$this->isWeakKeywordToken($token, $stopWords)) {
                $termFreq[$token] = ($termFreq[$token] ?? 0) + 1;
            }
        }

        $sentences = $this->sentences($text);
        $sentenceCount = max(1, count($sentences));
        $docFreq = [];

        foreach ($sentences as $sentence) {
            $sentenceTokens = $this->tokenise($sentence);
            $seenInSentence = [];

            foreach ($sentenceTokens as $word) {
                if (!$this->isWeakKeywordToken($word, $stopWords)) {
                    $seenInSentence[$word] = true;
                }
            }

            foreach ($this->candidateNgrams($sentenceTokens, $stopWords) as $ngram => $boost) {
                $termFreq[$ngram] = ($termFreq[$ngram] ?? 0) + $boost;
                $seenInSentence[$ngram] = true;
            }

            foreach (array_keys($seenInSentence) as $word) {
                $docFreq[$word] = ($docFreq[$word] ?? 0) + 1;
            }
        }

        $scores = [];
        foreach ($termFreq as $word => $tf) {
            $idf = log((1 + $sentenceCount) / (1 + ($docFreq[$word] ?? 1))) + 1;
            $phraseBoost = str_contains($word, ' ') ? 1.35 : 1.0;
            $legalBoost = $this->isLegalKeywordPhrase($word) ? 1.2 : 1.0;
            $scores[$word] = round($tf * $idf * $phraseBoost * $legalBoost, 4);
        }

        foreach ($this->adaptiveKeywordBoosts($text) as $term => $boost) {
            $scores[$term] = round(($scores[$term] ?? 0) + (float) $boost, 4);
        }

        if ($this->isLegislativeDocument($docType)) {
            foreach ($this->openingKeywordBoosts($text, 20) as $term => $boost) {
                $scores[$term] = round(($scores[$term] ?? 0) + $boost, 4);
            }
        }

        arsort($scores);

        return array_slice($scores, 0, 20, true);
    }

    private function openingKeywordBoosts(string $text, int $wordLimit): array
    {
        $stopWords = array_flip(self::STOP_WORDS);
        $opening = preg_replace('/\s+/u', ' ', trim(mb_substr($text, 0, 1200)));
        $tokens = array_values(array_filter(
            $this->tokenise((string) $opening),
            fn ($token) => in_array($token, ['act', 'bill'], true) || !$this->isWeakKeywordToken($token, $stopWords)
        ));
        $tokens = array_slice($tokens, 0, $wordLimit);

        $boosts = [];
        foreach ($tokens as $index => $token) {
            $boosts[$token] = max($boosts[$token] ?? 0, 5.0 - min($index, 12) * 0.18);
        }

        $tokenCount = count($tokens);
        for ($size = 2; $size <= 3; $size++) {
            for ($i = 0; $i <= $tokenCount - $size; $i++) {
                $slice = array_slice($tokens, $i, $size);
                $phrase = implode(' ', $slice);
                if ($phrase === '') {
                    continue;
                }

                $openingPriority = max(0, 20 - $i);
                $boosts[$phrase] = max($boosts[$phrase] ?? 0, 18.0 + $openingPriority + ($size === 3 ? 1.3 : 1.0));
            }
        }

        return $boosts;
    }

    private function extractMeta(string $text, string $docType): array
    {
        if ($this->isLegislativeDocument($docType)) {
            return [
                'case_number' => null,
                'parties' => [],
                'date' => $this->firstNonEmptyMatch($text, [
                    '/\b(\d{1,2}(?:st|nd|rd|th)?\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})\b/iu',
                    '/\b((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})\b/iu',
                    '/\b(\d{4})\b/u',
                ]),
                'court' => null,
                'judge' => null,
            ];
        }

        $caseNumber = $this->firstNonEmptyMatch($text, [
            '/\b(\d{4}\s+ZW[A-Z]+\s+\d+)\b/u',
            '/\b((?:HCH|HCC|HH|HC|SC|CCZ?)\s*\d{2,6}(?:\/\d{2,4})?)\b/u',
            '/(?:case\s+no\.?|case\s+number)\s*[:\-]?\s*([A-Z0-9\/\-\s]{3,30})/iu',
        ]);

        $parties = [];
        if (preg_match('/\b([A-Z][A-Za-z0-9,&\'\-\s()]{3,100}?)\s+v(?:s\.?)?\s+([A-Z][A-Za-z0-9,&\'\-\s()]{3,140}?)(?=(?:\s+\(|\s{2,}|[\.\n\r]))/u', $text, $match)) {
            $parties = [
                $this->cleanPartyName($match[1]),
                $this->cleanPartyName($match[2]),
            ];
        } elseif (preg_match('/between\s+(.{6,100}?)\s+(?:and|v\.?s?\.?)\s+(.{6,120}?)(?:\s+(?:and|v\.?|in\s+the\s+matter)|[\.\n\r])/isu', $text, $match)) {
            $parties = [
                $this->cleanPartyName($match[1]),
                $this->cleanPartyName($match[2]),
            ];
        } elseif (preg_match('/(?:^|\n)\s*between\s*\n+(.{3,100}?)\n+(?:and|v\.?s?\.?)\n+(.{3,140}?)(?:\n|$)/isu', $text, $match)) {
            $parties = [
                $this->cleanPartyName($match[1]),
                $this->cleanPartyName($match[2]),
            ];
        }
        $parties = array_values(array_filter($parties));

        $date = $this->firstNonEmptyMatch($text, [
            '/\b(\d{1,2}(?:st|nd|rd|th)?\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})\b/iu',
            '/\b((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})\b/iu',
            '/\((\d{1,2}\s+[A-Z][a-z]+\s+\d{4})\)/u',
            '/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\b/u',
            '/\b(\d{4}-\d{2}-\d{2})\b/u',
        ]);

        $court = $this->extractCourtName($text);

        $judge = $this->extractJudgeName($text, $court);
        if ($judge) {
            $judge = $this->normalisePersonName($judge);
        }

        return [
            'case_number' => $caseNumber ?? null,
            'parties' => $parties,
            'date' => $date ?? null,
            'court' => $court ?? null,
            'judge' => $judge ?? null,
        ];
    }

    private function extractLegalReferences(string $text): array
    {
        $references = [];

        foreach ([
            '/\b((?:Section|Sec\.|s\.)\s?\d+[A-Za-z]?(?:\([^)]+\))?(?:\s+of\s+the\s+[A-Z][A-Za-z\s]+)?(?:\s+Constitution)?)\b/u',
            '/\b(Chapter\s+\d{1,2}:\d{2})\b/u',
            '/\b([A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+){0,5}\s+Act(?:\s+Chapter\s+\d{1,2}:\d{2})?)\b/u',
        ] as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[1] ?? [] as $match) {
                $references[] = preg_replace('/\s+/u', ' ', trim((string) $match));
            }
        }

        return array_values(array_slice(array_unique(array_filter($references)), 0, 12));
    }

    private function extractClauses(string $text, string $docType): array
    {
        $clauses = array_merge(
            $this->extractHeadedClauses($text),
            $this->extractRegexClauses($text, $docType),
            $this->extractProvisionClauses($text, $docType)
        );

        $seen = [];
        $results = [];

        foreach ($clauses as $clause) {
            $heading = preg_replace('/\s+/u', ' ', trim((string) ($clause['heading'] ?? '')));
            $content = $this->trimClauseContent((string) ($clause['content'] ?? ''));
            if ($heading === '' || $content === '' || mb_strlen($content) < 20) {
                continue;
            }

            $fingerprint = $this->clauseFingerprint($heading, $content);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $results[] = [
                'clause_type' => $clause['clause_type'] ?? $this->determineClauseType($heading . ' ' . $content, $docType),
                'heading' => $heading,
                'content' => $content,
            ];

            if (count($results) >= 8) {
                break;
            }
        }

        return $results;
    }

    private function extractProvisionClauses(string $text, string $docType): array
    {
        $clauses = [];
        $sentences = $this->sentences($text);

        foreach ($sentences as $sentence) {
            $sentence = trim((string) preg_replace('/\s+/u', ' ', $sentence));
            if (mb_strlen($sentence) < 35 || mb_strlen($sentence) > 420 || $this->shouldIgnoreClauseLine($sentence)) {
                continue;
            }

            $type = $this->determineClauseType($sentence, $docType);
            if ($type === 'GENERAL_CLAUSE' && !$this->hasOperativeClauseCue($sentence, $docType)) {
                continue;
            }

            $clauses[] = [
                'clause_type' => $type === 'GENERAL_CLAUSE' ? 'OPERATIVE_PROVISION' : $type,
                'heading' => $this->clauseHeadingForType($type, $sentence, $docType),
                'content' => $sentence,
            ];
        }

        return $clauses;
    }

    private function hasOperativeClauseCue(string $sentence, string $docType): bool
    {
        if (preg_match('/\b(shall|must|may|is required to|is entitled to|is prohibited from|shall not|may not|is liable|is guilty|has the right|is deemed|is authorised|is appointed|is established)\b/iu', $sentence)) {
            return true;
        }

        if (in_array($docType, ['Act', 'Bill', 'Statutory Instrument'], true)) {
            return preg_match('/\b(register|registration|certificate|officer|minister|regulation|penalty|offence|commencement|amend(?:ed|ment)?|repeal|insert|substitut)\b/iu', $sentence) === 1;
        }

        if (preg_match('/\b(payment|confidential|termination|notice|liability|indemnity|warranty|governing law|jurisdiction|breach|default|renewal|deposit|rent)\b/iu', $sentence)) {
            return true;
        }

        return false;
    }

    private function clauseHeadingForType(string $type, string $sentence, string $docType): string
    {
        if (preg_match('/^\s*(?:section|clause|article)?\s*([0-9]+[A-Za-z]?(?:\.[0-9]+)*)\s+([A-Z][A-Za-z0-9 ,&\'()\/:-]{2,70})/u', $sentence, $match)) {
            return trim($match[1] . ' ' . $match[2]);
        }

        return match ($type) {
            'COURT_ORDER' => 'Court Order',
            'CONFIDENTIALITY' => 'Confidentiality',
            'TERMINATION' => 'Termination',
            'LIABILITY' => 'Liability',
            'PAYMENT_TERMS' => 'Payment Terms',
            'LEASE_TERM' => 'Lease Term',
            'PENALTY' => 'Penalty',
            'AMENDMENT' => 'Amendment',
            'REGISTRATION' => 'Registration',
            'CONSENT' => 'Consent',
            'OFFICER_POWER' => 'Officer Powers',
            'EVIDENCE_CERTIFICATE' => 'Certified Extracts',
            default => in_array($docType, ['Act', 'Bill', 'Statutory Instrument'], true) ? 'Operative Provision' : 'Key Clause',
        };
    }

    private function clauseFingerprint(string $heading, string $content): string
    {
        $normalised = mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $heading . ' ' . $content));
        $tokens = array_values(array_filter(preg_split('/\s+/u', trim($normalised), -1, PREG_SPLIT_NO_EMPTY) ?: []));

        return implode(' ', array_slice($tokens, 0, 28));
    }

    private function extractHeadedClauses(string $text): array
    {
        $lines = preg_split('/\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $clauses = [];
        $currentHeading = null;
        $buffer = [];

        foreach ($lines as $rawLine) {
            $line = trim((string) preg_replace('/\s+/u', ' ', $rawLine));
            if ($line === '' || $this->shouldIgnoreClauseLine($line)) {
                continue;
            }

            if ($this->isClauseHeading($line)) {
                if ($currentHeading !== null && $buffer !== []) {
                    $clauses[] = [
                        'clause_type' => $this->determineClauseType($currentHeading . ' ' . implode(' ', $buffer)),
                        'heading' => $currentHeading,
                        'content' => implode(' ', $buffer),
                    ];
                }

                $currentHeading = $line;
                $buffer = [];
                continue;
            }

            if ($currentHeading !== null) {
                $buffer[] = $line;
            }
        }

        if ($currentHeading !== null && $buffer !== []) {
            $clauses[] = [
                'clause_type' => $this->determineClauseType($currentHeading . ' ' . implode(' ', $buffer)),
                'heading' => $currentHeading,
                'content' => implode(' ', $buffer),
            ];
        }

        return $clauses;
    }

    private function extractRegexClauses(string $text, string $docType): array
    {
        $clauses = [];
        $patterns = [
            ['clause_type' => 'CASE_NUMBER', 'heading' => 'Case Number', 'pattern' => '/\b(Case\sNo\.?|HC|SC|CCZ)\s*[:\-]?\s*[A-Z0-9\/\-]+\b/u'],
            ['clause_type' => 'LEASE_TERM', 'heading' => 'Lease Term', 'pattern' => '/\b(?:lease term|duration)\b.{0,220}(?:\.|$)/iu'],
            ['clause_type' => 'PAYMENT_TERMS', 'heading' => 'Payment Terms', 'pattern' => '/\b(?:payment terms|rent|fees|consideration)\b.{0,220}(?:\.|$)/iu'],
            ['clause_type' => 'TERMINATION', 'heading' => 'Termination', 'pattern' => '/\btermination\b.{0,240}(?:\.|$)/iu'],
            ['clause_type' => 'CONFIDENTIALITY', 'heading' => 'Confidentiality', 'pattern' => '/\bconfidential(?:ity)?\b.{0,240}(?:\.|$)/iu'],
            ['clause_type' => 'LIABILITY', 'heading' => 'Liability', 'pattern' => '/\bliability\b.{0,240}(?:\.|$)/iu'],
            ['clause_type' => 'COURT_ORDER', 'heading' => 'Court Order', 'pattern' => '/\b(?:it is ordered that|ordered that|the court orders)\b.{0,320}(?:\.|$)/iu'],
        ];

        if ($docType === 'Bill') {
            $patterns[] = ['clause_type' => 'AMENDMENT', 'heading' => 'Amendment Clause', 'pattern' => '/\b(?:amend|insert|repeal)\b.{0,260}(?:\.|$)/iu'];
        }

        if (in_array($docType, ['Act', 'Statutory Instrument'], true)) {
            $patterns[] = ['clause_type' => 'PENALTY', 'heading' => 'Penalty', 'pattern' => '/\b(?:shall be guilty|penalty|shall be liable)\b.{0,260}(?:\.|$)/iu'];
            $patterns[] = ['clause_type' => 'REGISTRATION', 'heading' => 'Registration', 'pattern' => '/\b(?:register|registration|registered|certificate|certified extract)\b.{0,260}(?:\.|$)/iu'];
            $patterns[] = ['clause_type' => 'OFFICER_POWER', 'heading' => 'Officer Powers', 'pattern' => '/\b(?:officer|minister|chief|magistrate)\b.{0,260}\b(?:may|shall|must|appoint|authori[sz]e|solemni[sz]e)\b.{0,180}(?:\.|$)/iu'];
        }

        foreach ($patterns as $definition) {
            preg_match_all($definition['pattern'], $text, $matches);
            foreach ($matches[0] ?? [] as $match) {
                $content = preg_replace('/\s+/u', ' ', trim((string) $match));
                if ($content === '') {
                    continue;
                }

                $clauses[] = [
                    'clause_type' => $definition['clause_type'],
                    'heading' => $definition['heading'],
                    'content' => $content,
                ];
            }
        }

        return $clauses;
    }

    private function trimClauseContent(string $content): string
    {
        $content = preg_replace('/(?:-{3}|[\x{2500}\x{2501}]{3})\s*PAGE\s+\d+\s*(?:-{3}|[\x{2500}\x{2501}]{3})?/iu', ' ', $content);
        $content = preg_replace('/\bPage\s+\d+\s+(?:of|\/)\s+\d+\b/iu', ' ', (string) $content);
        $content = trim((string) preg_replace('/\s+/u', ' ', (string) $content));
        if ($content === '') {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $selected = [];
        foreach ($sentences as $sentence) {
            $sentence = trim((string) preg_replace('/\s+/u', ' ', $sentence));
            if ($sentence === '' || mb_strlen($sentence) < 20 || $this->shouldIgnoreClauseLine($sentence)) {
                continue;
            }

            $selected[] = $sentence;
            if (count($selected) >= 2) {
                break;
            }
        }

        $content = $selected !== [] ? implode(' ', $selected) : $content;
        $words = preg_split('/\s+/u', trim($content), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= 70) {
            return $content;
        }

        return implode(' ', array_slice($words, 0, 70)) . '...';
    }

    private function shouldIgnoreClauseLine(string $line): bool
    {
        return preg_match('/^(?:page\s+\d+(?:\s+(?:of|\/)\s+\d+)?|\d+|harare|bulawayo|mutare|in the .*court|before:|between|-{3}\s*page\s+\d+\s*-{3})$/iu', trim($line)) === 1;
    }

    private function isClauseHeading(string $line): bool
    {
        if (mb_strlen($line) > 120) {
            return false;
        }

        if (preg_match('/^(?:section|clause|article|part)\s+\d+[A-Za-z0-9().:-]*\b.*$/iu', $line)) {
            return true;
        }

        if (preg_match('/^\d+(?:\.\d+)*[A-Za-z]?[.)-]?\s+[A-Z][A-Za-z0-9 ,&\'()\/:-]{2,100}$/u', $line)) {
            return true;
        }

        if (preg_match('/^[A-Z][A-Z0-9 ,&\'()\/:-]{4,100}$/u', $line) && preg_match('/\b(termination|confidentiality|liability|rent|lease|payment|notice|obligations?|rights?|orders?|judgment|appeal|breach|indemnity|penalty|definitions?)\b/iu', $line)) {
            return true;
        }

        return false;
    }

    private function determineClauseType(string $text, string $docType = ''): string
    {
        $haystack = mb_strtolower($text);

        foreach ([
            'COURT_ORDER' => '/ordered that|court orders|appeal dismissed|judgment upheld|application granted/u',
            'CONFIDENTIALITY' => '/confidential/u',
            'TERMINATION' => '/termination|terminate|notice/u',
            'LIABILITY' => '/liability|liable|indemnity/u',
            'PAYMENT_TERMS' => '/payment terms|rent|fees|consideration|salary|purchase price/u',
            'LEASE_TERM' => '/lease term|duration|premises|tenant|landlord/u',
            'PENALTY' => '/penalty|shall be guilty|offence|offense/u',
            'AMENDMENT' => '/amend|insert|repeal/u',
            'EVIDENCE_CERTIFICATE' => '/evidence|certified extract|certified as a true copy|true copy|custody/u',
            'REGISTRATION' => '/register|registration|certificate/u',
            'CONSENT' => '/guardian|consent|solemnize|solemnise|marriage/u',
            'OFFICER_POWER' => '/officer|minister|chief|magistrate|appoint|authorize|authorise/u',
        ] as $label => $pattern) {
            if (preg_match($pattern, $haystack)) {
                return $label;
            }
        }

        if ($docType === 'Court Judgment') {
            return 'COURT_ORDER';
        }

        return 'GENERAL_CLAUSE';
    }

    private function extractObligations(array $sentences): array
    {
        $triggers = '/\b(shall|must|is required to|hereby agrees? to|undertakes? to|will be responsible|obliged to|duty to|entitled to|has the right to|may not|may terminate|termination|prohibited from|covenants? to)\b/i';
        $found = [];

        foreach ($sentences as $sentence) {
            if (preg_match($triggers, $sentence) && strlen($sentence) > 30 && strlen($sentence) < 300) {
                $found[] = ucfirst(trim($sentence));
                if (count($found) >= 6) {
                    break;
                }
            }
        }

        if (empty($found)) {
            $found = [
                'Review all obligations and deadlines with qualified legal counsel.',
                'Verify the identity and capacity of all contracting parties.',
                'Note time-sensitive provisions that may require immediate action.',
                'Ensure proper execution, witnessing, and notarisation where required.',
            ];
        }

        return $found;
    }

    private function extractiveSummary(array $sentences, array $keywords, int $limit): array
    {
        if (empty($sentences)) {
            return [];
        }

        $scored = [];
        foreach ($sentences as $index => $sentence) {
            $score = 0.0;
            $lowerSentence = mb_strtolower($sentence);

            foreach ($keywords as $keyword => $weight) {
                if (str_contains($lowerSentence, $keyword)) {
                    $score += (float) $weight;
                }
            }

            if ($index < 4) {
                $score += 2.0;
            }
            if ($this->hasSummaryCue($sentence)) {
                $score += 3.0;
            }
            if ($this->hasOutcomeCue($sentence)) {
                $score += 3.5;
            }
            if ($this->hasLegalPrincipleCue($sentence)) {
                $score += 1.5;
            }

            $length = strlen($sentence);
            if ($length > 80 && $length < 350) {
                $score += 1.0;
            } elseif ($length < 40 || $length > 500) {
                $score -= 1.5;
            }

            $scored[$index] = $score;
        }

        arsort($scored);

        $selected = [];
        foreach (array_keys($scored) as $index) {
            $sentence = $sentences[$index];
            if ($this->isRedundantSentence($sentence, $selected)) {
                continue;
            }
            $selected[$index] = $sentence;
            if (count($selected) >= $limit) {
                break;
            }
        }

        ksort($selected);

        return array_values($selected);
    }

    private function detectOutcome(string $text, array $sentences, string $docType): string
    {
        if ($this->isBillDocument($docType)) {
            $shortTitle = $this->extractBillTitle($text);

            if ($shortTitle) {
                return 'Legislative proposal: ' . preg_replace('/\s+/u', ' ', trim($shortTitle)) . '.';
            }

            return 'Legislative proposal setting out proposed amendments; enactment depends on parliamentary approval and presidential assent.';
        }

        if (in_array($docType, ['Act', 'Statutory Instrument'], true)) {
            return 'Legislative instrument setting out binding legal rules. Confirm the commencement date, amendments, and current regulatory status before relying on it.';
        }

        $tailSentences = array_slice($sentences, -12);
        $tailText = implode(' ', $tailSentences);

        $patterns = [
            '/(?:it\s+is\s+ordered|it\s+is\s+hereby\s+ordered|the\s+application\s+is\s+(?:granted|dismissed)|the\s+appeal\s+(?:succeeds|fails)|declared?\s+null(?:ity)?|null\s+and\s+void|certificate(?:s)?\s+(?:is|are)\s+cancelled|(?:appeal|application|claim|action|petition)[^.]{0,140}with\s+costs(?:\s+on\s+a\s+higher\s+scale)?)[^.]{0,260}\./iu',
            '/(?:court\s+orders?|judgment\s+is\s+entered|judgment\s+for|dismissed?|upheld?|granted?|refused?|set\s+aside?)[^.]{0,220}\./iu',
            '/(?:in\s+conclusion|therefore|accordingly|for\s+these\s+reasons)[^.]{0,220}\./iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $tailText, $match)) {
                return trim($match[0]);
            }
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[0]);
            }
        }

        foreach (array_reverse($tailSentences ?: $sentences) as $sentence) {
            if (strlen($sentence) > 40 && ($this->hasOutcomeCue($sentence) || preg_match('/\b(therefore|accordingly|consequently|in the result|for these reasons)\b/iu', $sentence))) {
                return $sentence;
            }
        }

        return 'Outcome not expressly stated in the extracted text. Please review the full document for the court order or dispositive clause.';
    }

    private function extractLegalPrinciples(array $sentences): string
    {
        $trigger = '/\b(principle of|rule of law|audi alteram partem|nemo iudex|ultra vires|res judicata|stare decisis|locus standi|ratio decidendi|obiter dictum|in limine|ex parte|bona fide|mala fide|force majeure|common law|statute|constitutional right)\b/i';
        $found = [];

        foreach ($sentences as $sentence) {
            if (preg_match($trigger, $sentence) && strlen($sentence) < 400) {
                $found[] = trim($sentence);
                if (count($found) >= 3) {
                    break;
                }
            }
        }

        return implode(' ', $found);
    }

    private function practicalImplications(string $text, string $docType): string
    {
        if ($docType !== 'Court Judgment' && preg_match('/\b(environmental impact assessment|eiac|environmental management agency)\b/i', $text)) {
            return 'Confirm that no project implementation or mining activity begins before the required environmental approvals remain valid. Check certificate conditions, rehabilitation duties, and any regulator-imposed timelines before proceeding.';
        }

        $map = [
            'Court Judgment' => 'Parties should comply with the court order promptly. Any appeal or review step should be checked against the applicable Zimbabwean procedural rules and filing deadlines before enforcement action is taken.',
            'Bill' => 'Review the clauses affected by the proposed amendments, identify institutional and constitutional changes, and assess operational impact if the bill is enacted. Legislative status, committee review, and final enacted wording should be confirmed before relying on the proposal.',
            'Statutory Instrument' => 'Check the gazetted text, effective date, enabling Act, penalties, and any regulator guidance before relying on the instrument for compliance decisions.',
            'Lease Agreement' => 'Both landlord and tenant should retain a signed copy. Note rent review dates, notice periods for termination, and permitted use clauses. Ensure the deposit is handled in accordance with the applicable rent regulations.',
            'Employment Contract' => 'Confirm the start date, remuneration, leave, confidentiality, termination, and grievance provisions against current labour law before implementation.',
            'Sale Agreement' => 'Confirm the purchase price, delivery or transfer conditions, warranties, risk allocation, and signing authority before completion.',
            'Loan Agreement' => 'Check repayment dates, interest, security, default triggers, acceleration rights, and any consumer-credit or banking-law requirements before funds are advanced.',
            'Shareholder Agreement' => 'Review voting rights, reserved matters, transfer restrictions, deadlock mechanisms, director appointment rights, and dividend provisions before shares are issued or transferred.',
            'Power of Attorney' => 'Verify the donor, attorney, scope of authority, execution formalities, and whether the power remains valid for the intended transaction.',
            'Will and Testament' => 'Confirm execution formalities, beneficiaries, executor appointment, revocation language, and any estate-planning risks with a qualified practitioner.',
            'Contract Agreement' => 'Review the duties, payment terms, liability language, confidentiality provisions, and termination rights carefully before anyone signs or performs under the agreement.',
            'Act' => 'Compliance with this legislation is mandatory. Note the commencement date, definitions, offences, penalties, and any transitional provisions, then confirm implementation requirements with the relevant regulator.',
        ];

        foreach ($map as $type => $implication) {
            if (stripos($docType, explode('/', $type)[0]) !== false) {
                return $implication;
            }
        }

        return 'This document should be reviewed carefully by a qualified Zimbabwean legal practitioner. Time limits, compliance steps, and procedural risks may apply.';
    }

    private function readability(array $tokens, array $sentences): float
    {
        $totalWords = count($tokens);
        $totalSentences = max(1, count($sentences));
        $totalSyllables = 0;

        foreach ($tokens as $word) {
            $totalSyllables += max(1, preg_match_all('/[aeiouy]+/i', $word));
        }

        if ($totalWords === 0) {
            return 0.0;
        }

        $score = 206.835
            - 1.015 * ($totalWords / $totalSentences)
            - 84.6 * ($totalSyllables / $totalWords);

        return round(max(0, min(100, $score)), 1);
    }

    private function sentiment(array $tokens): string
    {
        $positive = ['granted','upheld','awarded','approved','successful','favour','valid','enforceable','comply','agree','positive','benefit'];
        $negative = ['dismissed','refused','failed','unlawful','void','breach','invalid','penalty','damages','contempt','violate','failure'];

        $positiveScore = 0;
        $negativeScore = 0;

        foreach ($tokens as $token) {
            if (in_array($token, $positive, true)) {
                $positiveScore++;
            }
            if (in_array($token, $negative, true)) {
                $negativeScore++;
            }
        }

        if ($positiveScore > $negativeScore + 2) {
            return 'positive';
        }
        if ($negativeScore > $positiveScore + 2) {
            return 'negative';
        }

        return 'neutral';
    }

    private function legalCategories(string $text): array
    {
        $categories = [];
        $map = [
            'Constitutional Law' => '/constitutional|constitution|fundamental rights|bill of rights/i',
            'Commercial Law' => '/commercial|company|partnership|trade|business/i',
            'Property Law' => '/immovable property|land|title deed|cadastral|conveyancing/i',
            'Criminal Law' => '/accused|prosecution|criminal|offence|sentence|imprisonment/i',
            'Labour Law' => '/labour|employment|retrenchment|unfair dismissal|nssa/i',
            'Family Law' => '/divorce|matrimonial|custody|maintenance|marital/i',
            'Administrative Law' => '/administrative|authority|minister|gazette|statutory/i',
            'Contract Law' => '/contract|agreement|consideration|breach|damages/i',
            'Environmental Law' => '/environmental impact assessment|environmental management agency|eiac|eia|pollution|rehabilitation plan/i',
            'Mining Law' => '/mining|claims? registration|mining commissioner|prospecting|ore body/i',
        ];

        foreach ($map as $category => $regex) {
            if (preg_match($regex, $text)) {
                $categories[] = $category;
            }
        }

        return array_values(array_unique(array_merge($categories, $this->adaptiveCategoriesForText($text))));
    }

    private function adaptiveKeywordBoosts(string $text): array
    {
        try {
            return $this->adaptiveLearningService()?->keywordBoosts($text) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function adaptiveCategoriesForText(string $text): array
    {
        try {
            return $this->adaptiveLearningService()?->categoriesForText($text) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function adaptiveLearningService(): ?NlpAdaptiveLearningService
    {
        if ($this->adaptiveLearning instanceof NlpAdaptiveLearningService) {
            return $this->adaptiveLearning;
        }

        if (!function_exists('app')) {
            return null;
        }

        try {
            $service = app(NlpAdaptiveLearningService::class);
            return $this->adaptiveLearning = $service instanceof NlpAdaptiveLearningService ? $service : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function detectLanguage(array $tokens): string
    {
        $shona = ['ndiyo','kwete','zvakanaka','mwari','munhu','vanhu','zvino','pane','kuti','izvi','kana','uye'];
        $ndebele = ['yebo','hayi','kulungile','abantu','labo','yini','ngoba','kanti','lokhu','kodwa','futhi','umuntu'];
        $shonaHits = 0;
        $ndebeleHits = 0;
        $tokenCounts = array_count_values($tokens);

        foreach ($shona as $word) {
            if (isset($tokenCounts[$word])) {
                $shonaHits += $tokenCounts[$word];
            }
        }
        foreach ($ndebele as $word) {
            if (isset($tokenCounts[$word])) {
                $ndebeleHits += $tokenCounts[$word];
            }
        }

        if ($shonaHits >= 2 && $shonaHits > $ndebeleHits) {
            return 'sn';
        }
        if ($ndebeleHits >= 2 && $ndebeleHits > $shonaHits) {
            return 'nd';
        }

        return 'en';
    }

    private function buildExecutiveSummary(
        string $text,
        string $docType,
        array $topSentences,
        array $metaFields,
        array $entities,
        string $outcome,
        array $obligations,
        array $legalReferences,
        array $categories,
        string $implications
    ): string {
        if ($this->isLegislativeDocument($docType)) {
            $summary = $this->buildExpandedLegislativeSummary($text, $metaFields, $topSentences, $docType);
            if ($summary !== '') {
                return $summary;
            }
        }

        $sentences = [];
        $intro = $this->buildSummaryIntro($docType, $metaFields, $entities);
        if ($intro !== '') {
            $sentences[] = $intro;
        }

        foreach ($this->buildSummaryFactSentences($topSentences, 4) as $factSentence) {
            if (!$this->isRedundantSentence($factSentence, $sentences)) {
                $sentences[] = $factSentence;
            }
        }

        $outcomeSentence = $this->simplifyOutcomeForSummary($outcome, $docType);
        if ($outcomeSentence !== '' && !$this->isRedundantSentence($outcomeSentence, $sentences)) {
            $sentences[] = $outcomeSentence;
        }

        $effectSentence = $this->buildSummaryEffectSentence($docType, $obligations);
        if ($effectSentence !== '' && !$this->isRedundantSentence($effectSentence, $sentences)) {
            $sentences[] = $effectSentence;
        }

        $referenceSentence = $this->buildSummaryReferenceSentence($legalReferences, $categories, $docType);
        if ($referenceSentence !== '' && !$this->isRedundantSentence($referenceSentence, $sentences)) {
            $sentences[] = $referenceSentence;
        }

        $practicalSentence = $this->buildSummaryPracticalSentence($implications, $docType);
        if ($practicalSentence !== '' && !$this->isRedundantSentence($practicalSentence, $sentences)) {
            $sentences[] = $practicalSentence;
        }

        $takeawaySentence = $this->buildSummaryTakeawaySentence($docType, $text, $entities);
        if ($takeawaySentence !== '' && !$this->isRedundantSentence($takeawaySentence, $sentences)) {
            $sentences[] = $takeawaySentence;
        }

        return $this->composePlainEnglishSummary($sentences, 200, 500);
    }

    private function buildKeyFindings(string $text, string $docType, array $findings): string
    {
        if ($this->isLegislativeDocument($docType)) {
            $highlights = $this->extractLegislativeHighlightsFromText($text, 3);
            if (count($highlights) < 3) {
                $highlights = array_values(array_unique(array_merge(
                    $highlights,
                    $this->extractLegislativeHighlights($this->sentences($text), 3),
                    $this->extractLegislativeRuleSentences($this->sentences($text), 4)
                )));
                $highlights = array_slice($highlights, 0, 3);
            }
            if (!empty($highlights)) {
                return $this->joinHighlights(array_map(
                    fn (string $sentence): string => $this->rewriteLegislativeSentenceForSummary($sentence),
                    $highlights
                ));
            }
        }

        return $this->condenseParagraph($findings, 3, 620);
    }

    private function buildSummaryIntro(string $docType, array $metaFields, array $entities): string
    {
        $date = $metaFields['date'] ?? null;
        $court = $metaFields['court'] ?? null;
        $judge = $metaFields['judge'] ?? null;
        $parties = array_values(array_filter($metaFields['parties'] ?? []));

        if ($docType === 'Court Judgment') {
            $bits = ['This court judgment'];
            if ($court) {
                $bits[] = 'from the ' . $court;
            }
            if ($date) {
                $bits[] = 'dated ' . $date;
            }

            $sentence = implode(' ', $bits);
            if (count($parties) >= 2) {
                $sentence .= ' concerns a dispute between ' . $parties[0] . ' and ' . $parties[1] . '.';
            } else {
                $sentence .= ' explains a dispute that was placed before the court.';
            }

            if ($judge) {
                $sentence .= ' The matter was handled by ' . $judge . '.';
            }

            return $sentence;
        }

        if ($this->isLegislativeDocument($docType)) {
            $sentence = match ($docType) {
                'Bill' => 'This bill outlines proposed changes to the law in plain terms.',
                'Act' => 'This Act sets out binding legal rules and public duties in plain terms.',
                'Statutory Instrument' => 'This statutory instrument sets out detailed rules made under an enabling law.',
                default => 'This legislative document sets out legal rules in plain terms.',
            };
            if ($date) {
                $sentence .= ' The extracted text is dated ' . $date . '.';
            }

            return $sentence;
        }

        if (count($parties) >= 2) {
            return 'This ' . $this->friendlyDocType($docType) . ' sets out the relationship and responsibilities of ' . $parties[0] . ' and ' . $parties[1] . '.';
        }

        $namedEntities = array_values(array_filter(array_merge(
            array_slice($entities['organisations'] ?? [], 0, 2),
            array_slice($entities['persons'] ?? [], 0, 2)
        )));

        $subject = $this->friendlyDocType($docType);
        if (!empty($namedEntities)) {
            return 'This ' . $subject . ' sets out the relationship and responsibilities of ' . $this->formatList($namedEntities, 3) . '.';
        }

        return 'This ' . $subject . ' explains the main relationship, issue, and responsibilities covered in the document.';
    }

    private function buildSummaryFactSentences(array $topSentences, int $limit): array
    {
        $facts = [];

        foreach ($topSentences as $sentence) {
            $plain = $this->rewriteSentenceInPlainEnglish($sentence);
            if ($plain === '' || $this->isRedundantSentence($plain, $facts)) {
                continue;
            }

            $facts[] = $plain;
            if (count($facts) >= $limit) {
                break;
            }
        }

        return $facts;
    }

    private function simplifyOutcomeForSummary(string $outcome, string $docType): string
    {
        $outcome = trim($outcome);
        if ($outcome === '' || str_contains($outcome, 'Outcome not expressly stated')) {
            if ($docType === 'Court Judgment') {
                return 'The extracted text does not state the final order clearly, so the full judgment should still be checked before anyone relies on the result.';
            }

            return '';
        }

        return $this->rewriteSentenceInPlainEnglish($outcome);
    }

    private function buildSummaryEffectSentence(string $docType, array $obligations): string
    {
        $plainObligations = [];
        foreach ($obligations as $obligation) {
            $plain = $this->rewriteSentenceInPlainEnglish((string) $obligation);
            if ($plain !== '' && !$this->isRedundantSentence($plain, $plainObligations)) {
                $plainObligations[] = $plain;
            }
        }

        if ($docType === 'Court Judgment' && !empty($plainObligations)) {
            return 'In practical terms, the decision means ' . $this->summarisePlainEnglishFragments($plainObligations, 2) . '.';
        }

        if ($this->isBillDocument($docType)) {
            return 'For a non-expert reader, the important point is that this document proposes change, but the proposal only becomes binding law after the full legislative process is completed.';
        }

        if (!empty($plainObligations)) {
            return 'Key practical duties include that ' . $this->summarisePlainEnglishFragments($plainObligations, 2) . '.';
        }

        return '';
    }

    private function buildSummaryReferenceSentence(array $legalReferences, array $categories, string $docType): string
    {
        $references = array_values(array_filter(array_slice($legalReferences, 0, 3)));
        $categories = array_values(array_filter(array_slice($categories, 0, 2)));

        if ($references !== [] && $categories !== []) {
            return 'The extracted text refers to ' . $this->formatList($references, 3) . ', and the issues sit mainly within ' . $this->formatList($categories, 2) . '.';
        }

        if ($references !== []) {
            return 'The extracted text refers in particular to ' . $this->formatList($references, 3) . '.';
        }

        if ($categories !== [] && $docType !== 'Court Judgment') {
            return 'The document mainly deals with ' . $this->formatList($categories, 2) . '.';
        }

        return '';
    }

    private function buildSummaryPracticalSentence(string $implications, string $docType): string
    {
        $implications = $this->normaliseSummarySentence($implications);
        if ($implications === '') {
            return '';
        }

        if ($docType === 'Court Judgment') {
            return 'From a practical standpoint, ' . $this->lowercaseSentence($implications);
        }

        return 'In day-to-day terms, ' . $this->lowercaseSentence($implications);
    }

    private function buildSummaryTakeawaySentence(string $docType, string $text, array $entities): string
    {
        if ($docType === 'Court Judgment') {
            return 'The main takeaway is that the court focused on the real-world facts and whether the legal requirements were followed, not just on formal wording or technical argument.';
        }

        if ($this->isBillDocument($docType)) {
            return 'The main takeaway is that the draft text is trying to change how an area of law or public administration will work, so readers should focus on what will actually change in practice.';
        }

        if (preg_match('/\b(payment|salary|rent|termination|notice|leave|deposit|confidentiality)\b/iu', $text)) {
            return 'The main takeaway is that a reader should focus on what must be done, when it must be done, and what happens if one side does not follow the document.';
        }

        if (!empty($entities['organisations'] ?? []) || !empty($entities['persons'] ?? [])) {
            return 'The main takeaway is that this document is easiest to understand when you focus on the people involved, the main duty placed on each side, and the effect of non-compliance.';
        }

        return 'The main takeaway is that the summary should help a non-expert understand the purpose of the document, the main issue it addresses, and the effect it is likely to have in practice.';
    }

    private function composePlainEnglishSummary(array $sentences, int $minWords, int $maxWords): string
    {
        $sentences = array_values(array_filter(array_map(
            fn ($sentence) => $this->normaliseSummarySentence($sentence),
            $sentences
        )));

        if (empty($sentences)) {
            return '';
        }

        $summary = implode(' ', $sentences);
        $summary = preg_replace('/\s+/u', ' ', (string) $summary);
        $summary = trim((string) $summary);

        $summary = $this->expandSummaryToMinimumLength($summary, $sentences, $minWords);
        $summary = $this->trimSummaryToWordLimit($summary, $maxWords);

        return trim((string) preg_replace('/\s+/u', ' ', $summary));
    }

    private function expandSummaryToMinimumLength(string $summary, array $sentences, int $minWords): string
    {
        $wordCount = $this->wordCount($summary);
        if ($wordCount >= $minWords) {
            return $summary;
        }

        $fillers = [
            'Put simply, the document is best understood by looking at who is affected, what triggered the dispute or arrangement, and what decision or duty follows from that.',
            'Seen as a whole, the document explains the chain from facts, to rule, to consequence in a way that matters beyond the technical wording used in the original text.',
            'For a non-expert reader, the important point is not the formal style of the document but the practical message about responsibility, timing, and what happens when legal requirements are not followed.',
            'That wider context matters because legal documents often hide the central point inside formal language even though the real-world meaning is usually much simpler than it first appears.',
        ];

        foreach ($fillers as $filler) {
            if ($this->wordCount($summary) >= $minWords) {
                break;
            }

            if (!$this->isRedundantSentence($filler, $sentences)) {
                $summary .= ' ' . $filler;
                $sentences[] = $filler;
            }
        }

        return trim($summary);
    }

    private function trimSummaryToWordLimit(string $summary, int $maxWords): string
    {
        $words = preg_split('/\s+/u', trim($summary), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words || count($words) <= $maxWords) {
            return $summary;
        }

        $trimmed = implode(' ', array_slice($words, 0, $maxWords));
        $trimmed = rtrim($trimmed, " ,;:-");

        return preg_match('/[.!?]$/u', $trimmed) ? $trimmed : $trimmed . '.';
    }

    private function rewriteSentenceInPlainEnglish(string $sentence): string
    {
        $sentence = $this->normaliseSummarySentence($sentence);
        if ($sentence === '') {
            return '';
        }

        $replacements = [
            '/\bthe applicant\b/iu' => 'the party bringing the case',
            '/\bthe respondent\b/iu' => 'the other side',
            '/\bapplicant\b/iu' => 'the party bringing the case',
            '/\brespondent\b/iu' => 'the other side',
            '/\bplaintiff\b/iu' => 'the person suing',
            '/\bdefendant\b/iu' => 'the person being sued',
            '/\bcommenced\b/iu' => 'started',
            '/\bseeks to\b/iu' => 'asks to',
            '/\bsought to\b/iu' => 'asked to',
            '/\bin terms of\b/iu' => 'under',
            '/\bpursuant to\b/iu' => 'under',
            '/\bthereof\b/iu' => 'of it',
            '/\bhereby\b/iu' => '',
            '/\bshall\b/iu' => 'must',
            '/\bAccordingly,\s*/u' => 'As a result, ',
            '/\bThe court found that\b/iu' => 'The court decided that',
            '/\bIt is ordered that\b/iu' => 'The court ordered that',
            '/\bIn the result\b/iu' => 'In the end',
            '/\bdispositive clause\b/iu' => 'final order',
            '/\bEnvironmental Impact Assessment Certificate\b/iu' => 'environmental approval certificate',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $sentence = preg_replace($pattern, $replacement, $sentence);
        }

        $sentence = preg_replace('/^According to\s+[^,]+,\s*/iu', '', $sentence);
        $sentence = preg_replace('/\bsection\s+\d+[A-Z]?(?:\([^)]+\))?(?:\s+of\s+the\s+[A-Z][A-Za-z\s]+Act)?\b/iu', 'the relevant legal rule', $sentence);
        $sentence = preg_replace('/\s+/u', ' ', (string) $sentence);

        return $this->normaliseSummarySentence($sentence);
    }

    private function summarisePlainEnglishFragments(array $sentences, int $limit): string
    {
        $fragments = [];

        foreach (array_slice($sentences, 0, $limit) as $sentence) {
            $fragment = mb_strtolower(mb_substr($sentence, 0, 1)) . mb_substr($sentence, 1);
            $fragment = rtrim((string) $fragment, '.');
            $fragments[] = $fragment;
        }

        if (empty($fragments)) {
            return '';
        }

        if (count($fragments) === 1) {
            return $fragments[0];
        }

        $last = array_pop($fragments);

        return implode(', and ', $fragments) . ', and ' . $last;
    }

    private function friendlyDocType(string $docType): string
    {
        return match ($docType) {
            'Lease Agreement' => 'lease agreement',
            'Employment Contract' => 'employment contract',
            'Sale Agreement' => 'sale agreement',
            'Loan Agreement' => 'loan agreement',
            'Shareholder Agreement' => 'shareholder agreement',
            'Power of Attorney' => 'power of attorney',
            'Will and Testament' => 'will and testament',
            'Contract Agreement' => 'agreement',
            'Act', 'Statutory Instrument' => 'law',
            'Legal Document' => 'legal document',
            default => mb_strtolower($docType ?: 'legal document'),
        };
    }

    private function formatList(array $items, int $limit = 3): string
    {
        $items = array_values(array_filter(array_slice($items, 0, $limit)));
        if (empty($items)) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words ? count($words) : 0;
    }

    private function flattenSummaryText(array $parts): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))));
    }

    private function lowercaseSentence(string $sentence): string
    {
        $sentence = trim($sentence);
        if ($sentence === '') {
            return '';
        }

        return mb_strtolower(mb_substr($sentence, 0, 1)) . mb_substr($sentence, 1);
    }

    private function buildExpandedLegislativeSummary(string $text, array $metaFields, array $topSentences, string $docType = 'Bill'): string
    {
        $highlights = $this->extractLegislativeHighlightsFromText($text, 4);
        if (count($highlights) < 3) {
            $highlights = array_values(array_unique(array_merge(
                $highlights,
                $this->extractLegislativeHighlights($this->sentences($text), 4),
                $this->extractLegislativeRuleSentences($this->sentences($text), 5),
                $this->buildSummaryFactSentences($topSentences, 3)
            )));
            $highlights = array_slice($highlights, 0, 6);
        }

        $title = $this->extractLegislativeTitle($text, $docType);
        $sentences = [];

        $sentences[] = $this->legislativeIntroSentence($docType, $title);

        if (!empty($metaFields['date'])) {
            $dateLead = $docType === 'Bill' ? 'The extracted draft text' : 'The extracted text';
            $sentences[] = $dateLead . ' is dated ' . $metaFields['date'] . '.';
        }

        foreach ($highlights as $highlight) {
            $plain = $this->rewriteLegislativeSentenceForSummary($highlight);
            if ($plain !== '' && !$this->isRedundantSentence($plain, $sentences)) {
                $sentences[] = $plain;
            }
        }

        $sentences[] = $this->legislativePracticalSentence($docType);

        return $this->composePlainEnglishSummary($sentences, 80, 300);
    }

    private function legislativeIntroSentence(string $docType, ?string $title): string
    {
        $title = $title ? preg_replace('/\s+/u', ' ', trim($title)) : '';

        return match ($docType) {
            'Bill' => $title
                ? 'This bill, ' . $title . ', explains proposed changes to the law before they become binding.'
                : 'This bill explains proposed changes to the law before they become binding.',
            'Act' => $title
                ? 'This Act, ' . $title . ', is an existing law that sets out binding legal rules and public duties.'
                : 'This Act is an existing law that sets out binding legal rules and public duties.',
            'Statutory Instrument' => $title
                ? 'This statutory instrument, ' . $title . ', sets out detailed rules made under an enabling Act.'
                : 'This statutory instrument sets out detailed rules made under an enabling Act.',
            default => 'This legislative document sets out legal rules and practical duties.',
        };
    }

    private function legislativePracticalSentence(string $docType): string
    {
        return match ($docType) {
            'Bill' => 'The practical effect depends on whether Parliament passes the proposal and whether the final wording remains the same.',
            'Act' => 'The practical effect is that affected public bodies, officials, institutions, and citizens must follow the duties and procedures stated in the Act.',
            'Statutory Instrument' => 'The practical effect is that affected people and institutions must follow the detailed compliance rules unless the instrument is amended or repealed.',
            default => 'The practical effect depends on the duties, powers, and procedures created by the legislative text.',
        };
    }

    private function extractLegislativeRuleSentences(array $sentences, int $limit): array
    {
        $rules = [];

        foreach ($sentences as $sentence) {
            $sentence = $this->rewriteLegislativeSentenceForSummary($sentence);
            if ($sentence === '' || $this->isRedundantSentence($sentence, $rules)) {
                continue;
            }

            if (!preg_match('/\b(must|may|sets out|provides|requires|allows|establishes|appoints|applies|regulates|prohibits|creates|states)\b/iu', $sentence)) {
                continue;
            }

            $rules[] = $sentence;
            if (count($rules) >= $limit) {
                break;
            }
        }

        return $rules;
    }

    private function rewriteLegislativeSentenceForSummary(string $sentence): string
    {
        $sentence = $this->removeLegislativeNumbering($sentence);
        $sentence = $this->rewriteSentenceInPlainEnglish($sentence);
        $sentence = preg_replace('/\bWhere\b/u', 'When', (string) $sentence);
        $sentence = preg_replace('/\btherein\b/iu', 'in it', (string) $sentence);
        $sentence = preg_replace('/\bherein\b/iu', 'in this law', (string) $sentence);
        $sentence = preg_replace('/\bsubsections?\s+must\s+apply\b/iu', 'those rules must apply', (string) $sentence);
        $sentence = preg_replace('/\s*,\s*/u', ', ', (string) $sentence);
        $sentence = preg_replace('/\s+/u', ' ', (string) $sentence);
        $sentence = $this->normaliseSummarySentence((string) $sentence);

        if ($this->wordCount($sentence) > 55) {
            $sentence = $this->trimSummaryToWordLimit($sentence, 55);
        }

        return $sentence;
    }

    private function removeLegislativeNumbering(string $sentence): string
    {
        $sentence = preg_replace('/\[(?:Chapter\s*)?([0-9]{1,2}:[0-9]{2})\]/iu', ' Chapter $1 ', $sentence);
        $sentence = preg_replace('/\b(subsections?|paragraphs?|sections?)\s*(?:\(\s*[0-9]+[A-Za-z]?\s*\)\s*(?:,|and|or)?\s*)+/iu', '$1 ', (string) $sentence);
        $sentence = preg_replace('/\(\s*[0-9]+[A-Za-z]?\s*\)/u', '', (string) $sentence);
        $sentence = preg_replace('/^\s*[0-9]+[A-Za-z]?(?:\.[0-9]+)*[.)-]?\s*/u', '', (string) $sentence);
        $sentence = preg_replace('/,([^\s])/u', ', $1', (string) $sentence);
        $sentence = preg_replace('/\s+/u', ' ', (string) $sentence);

        return trim((string) $sentence, " \t\n\r\0\x0B,;:-");
    }

    private function extractLegislativeTitle(string $text, string $docType): ?string
    {
        if ($docType === 'Bill') {
            return $this->extractBillTitle($text);
        }

        $header = mb_substr($text, 0, 3500);
        $patterns = match ($docType) {
            'Act' => [
                '/This Act may be cited as\s+(.+?\bAct(?:,?\s*[0-9]{4})?)/iu',
                '/(?:^|\R)\s*([A-Z][A-Z0-9 ,&()\'\-.]{2,140}\s+ACT(?:\s+Chapter\s+[0-9]{1,2}:[0-9]{2})?(?:,?\s*[0-9]{4})?)\s*(?:\R|$)/u',
                '/\b([A-Z][A-Za-z0-9 ,&()\'\-.]{2,140}\s+Act(?:\s+Chapter\s+[0-9]{1,2}:[0-9]{2})?(?:,?\s*[0-9]{4})?)\b/u',
            ],
            'Statutory Instrument' => [
                '/\b((?:Statutory Instrument|S\.?I\.?)\s*[0-9]+\s*(?:of|\/)\s*[0-9]{4}[^.\n]{0,120})/iu',
            ],
            default => [],
        };

        $title = $this->firstNonEmptyMatch($header, $patterns);
        if (!$title) {
            return null;
        }

        $title = $this->removeLegislativeNumbering($title);
        $title = preg_replace('/\bARRANGEMENT OF SECTIONS\b.*$/iu', '', (string) $title);
        $title = trim((string) preg_replace('/\s+/u', ' ', (string) $title), " \t\n\r\0\x0B,.;:-");

        return $title !== '' && mb_strlen($title) <= 180 ? $title : null;
    }

    private function firstNonEmptyMatch(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $value = trim($match[1] ?? '');
                if ($value !== '') {
                    return preg_replace('/\s+/u', ' ', $value);
                }
            }
        }

        return null;
    }

    private function extractCourtName(string $text): ?string
    {
        $normalised = preg_replace('/[ \x{00A0}]+/u', ' ', $text);
        $header = mb_substr((string) $normalised, 0, 3500);

        $courtPatterns = [
            'Constitutional Court' => 'constitutional\s+court(?:\s+of\s+zimbabwe)?',
            'Supreme Court of Zimbabwe' => 'supreme\s+court(?:\s+of\s+zimbabwe)?',
            'High Court of Zimbabwe' => 'high\s+court(?:\s+of\s+zimbabwe)?',
            'Labour Court of Zimbabwe' => 'labour\s+court(?:\s+of\s+zimbabwe)?',
            'Magistrates Court' => 'magistrates?\s+court',
            'Administrative Court' => 'administrative\s+court',
            'Commercial Court' => 'commercial\s+court',
        ];

        $candidates = [];
        foreach ($courtPatterns as $court => $pattern) {
            if (preg_match('/(?:^|\n)\s*(?:in\s+the\s+)?' . $pattern . '\b/iu', $header, $match, PREG_OFFSET_CAPTURE)) {
                $candidates[] = [
                    'court' => $court,
                    'offset' => $match[0][1],
                    'score' => 0,
                ];
            }
        }

        if ($candidates === []) {
            foreach ($courtPatterns as $court => $pattern) {
                if (preg_match('/\b' . $pattern . '\b/iu', $header, $match, PREG_OFFSET_CAPTURE)) {
                    $candidates[] = [
                        'court' => $court,
                        'offset' => $match[0][1],
                        'score' => 1,
                    ];
                }
            }
        }

        if ($candidates !== []) {
            usort($candidates, static fn (array $left, array $right): int => [$left['score'], $left['offset']] <=> [$right['score'], $right['offset']]);

            return $candidates[0]['court'];
        }

        return $this->courtFromHeaderAbbreviation($header);
    }

    private function courtFromHeaderAbbreviation(string $header): ?string
    {
        $lines = preg_split('/\R/u', $header, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $headerText = implode("\n", array_slice(array_map(
            static fn ($line): string => trim((string) $line),
            $lines
        ), 0, 12));

        $abbreviations = [
            'Constitutional Court' => '/\b(?:CCZ|ZWCC)\s*\d{1,6}(?:[\/-]\d{2,4})?\b/iu',
            'Supreme Court of Zimbabwe' => '/\b(?:SC|ZWSC)\s*\d{1,6}(?:[\/-]\d{2,4})?\b/iu',
            'High Court of Zimbabwe' => '/\b(?:HH|HCH|HCC|HC)\s*\d{1,6}(?:[\/-]\d{2,4})?\b/iu',
            'Labour Court of Zimbabwe' => '/\bLC\s*\d{1,6}(?:[\/-]\d{2,4})?\b/iu',
        ];

        foreach ($abbreviations as $court => $pattern) {
            if (preg_match($pattern, $headerText)) {
                return $court;
            }
        }

        return null;
    }

    private function extractJudgeName(string $text, ?string $court): ?string
    {
        $courtPatterns = [
            'Supreme Court of Zimbabwe' => 'supreme\s+court(?:\s+of\s+zimbabwe)?',
            'High Court of Zimbabwe' => 'high\s+court(?:\s+of\s+zimbabwe)?',
            'Constitutional Court' => 'constitutional\s+court(?:\s+of\s+zimbabwe)?',
            'Labour Court of Zimbabwe' => 'labour\s+court(?:\s+of\s+zimbabwe)?',
            'Magistrates Court' => 'magistrates?\s+court',
            'Administrative Court' => 'administrative\s+court',
            'Commercial Court' => 'commercial\s+court',
        ];

        $patterns = [];
        if ($court && isset($courtPatterns[$court])) {
            $patterns[] = '/\b(?:in\s+the\s+)?' . $courtPatterns[$court] . '\s+([A-Z][A-Za-z\-]+)(?=\s+J(?:A|P)?\b|\s*,|\s*:|\s*\n|\s*$)/iu';
        }

        $patterns = array_merge($patterns, [
            '/\b(?:before|coram)\s*:?\s*([A-Z][A-Za-z\-]+(?:\s+[A-Z][A-Za-z\-]+){0,3})\s+J(?:A|P)?\b/iu',
            '/\b([A-Z][A-Za-z\-]+(?:\s+[A-Z][A-Za-z\-]+){0,2})\s+J(?:A|P)?\b/u',
            '/(?:Justice|JUDGE|Judge|Honourable)\s+([A-Z][A-Za-z\-]+(?:\s+[A-Z][A-Za-z\-]+){0,3})/u',
            '/(?:^|\n)\s*([A-Z][A-Z\-]+(?:\s+[A-Z][A-Z\-]+){0,2})\s+J(?:A|P)?(?:\s|\n|$)/u',
        ]);

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $text, $match)) {
                continue;
            }

            $candidate = trim((string) ($match[1] ?? ''));
            if ($candidate !== '' && !$this->isInvalidJudgeCandidate($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isInvalidJudgeCandidate(string $candidate): bool
    {
        $candidate = trim((string) preg_replace('/\s+/u', ' ', $candidate));
        if ($candidate === '') {
            return true;
        }

        return (bool) preg_match('/\b(court|zimbabwe|held|harare|bulawayo|mutare|gweru|masvingo|case|number|applicant|respondent|plaintiff|defendant|judgment|judgement|justice|judge|honourable|rules?|order)\b/iu', $candidate);
    }

    private function cleanPartyName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $value = preg_replace('/\b(OPPOSED APPLICATION|UNOPPOSED APPLICATION)\b/iu', '', $value);
        $value = preg_replace('/\b(applicant|respondent|plaintiff|defendant|appellant)\b/iu', '', $value);
        if ($value !== '' && $value === mb_strtoupper($value)) {
            $value = mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
            $value = preg_replace('/\bOf\b/u', 'of', $value);
            $value = preg_replace('/\bAnd\b/u', 'and', $value);
            $value = preg_replace('/\bPvt\b/u', 'Pvt', $value);
            $value = preg_replace('/\bLtd\b/u', 'Ltd', $value);
        }

        return trim((string) $value, " \t\n\r\0\x0B,.;:-");
    }

    private function uniqueTop(array $values, int $limit): array
    {
        $seen = [];
        $result = [];

        foreach ($values as $value) {
            $clean = trim((string) $value);
            if ($clean === '') {
                continue;
            }

            $key = mb_strtolower($clean);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $clean;

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function sanitizeExtractedName(string $value): string
    {
        $value = preg_replace('/\b(OPPOSED APPLICATION|UNOPPOSED APPLICATION)\b/iu', '', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        return trim((string) $value);
    }

    private function normaliseEntityName(string $value): string
    {
        $value = $this->sanitizeExtractedName($value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        if ($value === mb_strtoupper($value)) {
            $value = mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
            $value = preg_replace('/\bPvt\b/u', 'Pvt', $value);
            $value = preg_replace('/\bLtd\b/u', 'Ltd', $value);
            $value = preg_replace('/\bOf\b/u', 'of', $value);
            $value = preg_replace('/\bAnd\b/u', 'and', $value);
        }

        return trim((string) $value, " \t\n\r\0\x0B,.;:-");
    }

    private function normalisePersonName(string $value): string
    {
        $value = $this->sanitizeExtractedName($value);
        $value = preg_replace('/^(?:in\s+the\s+)?(?:supreme|high|constitutional|labour|magistrates?|administrative|commercial)\s+court(?:\s+of\s+zimbabwe)?\s+/iu', '', (string) $value);
        $value = preg_replace('/^(?:court\s+of\s+zimbabwe|of\s+zimbabwe)\s+/iu', '', (string) $value);
        $value = preg_replace('/\bJ(?:A|P)?\b/u', '', (string) $value);
        $value = trim((string) $value, " \t\n\r\0\x0B,.;:-");

        if ($value === '') {
            return '';
        }

        if ($value === mb_strtoupper($value)) {
            $value = mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
        }

        return $value;
    }

    private function buildLegislativeSummary(string $text): string
    {
        $sentences = $this->sentences($text);
        $highlights = $this->extractLegislativeHighlightsFromText($text, 2);
        if (count($highlights) < 2) {
            $highlights = array_values(array_unique(array_merge($highlights, $this->extractLegislativeHighlights($sentences, 2))));
            $highlights = array_slice($highlights, 0, 2);
        }
        $shortTitle = $this->extractBillTitle($text);

        if (empty($highlights)) {
            if ($shortTitle) {
                return 'This bill proposes legislative amendments set out in ' . preg_replace('/\s+/u', ' ', trim($shortTitle)) . '.';
            }

            return '';
        }

        $lead = $shortTitle
            ? 'This bill, ' . preg_replace('/\s+/u', ' ', trim($shortTitle)) . ', '
            : 'This bill ';

        return $lead . $this->joinHighlights($highlights);
    }

    private function extractLegislativeHighlightsFromText(string $text, int $limit): array
    {
        $highlights = [];

        if (preg_match('/insertion of a new section\s+([0-9]{1,4}[A-Z]?).*?(Zimbabwe Electoral Delimitation Commission)/iu', $text, $match)) {
            $highlights[] = 'proposes inserting section ' . $match[1] . ' to establish the ' . $match[2];
        }

        if (preg_match('/President shall appoint the\s+(Zimbabwe Electoral Delimitation Commission)/iu', $text, $match)) {
            $highlights[] = 'provides for the appointment of the ' . $match[1];
        }

        if (preg_match('/Section\s+([0-9]{1,4}[A-Z]?)\s+of\s+the\s+Constitution\s+is\s+amended.*?deletion of\s+(?:the words\s+)?["“]?Zimbabwe Electoral Commission["”]?\s+(?:with the substitution of|and the substitution of)\s+["“]?Zimbabwe Electoral Delimitation Commission["”]?/iu', $text, $match)) {
            $highlights[] = 'replaces references to the Zimbabwe Electoral Commission with the Zimbabwe Electoral Delimitation Commission in section ' . $match[1];
        }

        if (preg_match('/Section\s+([0-9]{1,4}[A-Z]?).*?amended by the repeal of\s+(.+?)(?:\.|$)/iu', $text, $match)) {
            $highlights[] = 'repeals ' . $this->trimLegislativeFragment($match[2]) . ' in section ' . $match[1];
        }

        return array_slice(array_values(array_unique(array_filter($highlights))), 0, $limit);
    }

    private function extractLegislativeHighlights(array $sentences, int $limit): array
    {
        $highlights = [];

        foreach ($sentences as $sentence) {
            $normalized = $this->normalizeLegislativeSentence($sentence);
            if ($normalized === '' || $this->isRedundantSentence($normalized, $highlights)) {
                continue;
            }

            $highlights[] = $normalized;
            if (count($highlights) >= $limit) {
                break;
            }
        }

        return $highlights;
    }

    private function normalizeLegislativeSentence(string $sentence): string
    {
        $sentence = preg_replace('/^\d+(?:\.\d+)?\s+/u', '', trim($sentence));
        $sentence = preg_replace('/\s+/u', ' ', (string) $sentence);

        if (!preg_match('/\b(insert(?:ion|ed)?|amend(?:ment|ed)?|repeal(?:ed)?|substitut(?:e|ion)|delete(?:ion|d)?|remove(?:d)?|establish(?:es|ed)?|appoint(?:ed)?|cited as|enacted)\b/iu', $sentence)) {
            return '';
        }

        if (preg_match('/new section\s+([0-9]{1,4}[A-Z]?).*?(Zimbabwe Electoral Delimitation Commission)/iu', $sentence, $match)) {
            return 'proposes inserting section ' . $match[1] . ' to establish the ' . $match[2] . '.';
        }

        if (preg_match('/President shall appoint the\s+(Zimbabwe Electoral Delimitation Commission)/iu', $sentence, $match)) {
            return 'provides for the appointment of the ' . $match[1] . '.';
        }

        if (preg_match('/Section\s+([0-9]{1,4}[A-Z]?)\s+of\s+the\s+Constitution\s+is\s+amended.*?deletion of\s+"?Zimbabwe Electoral Commission"? .*?substitution of\s+"?Zimbabwe Electoral Delimitation Commission"?/iu', $sentence, $match)) {
            return 'replaces references to the Zimbabwe Electoral Commission with the Zimbabwe Electoral Delimitation Commission in section ' . $match[1] . '.';
        }

        if (preg_match('/Section\s+([0-9]{1,4}[A-Z]?).*?amended by\s+(.+?)(?:\.|$)/iu', $sentence, $match)) {
            return 'amends section ' . $match[1] . ' by ' . $this->trimLegislativeFragment($match[2]) . '.';
        }

        if (preg_match('/This Act may be cited as\s+(.+?)(?:\.|$)/iu', $sentence, $match)) {
            return 'sets out the short title as ' . trim($match[1], "\"' ") . '.';
        }

        return $this->trimLegislativeFragment($sentence);
    }

    private function trimLegislativeFragment(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $text = preg_replace('/^(Insertion|Amendment|Clause)\b[^a-zA-Z]+/u', '', (string) $text);
        $text = preg_replace('/^\d+(?:\.\d+)?\s+/u', '', (string) $text);
        $text = preg_replace('/\bTHEREFORE,?\s*/iu', '', (string) $text);
        $text = trim((string) $text, " \t\n\r\0\x0B\"'");

        if ($text === '') {
            return '';
        }

        $text = mb_substr($text, 0, 220);
        $text = rtrim($text, " ,;:-");

        return lcfirst($text);
    }

    private function joinHighlights(array $highlights): string
    {
        $highlights = array_values(array_filter(array_map(fn($item) => rtrim(trim($item), '.'), $highlights)));
        if (empty($highlights)) {
            return '';
        }

        if (count($highlights) === 1) {
            return $highlights[0] . '.';
        }

        $last = array_pop($highlights);

        return implode('; ', $highlights) . '; and ' . $last . '.';
    }

    private function extractBillTitle(string $text): ?string
    {
        return $this->firstNonEmptyMatch($text, [
            '/This Act may be cited as\s+(.+?\bBill(?:,?\s*\d{4})?)/iu',
            '/\b((?:Constitution|Electoral|Finance|Criminal|Labour)[^.]{0,140}\bBill(?:,?\s*\d{4})?)\b/iu',
        ]);
    }

    private function condenseParagraph(array $sentences, int $limit, int $maxLength): string
    {
        $parts = [];

        foreach ($sentences as $sentence) {
            $clean = $this->normaliseSummarySentence($sentence);
            if ($clean === '' || $this->isRedundantSentence($clean, $parts)) {
                continue;
            }

            $parts[] = $clean;
            if (count($parts) >= $limit) {
                break;
            }
        }

        $paragraph = implode(' ', $parts);
        $paragraph = preg_replace('/\s+/u', ' ', (string) $paragraph);
        $paragraph = trim((string) $paragraph);

        if (mb_strlen($paragraph) > $maxLength) {
            $paragraph = rtrim(mb_substr($paragraph, 0, $maxLength - 1), " ,;:-") . '…';
        }

        return $paragraph;
    }

    private function normaliseSummarySentence(string $sentence): string
    {
        $sentence = trim((string) preg_replace('/\s+/u', ' ', $sentence));
        $sentence = $this->removeLegislativeNumbering($sentence);
        $sentence = preg_replace('/^\d+(?:\.\d+)?\s+/u', '', (string) $sentence);
        $sentence = preg_replace('/\bPAGE\s+\d+\b/iu', '', (string) $sentence);
        $sentence = trim((string) $sentence, " \t\n\r\0\x0B\"'");

        if ($sentence === '') {
            return '';
        }

        $sentence = rtrim($sentence, ';');
        $sentence = preg_match('/[.!?]$/u', $sentence) ? $sentence : $sentence . '.';
        $first = mb_substr($sentence, 0, 1);

        return mb_strtoupper($first) . mb_substr($sentence, 1);
    }

    private function isLegislativeDocument(string $docType): bool
    {
        return in_array($docType, ['Bill', 'Act', 'Statutory Instrument'], true);
    }

    private function isBillDocument(string $docType): bool
    {
        return $docType === 'Bill';
    }

    private function isWeakKeywordToken(string $token, array $stopWords): bool
    {
        return isset($stopWords[$token]) || mb_strlen($token) < 4 || preg_match('/^\d+$/', $token) === 1;
    }

    private function candidateNgrams(array $tokens, array $stopWords): array
    {
        $ngrams = [];
        $count = count($tokens);

        for ($size = 2; $size <= 3; $size++) {
            for ($i = 0; $i <= $count - $size; $i++) {
                $slice = array_slice($tokens, $i, $size);
                if (count(array_filter($slice, fn($token) => $this->isWeakKeywordToken($token, $stopWords))) > 0) {
                    continue;
                }

                $phrase = implode(' ', $slice);
                if (!$this->isUsefulKeywordPhrase($slice, $phrase, $size)) {
                    continue;
                }

                $ngrams[$phrase] = max($ngrams[$phrase] ?? 0, $size === 3 ? 1.3 : 1.0);
            }
        }

        return $ngrams;
    }

    private function isLegalKeywordPhrase(string $value): bool
    {
        return preg_match('/\b(agreement|appeal|assessment|certificate|claim|commissioner|contract|court|deed|director|document|employment|environmental|evidence|impact|judgment|judge|labour|lease|management|mining|notice|party|project|registration|regulation|schedule|section|statutory)\b/u', $value) === 1;
    }

    private function isUsefulKeywordPhrase(array $slice, string $phrase, int $size): bool
    {
        $edgeStopWords = [
            'according', 'before', 'following', 'however', 'issued', 'listed',
            'must', 'provides', 'shall', 'therefore', 'unless', 'whereas',
        ];

        if (in_array($slice[0], $edgeStopWords, true) || in_array($slice[$size - 1], $edgeStopWords, true)) {
            return false;
        }

        if (!$this->isLegalKeywordPhrase($phrase) && $size === 3) {
            return false;
        }

        return preg_match('/\b(agreement|assessment|certificate|claim|commissioner|contract|court|deed|document|employment|judgment|labour|lease|law|management|mining|notice|obligation|order|party|project|registration|report|schedule|section|statute|terms)\b/u', $slice[$size - 1]) === 1;
    }

    private function hasSummaryCue(string $sentence): bool
    {
        return (bool) preg_match('/\b(issue|issues|background|facts|held|found|evidence|dispute|applicant|respondent|claim|relief)\b/iu', $sentence);
    }

    private function hasOutcomeCue(string $sentence): bool
    {
        return (bool) preg_match('/\b(ordered|dismissed|granted|refused|upheld|set aside|null and void|cancelled|costs|declared)\b/iu', $sentence);
    }

    private function hasLegalPrincipleCue(string $sentence): bool
    {
        return (bool) preg_match('/\b(section|act|principle|constitutional|statutory|ultra vires|audi alteram partem|res judicata)\b/iu', $sentence);
    }

    private function isRedundantSentence(string $candidate, array $selected): bool
    {
        $candidateWords = array_unique(preg_split('/\W+/u', mb_strtolower($candidate), -1, PREG_SPLIT_NO_EMPTY));
        if (empty($candidateWords)) {
            return false;
        }

        foreach ($selected as $existing) {
            $existingWords = array_unique(preg_split('/\W+/u', mb_strtolower($existing), -1, PREG_SPLIT_NO_EMPTY));
            if (empty($existingWords)) {
                continue;
            }

            $overlap = count(array_intersect($candidateWords, $existingWords));
            $ratio = $overlap / max(1, min(count($candidateWords), count($existingWords)));
            if ($ratio >= 0.8) {
                return true;
            }
        }

        return false;
    }
}
