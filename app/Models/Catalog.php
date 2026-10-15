<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    protected $fillable = [
        'name',
        'abbreviation',
        'publisher',
        'website',
        'description',
    ];

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'catalog_countries')
            ->withTimestamps();
    }

    public function editions(): HasMany
    {
        return $this->hasMany(CatalogEdition::class);
    }
}
