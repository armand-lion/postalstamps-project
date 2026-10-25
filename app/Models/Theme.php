<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * @return BelongsToMany<Stamp, $this>
     */
    public function stamps(): BelongsToMany
    {
        return $this->belongsToMany(Stamp::class, 'stamp_themes')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ThemeTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ThemeTranslation::class);
    }

    public function translationWithFallback(?string $locale = null): ?ThemeTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', 'nl')
            ?? $this->translations->firstWhere('locale', 'en')
            ?? $this->translations->first();
    }
}
