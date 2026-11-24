<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait HasPermissions
{
    /**
     * Check if user has permission
     * @param string|array $permission
     * @return bool
     */
    protected function hasPermission($permission)
    {
        /* ── لماذا لا `hasAnyPermission()` ولا `hasPermissionTo()`؟ ──
         *
         * كلتاهما من spatie، وكلتاهما **ترمي** `PermissionDoesNotExist`
         * حين لا تجد الاسم في كاشها — لا تُرجع `false`. فاسمٌ قديم بقي في
         * شاشة، أو كاشُ صلاحيات سبق إضافة الاسم، يُسقط الصفحة كلَّها
         * برسالة «There is no permission named …» بدل أن يُخفي زرّاً.
         *
         * ثمّ إنّ صلاحيات هذا النظام على **الدور** (`users.role_id` ←
         * `role_has_permissions`) لا على المستخدم، وspatie تسأل عن
         * صلاحيات المستخدم المباشرة وأدواره في `model_has_roles` — وهي
         * ليست المصدر الذي تقرؤه بقيّة الشاشات.
         *
         * فالمصدر الواحد هو `hasPermission()`/`canAny()` العامّتان في
         * `app/Helpers/PermissionHelper.php`، ومن ورائهما
         * `User::hasRolePermission()`: استعلامٌ على الدور، لا يرمي أبداً.
         *
         * والنداء مسبوقٌ بشرطةٍ مائلة (`\hasPermission`) قصداً: بدونها
         * تبقى دالّةً عامّة يجدها PHP، لكنّ الشرطة تقطع أيّ لبسٍ مع هذه
         * الدالّة نفسِها.
         */
        if (! Auth::check()) {
            return false;
        }

        return is_array($permission)
            ? \canAny($permission)
            : \hasPermission($permission);
    }

    /**
     * Check permission and abort if not authorized
     * @param string|array $permission
     * @param string $message
     */
    protected function authorize($permission, $message = 'غير مصرح لك بالوصول لهذه الصفحة')
    {
        if (!$this->hasPermission($permission)) {
            abort(403, $message);
        }
    }

    /**
     * Check permission and redirect if not authorized
     * @param string|array $permission
     * @param string $route
     * @param string $message
     */
    protected function authorizeOrRedirect($permission, $route = null, $message = 'ليس لديك صلاحية للقيام بهذا الإجراء')
    {
        if (!$this->hasPermission($permission)) {
            $route = $route ?? url()->previous();
            return redirect($route)->with('error', $message);
        }
    }
}
