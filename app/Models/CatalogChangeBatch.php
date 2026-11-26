<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogChangeBatch extends Model
{
    protected $fillable = [
        'catalog_edition_id',
        'user_id',
        'user_name',
        'operation',
        'description',
        'catalog_name',
        'catalog_edition_name',
    ];

    /**
     * Geef de vertaalbare naam van de bewerking terug.
     */
    public function operationLabel(): string
    {
        return match ($this->operation) {
            'bulk_edit' => __('Bulkbewerking'),
            'duplicate' => __('Cataloguseditie dupliceren'),
            'stamp_create' => __('Postzegel aanmaken'),
            'stamp_update' => __('Postzegel wijzigen'),
            'review' => __('Cataloguswaarde controleren'),
            'import_create' => __('Nieuwe cataloguseditie importeren'),
            'import_merge' => __('Cataloguseditie aanvullen'),
            'import_replace' => __('Cataloguseditie vervangen'),
            default => ucfirst(str_replace('_', ' ', $this->operation)),
        };
    }

    /**
     * De cataloguseditie waarop deze bewerking betrekking heeft.
     */
    public function catalogEdition(): BelongsTo
    {
        return $this->belongsTo(CatalogEdition::class);
    }

    /**
     * De gebruiker die de bewerking heeft uitgevoerd.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alle wijzigingen die bij deze bewerking horen.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(CatalogChangeLog::class);
    }
}
