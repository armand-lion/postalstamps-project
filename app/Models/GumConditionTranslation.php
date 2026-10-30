<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GumConditionTranslation extends Model
{
    protected $fillable = [
        'gum_condition_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * De gomconditie waartoe deze vertaling behoort.
     *
     * @return BelongsTo<GumCondition, $this>
     */
    public function gumCondition(): BelongsTo
    {
        return $this->belongsTo(GumCondition::class);
    }
}
