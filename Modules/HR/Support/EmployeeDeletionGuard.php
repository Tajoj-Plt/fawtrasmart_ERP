<?php

namespace Modules\HR\Support;

use App\Models\Employee;
use App\Models\User;
use App\Services\Backup\ArabicSchemaTranslator;
use App\Support\OperationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حارسُ حذف الموظف — يُجيب سؤالاً واحداً: هل يُمحى هذا الصفُّ بلا أثر؟
 *
 * **لماذا حارسٌ لا مجرّدُ `delete()`**
 *
 * جدولُ `employees` يشير إليه ثلاثةٌ وثلاثون مفتاحاً أجنبياً، أكثرُها
 * `ON DELETE CASCADE` — فحذفٌ صلبٌ لموظفٍ يمحو معه عقودَه وخصمياتِه
 * وسجلَّ حضوره وتسوياتِ أهدافه ونقاطَه، و**يمحو شجرةَ مرؤوسيه كلَّها**
 * عبر `direct_manager_id`. فالحذفُ لا يُمنع لأنه صعب، بل يُمنع ما دام
 * للموظف أثرٌ يُمحى معه.
 *
 * **كيف يُعرف الأثر**
 *
 * على ثلاث طبقات، لأن طبقةً واحدة تُفلت شيئاً:
 *
 *   ١. **مسحٌ آلي للمفاتيح الأجنبية** من `information_schema`: كلُّ جدولٍ
 *      يشير إلى `employees.id` أو إلى `users.id` — فجدولٌ يُضاف غداً
 *      بمفتاحٍ أجنبي يدخل الحسابَ بلا تعديلِ سطرٍ هنا.
 *   ٢. **قائمةٌ مكتوبة** للجداول التي تحمل `employee_id` **بلا** مفتاحٍ
 *      أجنبي — وهي كثيرة (الإجازات، مسيّرات الرواتب، قيودُ اليومية،
 *      الزيارات…) ولا يبلغها المسحُ الآلي.
 *   ٣. **الحساباتُ المحاسبية** للموظف: قيدٌ واحدٌ على حسابِ ذمّته أو
 *      سُلَفِه حركةٌ مالية، وإن خلا كلُّ جدولٍ آخر.
 *
 * **وما لا يمنع**
 *
 * `PURGED_BY_*` جداولُ إعدادِ الموظف نفسِه — فروعُه وأقسامُه ومرفقاتُه
 * وحسابُ دخوله. لا معنى لها بعده، ولا تاريخَ فيها يُفقد، فتُمحى معه ولا
 * تُحسب مانعاً. ولولا ذلك لَما حُذف موظفٌ قطّ: `employee_branch` و
 * `users` يُنشآن مع الموظف في `store()` نفسِها.
 *
 * ومسارُ **الأرشفة** باقٍ لمن لا يُحذف: `EmployeeController::archive()`.
 */
final class EmployeeDeletionGuard
{
    /**
     * جداولُ تُمحى مع الموظف — إعدادُه هو، لا حركةٌ له.
     *
     * `employee_branch` و`users` منها لأنهما يُنشآن في `store()` مع
     * الموظف: عدُّهما مانعاً يجعل كلَّ موظفٍ غيرَ قابلٍ للحذف.
     */
    private const PURGED_BY_EMPLOYEE = [
        'employee_branch' => 'employee_id',
        'department_employee' => 'employee_id',
        'employee_attachments' => 'employee_id',
        'employee_documents' => 'employee_id',
        'employee_attendance_configs' => 'employee_id',
        'attendance_settings_employees' => 'employee_id',
        'custom_shifts_employees' => 'employee_id',
        'holy_day_list_customize_employees' => 'employee_id',
        'leave_policy_customize_employees' => 'employee_id',
        'treasury_employees' => 'employee_id',
        'default_warehouses' => 'employee_id',
    ];

    /** ومثلُها لحساب الدخول: جلساتٌ وتنبيهاتٌ لا تُقرأ بعد صاحبها. */
    private const PURGED_BY_USER = [
        'sessions' => 'user_id',
        'notifications' => 'user_id',
    ];

