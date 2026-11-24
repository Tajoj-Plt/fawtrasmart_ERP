<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * يُصلح حسابات دخول الموظفين التي فقدت دورها.
 *
 * فحصُ الصلاحيات كلُّه يقرأ `users.role_id` (انظر `User::hasRolePermission()`
 * و`CheckPermissionRole`). و«الدور الوظيفي» المعروض في ملفّ الموظف عمودٌ آخر
 * تماماً — `employees.Job_role_id` — لا أثر له في أي فحص. وكان حفظُ تعديل
 * الموظف يُسنِد الدور إلى حساب الدخول **قبل** إنشائه؛ فمن لم يكن له حسابٌ
 * وقتَها أُنشئ حسابُه بلا `role_id`، ومن حُذف حسابُه ثم أُعيد كذلك. النتيجة
 * موظفٌ يقول ملفُّه «مدير مبيعات» ويقول له النظامُ عند كل شاشة «ليس لديك
 * صلاحية».
 *
 * أُصلح ترتيبُ الإسناد في `EmployeeController::update()`، لكن من انكسر قبل
 * ذلك يبقى مكسوراً حتى يُعاد حفظُ ملفّه واحداً واحداً. هذا الأمر يفعلها دفعةً
 * واحدة، ويطبع ما سيغيّره قبل أن يغيّره.
 */
class SyncEmployeeLoginRoles extends Command
{
    protected $signature = 'hr:sync-login-roles
        {--dry-run : اعرض ما سيتغيّر بلا كتابة}
        {--only-missing : لا تمسّ حساباً له دورٌ أصلاً — املأ الفارغَ وحده}';

    protected $description = 'مزامنة الدور الوظيفي للموظف (employees.Job_role_id) إلى حساب دخوله (users.role_id)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');
        $fixed = 0;
        $missingAccount = 0;
        $missingRole = 0;
        $emptyRole = 0;
        $skippedHasRole = 0;
        $rows = [];

        $employees = Employee::query()
            ->select('id', 'email', 'Job_role_id')
            ->get();

        foreach ($employees as $employee) {
            $user = User::where('employee_id', $employee->id)->first()
                 ?? ($employee->email ? User::where('email', $employee->email)->first() : null);

            if (! $user) {
                $missingAccount++;
                $rows[] = [$employee->id, $employee->email ?: '—', '—', 'لا حساب دخول'];

                continue;
            }

            if (! $employee->Job_role_id) {
                $missingRole++;
                $rows[] = [$employee->id, $user->email, '—', 'لا دور وظيفي في الملفّ'];

                continue;
            }

            $role = \Spatie\Permission\Models\Role::find($employee->Job_role_id);

            if (! $role) {
                $missingRole++;
                $rows[] = [$employee->id, $user->email, (string) $employee->Job_role_id, 'دورٌ محذوف'];

                continue;
            }

            if ((int) $user->role_id === (int) $role->id) {
                // الدورُ صحيح — يبقى التحذير إن كان فارغاً من الصلاحيات، فهو
                // نفسُ العطب من الوجه الآخر: دورٌ مُسنَدٌ لا يفتح شيئاً.
                if ($role->permissions()->count() === 0) {
                    $emptyRole++;
                    $rows[] = [$employee->id, $user->email, $role->name, 'الدور بلا أي صلاحية'];
                }

                continue;
            }

            /* `--only-missing`: الأمرُ يفرض دورَ **الملفّ** على الحساب،
               و`employees.Job_role_id` في قواعد قائمةٍ قديمٌ أو افتراضيّ —
               فيُنزِّل حسابَ المالك من `owner` إلى «مندوب مبيعات» بصمت.
               والعطبُ المقصود إصلاحُه هو الحسابُ **بلا دورٍ إطلاقاً** (لا
               يمرّ فحصَ صلاحيةٍ واحداً)، وهذا يعالجه بلا مساسٍ بمن له دور. */
            if ($onlyMissing && $user->role_id !== null) {
                $skippedHasRole++;
                $rows[] = [
                    $employee->id,
                    $user->email,
                    $user->primaryRole?->name ?? (string) $user->role_id,
                    'تُرك كما هو (الملفّ يقول: '.$role->name.')',
                ];

                continue;
            }

            $rows[] = [
                $employee->id,
                $user->email,
                $role->name,
                $dry
                    ? 'سيُسنَد (كان: '.($user->primaryRole?->name ?? 'بلا دور').')'
                    : 'أُسنِد (كان: '.($user->primaryRole?->name ?? 'بلا دور').')',
            ];

            if (! $dry) {
                $user->assignPrimaryRole($role);
            }

            $fixed++;
        }

        if ($rows) {
            $this->table(['الموظف', 'حساب الدخول', 'الدور', 'الحالة'], $rows);
        }

        $this->line('');
        $this->info(($dry ? 'سيُصلَح: ' : 'أُصلِح: ').$fixed.' حساب');

        if ($skippedHasRole) {
            $this->line('تُرك بلا مساس (له دورٌ أصلاً): '.$skippedHasRole.' حساب — أزل `--only-missing` لفرض دور الملفّ عليها.');
        }

        if ($missingAccount) {
            $this->warn('بلا حساب دخول: '.$missingAccount.' موظف — لن يستطيعوا الدخول إطلاقاً.');
        }

        if ($missingRole) {
            $this->warn('بلا دور وظيفي صالح: '.$missingRole.' موظف — اختر لهم دوراً من شاشة تعديل الموظف.');
        }

        if ($emptyRole) {
            $this->warn('دورهم بلا أي صلاحية: '.$emptyRole.' موظف — امنح الدور صلاحياته من الإعدادات ← المستخدمون والأدوار.');
        }

        return self::SUCCESS;
    }
}
