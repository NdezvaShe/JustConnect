<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password',
        'organisation', 'role', 'email_verified_at',
        'mfa_enabled', 'mfa_channel',
        'mfa_security_question', 'mfa_security_answer_hash',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_security_answer_hash'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'mfa_enabled'       => 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1)
        );
    }
}
