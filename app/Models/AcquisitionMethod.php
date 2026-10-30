<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcquisitionMethod extends Model
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
     * De vertalingen van deze verkrijgingswijze.
     *
     * @return HasMany<AcquisitionMethodTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AcquisitionMethodTranslation::class);
    }

    /**
     * Haal de vertaling op in de geselecteerde taal.
     * Gebruik de fallbacktaal wanneer de vertaling ontbreekt.
     */
    public function translationWithFallback(): ?AcquisitionMethodTranslation
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
