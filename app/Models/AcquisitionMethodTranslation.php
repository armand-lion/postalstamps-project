<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionMethodTranslation extends Model
{
    protected $fillable = [
        'acquisition_method_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * De verkrijgingswijze waartoe deze vertaling behoort.
     *
     * @return BelongsTo<AcquisitionMethod, $this>
     */
    public function acquisitionMethod(): BelongsTo
    {
        return $this->belongsTo(AcquisitionMethod::class);
    }
}
