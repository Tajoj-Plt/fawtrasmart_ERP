<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientStageRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'requested_by',
        'old_stage_id',
        'new_stage_id',
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

    public function oldStage()
    {
        return $this->belongsTo(Statuses::class, 'old_stage_id');
    }

    public function newStage()
    {
        return $this->belongsTo(Statuses::class, 'new_stage_id');
    }

    /**
     * من يصلح مُعتمِداً لطلب تغيير وضع عميل — حاملو صلاحية
     * `clients_approve_stage` أنفسهم، لا `User::role('manager')`.
     *
     * نفس منطق `ClientStatusRequest::approvers()` حرفياً (انظر توثيقها):
     * القائمة تُبنى من الصلاحية لأنها السلطة الفعلية، لا من اسم دورٍ حرفيّ
     * قد لا يحمل الاعتماد أصلاً أو قد تحمله منشأةٌ سمّت دورها شيئاً آخر.
     *
     * **ومن `users.role_id` تُقرأ لا من `model_has_roles`** — نفس السبب:
     * `hasPermission()` التي تحرس زرّ الاعتماد تقرأ `users.role_id` وحده،
     * فبناء القائمة من Spatie قد يختار مُعتمِداً يجتاز الميدلوير ولا يرى
     * الزرّ.
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
     * أيصلح هذا المستخدم مُعتمِداً للطلب؟ يُسأل عند الحفظ لا عند العرض وحده —
     * نفس تحفّظ `ClientStatusRequest::isApprover()`: القائمة في المودال حقلٌ
     * منشور يُبدّل من أدوات المتصفّح.
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
     * `ClientStatusRequest::approverRoleIds()` بصلاحيةٍ مختلفة.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected static function approverRoleIds()
    {
        return \App\Models\Role::whereHas(
            'permissions',
            fn ($q) => $q->where('name', 'clients_approve_stage')
        )->pluck('id');
    }
}
