<?php

namespace App\Models;

use App\Support\TaskManager\RequestMemo;
use Illuminate\Database\Eloquent\Model;

class TaskSettings extends Model
{
    protected $fillable = [
        'project_id',
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'is_global',
    ];

    protected $casts = [
        'setting_value' => 'array',
        'is_global' => 'boolean',
    ];

    /** كل كتابةٍ تُسقط ما حُفظ من الإعدادات لمدّة الطلب — وإلّا قرأ الطلبُ نفسه ما قبل حفظه. */
    protected static function booted(): void
    {
        $forget = function () {
            RequestMemo::forget('setting.');
            RequestMemo::forget('vocabulary.');
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * إنشاء أو تحديث إعداد مشروع (أو إعداد عام إذا كان $isGlobal = true).
     */
    public static function setSetting($projectId, string $key, $value, string $type = 'json', string $description = '', bool $isGlobal = false): self
    {
        return self::updateOrCreate(
            [
                'project_id' => $isGlobal ? null : $projectId,
                'setting_key' => $key,
                'is_global' => $isGlobal,
            ],
            [
                'setting_value' => $value,
                'setting_type' => $type,
                'description' => $description,
            ]
        );
    }
}
