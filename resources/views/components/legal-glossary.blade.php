@php
    /*
     * Legal glossary terms used by the dashboard display layer.
     * Keep this data plain-text only; the client highlighter escapes all output
     * before wrapping matched terms.
     */
    $terms = [
        'plaintiff' => 'The person suing someone.',
        'defendant' => 'The person being sued or accused.',
        'litigant' => 'Anyone involved in a court case.',
        'applicant' => 'Person asking the court for something.',
        'respondent' => 'Person answering a court request.',
        'appellant' => 'Person challenging a court decision.',
        'appeal' => 'Asking a higher court to change a decision.',
        'acquittal' => 'A finding that a person is not guilty.',
        'affidavit' => 'Written statement sworn to be true.',
        'alibi' => 'A defence saying the person was somewhere else when the event happened.',
        'burden of proof' => 'The duty to prove a claim or charge.',
        'judgment' => "Court's final decision.",
        'order' => 'An instruction made by a court.',
        'interdict' => 'A court order stopping someone from doing something.',
        'jurisdiction' => "Court's legal power to hear a case.",
        'ratio decidendi' => 'The legal reason for a court decision.',
        'obiter dictum' => 'Extra comments by a judge that are not the main reason for the decision.',
        'locus standi' => 'The legal right to bring a case.',
        'res judicata' => 'A matter that has already been finally decided.',
        'prima facie' => 'Based on first evidence, unless disproved.',
        'mens rea' => 'Criminal intention.',
        'culpable homicide' => 'Unlawfully causing death without the intention required for murder.',
        'mitigation' => 'Reasons given to reduce punishment.',
        'damages' => 'Money paid for harm or loss caused.',
        'negligence' => 'Carelessness that causes harm.',
        'breach' => 'Breaking a law or agreement.',
        'clause' => 'A part of a contract or legal document.',
        'statute' => 'A written law.',
        'regulation' => 'Detailed rule made under an Act.',
        'statutory instrument' => 'A legal rule made under authority given by an Act.',
        'provision' => 'A specific rule or section in a legal document.',
        'remedy' => 'What the court gives to fix a legal problem.',
        'costs' => 'Money one side may have to pay for legal expenses.',
        'liable' => 'Legally responsible.',
        'deceased' => 'The person who died.',
        'complainant' => 'The person who made a criminal complaint.',
    ];
@endphp

<script type="application/json" id="legalGlossaryTerms">@json($terms)</script>
