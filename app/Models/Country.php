<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name',
        'iso_code',
        'slug',
        'description',
    ];

    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }

    public function stampSeries(): HasMany
    {
        return $this->hasMany(StampSeries::class);
    }

    public function catalogs(): BelongsToMany
    {
        return $this->belongsToMany(Catalog::class, 'catalog_countries')
            ->withTimestamps();
    }
}
