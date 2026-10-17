<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Stamp extends Model
{
    protected $fillable = [
        'country_id',
        'parent_stamp_id',
        'stamp_series_id',
        'name',
        'description',
        'issue_year',
        'issue_date',
        'face_value',
        'currency',
        'designer',
        'printing_method',
        'catalog_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_year' => 'integer',
            'issue_date' => 'date',
            'face_value' => 'decimal:2',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(StampSeries::class, 'stamp_series_id');
    }

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'stamp_themes')
            ->withTimestamps();
    }

    public function copies(): HasMany
    {
        return $this->hasMany(StampCopy::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(StampImage::class);
    }

    public function catalogEntries(): HasMany
    {
        return $this->hasMany(StampCatalogEntry::class);
    }

    public function catalogValues(): HasManyThrough
    {
        return $this->hasManyThrough(
            StampCatalogValue::class,
            StampCatalogEntry::class,
            'stamp_id',
            'stamp_catalog_entry_id',
            'id',
            'id'
        );
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(StampValuation::class);
    }

    /**
     * The parent stamp of this variant.
     */
    public function parentStamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class, 'parent_stamp_id');
    }

    /**
     * The variants belonging to this stamp.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Stamp::class, 'parent_stamp_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(StampTranslation::class);
    }

    public function translationWithFallback(): ?StampTranslation
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
