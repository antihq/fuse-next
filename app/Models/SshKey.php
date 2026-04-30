<?php

namespace App\Models;

use Database\Factories\SshKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'public_key', 'fingerprint'])]
class SshKey extends Model
{
    /** @use HasFactory<SshKeyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SshKey $key) {
            if (empty($key->fingerprint)) {
                $key->fingerprint = $key->generateFingerprint();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generateFingerprint(): string
    {
        $parts = preg_split('/\s+/', trim($this->public_key), 3);
        $key = base64_decode($parts[1] ?? '', strict: true);

        if ($key === false) {
            return 'invalid';
        }

        $hash = md5($key);

        return strtoupper(implode(':', str_split($hash, 2)));
    }
}
