<?php
// app/Models/Document.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    protected $fillable = [
        'user_id', 'original_name', 'stored_name', 'mime_type',
        'file_size', 'page_count', 'word_count', 'extracted_text', 'summary_type', 'status',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function summary(): HasOne  { return $this->hasOne(Summary::class); }
}
