<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampChangeBatch extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'operation',
        'description',
        'stamp_count',
        'operations',
    ];

    protected function casts(): array
    {
        return [
            'stamp_count' => 'integer',
            'operations' => 'array',
        ];
    }

    /**
     * De gebruiker die de bewerking heeft uitgevoerd.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * De afzonderlijke postzegelwijzigingen binnen deze batch.
     *
     * @return HasMany<StampChangeLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(StampChangeLog::class);
    }
}
