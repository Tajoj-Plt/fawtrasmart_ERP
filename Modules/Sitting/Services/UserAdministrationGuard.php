<?php

namespace Modules\Sitting\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * حارسُ إدارة المستخدمين والأدوار — «لا تمنح ما لا تملك، ولا تمسّ من فوقك».
 *
 * ══════════════════ ما كان ══════════════════
 *
 * شاشتا المستخدمين والأدوار تحرسهما صلاحيةُ الفعل وحدَها (`settings.users.edit`،
 * `settings.roles.edit`) بلا نظرٍ إلى **من يُعدَّل ولا بماذا**. فمن مُنح
 * «تعديل المستخدمين» وحدَها كان يستطيع:
 *
 *   • أن يسند إلى نفسه دورَ المالك فيملك النظامَ كلَّه،
 *   • أن يغيّر بريدَ المالك وكلمةَ مروره فيدخل بحسابه،
 *   • أن يحذف المالكَ الأخير فلا يبقى من يدير النظام،
 *
 * ومن مُنح «تعديل الأدوار» كان يضيف إلى دوره هو كلَّ الصلاحيات، أو يجرّد
 * دورَ المالك منها، أو يقفل المالكين خارج الموقع بـ«تطبيق الجوال فقط».
 *
 * ══════════════════ القاعدة ══════════════════
 *
 * قاعدةٌ واحدة تُقاس بالصلاحيات لا بأسماء الأدوار — فدورٌ اسمُه «مدير» يحمل
 * كلَّ الصلاحيات يُعامَل كما هو فعلاً، لا كما سُمّي:
 *
 *   ١) لا يُسنَد دورٌ ولا تُمنَح صلاحيةٌ إلّا لمن يملكها المُسنِدُ نفسُه.
 *   ٢) لا يُعدَّل مستخدمٌ ولا دورٌ يحمل ما لا يحمله المعدِّل.
 *   ٣) لا يغيّر أحدٌ دورَه بنفسه ولا صلاحياتِ دوره هو.
 *   ٤) دورُ المالك وأدوارُ النظام لا تُعاد تسميتُها ولا تُحذف، ودورُ المالك
 *      لا يُقفل على الجوال، وآخرُ مالكٍ لا يُحذف ولا يُنزَع دورُه.
 *
 * كلُّ مخالفةٍ تُرمى `ValidationException` على حقلها، فتعود الشاشةُ برسالةٍ
 * مفهومة بدل صفحة خطأ.
 */
class UserAdministrationGuard
{
    public const OWNER_ROLE = 'owner';

    /**
     * أدوارٌ تقارن الشيفرةُ بأسمائها في مواضع كثيرة (`where('role', 'employee')`،
     * `hasRoleName('manager')`)، فإعادةُ تسميتها تكسر تلك المواضع بصمت.
     */
    public const SYSTEM_ROLES = ['owner', 'manager', 'admin', 'employee'];

    /** صلاحياتٌ لو نُزعت من دور المالك لم يبقَ من يعيدها. */
    protected const OWNER_MUST_KEEP = ['settings.roles.view', 'settings.roles.edit', 'settings.users.view', 'settings.users.edit'];

    // ═══════════════════════ المستخدمون ═══════════════════════

    /** أيجوز للفاعل أن يسند هذا الدور (إنشاءً أو تعديلاً)؟ */
    public function assertCanAssignRole(User $actor, Role $role, string $field = 'role_id'): void
    {
        if ($this->missingPermissions($actor, $this->rolePermissionNames($role)) !== []) {
            $this->deny($field, 'لا يمكنك إسناد دورٍ يحمل صلاحياتٍ لا تملكها أنت.');
        }
    }

    /** أيجوز للفاعل أن يعدّل هذا المستخدم أو يحذفه؟ */
    public function assertCanManageUser(User $actor, User $target, string $field = 'role_id'): void
    {
        if ($actor->is($target)) {
            return; // بياناتُه هو — وتغييرُ دوره يُفحص على حدة
        }

        if ($this->missingPermissions($actor, $target->getAllPermissions()->pluck('name')->all()) !== []) {
            $this->deny($field, 'لا يمكنك تعديل مستخدمٍ صلاحياتُه أعلى من صلاحياتك.');
        }
    }

    /** تغييرُ دور مستخدمٍ قائم: لا لنفسه، ولا لآخر مالك. */
    public function assertRoleChangeAllowed(User $actor, User $target, Role $newRole, string $field = 'role_id'): void
    {
        if ((int) $target->role_id === (int) $newRole->id) {
            return;
        }

        if ($actor->is($target)) {
            $this->deny($field, 'لا يمكنك تغيير دورك بنفسك — اطلب ذلك من مستخدمٍ آخر يملك الصلاحية.');
        }

        if ($this->isLastOwner($target)) {
            $this->deny($field, 'هذا آخر مالكٍ في النظام؛ عيّن مالكاً آخر قبل تغيير دوره.');
        }
    }

