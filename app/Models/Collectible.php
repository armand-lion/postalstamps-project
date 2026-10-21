<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collectible extends Model
{
    protected $fillable = [
        'collectible_type_id',
        'country_id',
        'stamp_series_id',
        'name',
        'description',
        'issue_year',
        'issue_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'collectible_type_id' => 'integer',
            'country_id' => 'integer',
            'stamp_series_id' => 'integer',
            'issue_year' => 'integer',
            'issue_date' => 'date',
        ];
    }

    /**
     * Het type verzamelobject.
     */
    public function collectibleType(): BelongsTo
    {
        return $this->belongsTo(CollectibleType::class);
    }

    /**
     * Het land van uitgifte.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * De bijbehorende postzegelserie (optioneel).
     */
    public function stampSeries(): BelongsTo
    {
        return $this->belongsTo(StampSeries::class);
    }

    /**
     * De postzegels die bij dit verzamelobject horen.
     */
    public function stamps(): BelongsToMany
    {
        return $this->belongsToMany(
            Stamp::class,
            'collectible_stamps',
            'collectible_id',
            'stamp_id'
        )->withTimestamps();
    }

    /**
     * De afbeeldingen die bij dit verzamelobject horen.
     */
    public function images(): HasMany
    {
        return $this->hasMany(CollectibleImage::class);
    }

    /**
     * De catalogusregistraties van dit verzamelobject.
     */
    public function catalogEntries(): HasMany
    {
        return $this->hasMany(CollectibleCatalogEntry::class);
    }

    /**
     * De persoonlijke exemplaren van dit verzamelobject.
     */
    public function copies(): HasMany
    {
        return $this->hasMany(CollectibleCopy::class);
    }
}
