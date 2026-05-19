<?php

namespace App\Services;

class LegalInsightService
{
    private const KNOWN_LOCATIONS = [
        'Harare',
        'Bulawayo',
        'Mutare',
        'Gweru',
        'Masvingo',
        'Chinhoyi',
        'Bindura',
        'Marondera',
        'Hwange',
        'Kadoma',
        'Kwekwe',
        'Beitbridge',
        'Lupane',
    ];

    private const LATIN_LEGAL_REPLACEMENTS = [
        'ignorantia juris non excusat' => 'ignorance of the law is not an excuse',
        'volenti non fit injuria' => 'accepted the risk willingly',
        'qui facit per alium facit per se' => 'a person acting through another acts personally',
        'ex turpi causa non oritur actio' => 'no legal claim arises from wrongful conduct',
        'nemo dat quod non habet' => 'no one can give what they do not have',
        'nemo judex in causa sua' => 'no one should judge their own case',
        'audi alteram partem' => 'hear both sides',
        'pacta sunt servanda' => 'agreements must be kept',
        'causa sine qua non' => 'necessary cause',
        'lis alibi pendens' => 'same dispute pending in another court',
        'animus contrahendi' => 'intention to make a contract',
        'animus possidendi' => 'intention to possess',
        'ratio decidendi' => 'legal reason for the decision',
        'obiter dictum' => 'additional comment by the judge',
        'nolle prosequi' => 'decision not to prosecute',
        'amicus curiae' => 'friend of the court',
        'mutatis mutandis' => 'with necessary changes',
        'caveat emptor' => 'buyer beware',
        'stare decisis' => 'follow previous court decisions',
        'res judicata' => 'already decided',
        'locus standi' => 'legal right to sue',
        'habeas corpus' => 'right against unlawful detention',
        'mens rea' => 'criminal intention',
        'actus reus' => 'criminal act',
        'prima facie' => 'based on first evidence',
        'ultra vires' => 'beyond legal powers',
        'bona fide' => 'genuine',
        'mala fide' => 'in bad faith',
        'inter alia' => 'among other things',
        'sub judice' => 'currently before the court',
        'ex parte' => 'involving one side only',
        'pro bono' => 'free legal service',
        'de facto' => 'in practice',
        'de jure' => 'by law',
        'in camera' => 'in private',
        'ad hoc' => 'for a specific purpose',
        'ipso facto' => 'automatically',
        'per annum' => 'per year',
        'ad litem' => 'for this case',
        'ad idem' => 'in agreement',
        'ad valorem' => 'according to value',
        'casus omissus' => 'situation not covered by the law',
        'causa causans' => 'direct legal cause',
        'corpus delicti' => 'proof that a crime was committed',
        'culpa lata' => 'serious negligence',
        'culpa levis' => 'ordinary negligence',
        'damnum sine injuria' => 'harm without legal injury',
        'de bonis propriis' => 'from personal property',
        'de novo' => 'from the beginning',
        'dies non' => 'day when legal business is not done',
        'dolus eventualis' => 'accepting the risk that unlawful harm may occur',
        'ejusdem generis' => 'of the same kind',
        'ex officio' => 'by virtue of office',
        'ex post facto' => 'after the event',
        'functus officio' => 'having no further legal authority',
        'in limine' => 'at the preliminary stage',
        'in pari delicto' => 'equally at fault',
        'in personam' => 'against a person',
        'in rem' => 'against property or a thing',
        'in situ' => 'in its original place',
        'in toto' => 'completely',
        'jus cogens' => 'fundamental rule of international law',
        'jus naturale' => 'natural law',
        'jus sanguinis' => 'citizenship by descent',
        'jus soli' => 'citizenship by place of birth',
        'lis pendens' => 'pending legal dispute',
        'non est factum' => 'this is not my deed',
        'onus probandi' => 'burden of proof',
        'per curiam' => 'by the court',
        'per se' => 'by itself',
        'persona non grata' => 'unacceptable person',
        'quid pro quo' => 'something given in return',
        'sine die' => 'without setting a date',
        'sine qua non' => 'essential requirement',
        'status quo' => 'existing position',
        'sui generis' => 'unique in its own class',
        'suo motu' => "on the court's own initiative",
        'uberrimae fidei' => 'utmost good faith',
        'vis major' => 'unavoidable superior force',
        'viva voce' => 'oral evidence',
        'animus' => 'intention',
        'causa' => 'legal reason',
        'certiorari' => 'court review order',
        'culpa' => 'fault or negligence',
        'delictum' => 'civil wrong',
        'dolus' => 'intentional wrongdoing',
        'mandamus' => 'court order to perform a public duty',
        'subpoena' => 'order to attend court or produce evidence',
        'affidavit' => 'sworn written statement',
        'vicarious liability' => "legal responsibility for another person's conduct",
    ];

    public function __construct(
        private readonly PythonNlpBridgeService $pythonNlp
    ) {
    }

    public function buildAiContext(string $text, array $nlpResult): array
    {
        $passages = $this->supportingPassages($text, $nlpResult, 6);

        return [
            'passages' => $passages,
            'prompt_text' => $this->formatAiPassages($passages, $nlpResult),
        ];
    }

