<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectibleCopy extends Model
{
    protected $fillable = [
        'collectible_id',
        'condition_id',
        'acquisition_date',
        'purchase_price',
        'purchase_currency',
        'acquisition_method',
        'is_variant',
        'variant_description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'collectible_id' => 'integer',
            'condition_id' => 'integer',
            'acquisition_date' => 'date',
            'purchase_price' => 'decimal:2',
            'is_variant' => 'boolean',
        ];
    }

    /**
     * Het bijzondere verzamelobject waarvan dit
     * een persoonlijk exemplaar is.
     *
     * @return BelongsTo<Collectible, $this>
     */
    public function collectible(): BelongsTo
    {
        return $this->belongsTo(Collectible::class);
    }

    /**
     * De conditie van dit persoonlijke exemplaar.
     *
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
