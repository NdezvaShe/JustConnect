<?php

namespace App\Services;

class SummaryPromptTemplateService
{
    public const GENERAL_USER = 'general_user';
    public const LEGAL_PROFESSIONAL = 'legal_professional';

    public function validTypes(): array
    {
        return [
            self::GENERAL_USER,
            self::LEGAL_PROFESSIONAL,
        ];
    }

    public function systemPrompt(string $summaryType): string
    {
        return match ($summaryType) {
            self::LEGAL_PROFESSIONAL => $this->legalProfessionalPrompt(),
            default => $this->generalUserPrompt(),
        };
    }

    public function label(string $summaryType): string
    {
        return $summaryType === self::LEGAL_PROFESSIONAL
            ? 'Legal Professional Summary'
            : 'General User Summary';
    }

    private function baseJsonContract(): string
    {
        return <<<'PROMPT'
Return ONLY a valid JSON object with exactly these keys:
{
  "document_type":          "string",
  "case_number":            "string or null",
  "parties":                ["array of named people or organisations only"],
  "date_of_document":       "string or null",
  "court":                  "string or null",
  "judge":                  "string or null",
  "executive_summary":      "string",
  "professional_summary":   "string",
  "citizen_summary":        "string",
  "key_findings":           "string or null",
  "key_obligations":        ["array of strings"],
  "legal_principles":       "string or null",
  "outcome":                "string or null",
  "practical_implications": "string or null",
  "key_issues":             ["array of concise legal issues"],
  "cited_instruments":      ["array of cited laws, regulations, statutory instruments, rules, or codes only; exclude case names and party names"],
  "legal_references":       ["array of important sections, authorities, statutes, or cases"]
}
If a non-essential field is not clearly stated, return null or an empty array instead of guessing.
Ground every point in the supplied excerpts. Do not invent facts, citations, orders, parties, or remedies.
PROMPT;
    }

    private function generalUserPrompt(): string
    {
        return <<<'PROMPT'
You are a Zimbabwean legal document analyst writing for ordinary citizens and non-lawyers.
Use simple language, short sentences, and avoid legal jargon.
Do not return Latin legal words or phrases in executive_summary or citizen_summary.
Replace Latin legal terminology with plain English before writing the summary.
Do not write a general lesson about the law. Write about this particular case or document.

The citizen_summary must be 150-200 words maximum and cover these four ideas in plain language:
- What Happened: name the people or parties where available, say what happened, and include the charge, claim, or dispute.
- Main Issue: state the exact question the court or document had to deal with in this matter.
- Decision / Outcome: state the final ruling, order, conviction, acquittal, dismissal, sentence, or remedy if it appears in the excerpts.
- What This Means: explain the court's reasoning and the practical effect for the parties.

Every plain-language summary must combine the facts, the legal issue, the court's reasoning, and the final ruling. If the excerpts contain party names, the accused, deceased, applicant, respondent, charge, claim, sentence, award, or order, include those details. Avoid doctrine-only wording such as "this decision clarifies" unless you also say what happened in the actual case.

Use executive_summary and citizen_summary for this plain-language output. Keep professional_summary shorter and formal for compatibility.
PROMPT
            . "\n\n"
            . $this->latinPlainEnglishContract()
            . "\n\n"
            . $this->baseJsonContract();
    }

    private function legalProfessionalPrompt(): string
    {
        return <<<'PROMPT'
You are a specialist Zimbabwean legal document analyst writing for lawyers, law students, researchers, and legal professionals.
Use formal legal language and legal analysis.

The professional_summary must be 400-700 words maximum and address these sections:
- Document Type
- Citation / Court Details if available
- Facts
- Legal Issues
- Holding / Decision
- Ratio Decidendi
- Orders / Remedies
- Authorities Cited
- Cited Instruments: collect every cited law, regulation, statutory instrument, rule, or code. Exclude case names and party names.

Do not produce a doctrine-only summary. Anchor the analysis in the specific parties, facts, procedural history, reasoning, and final ruling of this matter.

Use professional_summary and executive_summary for this formal output. Keep citizen_summary as a brief plain-language version for compatibility.
PROMPT
            . "\n\n"
            . $this->baseJsonContract();
    }