    public function enrich(string $text, array $result, array $context = []): array
    {
        $supportingPassages = $context['passages'] ?? $this->supportingPassages($text, $result, 6);
        $labelledEntities = $this->labelledEntities($text, $result);
        $structuredPanels = $this->structuredPanels($text, $result, $labelledEntities, $supportingPassages);
        $result['structured_panels'] = $structuredPanels;
        $professionalSummary = $this->professionalSummary($result, $supportingPassages);
        $citizenSummary = $this->citizenSummary($result, $supportingPassages);
        $resultCards = $this->resultCards($text, $result, $structuredPanels, $supportingPassages);
        $sourceMap = $this->sourceMap($supportingPassages, $resultCards, $structuredPanels);
        $semanticProfile = $this->semanticProfile($text, $result, $structuredPanels);

        $result['professional_summary'] = $professionalSummary;
        $result['citizen_summary'] = $citizenSummary;
        $result['executive_summary'] = $result['executive_summary'] ?: $professionalSummary;
        if (($result['summary_type'] ?? null) === SummaryPromptTemplateService::GENERAL_USER) {
            $result['executive_summary'] = $this->plainLanguage((string) $result['executive_summary']);
            $result['citizen_summary'] = $this->plainLanguage((string) $result['citizen_summary']);
        }
        $result['clauses'] = $structuredPanels['structured_extraction']['clauses'] ?? ($result['clauses'] ?? []);
        $result['structured_extraction'] = $structuredPanels['structured_extraction'] ?? [];
        $result['result_cards'] = $resultCards;
        $result['structured_panels'] = $structuredPanels;
        $result['supporting_passages'] = $supportingPassages;
        $result['source_map'] = $sourceMap;
        $result['semantic_profile'] = $semanticProfile;
        $result['nlp_entities'] = array_merge($result['nlp_entities'] ?? [], [
            'labels' => $labelledEntities,
        ]);

        return $result;
    }

    public function scoreSearch(string $query, array $summaryPayload): float
    {
        $queryProfile = $this->semanticProfile($query, [
            'document_type' => null,
            'nlp_keywords' => [],
            'nlp_legal_categories' => [],
        ], []);

        $summaryProfile = $summaryPayload['semantic_profile'] ?? [];
        $queryVector = $queryProfile['vector'] ?? [];
        $summaryVector = $summaryProfile['vector'] ?? [];

        if (!is_array($queryVector) || !is_array($summaryVector) || $queryVector === [] || $summaryVector === []) {
            return 0.0;
        }

        return round($this->cosineSimilarity($queryVector, $summaryVector), 4);
    }

    private function formatAiPassages(array $passages, array $nlpResult): string
    {
        $header = [
            'Document type: ' . ($nlpResult['document_type'] ?? 'Legal Document'),
            'Court: ' . ($nlpResult['court'] ?? 'Unknown'),
            'Case number: ' . ($nlpResult['case_number'] ?? 'Unknown'),
            'Keywords: ' . implode(', ', array_slice($nlpResult['nlp_keywords'] ?? [], 0, 8)),
        ];

        $items = array_map(function (array $passage): string {
            return '[Passage ' . $passage['id'] . ' | page ' . ($passage['page'] ?? '?') . '] ' . $passage['text'];
        }, $passages);

        return implode("\n", array_filter(array_merge($header, [''], $items)));
    }

    private function professionalSummary(array $result, array $supportingPassages): string
    {
        if (!empty($result['professional_summary'])) {
            return $this->normaliseParagraph((string) $result['professional_summary']);
        }

        $intro = $this->buildIntro($result, true);
        $issues = $this->listToSentence($result['structured_panels']['key_legal_issues'] ?? []);
        $outcome = $this->normaliseParagraph((string) ($result['outcome'] ?? ''));
        $references = $this->listToSentence($result['structured_panels']['important_legal_references'] ?? []);
        $implications = $this->normaliseParagraph((string) ($result['practical_implications'] ?? ''));
        $support = $this->listToSentence($this->passageHighlights($supportingPassages, 2, 170));

        $parts = array_filter([
            $intro,
            $issues ? 'The central legal issues include ' . $issues . '.' : null,
            $outcome && !str_contains(mb_strtolower($outcome), 'not expressly stated') ? 'The operative result is that ' . $this->lowercaseFirst($outcome) : null,
            $references ? 'Important legal references include ' . $references . '.' : null,
            $implications ? 'In practical terms, ' . $this->lowercaseFirst($implications) : null,
            $support ? 'The most relevant extracted passages show that ' . $support . '.' : null,
        ]);

        return $this->normaliseParagraph(implode(' ', $parts));
    }

    private function citizenSummary(array $result, array $supportingPassages): string
    {
        $providedCitizenSummary = $this->normaliseParagraph((string) ($result['citizen_summary'] ?? ''));
        if ($providedCitizenSummary !== '' && !$this->shouldRegenerateCitizenSummary($providedCitizenSummary, $result)) {
            return $providedCitizenSummary;
        }

        $intro = $this->buildIntro($result, false);
        $outcome = $this->plainLanguage((string) ($result['outcome'] ?? ''));
        $issues = $result['structured_panels']['key_legal_issues'] ?? [];
        $implications = $this->plainLanguage((string) ($result['practical_implications'] ?? ''));
        $caseSnippets = $this->caseSpecificPlainSnippets($supportingPassages);
        $existing = $this->plainLanguage((string) (($result['executive_summary'] ?? '') ?: ($result['professional_summary'] ?? '')));

        $parts = array_filter([
            $intro,
            !empty($caseSnippets['facts']) ? 'The main facts are that ' . $this->lowercaseFirst($caseSnippets['facts']) : null,
            !empty($issues) ? 'At its core, the document is about ' . $this->listToSentence($issues) . '.' : null,
            !empty($caseSnippets['reasoning']) ? 'The court reasoned that ' . $this->lowercaseFirst($caseSnippets['reasoning']) : null,
            $outcome && !str_contains(mb_strtolower($outcome), 'not expressly stated') ? 'In everyday terms, the result is that ' . $this->lowercaseFirst($outcome) : null,
            !empty($caseSnippets['outcome']) && ($outcome === '' || str_contains(mb_strtolower($outcome), 'not expressly stated')) ? 'The result stated in the extracted text is that ' . $this->lowercaseFirst($caseSnippets['outcome']) : null,
            $implications !== '' ? 'What this means in practice is that ' . $this->lowercaseFirst($implications) : null,
            empty($issues) && $outcome === '' && $existing !== '' ? $existing : null,
        ]);

        return $this->normaliseParagraph(implode(' ', $parts));
    }