    /**
     * جداولٌ تحمل `employee_id` بلا مفتاحٍ أجنبي — لا يبلغها المسحُ الآلي.
     *
     * والعمودُ فيها يشير إلى `employees.id` (ما يشير إلى `users.id` في
     * `CHECKED_BY_USER` أدناه — والفضاءان مختلفان، فخلطُهما يمنع حذفَ
     * موظفٍ بريء أو يسمح بحذفِ ذي حركة).
     */
    private const CHECKED_BY_EMPLOYEE = [
        /*
            وأوّلُها مكرَّرةٌ عمداً مع المسح الآلي — لها مفاتيحُ أجنبية.
            التكرارُ يُطرح في `probes()`، وفائدتُه أن أخطرَ العلاقات
            مضمونةٌ ولو غاب `information_schema` (sqlite في الاختبارات).
        */
        ['employees', 'direct_manager_id'],
        ['contracts', 'employee_id'],
        ['attendance_days', 'employee_id'],
        ['attendance_records', 'employee_id'],
        ['deductions', 'employee_id'],
        ['salary_advance_requests', 'employee_id'],
        ['sales_target_settlements', 'employee_id'],
        ['points_transactions', 'employee_id'],
        ['invoices', 'employee_id'],
        ['clients', 'employee_id'],
        ['client_employee', 'employee_id'],
        ['appointments', 'employee_id'],

        ['leave_requests', 'employee_id'],
        ['leave_permissions', 'employee_id'],
        ['employee_leave_balances', 'employee_id'],
        ['salary_slips', 'employee_id'],
        ['payroll_employee', 'employee_id'],
        ['payroll_runs', 'employee_id'],
        ['salary_advances', 'employee_id'],
        ['end_of_service_benefits', 'employee_id'],
        ['sales_target_achievements', 'employee_id'],
        ['journal_entries', 'employee_id'],
        ['payment_vouchers', 'employee_id'],
        ['periodic_invoices', 'employee_id'],
        ['quotes', 'employee_id'],
        ['purchase_invoices', 'employee_id'],
        ['pos_transactions', 'employee_id'],
        ['manufactur_orders', 'employee_id'],
        ['suppliers', 'employee_id'],
        ['attendance_photos', 'employee_id'],
        ['credit_notifications', 'employee_id'],
        ['employee_group', 'employee_id'],
    ];

    /** والعمودُ هنا معرّفُ **مستخدم**: نشاطُ الموظف بحسابه لا بملفّه. */
    private const CHECKED_BY_USER = [
        ['session_logs', 'user_id'],
        ['trusted_devices', 'user_id'],
        ['visits', 'employee_id'],
        ['visit_follow_up_tasks', 'employee_id'],
        ['visit_competitor_observations', 'employee_id'],
        ['client_relations', 'employee_id'],
        ['collections_target_settlements', 'employee_id'],
        ['payments_process', 'employee_id'],
        ['employee_tracking', 'employee_id'],
        ['locations', 'employee_id'],
        ['bookings', 'employee_id'],
        ['invoice_transfers', 'from_employee_id'],
        ['invoice_transfers', 'to_employee_id'],
        // سجلُّ العمليات: أثرُ ما فعله في النظام، وهو «نشاطٌ» بعينه
        ['logs', 'created_by'],
    ];

    /** أسماءٌ أدقُّ من ترجمة اسم الجدول حيث تكون الترجمةُ مضلِّلة. */
    private const LABELS = [
        'employees.direct_manager_id' => 'موظفون يتبعونه إدارياً',
        'logs.created_by' => 'سجلّ عملياته في النظام',
        'session_logs.user_id' => 'سجلّ دخوله للنظام',
        'trusted_devices.user_id' => 'أجهزةٌ موثوقة لحسابه',
    ];

    private const LEDGER_LABEL = 'قيودٌ محاسبية على حساباته';

