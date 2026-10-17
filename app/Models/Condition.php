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

    public function stampCopies(): HasMany
    {
        return $this->hasMany(StampCopy::class);
    }

    public function stampCatalogValues(): HasMany
    {
        return $this->hasMany(StampCatalogValue::class);
    }

    public function seriesCatalogValues(): HasMany
    {
        return $this->hasMany(SeriesCatalogValue::class);
    }

    public function stampValuations(): HasMany
    {
        return $this->hasMany(StampValuation::class);
    }

    public function seriesValuations(): HasMany
    {
        return $this->hasMany(SeriesValuation::class);
    }

    public function translation(): HasOne
    {
        return $this->hasOne(ConditionTranslation::class)
            ->where('locale', app()->getLocale());
    }
}
