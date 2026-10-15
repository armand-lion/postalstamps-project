<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCatalogValue extends Model
{
    protected $fillable = [
        'catalog_edition_id',
        'stamp_id',
        'condition_id',
        'catalog_number',
        'catalog_value',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'catalog_value' => 'decimal:2',
        ];
    }

    public function catalogEdition(): BelongsTo
    {
        return $this->belongsTo(CatalogEdition::class);
    }

    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
