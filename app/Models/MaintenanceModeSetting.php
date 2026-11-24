<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حالة وضع الصيانة — صفٌّ واحد لا غير.
 *
 * القراءة والكتابة تمرّان بـ`App\Services\Maintenance\MaintenanceModeService`
 * لا بهذا الصنف مباشرةً: الخدمة هي التي تُسقط الكاش بعد الحفظ، وتفرض ألّا
 * يُقفَل النظام على من أقفله. ونداءُ `save()` من خارجها يترك الوسيطَ يقرأ
 * حالةً قديمة حتى تنتهي مدّة الكاش — وهي في وضع الصيانة أسوأ ما يقع.
 *
 * @property bool $is_active
 * @property string|null $message
 * @property array|null $allowed_user_ids
 * @property string|null $secret
 */
class MaintenanceModeSetting extends Model
{
    protected $table = 'maintenance_mode_settings';

    protected $fillable = [
        'is_active',
        'message',
        'allowed_user_ids',
        'secret',
        'activated_by',
        'activated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_user_ids' => 'array',
        'activated_at' => 'datetime',
    ];

    /** من فعّل الوضع — يُعرَض في الشاشة وفي صفحة الحجب. */
    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * المعرّفات المسموح لها، أعداداً صحيحة.
     *
     * التحويل هنا لا عند المقارنة: القيمة تعود من JSON نصوصاً أحياناً
     * (`["3","7"]`) وأعداداً أحياناً حسب ما كُتبت، و`in_array` الصارمة على
     * `auth()->id()` العددي تُخرِج المسموحَ له من نظامه.
     *
     * @return array<int, int>
     */
    public function allowedIds(): array
    {
        return array_values(array_unique(array_map(
            'intval',
            (array) ($this->allowed_user_ids ?? [])
        )));
    }

    /** الرسالة المعروضة للمحجوب — أو المبدئية إن لم تُكتب واحدة. */
    public function displayMessage(): string
    {
        $message = trim((string) $this->message);

        return $message !== ''
            ? $message
            : 'النظام في وضع الصيانة حالياً. نعمل على تحديثه، وسيعود للعمل قريباً.';
    }
}
