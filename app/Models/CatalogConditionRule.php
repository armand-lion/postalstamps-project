<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogConditionRule extends Model
{
    protected $fillable = [
        'catalog_id',
        'country_id',
        'condition_id',
        'issue_year_from',
        'issue_year_to',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'catalog_id' => 'integer',
            'country_id' => 'integer',
            'condition_id' => 'integer',
            'issue_year_from' => 'integer',
            'issue_year_to' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * De catalogus waarvoor deze waarderingsregel geldt.
     *
     * @return BelongsTo<Catalog, $this>
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    /**
     * Het land waarvoor deze regel geldt.
     * NULL betekent dat de regel voor alle landen geldt.
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * De waarderingscategorie die door deze regel wordt gebruikt.
     *
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
