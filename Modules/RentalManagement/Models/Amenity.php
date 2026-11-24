<?php

namespace Modules\RentalManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Amenity extends Model
{
    protected $table = 're_amenities';

    protected $guarded = ['id'];

    /**
     * مرفقٌ مستخدم في وحدات لا يُحذف بصمت (الحذف كان يسلخه عن الوحدات
     * عبر cascade بلا تنبيه) — يُزال من الوحدات أولاً ثم يُحذف.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $amenity) {
            $used = $amenity->units()->count();

            if ($used > 0) {
                throw ValidationException::withMessages(['amenity' => sprintf(
                    'المرفق «%s» مستخدم في %d وحدة؛ أزله من الوحدات أولاً ثم احذفه.', $amenity->name, $used
                )]);
            }
        });
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class, 're_unit_amenity', 'amenity_id', 'unit_id');
    }
}
