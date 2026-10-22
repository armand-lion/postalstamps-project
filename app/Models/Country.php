<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name',
        'iso_code',
        'entity_type',
        'start_year',
        'end_year',
        'is_active',
        'slug',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Stamp, $this>
     */
    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }

    /**
     * @return HasMany<Collectible, $this>
     */
    public function collectibles(): HasMany
    {
        return $this->hasMany(Collectible::class);
    }

    /**
     * @return HasMany<StampSeries, $this>
     */
    public function stampSeries(): HasMany
    {
        return $this->hasMany(StampSeries::class);
    }

    /**
     * @return BelongsToMany<Catalog, $this>
     */
    public function catalogs(): BelongsToMany
    {
        return $this->belongsToMany(Catalog::class, 'catalog_countries')
            ->withTimestamps();
    }

    /**
     * @return HasMany<CountryTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function translationWithFallback(): ?CountryTranslation
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'nl');

        return $this->translations()
            ->where('locale', $locale)
            ->first()
            ?? $this->translations()
                ->where('locale', $fallbackLocale)
                ->first();
    }
}
