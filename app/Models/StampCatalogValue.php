<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCatalogValue extends Model
{
    protected $fillable = [
        'stamp_catalog_entry_id',
        'condition_id',
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

    public function catalogEntry(): BelongsTo
    {
        return $this->belongsTo(StampCatalogEntry::class, 'stamp_catalog_entry_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