    public function assertCanDeleteUser(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            $this->deny('user', 'لا يمكنك حذف حسابك الخاص.');
        }

        $this->assertCanManageUser($actor, $target, 'user');

        if ($this->isLastOwner($target)) {
            $this->deny('user', 'هذا آخر مالكٍ في النظام ولا يمكن حذفه.');
        }
    }

    // ═══════════════════════ الأدوار ═══════════════════════

    /**
     * تعديلُ دورٍ قائم (أو إنشاءُ جديد حين `$role` فارغ).
     *
     * @param  array<int, string>  $newPermissions  الصلاحياتُ كما ستصير بعد الحفظ
     */
    public function assertRoleWriteAllowed(User $actor, ?Role $role, string $newName, array $newPermissions, bool $mobileOnly): void
    {
        // ما يُمنَح: لا يتجاوز ما يملكه المانح
        if ($this->missingPermissions($actor, $newPermissions) !== []) {
            $this->deny('permissions', 'لا يمكنك منح صلاحياتٍ لا تملكها أنت.');
        }

        if ($role === null) {
            return;
        }

        // ما يُمسّ: دورٌ فوق الفاعل لا يُعدَّل ولو لإنقاصه
        if ($this->missingPermissions($actor, $this->rolePermissionNames($role)) !== []) {
            $this->deny('permissions', 'لا يمكنك تعديل دورٍ يحمل صلاحياتٍ لا تملكها أنت.');
        }

        $isOwnerRole = $role->name === self::OWNER_ROLE;
        $isOwnRole = (int) $actor->role_id === (int) $role->id;

        // دورُ الفاعل نفسِه: لا يزيده ولا ينقصه — وإلّا رفع نفسَه أو أقفل عليها.
        // والمالكُ مستثنًى: دورُه محروسٌ بقواعده الخاصّة أدناه.
        if ($isOwnRole && ! $isOwnerRole && $this->permissionsDiffer($role, $newPermissions)) {
            $this->deny('permissions', 'لا يمكنك تغيير صلاحيات دورك أنت — اطلب ذلك من مستخدمٍ بدورٍ آخر.');
        }

        if (in_array($role->name, self::SYSTEM_ROLES, true) && $newName !== $role->name) {
            $this->deny('name', 'هذا دورٌ من أدوار النظام ولا يُعاد تسميتُه.');
        }

        if ($isOwnerRole) {
            if ($mobileOnly) {
                $this->deny('mobile_only', 'دور المالك لا يُقصر على تطبيق الجوال — يُقفل به المالكون خارج الموقع.');
            }

            if (array_diff(self::OWNER_MUST_KEEP, $newPermissions) !== []) {
                $this->deny('permissions', 'دور المالك يحتفظ بصلاحيات إدارة المستخدمين والأدوار دائماً.');
            }
        }
    }

    public function assertCanDeleteRole(User $actor, Role $role): void
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            $this->deny('role', 'هذا دورٌ من أدوار النظام ولا يُحذف.');
        }

        if ($this->missingPermissions($actor, $this->rolePermissionNames($role)) !== []) {
            $this->deny('role', 'لا يمكنك حذف دورٍ يحمل صلاحياتٍ لا تملكها أنت.');
        }
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** أهو المالكُ الوحيد الباقي؟ */
    public function isLastOwner(User $user): bool
    {
        $ownerRoleId = Role::where('name', self::OWNER_ROLE)->value('id');

        if (! $ownerRoleId || (int) $user->role_id !== (int) $ownerRoleId) {
            return false;
        }

        return User::where('role_id', $ownerRoleId)->count() <= 1;
    }

    /**
     * ما في القائمة ولا يملكه الفاعل.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    protected function missingPermissions(User $actor, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $held = $actor->getAllPermissions()->pluck('name')->flip();

        return array_values(array_filter($names, fn (string $name) => ! $held->has($name)));
    }

    /** @return array<int, string> */
    protected function rolePermissionNames(Role $role): array
    {
        return $role->permissions()->pluck('name')->all();
    }

    /** @param  array<int, string>  $newPermissions */
    protected function permissionsDiffer(Role $role, array $newPermissions): bool
    {
        $current = $this->rolePermissionNames($role);

        return array_diff($current, $newPermissions) !== [] || array_diff($newPermissions, $current) !== [];
    }

    /** @return never */
    protected function deny(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
