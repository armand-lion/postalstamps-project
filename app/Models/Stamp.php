<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stamp extends Model
{
    protected $fillable = [
        'country_id',
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

    public function catalogValues(): HasMany
    {
        return $this->hasMany(StampCatalogValue::class);
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(StampValuation::class);
    }
}