    /** @var array<int, array{label: string, count: int}>|null */
    private ?array $blockers = null;

    /** @param Collection<int, User> $users */
    private function __construct(
        private readonly Employee $employee,
        private readonly Collection $users,
    ) {}

    public static function for(Employee $employee): self
    {
        // نفسُ بحثِ `show()`: الربطُ بـ`employee_id` وإلا فبالبريد
        $users = User::where('employee_id', $employee->id)->get();

        if ($users->isEmpty() && $employee->email) {
            $users = User::where('email', $employee->email)->get();
        }

        return new self($employee, $users);
    }

    /** @return Collection<int, User> */
    public function users(): Collection
    {
        return $this->users;
    }

    public function allowsDeletion(): bool
    {
        return $this->blockers() === [];
    }

    /**
     * ما يمنع الحذف — مجموعاً باسمٍ عربيٍّ وعدد.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function blockers(): array
    {
        if ($this->blockers !== null) {
            return $this->blockers;
        }

        $probes = array_values(array_filter(
            $this->probes(),
            fn (array $probe) => self::columnExists($probe[0], $probe[1])
        ));

        $found = [];

        foreach ($this->countAll($probes) as $index => $count) {
            if ($count === 0) {
                continue;
            }

            [$table, $column] = $probes[$index];

            $label = self::label($table, $column);
            $found[$label] = ['label' => $label, 'count' => ($found[$label]['count'] ?? 0) + $count];
        }

        /*
            ولا يحذف أحدٌ نفسَه: حسابُ الدخول يُمحى مع الموظف، فمن حذف
            ملفَّه قطع جلستَه وأقفل البابَ خلفه. ونشاطُه في النظام كان
            سيمنعه غالباً — لكنّ «غالباً» لا تكفي هنا.
        */
        if (Auth::id() && $this->users->contains('id', Auth::id())) {
            $found['حسابُك أنت'] = ['label' => 'حسابُك أنت', 'count' => 1];
        }

        $ledgerEntries = $this->ledgerEntryCount();

        if ($ledgerEntries > 0) {
            $found[self::LEDGER_LABEL] = ['label' => self::LEDGER_LABEL, 'count' => $ledgerEntries];
        }

