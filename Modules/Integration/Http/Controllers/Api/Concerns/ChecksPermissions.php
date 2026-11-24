<?php

namespace Modules\Integration\Http\Controllers\Api\Concerns;

/**
 * فحص صلاحيات المستخدم الحالي لبناء أعلام `can_*` على كل سجل — نفس
 * `hasRolePermission()` الذي يحرس المسارات، بذاكرةٍ للطلب الواحد لئلا
 * يُسأل عن الصلاحية نفسها مرّةً لكل صفٍّ في القائمة.
 */
trait ChecksPermissions
{
    /** @var array<string, bool> */
    private array $permissionMemo = [];

    protected function userCan(string $permission): bool
    {
        if (! array_key_exists($permission, $this->permissionMemo)) {
            try {
                $this->permissionMemo[$permission] = (bool) auth()->user()?->hasRolePermission($permission);
            } catch (\Throwable $e) {
                $this->permissionMemo[$permission] = false;
            }
        }

        return $this->permissionMemo[$permission];
    }
}
