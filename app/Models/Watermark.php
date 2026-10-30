<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Watermark extends Model
{
    protected $fillable = [
        'country_id',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Het land waartoe dit watermerk behoort.
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * De vertalingen van dit watermerk.
     *
     * @return HasMany<WatermarkTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(WatermarkTranslation::class);
    }

    /**
     * Haal de vertaling op in de geselecteerde taal.
     * Gebruik de fallbacktaal wanneer de vertaling ontbreekt.
     */
    public function translationWithFallback(): ?WatermarkTranslation
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
