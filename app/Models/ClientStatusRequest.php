<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientStatusRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'requested_by',
        'old_status_id',
        'new_status_id',
        'status',
        'approved_by',
        'notes',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function oldStatus()
    {
        return $this->belongsTo(Statuses::class, 'old_status_id');
    }

    public function newStatus()
    {
        return $this->belongsTo(Statuses::class, 'new_status_id');
    }

    /**
     * من يصلح مُعتمِداً لطلب تغيير حالة عميل — حاملو صلاحية
     * `clients_approve_status` أنفسهم، لا `User::role('manager')`.
     *
     * مودال طلب الحالة في ملفّ العميل كان يبني قائمته من اسم الدور الحرفيّ
     * `manager` وحده: منشأةٌ سمّت دورها «مشرف» أو أعطت الاعتماد لـ`owner`
     * تُظهِر قائمةً فارغة (فلا يُرسَل طلبٌ أبداً)، ومنشأةٌ فيها `manager` بلا
     * صلاحية الاعتماد تُظهِر مُعتمِداً لا يملك الاعتماد — فيصل الإشعار إلى من
     * لا زرّ له، ويبقى الطلب `pending` إلى الأبد. الصلاحية هي السلطة، فمنها
     * تُبنى القائمة.
     *
     * **ومن `users.role_id` تُقرأ لا من `model_has_roles`.** للنظام مساران
     * لنفس السؤال: ميدلوير المسارات يسأل Spatie، و`hasPermission()` — التي
     * تحرس كلّ زرٍّ في الشاشات — تسأل `User::hasRolePermission()` أي
     * `users.role_id` وحده. و`role_id` هو مصدر الحقيقة وما دونه مُشتقٌّ منه
     * (راجع `User::syncRole()`). بناءُ القائمة من Spatie كان يعني احتمال
     * اختيار مُعتمِدٍ يجتاز الميدلوير ولا يرى زرّ الاعتماد أصلاً.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function approvers()
    {
        return User::query()
            ->whereIn('role_id', static::approverRoleIds())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * أيصلح هذا المستخدم مُعتمِداً للطلب؟ يُسأل عند الحفظ لا عند العرض وحده:
     * القائمة في المودال حقلٌ منشور يُبدّل من أدوات المتصفّح، و`manager_id`
     * لم يكن عليه تحقّقٌ أصلاً.
     */
    public static function isApprover(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return User::whereKey($userId)
            ->whereIn('role_id', static::approverRoleIds())
            ->exists();
    }

    /**
     * الأدوار التي تحمل صلاحية الاعتماد — نفس استعلام
     * `User::hasRolePermission()` مقلوباً: هناك دورٌ واحد يُسأل عن صلاحية،
     * وهنا صلاحيةٌ تُسأل عن أدوارها.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected static function approverRoleIds()
    {
        return \App\Models\Role::whereHas(
            'permissions',
            fn ($q) => $q->where('name', 'clients_approve_status')
        )->pluck('id');
    }
}
