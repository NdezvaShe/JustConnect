<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Download extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'summary_id'];
    protected $casts    = ['downloaded_at' => 'datetime'];

    const CREATED_AT = 'downloaded_at';
    const UPDATED_AT = null;

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function summary(): BelongsTo { return $this->belongsTo(Summary::class); }
}