    private function caseSpecificPlainSnippets(array $supportingPassages): array
    {
        $snippets = ['facts' => '', 'reasoning' => '', 'outcome' => ''];

        foreach ($supportingPassages as $passage) {
            $text = $this->plainLanguage($this->trimSentence((string) ($passage['text'] ?? '')));
            if ($text === '' || $text === '.') {
                continue;
            }

            $kind = $this->passageKind($text);
            if (isset($snippets[$kind]) && $snippets[$kind] === '') {
                $snippets[$kind] = $this->shortSnippet($text, 46);
            }
        }

        return $snippets;
    }

    private function shortSnippet(string $text, int $wordLimit): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= $wordLimit) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
    }

    private function buildIntro(array $result, bool $formal): string
    {
        $court = $result['court'] ?? null;
        $date = $result['date_of_document'] ?? null;
        $parties = array_values(array_filter($result['parties'] ?? []));
        $docType = (string) ($result['document_type'] ?? 'Legal document');

        if ($formal) {
            $sentence = 'This ' . mb_strtolower($docType) . ' ';
            if ($court) {
                $sentence .= 'from the ' . $court . ' ';
            }
            if ($date) {
                $sentence .= 'dated ' . $date . ' ';
            }
            $sentence = trim($sentence);

            if (count($parties) >= 2) {
                return rtrim($sentence, '.') . ' concerns a dispute between ' . $parties[0] . ' and ' . $parties[1] . '.';
            }

            return rtrim($sentence, '.') . ' addresses a Zimbabwean legal dispute or legal arrangement.';
        }

        if (count($parties) >= 2) {
            return 'This document explains a legal problem involving ' . $parties[0] . ' and ' . $parties[1] . '.';
        }

        return 'This document explains what happened, what legal rules mattered, and what the result means in practice.';
    }

    private function resultCards(string $text, array $result, array $structuredPanels, array $supportingPassages): array
    {
        $cards = [];
        $outcomeText = mb_strtolower((string) ($result['outcome'] ?? ''));
        $fullText = mb_strtolower($text . "\n" . implode("\n", array_column($supportingPassages, 'text')));

        $caseResults = [];
        foreach ([
            'Appeal dismissed' => '/appeal[^.]{0,80}dismissed|dismissed[^.]{0,80}appeal/u',
            'Judgment upheld' => '/judgment[^.]{0,40}upheld|upheld[^.]{0,40}judgment/u',
            'Application granted' => '/application[^.]{0,60}granted|granted[^.]{0,60}application/u',
            'Applicant ordered to pay costs' => '/applicant[^.]{0,60}pay costs|costs[^.]{0,40}applicant/u',
            'Respondent ordered to pay costs' => '/respondent[^.]{0,60}pay costs|costs[^.]{0,40}respondent/u',
            'Sentence imposed' => '/sentence[^.]{0,80}(imprisonment|years|months|fine)|imprisoned|sentenced/u',
        ] as $label => $pattern) {
            if (preg_match($pattern, $fullText) || str_contains($outcomeText, mb_strtolower($label))) {
                $caseResults[] = $label;
            }
        }
        if ($caseResults === [] && !empty($result['outcome'])) {
            $caseResults[] = $this->trimSentence((string) $result['outcome']);
        }
        if ($caseResults !== []) {
            $cards[] = [
                'title' => 'Case Result',
                'tone' => 'success',
                'items' => array_values(array_unique($caseResults)),
            ];
        }

        $compensation = $this->extractMatches($text, [
            '/\b(compensation(?: of)?\s+(?:USD|US\$|ZWL|\$)?\s?[\d,]+(?:\.\d{2})?)\b/iu',
            '/\b(damages(?: of)?\s+(?:USD|US\$|ZWL|\$)?\s?[\d,]+(?:\.\d{2})?)\b/iu',
            '/\b(awarded\s+(?:USD|US\$|ZWL|\$)?\s?[\d,]+(?:\.\d{2})?)\b/iu',
        ]);
        if ($compensation !== []) {
            $cards[] = [
                'title' => 'Compensation Awarded',
                'tone' => 'warning',
                'items' => $compensation,
            ];
        }

        $sentences = $this->extractMatches($text, [
            '/\b(sentenced to [^.]{3,120})\b/iu',
            '/\b(imprison(?:ment|ed)[^.]{3,120})\b/iu',
            '/\b(fined [^.]{3,120})\b/iu',
        ]);
        if ($sentences !== []) {
            $cards[] = [
                'title' => 'Sentence Imposed',
                'tone' => 'danger',
                'items' => $sentences,
            ];
        }

        $rights = array_values(array_unique(array_filter($structuredPanels['constitutional_rights_affected'] ?? [])));
        if ($rights !== []) {
            $cards[] = [
                'title' => 'Constitutional Rights Affected',
                'tone' => 'info',
                'items' => $rights,
            ];
        }

        $orders = $this->extractCourtOrders($text, $supportingPassages);
        if ($orders !== []) {
            $cards[] = [
                'title' => 'Court Orders',
                'tone' => 'neutral',
                'items' => $orders,
            ];
        }

        return $cards;
    }

    private function structuredPanels(string $text, array $result, array $labelledEntities, array $supportingPassages): array
    {
        $clauses = $this->normaliseClauses($result['clauses'] ?? []);
        $issues = array_values(array_unique(array_merge(
            (array) ($result['key_issues'] ?? []),
            $this->issues(
            implode("\n", array_column($supportingPassages, 'text')) . "\n" . ($result['professional_summary'] ?? '') . "\n" . ($result['executive_summary'] ?? ''),
            $result['nlp_legal_categories'] ?? [],
            $result['nlp_keywords'] ?? []
            )
        )));
        $references = array_values(array_unique(array_merge(
            (array) ($result['legal_references'] ?? []),
            $this->legalReferences(
            $labelledEntities['LEGAL_SECTION'] ?? [],
            $result['legal_principles'] ?? '',
            $result['nlp_keywords'] ?? []
            )
        )));
        $citedInstruments = $this->citedInstruments(
            implode("\n", [
                $text,
                implode("\n", array_column($supportingPassages, 'text')),
                (string) ($result['legal_principles'] ?? ''),
                implode("\n", (array) ($result['legal_references'] ?? [])),
                implode("\n", (array) ($result['cited_instruments'] ?? [])),
            ]),
            array_merge(
                (array) ($result['cited_instruments'] ?? []),
                (array) ($result['legal_references'] ?? []),
                $labelledEntities['LEGAL_SECTION'] ?? []
            )
        );
        $peopleAndOrganisations = array_values(array_slice(array_unique(array_merge(
            $labelledEntities['PERSON'] ?? [],
            $labelledEntities['ORGANISATION'] ?? []
        )), 0, 10));

        return [
            'case_information' => [
                'Court' => $result['court'] ?? 'Not clearly stated',
                'Judge' => $result['judge'] ?? 'Not clearly stated',
                'Case Number' => $result['case_number'] ?? 'Not clearly stated',
                'Date' => $result['date_of_document'] ?? 'Not clearly stated',
            ],
            'key_legal_issues' => $issues,
            'cited_instruments' => $citedInstruments,
            'important_legal_references' => $references,
            'people_and_organisations' => $peopleAndOrganisations,
            'constitutional_rights_affected' => $this->constitutionalRights($references, $issues),
            'key_clauses' => array_slice($clauses, 0, 6),
            'structured_extraction' => [
                'document_type' => $result['document_type'] ?? '',
                'entities' => [
                    'court' => $result['court'] ?? '',
                    'judge' => $result['judge'] ?? '',
                    'parties' => array_values(array_filter($result['parties'] ?? [])),
                    'dates' => array_values(array_filter($labelledEntities['DATE'] ?? [])),
                    'money_amounts' => array_values(array_filter($result['nlp_entities']['amounts'] ?? [])),
                    'laws_cited' => $citedInstruments,
                    'legal_references' => $references,
                ],
                'clauses' => $clauses,
            ],
        ];
    }

    private function supportingPassages(string $text, array $result, int $limit): array
    {
        $pages = $this->pages($text);
        $queryTerms = array_filter(array_merge(
            explode(' ', mb_strtolower((string) ($result['document_type'] ?? ''))),
            array_map('mb_strtolower', $result['nlp_keywords'] ?? []),
            array_map('mb_strtolower', $result['nlp_legal_categories'] ?? []),
            array_map('mb_strtolower', $result['parties'] ?? []),
            [$result['case_number'] ?? '', $result['court'] ?? '', $result['judge'] ?? '']
        ));

        $passages = [];
        foreach ($pages as $page) {
            $paragraphs = preg_split('/\n{2,}/u', trim($page['text']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($paragraphs as $paragraph) {
                $clean = trim((string) preg_replace('/\s+/u', ' ', $paragraph));
                if (mb_strlen($clean) < 80) {
                    continue;
                }

                $score = $this->passageScore($clean, $queryTerms);
                if ($score <= 0) {
                    continue;
                }

                $passages[] = [
                    'id' => 'P' . $page['page'] . '-' . (count($passages) + 1),
                    'page' => $page['page'],
                    'score' => round($score, 3),
                    'text' => $clean,
                ];
            }
        }

        usort($passages, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $this->balancedCasePassages($passages, $limit);
    }

    private function balancedCasePassages(array $passages, int $limit): array
    {
        $selected = [];
        foreach (['facts', 'outcome', 'reasoning'] as $kind) {
            foreach ($passages as $passage) {
                if (isset($selected[$passage['id']])) {
                    continue;
                }

                if ($this->passageKind((string) ($passage['text'] ?? '')) === $kind) {
                    $selected[$passage['id']] = $passage;
                    break;
                }
            }
        }

        foreach ($passages as $passage) {
            if (count($selected) >= $limit) {
                break;
            }

            $selected[$passage['id']] = $passage;
        }

        return array_values(array_slice($selected, 0, $limit));
    }

    private function passageKind(string $passage): string
    {
        $haystack = mb_strtolower($passage);
        if (preg_match('/\b(accused|deceased|applicant|respondent|plaintiff|defendant|appellant|complainant|charged|convicted|murder|culpable homicide|assault|evidence|testified|witness|facts?|on the day|incident)\b/iu', $haystack)) {
            return 'facts';
        }

        if (preg_match('/\b(ordered|dismissed|granted|refused|upheld|set aside|convicted|acquitted|sentenced|sentence|costs|in the result|accordingly|therefore)\b/iu', $haystack)) {
            return 'outcome';
        }

        if (preg_match('/\b(reason|because|found that|held that|satisfied|not satisfied|accepted|rejected|evidence shows|court finds|court found)\b/iu', $haystack)) {
            return 'reasoning';
        }

        return 'other';
    }

    private function normaliseClauses(array $clauses): array
    {
        $results = [];
        $seen = [];

        foreach ($clauses as $clause) {
            if (!is_array($clause)) {
                continue;
            }

            $heading = preg_replace('/\s+/u', ' ', trim((string) ($clause['heading'] ?? '')));
            $content = preg_replace('/\s+/u', ' ', trim((string) ($clause['content'] ?? '')));
            if ($heading === '' || $content === '') {
                continue;
            }

            $key = mb_strtolower($heading . '|' . $content);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $results[] = [
                'clause_type' => trim((string) ($clause['clause_type'] ?? 'GENERAL_CLAUSE')),
                'heading' => $heading,
                'content' => $content,
            ];
        }

        return $results;
    }

    private function passageScore(string $passage, array $queryTerms): float
    {
        $score = 0.0;
        $haystack = mb_strtolower($passage);

        foreach ($queryTerms as $term) {
            $term = trim((string) $term);
            if ($term === '' || mb_strlen($term) < 4) {
                continue;
            }

            if (str_contains($haystack, $term)) {
                $score += str_contains($term, ' ') ? 1.3 : 0.7;
            }
        }

        foreach ([
            'ordered', 'dismissed', 'granted', 'upheld', 'costs',
            'section', 'constitution', 'labour', 'agreement', 'appeal',
        ] as $cue) {
            if (str_contains($haystack, $cue)) {
                $score += 0.55;
            }
        }

        foreach ([
            'accused', 'deceased', 'charged', 'convicted', 'acquitted', 'sentenced',
            'applicant', 'respondent', 'plaintiff', 'defendant', 'appellant',
            'witness', 'testified', 'evidence', 'facts', 'incident',
        ] as $cue) {
            if (str_contains($haystack, $cue)) {
                $score += 0.9;
            }
        }

        if (preg_match('/\b(principle|requirements?|defence|self-defence|burden of proof|reasonable possibility)\b/iu', $haystack)
            && !preg_match('/\b(accused|deceased|applicant|respondent|convicted|acquitted|sentenced|dismissed|granted|ordered)\b/iu', $haystack)) {
            $score -= 1.0;
        }

        return $score;
    }

    private function labelledEntities(string $text, array $result): array
    {
        $entities = [
            'COURT_NAME' => array_values(array_unique(array_filter(array_merge(
                (array) ($result['nlp_entities']['courts'] ?? []),
                [$result['court'] ?? null]
            )))),
            'PERSON' => array_values(array_unique(array_filter(array_merge(
                (array) ($result['nlp_entities']['persons'] ?? []),
                [$result['judge'] ?? null]
            )))),
            'ORGANISATION' => array_values(array_unique((array) ($result['nlp_entities']['organisations'] ?? []))),
            'LOCATION' => [],
            'DATE' => array_values(array_unique(array_filter(array_merge(
                (array) ($result['nlp_entities']['dates'] ?? []),
                [$result['date_of_document'] ?? null]
            )))),
            'LEGAL_SECTION' => [],
            'CASE_NUMBER' => array_values(array_unique(array_filter([$result['case_number'] ?? null]))),
        ];

        foreach (self::KNOWN_LOCATIONS as $location) {
            if (preg_match('/\b' . preg_quote($location, '/') . '\b/u', $text)) {
                $entities['LOCATION'][] = $location;
            }
        }

        foreach ($this->extractMatches($text, [
            '/\b(Section\s+\d+[A-Za-z]?(?:\([^)]+\))?(?:\s+of\s+the\s+[A-Z][A-Za-z\s]+)?(?:\s+Constitution)?)\b/u',
            '/\b(Chapter\s+\d{1,2}:\d{2})\b/u',
            '/\b(Article\s+\d+[A-Za-z]?(?:\([^)]+\))?)\b/u',
        ]) as $section) {
            $entities['LEGAL_SECTION'][] = $section;
        }

        foreach ($this->extractMatches($text, [
            '/\b((?:HH|HC|HCH|HCC|SC|CCZ|LC)\s*\d{1,4}(?:[-\/]\d{2,4})?)\b/u',
            '/\b(\d{4}\s+ZW[A-Z]+\s+\d+)\b/u',
        ]) as $caseNumber) {
            $entities['CASE_NUMBER'][] = $caseNumber;
        }

        if ($pythonEntities = $this->pythonNlp->analyse($text, $result['document_type'] ?? '')) {
            foreach (['COURT_NAME', 'PERSON', 'ORGANISATION', 'LOCATION', 'DATE', 'LEGAL_SECTION', 'CASE_NUMBER'] as $label) {
                $entities[$label] = array_values(array_unique(array_merge(
                    $entities[$label],
                    is_array($pythonEntities['entities'][$label] ?? null) ? $pythonEntities['entities'][$label] : []
                )));
            }
        }

        foreach ($entities as $label => $values) {
            $entities[$label] = array_values(array_slice(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                $values
            ))), 0, 12));
        }

        return $entities;
    }

    private function issues(string $text, array $categories, array $keywords): array
    {
        $map = [
            'Labour dispute' => '/labour|employment|employee|employer|dismissal|salary|retrenchment/i',
            'Unfair dismissal' => '/unfair dismissal|wrongful dismissal|termination of employment/i',
            'Breach of contract' => '/breach|contract|agreement|non-compliance|default/i',
            'Property dispute' => '/property|land|title deed|lease|ownership/i',
            'Constitutional rights' => '/constitution|bill of rights|section 56|section 48|section 58/i',
            'Criminal sentence' => '/sentence|imprisonment|accused|convicted|fine/i',
            'Administrative review' => '/administrative|review|minister|authority|commission/i',
        ];

        $issues = [];
        foreach ($map as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                $issues[] = $label;
            }
        }

        foreach ($categories as $category) {
            if ($category === 'Labour Law' && !in_array('Labour dispute', $issues, true)) {
                $issues[] = 'Labour dispute';
            }
            if ($category === 'Contract Law' && !in_array('Breach of contract', $issues, true)) {
                $issues[] = 'Breach of contract';
            }
            if ($category === 'Constitutional Law' && !in_array('Constitutional rights', $issues, true)) {
                $issues[] = 'Constitutional rights';
            }
        }

        foreach ($keywords as $keyword) {
            if (preg_match('/rights?/i', $keyword) && !in_array('Constitutional rights', $issues, true)) {
                $issues[] = 'Constitutional rights';
            }
        }

        return array_slice(array_values(array_unique($issues)), 0, 6);
    }

    private function legalReferences(array $legalSections, string $principles, array $keywords): array
    {
        $references = array_values(array_unique(array_filter($legalSections)));
        if ($principles !== '') {
            foreach ($this->extractMatches($principles, [
                '/\b(Section\s+\d+[A-Za-z]?(?:\([^)]+\))?(?:\s+of\s+the\s+[A-Z][A-Za-z\s]+)?(?:\s+Constitution)?)\b/u',
                '/\b(Chapter\s+\d{1,2}:\d{2})\b/u',
                '/\b([A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+){0,5}\s+Act(?:\s+Chapter\s+\d{1,2}:\d{2})?)\b/u',
            ]) as $ref) {
                $references[] = $ref;
            }
        }

        foreach ($keywords as $keyword) {
            if (preg_match('/section|chapter|act/i', $keyword)) {
                $references[] = $keyword;
            }
        }

        return array_slice(array_values(array_unique(array_filter($references))), 0, 8);
    }

    private function citedInstruments(string $text, array $knownReferences): array
    {
        $source = trim($text . "\n" . implode("\n", array_map('strval', $knownReferences)));
        if ($source === '') {
            return [];
        }

        $items = [];

        foreach ($this->extractMatches($source, [
            '/\b(Constitution(?:\s+of\s+Zimbabwe)?(?:\s+Amendment\s+\(No\.?\s*\d+\)\s+Act,\s*\d{4})?)\b/iu',
            '/\b([A-Z][A-Za-z&,\-()]+(?:\s+(?:and|of|the|for|in|on|to|[A-Z][A-Za-z&,\-()]+)){0,9}\s+(?:Act|Regulations|Rules|Code|By-laws|Statutory Instrument)(?:\s*(?:No\.?\s*)?\d+)?(?:\s*(?:of|,)\s*\d{4})?(?:\s*\[?Chapter\s+\d{1,2}:\d{2}\]?)?)\b/u',
            '/\b((?:SI|S\.I\.|Statutory Instrument)\s*\d+\s*(?:of|\/)\s*\d{4})\b/iu',
            '/\b(Chapter\s+\d{1,2}:\d{2})\b/u',
        ]) as $instrument) {
            $normalised = $this->normaliseInstrumentName($instrument);
            if ($normalised !== '') {
                $items[$this->instrumentKey($normalised)] = $normalised;
            }
        }

        foreach ($knownReferences as $reference) {
            $reference = trim((string) $reference);
            if ($reference === '' || !$this->looksLikeInstrumentReference($reference)) {
                continue;
            }

            $normalised = $this->normaliseInstrumentName($reference);
            if ($normalised !== '') {
                $items[$this->instrumentKey($normalised)] = $normalised;
            }
        }

        $items = $this->removeRedundantInstruments($items);

        $withProvisions = [];
        foreach ($items as $key => $instrument) {
            $provisions = $this->instrumentProvisions($source, $instrument);
            $withProvisions[$key] = $provisions === []
                ? $instrument
                : $instrument . ' - ' . implode(', ', $provisions);
        }

        return array_slice(array_values($withProvisions), 0, 15);
    }

    private function removeRedundantInstruments(array $items): array
    {
        foreach ($items as $key => $instrument) {
            $lower = mb_strtolower($instrument);
            if (preg_match('/^chapter\s+\d{1,2}:\d{2}$/iu', $instrument)) {
                foreach ($items as $otherKey => $other) {
                    if ($otherKey !== $key && str_contains(mb_strtolower($other), $lower)) {
                        unset($items[$key]);
                        continue 2;
                    }
                }
            }

            foreach ($items as $otherKey => $other) {
                $otherLower = mb_strtolower($other);
                if ($otherKey === $key || mb_strlen($otherLower) <= mb_strlen($lower)) {
                    continue;
                }

                if (str_contains($otherLower, $lower)) {
                    unset($items[$key]);
                    continue 2;
                }
            }
        }

        return $items;
    }

    private function looksLikeInstrumentReference(string $reference): bool
    {
        return (bool) preg_match('/\b(Act|Regulations|Rules|Code|By-laws|Statutory Instrument|S\.?I\.?|Constitution|Chapter\s+\d{1,2}:\d{2})\b/iu', $reference);
    }

    private function normaliseInstrumentName(string $instrument): string
    {
        $instrument = preg_replace('/\s+/u', ' ', trim($instrument));
        $instrument = preg_replace('/^the\s+/iu', '', (string) $instrument);
        $instrument = preg_replace('/^(?:section|sections|article|articles)\s+[\dA-Za-z(),\s.-]+\s+of\s+the\s+/iu', '', (string) $instrument);
        $instrument = preg_replace('/\s+section\s+[\dA-Za-z(),\s.-]+$/iu', '', (string) $instrument);
        $instrument = trim((string) $instrument, " \t\n\r\0\x0B.;:");

        if (mb_strlen($instrument) < 4 || preg_match('/\b(applicant|respondent|plaintiff|defendant|court|judge)\b/iu', $instrument)) {
            return '';
        }

        return $instrument;
    }

    private function instrumentKey(string $instrument): string
    {
        return mb_strtolower(preg_replace('/[^a-z0-9:]+/iu', '', $instrument));
    }

    private function instrumentProvisions(string $source, string $instrument): array
    {
        $name = preg_quote($instrument, '/');
        $shortName = preg_quote(preg_replace('/\s*\[?Chapter\s+\d{1,2}:\d{2}\]?/iu', '', $instrument), '/');
        $patterns = [
            '/\b((?:section|sections|article|articles)\s+[\dA-Za-z(),\s.-]{1,40})\s+of\s+the\s+' . $name . '\b/iu',
            '/\b((?:section|sections|article|articles)\s+[\dA-Za-z(),\s.-]{1,40})\s+of\s+the\s+' . $shortName . '\b/iu',
        ];

        $provisions = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $source, $matches);
            foreach ($matches[1] ?? [] as $match) {
                $clean = trim((string) preg_replace('/\s+/u', ' ', $match), " \t\n\r\0\x0B.,;");
                if ($clean !== '') {
                    $provisions[] = $clean;
                }
            }
        }

        return array_slice(array_values(array_unique($provisions)), 0, 6);
    }

    private function constitutionalRights(array $references, array $issues): array
    {
        $rights = [];
        $joined = mb_strtolower(implode(' ', array_merge($references, $issues)));

        $map = [
            'Equality and non-discrimination (Section 56)' => 'section 56',
            'Personal liberty (Section 49)' => 'section 49',
            'Human dignity (Section 51)' => 'section 51',
            'Administrative justice (Section 68)' => 'section 68',
            'Fair labour practices (Section 65)' => 'section 65',
            'Freedom of assembly and association (Section 58)' => 'section 58',
            'Property rights (Section 71)' => 'section 71',
        ];

        foreach ($map as $label => $needle) {
            if (str_contains($joined, $needle)) {
                $rights[] = $label;
            }
        }

        if (in_array('Constitutional rights', $issues, true) && $rights === []) {
            $rights[] = 'Constitutional rights are implicated, but the exact section should be checked in the source text.';
        }

        return $rights;
    }

    private function extractCourtOrders(string $text, array $supportingPassages): array
    {
        $orders = $this->extractMatches($text, [
            '/\b(It is ordered that[^.]{10,200})\b/iu',
            '/\b(The application is [^.]{4,120})\b/iu',
            '/\b(The appeal is [^.]{4,120})\b/iu',
            '/\b(Accordingly,[^.]{10,200})\b/iu',
        ]);

        if ($orders === []) {
            foreach ($supportingPassages as $passage) {
                if (preg_match('/\b(ordered|dismissed|granted|upheld|costs)\b/i', $passage['text'])) {
                    $orders[] = $this->trimSentence($passage['text']);
                }
            }
        }

        return array_slice(array_values(array_unique($orders)), 0, 5);
    }

    private function sourceMap(array $supportingPassages, array $resultCards, array $structuredPanels): array
    {
        $passageIds = array_column($supportingPassages, 'id');

        return [
            'professional_summary' => array_slice($passageIds, 0, 3),
            'citizen_summary' => array_slice($passageIds, 0, 2),
            'result_cards' => array_map(
                static fn (array $card): array => [
                    'title' => $card['title'],
                    'passage_ids' => array_slice($passageIds, 0, 2),
                ],
                $resultCards
            ),
            'structured_panels' => [
                'case_information' => array_slice($passageIds, 0, 2),
                'key_legal_issues' => array_slice($passageIds, 0, 3),
                'cited_instruments' => $structuredPanels['cited_instruments'] ? array_slice($passageIds, 0, 3) : [],
                'important_legal_references' => array_slice($passageIds, 0, 3),
                'people_and_organisations' => array_slice($passageIds, 0, 2),
                'constitutional_rights_affected' => $structuredPanels['constitutional_rights_affected'] ? array_slice($passageIds, 0, 2) : [],
            ],
        ];
    }

    private function semanticProfile(string $text, array $result, array $structuredPanels): array
    {
        $terms = array_filter(array_merge(
            $this->normaliseTermsFromText($text),
            $this->normaliseTerms((array) ($result['nlp_keywords'] ?? [])),
            $this->normaliseTerms((array) ($result['nlp_legal_categories'] ?? [])),
            $this->normaliseTerms((array) ($structuredPanels['key_legal_issues'] ?? [])),
            $this->normaliseTerms((array) ($structuredPanels['cited_instruments'] ?? [])),
            $this->normaliseTerms((array) ($structuredPanels['important_legal_references'] ?? [])),
            $this->synonymExpansions($text)
        ));

        $counts = [];
        foreach ($terms as $term) {
            $counts[$term] = ($counts[$term] ?? 0) + 1;
        }

        $vector = [];
        foreach ($counts as $term => $count) {
            $bucket = 'dim_' . (abs(crc32($term)) % 48);
            $vector[$bucket] = ($vector[$bucket] ?? 0.0) + $count;
        }
        ksort($vector);

        if ($pythonVector = $this->pythonNlp->embed($text)) {
            foreach ($pythonVector as $index => $value) {
                $vector['py_' . $index] = (float) $value;
            }
        }

        return [
            'terms' => array_slice(array_keys($counts), 0, 20),
            'vector' => $vector,
        ];
    }

    private function synonymExpansions(string $text): array
    {
        $haystack = mb_strtolower($text);
        $map = [
            'unfair dismissal' => ['employment termination', 'labour dispute'],
            'property dispute' => ['ownership conflict', 'land matter'],
            'constitutional rights' => ['bill of rights', 'rights infringement'],
            'breach of contract' => ['non-compliance', 'agreement dispute'],
            'criminal sentence' => ['penalty', 'imprisonment'],
        ];

        $terms = [];
        foreach ($map as $needle => $synonyms) {
            if (str_contains($haystack, $needle)) {
                $terms = array_merge($terms, [$needle], $synonyms);
            }
        }

        return $terms;
    }

    private function normaliseTermsFromText(string $text): array
    {
        preg_match_all("/\p{L}[\p{L}\p{Mn}'-]{2,}/u", mb_strtolower($text), $matches);

        return $this->normaliseTerms($matches[0] ?? []);
    }

    private function normaliseTerms(array $terms): array
    {
        $stopWords = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'were', 'shall',
            'must', 'court', 'judge', 'legal', 'document', 'party', 'parties',
        ];

        $cleaned = [];
        foreach ($terms as $term) {
            $term = trim(mb_strtolower((string) $term));
            if ($term === '' || in_array($term, $stopWords, true) || mb_strlen($term) < 3) {
                continue;
            }
            $cleaned[] = $term;
        }

        return $cleaned;
    }

    private function cosineSimilarity(array $left, array $right): float
    {
        $keys = array_unique(array_merge(array_keys($left), array_keys($right)));
        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        foreach ($keys as $key) {
            $leftValue = (float) ($left[$key] ?? 0.0);
            $rightValue = (float) ($right[$key] ?? 0.0);

            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue ** 2;
            $rightNorm += $rightValue ** 2;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    private function extractMatches(string $text, array $patterns): array
    {
        $matches = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $groupMatches);
            foreach ($groupMatches[1] ?? [] as $value) {
                $matches[] = $this->trimSentence((string) $value);
            }
        }

        return array_slice(array_values(array_unique(array_filter($matches))), 0, 8);
    }

    private function pages(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        preg_match_all('/(?:^|\n)\s*[—-]{2,}\s*PAGE\s+(\d+)\s*[—-]{2,}\s*\n?/iu', $text, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[0])) {
            return [['page' => 1, 'text' => $text]];
        }

        $pages = [];
        $markers = $matches[0];
        $numbers = $matches[1];

        foreach ($markers as $index => $marker) {
            $pageNumber = (int) $numbers[$index][0];
            $start = $marker[1] + strlen($marker[0]);
            $end = $markers[$index + 1][1] ?? strlen($text);
            $pages[] = [
                'page' => $pageNumber,
                'text' => trim(substr($text, $start, $end - $start)),
            ];
        }

        return array_values(array_filter($pages, static fn (array $page): bool => $page['text'] !== ''));
    }

    private function listToSentence(array $items): string
    {
        $items = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $items
        )));

        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }

    private function plainLanguage(string $text): string
    {
        $replacements = [
            '/\bapplicant\b/iu' => 'person bringing the case',
            '/\brespondent\b/iu' => 'other side',
            '/\bplaintiff\b/iu' => 'person suing',
            '/\bdefendant\b/iu' => 'person being sued',
            '/\btherefore\b/iu' => 'so',
            '/\bpursuant to\b/iu' => 'under',
            '/\bin terms of\b/iu' => 'under',
            '/\bthereof\b/iu' => 'of it',
            '/\bhereby\b/iu' => '',
            '/\bshall\b/iu' => 'must',
            '/\bjudgment\b/iu' => 'decision',
            '/\bjudgement\b/iu' => 'decision',
            '/\bdispute\b/iu' => 'problem',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        $text = $this->replaceLatinLegalTerms($text);

        return $this->normaliseParagraph($text);
    }

    private function replaceLatinLegalTerms(string $text): string
    {
        foreach (self::LATIN_LEGAL_REPLACEMENTS as $term => $replacement) {
            $text = preg_replace('/\b' . preg_quote($term, '/') . '\b/iu', $replacement, $text);
        }

        return $text;
    }

    private function shouldRegenerateCitizenSummary(string $citizenSummary, array $result): bool
    {
        $professional = $this->normaliseParagraph((string) ($result['professional_summary'] ?? ''));
        $executive = $this->normaliseParagraph((string) ($result['executive_summary'] ?? ''));
        $similarity = max(
            $this->tokenSimilarity($citizenSummary, $professional),
            $this->tokenSimilarity($citizenSummary, $executive)
        );

        if ($similarity >= 0.78) {
            return true;
        }

        if ($this->isDoctrineOnlyCitizenSummary($citizenSummary, $result)) {
            return true;
        }

        return $similarity >= 0.55 && $this->legalJargonCount($citizenSummary) >= 4;
    }

    private function isDoctrineOnlyCitizenSummary(string $citizenSummary, array $result): bool
    {
        $lower = mb_strtolower($citizenSummary);
        $doctrineHits = preg_match_all('/\b(clarifies|outlines|requirements?|complete defence|partial defence|burden of proof|reasonable possibility|must be|the court emphasizes|legal principle|the prosecution bears)\b/iu', $lower) ?: 0;
        if ($doctrineHits < 3) {
            return false;
        }

        $caseTerms = array_filter(array_merge(
            (array) ($result['parties'] ?? []),
            [$result['case_number'] ?? null, $result['judge'] ?? null],
            ['deceased', 'complainant', 'convicted', 'acquitted', 'sentenced', 'dismissed', 'granted', 'ordered']
        ));

        foreach ($caseTerms as $term) {
            $term = trim(mb_strtolower((string) $term));
            if ($term !== '' && mb_strlen($term) >= 4 && str_contains($lower, $term)) {
                return false;
            }
        }

        return true;
    }

    private function tokenSimilarity(string $left, string $right): float
    {
        $leftTokens = $this->comparisonTokens($left);
        $rightTokens = $this->comparisonTokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $intersection = array_intersect($leftTokens, $rightTokens);
        $union = array_unique(array_merge($leftTokens, $rightTokens));

        return count($union) > 0 ? count($intersection) / count($union) : 0.0;
    }

    private function comparisonTokens(string $text): array
    {
        preg_match_all("/\p{L}[\p{L}\p{Mn}'-]{2,}/u", mb_strtolower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function legalJargonCount(string $text): int
    {
        preg_match_all(
            '/\b(applicant|respondent|plaintiff|defendant|pursuant|thereof|hereby|constitutional|jurisdiction|statute|labour act|dispositive|procedural|relief|affidavit|interdict)\b/iu',
            $text,
            $matches
        );

        return count(array_unique(array_map('mb_strtolower', $matches[0] ?? [])));
    }

    private function trimSentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return rtrim($text, " \t\n\r\0\x0B.;") . '.';
    }

    private function passageHighlights(array $supportingPassages, int $limit, int $maxLength): array
    {
        $highlights = [];

        foreach (array_slice($supportingPassages, 0, $limit) as $passage) {
            $text = $this->trimSentence((string) ($passage['text'] ?? ''));
            if ($text === '.') {
                continue;
            }

            if (mb_strlen($text) > $maxLength) {
                $text = rtrim(mb_substr($text, 0, $maxLength - 1), " ,;:-") . '...';
            }

            $highlights[] = $text;
        }

        return $highlights;
    }

    private function normaliseParagraph(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function lowercaseFirst(string $text): string
    {
        $text = $this->normaliseParagraph($text);
        if ($text === '') {
            return '';
        }

        return mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
