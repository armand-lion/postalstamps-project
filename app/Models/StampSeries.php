<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampSeries extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'issue_year',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'issue_year' => 'integer',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }

    public function catalogValues(): HasMany
    {
        return $this->hasMany(SeriesCatalogValue::class);
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(SeriesValuation::class);
    }
}
