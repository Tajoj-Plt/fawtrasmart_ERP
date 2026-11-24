<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

if (! function_exists('hasPermission')) {
    /**
     * Check if the authenticated user has a specific permission.
     *
     * يمرّ عبر `User::hasRolePermission()` — دور المستخدم (`role_id`) وحده
     * وصلاحياته من `role_has_permissions`، لا Spatie (`model_has_roles`/
     * `model_has_permissions`). كانت هذه الدالّة تقرأ Spatie مباشرةً بينما
     * صارت شاشات الخادم (middleware، DataScopeService) تقرأ النظام الجديد
     * حصراً — فكان زرٌّ يظهر في الواجهة والخادم يرفض الإجراء نفسه.
     *
     * صلاحيةٌ غير موجودة في القاعدة تعني «ممنوع» بهدوء لا استثناءً: لا
     * يصحّ أن يُسقط اسمٌ واحد نسيه سيدر الصفحة كلّها بخطأ 500 بدل أن يُخفي
     * زرّاً — و`hasRolePermission()` مبنيّةٌ على هذا أصلاً.
     *
     * @param  string|array  $permission
     * @return bool
     */
    function hasPermission($permission)
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        if (is_array($permission)) {
            foreach ($permission as $name) {
                if ($user->hasRolePermission($name)) {
                    return true;
                }
            }

            return false;
        }

        return $user->hasRolePermission($permission);
    }
}

if (! function_exists('checkPermission')) {
    /**
     * Check permission and abort if not authorized
     *
     * @param  string|array  $permission
     * @param  string  $message
     * @return void
     */
    function checkPermission($permission, $message = 'غير مصرح لك بالوصول')
    {
        if (! hasPermission($permission)) {
            abort(403, $message);
        }
    }
}

if (! function_exists('canAny')) {
    /**
     * Check if user has any of the given permissions
     *
     * @return bool
     */
    function canAny(array $permissions)
    {
        return hasPermission($permissions);
    }
}

if (! function_exists('canAll')) {
    /**
     * Check if user has all of the given permissions
     *
     * @return bool
     */
    function canAll(array $permissions)
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        foreach ($permissions as $name) {
            if (! $user->hasRolePermission($name)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('userPermissions')) {
    /**
     * Get all permissions for the authenticated user's role.
     *
     * @return Collection
     */
    function userPermissions()
    {
        if (! Auth::check()) {
            return collect([]);
        }

        return Auth::user()->primaryRole?->permissions ?? collect([]);
    }
}

if (! function_exists('languagePermissions')) {
    /**
     * خريطة صلاحيات إدارة اللغات.
     *
     * لكل عملية صلاحية دقيقة خاصة بها، ومعها صلاحيات الإعدادات العامة
     * الموجودة أصلاً في النظام كبديل — حتى لا يفقد من كان يدير الإعدادات
     * وصوله لشاشة اللغات بعد إضافة الصلاحيات الجديدة.
     *
     * @return array<string, array<int, string>>
     */
    function languagePermissions(): array
    {
        return [
            'view' => ['settings_languages_view', 'settings_general_view', 'settings_edit_general_settings'],
            'create' => ['settings_languages_create', 'settings_edit_general_settings'],
            'edit' => ['settings_languages_edit', 'settings_edit_general_settings'],
            'delete' => ['settings_languages_delete', 'settings_edit_general_settings'],
            'activate' => ['settings_languages_activate', 'settings_edit_general_settings'],
            'set_default' => ['settings_languages_set_default', 'settings_edit_general_settings'],
        ];
    }
}

if (! function_exists('canLanguages')) {
    /**
     * هل يملك المستخدم الحالي صلاحية عملية معيّنة على اللغات؟
     *
     * @param  string  $ability  view|create|edit|delete|activate|set_default
     */
    function canLanguages(string $ability): bool
    {
        $map = languagePermissions();

        if (! isset($map[$ability])) {
            return false;
        }

        // canAny يتجاهل الصلاحيات غير الموجودة في قاعدة البيانات بدل رمي استثناء
        return canAny($map[$ability]);
    }
}

if (! function_exists('checkLanguagePermission')) {
    /**
     * يوقف الطلب بـ403 إن لم يملك المستخدم صلاحية العملية المطلوبة على اللغات.
     */
    function checkLanguagePermission(string $ability, string $message = 'غير مصرح لك بإدارة لغات النظام'): void
    {
        if (! canLanguages($ability)) {
            abort(403, $message);
        }
    }
}

if (! function_exists('canViewHrTeamDashboard')) {
    /**
     * من يمكنه فتح لوحة أداء الفريق / تقرير أداء الموظفين:
     * صلاحيات إدارة الموارد البشرية (نفس @canany في dashboard.blade.php) أو حساب مرتبط بسجل موظف.
     */
    function canViewHrTeamDashboard($user = null): bool
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }
        if ($user->employee_id) {
            return true;
        }

        // نسخة hasPermission() لمستخدمٍ مُمرَّر صراحةً، لا Auth::user() دائماً
        foreach ([
            // مجموعات `HrPermissions` (<الشاشة>.<الفعل>) — لا أسماءَ قديمة ولا «هو»
            'employees.index', 'employees.create', 'employees.edit', 'employees.destroy',
            'settings.roles.create', 'settings.roles.edit',
            'employee_accounts.access', 'employee_accounts.roles',
            'attendance_records.index', 'attendance_records.create',
            'attendance_places.index', 'attendance_cards.index', 'attendance_books.index', 'attendance_books.create',
            'attendance_books.edit', 'attendance_books.destroy', 'attendance_days.index', 'attendance_days.edit',
            'attendance_registration.kiosk', 'attendance_settings.manage',
            'attendance_corrections.index', 'attendance_corrections.create', 'attendance_corrections.approve',
            'leave_requests.index', 'leave_requests.create', 'leave_requests.edit',
            'leave_requests.destroy', 'leave_requests.approve',
            'leave_permissions.index', 'leave_permissions.create',
            // الرواتب
            'contracts.index', 'contracts.create', 'contracts.edit',
            'payroll.index', 'payroll.create', 'payroll.edit', 'payroll.destroy', 'payroll.approve',
            'payslips.index', 'payslips.disburse',
            'disbursements.index', 'disbursements.stop', 'disbursements.reverse',
            'loans.index', 'loans.create', 'loans.approve',
            'payroll_settings.manage', 'overtime.settings', 'eos.index', 'insurance.settings',
            'bank_files.index',
            'deductions.index', 'deductions.create', 'deductions.edit', 'deductions.destroy',
            'deductions.approve', 'deductions.settle',
        ] as $name) {
            if ($user->hasRolePermission($name)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('hrAttendanceCan')) {
    /**
     * هل يملك المستخدم أيّ صلاحية لبوابة حضور (الجديدة أو توافقها القديم)؟
     *
     * @see \App\Support\HrAttendancePermissions
     */
    function hrAttendanceCan(string ...$actions): bool
    {
        foreach ($actions as $action) {
            $names = explode('|', \App\Support\HrAttendancePermissions::gate($action));
            if (hasPermission($names)) {
                return true;
            }
        }

        return false;
    }
}
