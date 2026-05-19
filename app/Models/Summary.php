<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Summary extends Model
{
    protected $fillable = [
        'document_id','user_id','summary_type','document_type','case_number','parties',
        'date_of_document','court','judge','executive_summary','professional_summary',
        'citizen_summary','key_findings',
        'key_obligations','legal_principles','outcome','practical_implications',
        'result_cards','structured_panels','supporting_passages','source_map',
        'semantic_profile','nlp_entities','nlp_keywords','nlp_sentiment','nlp_readability',
        'nlp_language','nlp_legal_categories','ai_provider','processing_ms','pdf_path',
    ];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function downloads(): HasMany  { return $this->hasMany(Download::class); }
}
