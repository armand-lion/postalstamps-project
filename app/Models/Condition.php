<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Condition extends Model
{
    protected $fillable = [
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ConditionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ConditionTranslation::class);
    }

    public function translationWithFallback(): ?ConditionTranslation
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

    /**
     * @return HasMany<StampCopy, $this>
     */
    public function stampCopies(): HasMany
    {
        return $this->hasMany(StampCopy::class);
    }

    /**
     * De persoonlijke exemplaren van bijzondere verzamelobjecten
     * met deze conditie.
     *
     * @return HasMany<CollectibleCopy, $this>
     */
    public function collectibleCopies(): HasMany
    {
        return $this->hasMany(CollectibleCopy::class);
    }

    /**
     * @return HasMany<StampCatalogValue, $this>
     */
    public function stampCatalogValues(): HasMany
    {
        return $this->hasMany(StampCatalogValue::class);
    }

    /**
     * De cataloguswaarden van bijzondere verzamelobjecten
     * die bij deze conditie horen.
     *
     * @return HasMany<CollectibleCatalogValue, $this>
     */
    public function collectibleCatalogValues(): HasMany
    {
        return $this->hasMany(CollectibleCatalogValue::class);
    }

    /**
     * @return HasMany<SeriesCatalogValue, $this>
     */
    public function seriesCatalogValues(): HasMany
    {
        return $this->hasMany(SeriesCatalogValue::class);
    }

    /**
     * @return HasMany<StampValuation, $this>
     */
    public function stampValuations(): HasMany
    {
        return $this->hasMany(StampValuation::class);
    }

    /**
     * @return HasMany<SeriesValuation, $this>
     */
    public function seriesValuations(): HasMany
    {
        return $this->hasMany(SeriesValuation::class);
    }

    /**
     * @return HasOne<ConditionTranslation, $this>
     */
    public function translation(): HasOne
    {
        return $this->hasOne(ConditionTranslation::class)
            ->where('locale', app()->getLocale());
    }
}
