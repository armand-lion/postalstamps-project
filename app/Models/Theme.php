<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function stamps(): BelongsToMany
    {
        return $this->belongsToMany(Stamp::class, 'stamp_themes')
            ->withTimestamps();
    }
}
