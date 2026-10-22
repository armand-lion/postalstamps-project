<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CollectibleType extends Model
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
     * @return HasMany<CollectibleTypeTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CollectibleTypeTranslation::class);
    }

    public function translationWithFallback(): ?CollectibleTypeTranslation
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
     * @return HasOne<CollectibleTypeTranslation, $this>
     */
    public function translation(): HasOne
    {
        return $this->hasOne(CollectibleTypeTranslation::class)
            ->where('locale', app()->getLocale());
    }

    /**
     * Alle bijzondere verzamelobjecten van dit type.
     *
     * @return HasMany<Collectible, $this>
     */
    public function collectibles(): HasMany
    {
        return $this->hasMany(Collectible::class);
    }
}
