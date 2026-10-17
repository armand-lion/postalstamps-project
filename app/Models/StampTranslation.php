<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampTranslation extends Model
{
    protected $fillable = [
        'stamp_id',
        'locale',
        'name',
        'description',
    ];

    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }
}