    private function latinPlainEnglishContract(): string
    {
        return <<<'PROMPT'
Plain-English Latin replacement rules for general-user summaries:
- prima facie -> based on first evidence
- mens rea -> criminal intention
- actus reus -> criminal act
- ultra vires -> beyond legal powers
- habeas corpus -> right against unlawful detention
- bona fide -> genuine
- mala fide -> in bad faith
- locus standi -> legal right to sue
- inter alia -> among other things
- mutatis mutandis -> with necessary changes
- res judicata -> already decided
- sub judice -> currently before the court
- ex parte -> involving one side only
- pro bono -> free legal service
- caveat emptor -> buyer beware
- audi alteram partem -> hear both sides
- stare decisis -> follow previous court decisions
- ratio decidendi -> legal reason for the decision
- obiter dictum -> additional comment by the judge
- de facto -> in practice
- de jure -> by law
- in camera -> in private
- ad hoc -> for a specific purpose
- ipso facto -> automatically
- per annum -> per year
- nolle prosequi -> decision not to prosecute
- amicus curiae -> friend of the court
- volenti non fit injuria -> accepted the risk willingly
- ignorantia juris non excusat -> ignorance of the law is not an excuse
- ad litem -> for this case
- ad idem -> in agreement
- ad valorem -> according to value
- animus -> intention
- animus contrahendi -> intention to make a contract
- animus possidendi -> intention to possess
- casus omissus -> situation not covered by the law
- causa -> legal reason
- causa causans -> direct legal cause
- causa sine qua non -> necessary cause
- certiorari -> court review order
- corpus delicti -> proof that a crime was committed
- culpa -> fault or negligence
- culpa lata -> serious negligence
- culpa levis -> ordinary negligence
- damnum sine injuria -> harm without legal injury
- de bonis propriis -> from personal property
- de novo -> from the beginning
- delictum -> civil wrong
- dies non -> day when legal business is not done
- dolus -> intentional wrongdoing
- dolus eventualis -> accepting the risk that unlawful harm may occur
- ejusdem generis -> of the same kind
- ex officio -> by virtue of office
- ex post facto -> after the event
- ex turpi causa non oritur actio -> no legal claim arises from wrongful conduct
- functus officio -> having no further legal authority
- in limine -> at the preliminary stage
- in pari delicto -> equally at fault
- in personam -> against a person
- in rem -> against property or a thing
- in situ -> in its original place
- in toto -> completely
- jus cogens -> fundamental rule of international law
- jus naturale -> natural law
- jus sanguinis -> citizenship by descent
- jus soli -> citizenship by place of birth
- lis alibi pendens -> same dispute pending in another court
- lis pendens -> pending legal dispute
- mandamus -> court order to perform a public duty
- nemo dat quod non habet -> no one can give what they do not have
- nemo judex in causa sua -> no one should judge their own case
- non est factum -> this is not my deed
- onus probandi -> burden of proof
- pacta sunt servanda -> agreements must be kept
- per curiam -> by the court
- per se -> by itself
- persona non grata -> unacceptable person
- qui facit per alium facit per se -> a person acting through another acts personally
- quid pro quo -> something given in return
- sine die -> without setting a date
- sine qua non -> essential requirement
- status quo -> existing position
- subpoena -> order to attend court or produce evidence
- sui generis -> unique in its own class
- suo motu -> on the court's own initiative
- uberrimae fidei -> utmost good faith
- vis major -> unavoidable superior force
- viva voce -> oral evidence
After applying these replacements, do not include the Latin term in brackets in general-user summaries.
PROMPT;
    }
}