        return $this->blockers = array_values($found);
    }

    /** جملةٌ تُعرض للمستخدم، أو `null` إن كان الحذف مسموحاً. */
    public function reason(): ?string
    {
        $blockers = $this->blockers();

        if ($blockers === []) {
            return null;
        }

        $parts = array_map(
            fn (array $blocker) => $blocker['label'].' ('.$blocker['count'].')',
            $blockers
        );

        return 'لا يمكن حذف الموظف — له ارتباطاتٌ وحركةٌ في النظام: '
            .implode('، ', $parts)
            .'. حذفُه يمحو هذه البيانات معه، فأرشِفْه بدل ذلك ليخرج من القوائم ويبقى سجلُّه.';
    }

    /**
     * محوُ ما يُمحى مع الموظف — يُنادى داخل معاملة وقبل `forceDelete()`.
     *
     * صراحةً لا اتّكالاً على `ON DELETE CASCADE`: الشلّالُ يعمل في MySQL
     * وحدها، ومحرّكُ الاختبارات (sqlite) لا يُفعّله ما لم يُطلب.
     */
    public function purge(): void
    {
        foreach (self::PURGED_BY_EMPLOYEE as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, $this->employee->id)->delete();
            }
        }

        foreach ($this->users as $user) {
            foreach (self::PURGED_BY_USER as $table => $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, $user->id)->delete();
                }
            }

            if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'receiver_id')) {
                DB::table('notifications')->where('receiver_id', $user->id)->delete();
            }

            foreach (['model_has_roles', 'model_has_permissions'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)
                        ->where('model_id', $user->id)
                        ->where('model_type', $user->getMorphClass())
                        ->delete();
                }
            }

            if ($user->email && Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }

            DB::table('users')->where('id', $user->id)->delete();
        }

        $this->purgeEmptyLedgerAccounts();
    }

    // ═══════════════ الداخل ═══════════════

    /**
     * كلُّ ما يُفحص: مسحُ المفاتيح الأجنبية + المكتوبُ يدوياً، منقوصاً منه
     * ما يُمحى مع الموظف.
     *
     * @return array<int, array{0: string, 1: string, 2: int}>
     */
    private function probes(): array
    {
        $probes = [];

        foreach (self::foreignKeysTo('employees') as [$table, $column]) {
            if ((self::PURGED_BY_EMPLOYEE[$table] ?? null) === $column) {
                continue;
            }

            $probes[] = [$table, $column, (int) $this->employee->id];
        }

        foreach (self::CHECKED_BY_EMPLOYEE as [$table, $column]) {
            if ((self::PURGED_BY_EMPLOYEE[$table] ?? null) === $column) {
                continue;
            }

            $probes[] = [$table, $column, (int) $this->employee->id];
        }

        foreach ($this->users as $user) {
            foreach (self::foreignKeysTo('users') as [$table, $column]) {
                if ((self::PURGED_BY_USER[$table] ?? null) === $column) {
                    continue;
                }

                $probes[] = [$table, $column, (int) $user->id];
            }

            foreach (self::CHECKED_BY_USER as [$table, $column]) {
                if ((self::PURGED_BY_USER[$table] ?? null) === $column) {
                    continue;
                }

                $probes[] = [$table, $column, (int) $user->id];
            }
        }

        // المسحُ الآلي والقائمةُ المكتوبة قد يلتقيان على عمودٍ واحد
        return array_values(array_unique($probes, SORT_REGULAR));
    }

    /**
     * عدُّ كلّ المجسّات في رحلةٍ واحدة إلى القاعدة.
     *
     * المجسّاتُ نحوٌ من مئةٍ وأربعين، وهي تُحسب عند كلّ فتحةٍ لبطاقة
     * الموظف. فمئةٌ وأربعون `SELECT COUNT(*)` متعاقبة تكلّف عُشرَ ثانيةٍ
     * في الذهاب والإياب وحدَه، و`UNION ALL` واحدةٌ تكلّف رحلةً واحدة.
     *
     * @param  array<int, array{0: string, 1: string, 2: int}>  $probes
     * @return array<int, int> المفتاحُ موضعُ المجسّ في `$probes`
     */
    private function countAll(array $probes): array
    {
        if ($probes === []) {
            return [];
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            $counts = [];

            foreach ($probes as $index => [$table, $column, $id]) {
                $counts[$index] = (int) DB::table($table)->where($column, $id)->count();
            }

            return $counts;
        }

        $selects = [];
        $bindings = [];

        foreach ($probes as $index => [$table, $column, $id]) {
            // الأسماءُ من ثوابتِ هذا الصنف ومن `information_schema` لا من
            // طلبٍ — والفحصُ مع ذلك، لأن التركيبَ النصّي لا يُؤمَّن بالنيّة
            if (! preg_match('/^[A-Za-z0-9_]+$/', $table) || ! preg_match('/^[A-Za-z0-9_]+$/', $column)) {
                continue;
            }

            $selects[] = "SELECT {$index} AS k, COUNT(*) AS n FROM `{$table}` WHERE `{$column}` = ?";
            $bindings[] = $id;
        }

        if ($selects === []) {
            return [];
        }

        $counts = [];

        foreach (DB::select(implode(' UNION ALL ', $selects), $bindings) as $row) {
            $counts[(int) $row->k] = (int) $row->n;
        }

        return $counts;
    }

    /**
     * وجودُ العمود — من خريطةٍ تُبنى مرّةً لا باستعلامٍ لكلّ عمود.
     *
     * `Schema::hasColumn()` تسأل القاعدةَ في كلّ نداء، ومئةٌ وأربعون نداءً
     * تضاعف كلفةَ العدّ نفسِه.
     */
    private static function columnExists(string $table, string $column): bool
    {
        static $map = null;

        if ($map === null) {
            $map = [];

            if (DB::connection()->getDriverName() === 'mysql') {
                foreach (DB::select('SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()') as $row) {
                    $map[(string) $row->t][(string) $row->c] = true;
                }
            }
        }

        if ($map !== []) {
            return isset($map[$table][$column]);
        }

        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    /**
     * الجداولُ المشيرةُ إلى `$table.id` بمفتاحٍ أجنبي.
     *
     * MySQL وحدها: `information_schema` لا وجود لها في sqlite، وهو محرّكُ
     * الاختبارات — فتسقط الطبقةُ الأولى هناك وتبقى الطبقتان الأخريان.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function foreignKeysTo(string $table): array
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return $cache[$table] = [];
        }

        $rows = DB::select(
            'SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c
               FROM information_schema.KEY_COLUMN_USAGE k
              WHERE k.TABLE_SCHEMA = DATABASE()
                AND k.REFERENCED_TABLE_NAME = ?
                AND k.REFERENCED_COLUMN_NAME = ?',
            [$table, 'id']
        );

        return $cache[$table] = array_map(
            fn ($row) => [(string) $row->t, (string) $row->c],
            $rows
        );
    }

    private static function label(string $table, string $column): string
    {
        return self::LABELS[$table.'.'.$column] ?? ArabicSchemaTranslator::table($table);
    }

    /** معرّفاتُ حسابات الموظف: ذمّتُه وسُلَفُه وخزائنُه. */
    private function ledgerAccountIds(): array
    {
        $ids = [];

        foreach (['salary_payable_account_id', 'employee_advance_account_id'] as $column) {
            if (Schema::hasColumn('employees', $column) && $this->employee->{$column}) {
                $ids[] = (int) $this->employee->{$column};
            }
        }

        if (Schema::hasTable('treasury_employees') && Schema::hasTable('treasuries')) {
            $ids = array_merge($ids, DB::table('treasury_employees')
                ->join('treasuries', 'treasuries.id', '=', 'treasury_employees.treasury_id')
                ->where('treasury_employees.employee_id', $this->employee->id)
                ->whereNotNull('treasuries.account_id')
                ->pluck('treasuries.account_id')
                ->map(fn ($id) => (int) $id)
                ->all());
        }

        return array_values(array_unique($ids));
    }

    private function ledgerEntryCount(): int
    {
        $ids = $this->ledgerAccountIds();

        if ($ids === [] || ! Schema::hasTable('journal_entry_details')) {
            return 0;
        }

        return (int) DB::table('journal_entry_details')->whereIn('account_id', $ids)->count();
    }

    /**
     * حساباتُ الموظف الخالية تُمحى معه — وإلّا تراكمت في شجرة الحسابات
     * بلا صاحب. والخلوُّ مضمون: قيدٌ واحدٌ عليها كان قد منع الحذف أصلاً.
     */
    private function purgeEmptyLedgerAccounts(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        $ids = [];

        foreach (['salary_payable_account_id', 'employee_advance_account_id'] as $column) {
            if (Schema::hasColumn('employees', $column) && $this->employee->{$column}) {
                $ids[] = (int) $this->employee->{$column};
            }
        }

        foreach ($ids as $id) {
            $hasChildren = DB::table('accounts')->where('parent_id', $id)->exists();

            if ($hasChildren) {
                continue;
            }

            try {
                DB::table('accounts')
                    ->where('id', $id)
                    ->when(Schema::hasColumn('accounts', 'is_system'), fn ($q) => $q->where('is_system', 0))
                    ->delete();
            } catch (\Throwable $e) {
                /*
                    حسابٌ يشير إليه شيءٌ آخر يبقى، ولا يُسقط حذفَ الموظف:
                    تنظيفُ الشجرة غايةٌ ثانوية، والمعاملةُ لا تنهار بفشل
                    عبارةٍ واحدة في InnoDB.
                */
                OperationLog::failure($e, 'HR.Employee', 'purge_ledger_account');
            }
        }
    }
}
