<?php

namespace App\Models;

use Database\Factories\StampFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Stamp extends Model
{
    /** @use HasFactory<StampFactory> */
    use HasFactory;

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

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<StampSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(StampSeries::class, 'stamp_series_id');
    }

    /**
     * @return BelongsToMany<Theme, $this>
     */
    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'stamp_themes')
            ->withTimestamps();
    }

    /**
     * De verzamelobjecten waaraan deze postzegel gekoppeld is.
     *
     * @return BelongsToMany<Collectible, $this>
     */
    public function collectibles(): BelongsToMany
    {
        return $this->belongsToMany(
            Collectible::class,
            'collectible_stamps',
            'stamp_id',
            'collectible_id'
        )->withTimestamps();
    }

    /**
     * @return HasMany<StampCopy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(StampCopy::class);
    }

    /**
     * @return HasMany<StampImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(StampImage::class);
    }

    /**
     * @return HasMany<StampCatalogEntry, $this>
     */
    public function catalogEntries(): HasMany
    {
        return $this->hasMany(StampCatalogEntry::class);
    }

    /**
     * @return HasManyThrough<StampCatalogValue, StampCatalogEntry, $this>
     */
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

    /**
     * @return HasMany<StampValuation, $this>
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(StampValuation::class);
    }

    /**
     * The parent stamp of this variant.
     *
     * @return BelongsTo<Stamp, $this>
     */
    public function parentStamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class, 'parent_stamp_id');
    }

    /**
     * The variants belonging to this stamp.
     *
     * @return HasMany<Stamp, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Stamp::class, 'parent_stamp_id');
    }

    /**
     * @return HasMany<StampTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(StampTranslation::class);
    }

    public function translationWithFallback(): ?StampTranslation
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'nl');

        $translations = $this->translations;

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', $fallbackLocale);
    }
}
