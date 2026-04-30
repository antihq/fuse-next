<?php

namespace App\Models;

use App\Enums\SiteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['server_id', 'domain', 'repository', 'php_version', 'status'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
