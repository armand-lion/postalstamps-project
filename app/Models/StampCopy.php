<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampCopy extends Model
{
    protected $fillable = [
        'stamp_id',
        'condition_id',
        'quantity',
        'acquisition_date',
        'purchase_price',
        'purchase_currency',
        'acquisition_method',
        'watermark',
        'perforation',
        'gum_condition',
        'is_variant',
        'variant_description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'acquisition_date' => 'date',
            'purchase_price' => 'decimal:2',
            'is_variant' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Stamp, $this>
     */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }

    /**
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    /**
     * De gomconditie van deze postzegel.
     *
     * @return BelongsTo<GumCondition, $this>
     */
    public function gumCondition(): BelongsTo
    {
        return $this->belongsTo(
            GumCondition::class,
            'gum_condition',
            'slug'
        );
    }

    /**
     * @return HasMany<StampCopyImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(StampCopyImage::class);
    }

    /**
     * @return HasMany<StampCopyValuation, $this>
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(StampCopyValuation::class);
    }

    /**
     * De vertalingen van de variantbeschrijving.
     *
     * @return HasMany<StampCopyTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(StampCopyTranslation::class);
    }

    /**
     * Haal de vertaling op voor de huidige taal,
     * met Nederlands als terugvaltaal.
     */
    public function translationWithFallback(): ?StampCopyTranslation
    {
        $locale = app()->getLocale();

        return $this->translations()
            ->where('locale', $locale)
            ->first()
            ?? $this->translations()
                ->where('locale', 'nl')
                ->first();
    }
}
