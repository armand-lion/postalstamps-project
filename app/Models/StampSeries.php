<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampSeries extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'issue_year',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'issue_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<Stamp, $this>
     */
    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }

    /**
     * De bijzondere verzamelobjecten die bij deze serie horen.
     *
     * @return HasMany<Collectible, $this>
     */
    public function collectibles(): HasMany
    {
        return $this->hasMany(Collectible::class);
    }

    /**
     * @return HasMany<SeriesCatalogValue, $this>
     */
    public function catalogValues(): HasMany
    {
        return $this->hasMany(SeriesCatalogValue::class);
    }

    /**
     * @return HasMany<SeriesValuation, $this>
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(SeriesValuation::class);
    }

    /**
     * @return HasMany<StampSeriesTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(StampSeriesTranslation::class);
    }

    public function translationWithFallback(): ?StampSeriesTranslation
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
