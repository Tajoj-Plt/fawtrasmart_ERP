<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralClientSetting extends Model
{
    protected $fillable = [
        'key',
        'name',
        'is_active',
        'location_required',
        'group_required',
        'employee_can_edit_visit_status',
        'employee_can_add_visit_notes',
        'enable_route_tracking',
        'route_settings',
        'visit_proximity_radius_km',
        'default_new_client_status_id',
        'monthly_collection_target',
        'classification_thresholds',
        'max_upload_size_kb',
        'bad_debt_writeoff_approval_threshold',
        'notify_employee_on_assignment_change',
        'map_hide_duration_hours',
        'itinerary_restrict_to_assigned_clients',
        'options',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'location_required' => 'boolean',
        'group_required' => 'boolean',
        'employee_can_edit_visit_status' => 'boolean',
        'employee_can_add_visit_notes' => 'boolean',
        'enable_route_tracking' => 'boolean',
        'route_settings' => 'array',
        'visit_proximity_radius_km' => 'float',
        'default_new_client_status_id' => 'integer',
        'monthly_collection_target' => 'float',
        'classification_thresholds' => 'array',
        'max_upload_size_kb' => 'integer',
        'bad_debt_writeoff_approval_threshold' => 'float',
        'notify_employee_on_assignment_change' => 'boolean',
        'map_hide_duration_hours' => 'integer',
        'itinerary_restrict_to_assigned_clients' => 'boolean',
        'options' => 'array',
    ];

    /**
     * قيمة من حقيبة `options` — بمفتاحها المسطَّح ومع افتراضٍ عند الغياب.
     *
     * الحقيبة موجودة لأن كل إعدادٍ جديد كان يعني عموداً في الجدول يبقى
     * فارغاً في كل صفٍّ سوى صفِّ مفتاحه. الأعمدة القائمة تبقى بأسمائها،
     * والجديد يسكن هنا.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        $options = $this->options;

        if (! is_array($options) || ! array_key_exists($key, $options)) {
            return $default;
        }

        return $options[$key];
    }

    /**
     * دمج قيمٍ في الحقيبة دون مسح ما لم يُذكر — فإضافةُ مفتاحٍ جديد إلى
     * الشاشة لا تمسح ما حفظته نسخةٌ أقدم منها.
     *
     * @param  array<string, mixed>  $values
     */
    public function mergeOptions(array $values): self
    {
        $this->options = array_merge(is_array($this->options) ? $this->options : [], $values);

        return $this;
    }

    /**
     * جلب صفّ إعداد بمفتاحه، أو إنشاؤه بالقيم الافتراضية إن لم يوجد.
     */
    public static function getOrCreateByKey(string $key, string $name, array $defaults): self
    {
        return static::firstOrCreate(['key' => $key], array_merge(['name' => $name], $defaults));
    }
}
