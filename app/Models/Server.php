<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'name', 'ip_address', 'status'])]
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ServerStatus::class,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
