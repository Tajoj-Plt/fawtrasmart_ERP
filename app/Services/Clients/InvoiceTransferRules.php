<?php

namespace App\Services\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceTransfer;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * قواعد تسليم الفواتير بين الموظفين — مكانٌ واحد تقرأ منه نافذةُ التسليم
 * في شاشة «تحويلات الفواتير» وتبويبُ «تسليم الفواتير» في ملفّ العميل،
 * ويتحقّق به `InvoiceTransferController::store()`.
 *
 * ══════════ العهدة لا الرؤية ══════════
 *
 * كانت قائمةُ الفواتير تُبنى على نطاق الرؤية: من يحمل `data_scope_all`
 * يرى فواتير المنشأة كلَّها فيُسلِّمها كلَّها، ولو كانت باسم مندوبٍ آخر.
 * و`store()` لم يسأل عن صاحب الفاتورة أصلاً — أيُّ `invoice_id` يُرسَل
 * يُقبل. التسليمُ نقلُ عهدة، فلا يُسلِّم إلّا صاحبُها: الموظفُ الذي هو
 * «مسؤول المبيعات» فيها (`invoices.employee_id` = `employees.id`)، أو من
 * أنشأها إن لم يكن لها مسؤول.
 *
 * ══════════ من يُسلَّم إليه ══════════
 *
 * كانت القائمة `users.role IN ('employee','manager')`، وعمودُ `role`
 * لا يحمل `employee` أبداً — يحمل اسمَ الدور («مندوب مبيعات»…) — فلم يظهر
 * فيها مندوبٌ واحد. المستلِم الآن كلُّ حسابٍ مربوطٍ بموظفٍ على رأس العمل.
 * وحين يُشترط الاعتماد يُعلَّم من لا يملك دورُه صلاحيةَ الردّ: الطلبُ إليه
 * يبقى معلّقاً أبداً لأنه لا يصل الشاشة.
 *
 * ══════════ طلبُ الاستلام ══════════
 *
 * كان للتحويل اتجاهٌ ثانٍ (`InvoiceTransfer::DIRECTION_REQUEST`): موظفٌ يطلب
 * فاتورةً في عهدة غيره. أُلغي إنشاؤه في 2026-09-26 بطلب المستخدم — يرى
 * الموظف ما سُلِّم إليه فقط ويستلمه. الطلبات القديمة المعلّقة ما زالت
 * تُقبل وتُرفض (يردّ صاحبُ العهدة `from`).
 *
 * ══════════ فرعُ العميل وخطُّ السير ══════════
 *
 * لا تُسلَّم فاتورةٌ ولا تُطلب إلّا لموظفٍ من فرع عميلها: الفرعُ المحاسبي
 * فوق عقدة العميل (`Branch::resolveAccountingBranchId()`)، والموظفُ منه إن
 * كان في فروعه (`employee_branch` + `employees.branch_id`) ذلك الفرعُ أو
 * عقدةٌ تحته. العميلُ بلا عقدةٍ لا يُقيِّد أحداً.
 *
 * وخطُّ السير يرتّب القائمة ولا يحجب: المديرُ يضع العميلَ في خطّ سير
 * المستلِم، فيظهر المستلِمُ أوّلاً ومعه تاريخُ زيارته القادمة، وحين يصل
 * إلى العميل يستلم من مساحة الزيارة.
 *
 * ══════════ تسليمُ الرصيد ══════════
 *
 * مستحقُّ الفاتورة لا ينقص بإشعارٍ دائنٍ لم يُحوَّل مرتجعاً، فيُسلَّم رصيدُ
 * حساب العميل من الأستاذ بدلها (`InvoiceTransfer::KIND_BALANCE`). صاحبُ
 * الرصيد آخرُ من استلمه بتحويلٍ مقبول؛ وإن لم يُسلَّم قطّ فمسؤولُ العميل
 * (`client_employee`) أو من في عهدته فاتورةٌ مستحقّة له. وحين يُستلَم الرصيد
 * تنتقل معه فواتيرُ المسلِّم المستحقّة لذلك العميل، فيتبع التحصيلُ الرصيد.
 */
class InvoiceTransferRules
{
    /** صلاحيةُ قبول التحويل الوارد أو رفضه */
    public const RECEIVE_PERMISSION = 'clients_invoice_transfer_respond';

    /**
     * المستحقّ الفعليّ للفاتورة: `due_value` ناقصاً ما ردّه مرتجعُها أو سوّاه إشعارٌ دائن (`returned_payment`).
     * المرتجعُ لا يُنقص `due_value` — يكتب `returned_payment` — فبدون الطرح تظهر الفاتورة التي رُدّت كاملةً
     * مستحقّةً بقيمتها كلّها (كـ`Invoice::calculateRemainingAmount()` الذي يطرحه هو أيضاً).
     */
    public const NET_DUE_SQL = 'GREATEST(COALESCE(due_value, 0) - COALESCE(returned_payment, 0), 0)';

    /** فواتيرُ بقي عليها مستحقٌّ فعليّ — بديلُ `where('due_value', '>', 0)` في كلّ ما يُعرض ويُسلَّم */
    public static function whereNetDue(Builder $query): Builder
    {
        return $query->whereRaw(self::NET_DUE_SQL.' > 0.009');
    }

    /**
     * الفواتير التي يملك المستخدم تسليمها: في عهدته، ليست مرتجعاً ولا
     * ملغاة، ولا عليها طلبٌ معلّق — والمسدَّدة كلياً فقط إن سمحت الإعدادات.
     */
    public static function transferableInvoices(User $user): Builder
    {
        $query = Invoice::query()
            ->where(fn ($custody) => static::constrainCustody($custody, $user))
            ->where(fn ($type) => $type->whereNull('type')->orWhere('type', '!=', 'returned'))
            ->whereNull('voided_at')
            ->whereNotIn('id', fn ($pending) => $pending->select('invoice_id')
                ->from('invoice_transfers')
                ->where('status', 'pending'));

        if (! ClientSettingsHelper::invoiceTransferAllowPaid()) {
            static::whereNetDue($query);
        }

        return $query;
    }

    /**
     * أسماءُ أصحاب عهدة الفواتير — من الحساب المربوط بمسؤول المبيعات (أقدمُها)
     * أو من المنشئ.
     *
     * @return array<int, string|null> مفتاحُه معرّفُ الفاتورة
     */
    public static function holderNames(Collection $invoices): array
    {
        $byEmployee = User::whereIn('employee_id', $invoices->pluck('employee_id')->filter()->unique())
            ->orderByDesc('id')
            ->pluck('name', 'employee_id');
        $byUser = User::whereIn('id', $invoices->whereNull('employee_id')->pluck('created_by')->filter()->unique())
            ->pluck('name', 'id');

        return $invoices->mapWithKeys(fn ($invoice) => [$invoice->id => $invoice->employee_id !== null
            ? ($byEmployee[$invoice->employee_id] ?? null)
            : ($byUser[$invoice->created_by] ?? null)])->all();
    }

    /** هل الفاتورة في عهدة المستخدم؟ */
    public static function inCustodyOf(User $user, Invoice $invoice): bool
    {
        if ($invoice->employee_id !== null) {
            return $user->employee_id !== null
                && (int) $invoice->employee_id === (int) $user->employee_id;
        }

        return (int) $invoice->created_by === (int) $user->id;
    }

    /** سببُ منع تسليم هذه الفاتورة — أو `null`. */
    public static function invoiceBlock(User $user, Invoice $invoice): ?string
    {
        if (! static::inCustodyOf($user, $invoice)) {
            return "الفاتورة ({$invoice->code}) ليست في عهدتك — لا يُسلِّم الموظف إلّا فاتورةً هو مسؤولُ مبيعاتها";
        }

        if ($invoice->type === 'returned') {
            return 'فاتورة المرتجع لا تُسلَّم';
        }

        if ($invoice->voided_at !== null) {
            return 'الفاتورة ملغاة ولا تُسلَّم';
        }

        return null;
    }

    /**
     * المستلِمون الممكنون، مرتّبين بالاسم، ولكلٍّ `can_receive`.
     *
     * @return Collection<int, User>
     */
    public static function recipients(User $sender): Collection
    {
        $needsPermission = ClientSettingsHelper::invoiceTransferRequiresApproval();

        return User::query()
            ->where('id', '!=', $sender->id)
            ->whereNotNull('employee_id')
            ->whereHas('employee', fn ($employee) => $employee->active())
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'role_id', 'employee_id'])
            ->each(fn (User $user) => $user->setAttribute(
                'can_receive',
                ! $needsPermission || $user->hasRolePermission(self::RECEIVE_PERMISSION)
            ));
    }

    /** سببُ رفض هذا المستلِم — أو `null`. */
    public static function recipientBlock(?User $receiver): ?string
    {
        if (! $receiver || ! $receiver->employee_id) {
            return 'المستلِم ليس موظفاً في النظام — لا يمكن أن تصير الفاتورة باسمه';
        }

        if (! $receiver->employee()->active()->exists()) {
            return "الموظف «{$receiver->name}» ليس على رأس العمل";
        }

        if (ClientSettingsHelper::invoiceTransferRequiresApproval()
            && ! $receiver->hasRolePermission(self::RECEIVE_PERMISSION)) {
            return "الموظف «{$receiver->name}» لا يملك صلاحية «قبول أو رفض تحويل وارد» فلن يصله الطلب — امنح دورَه الصلاحية من شاشة الأدوار أو اختر موظفاً آخر";
        }

        return null;
    }

    /**
     * فواتيرُ العميل التي عليها مستحقّ — ما تعرضه مساحة الزيارة للتسليم
     * والاستلام. المستحقُّ شرطٌ هنا أيّاً كان إعداد «تحويل المسدَّدة»: ما
     * يُسلَّم في الزيارة عهدةُ تحصيل، والمسدَّدة لا تحصيل عليها.
     *
     * لكلّ فاتورة: `is_mine`، و`holder` (اسم صاحب العهدة)، و`pending`
     * (التحويل المعلّق عليها إن وُجد).
     *
     * @return Collection<int, Invoice>
     */
    public static function outstandingForClient(User $user, int $clientId): Collection
    {
        // `due_value` هنا هو المستحقّ الفعليّ (بعد المرتجع والإشعار الدائن) — للعرض والجمع، والمجموعةُ لا تُحفظ
        $invoices = static::whereNetDue(Invoice::query())
            ->where('client_id', $clientId)
            ->where(fn ($type) => $type->whereNull('type')->orWhere('type', '!=', 'returned'))
            ->whereNull('voided_at')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->select(['id', 'code', 'invoice_date', 'grand_total', 'employee_id', 'created_by', 'client_id', 'type', 'voided_at'])
            ->selectRaw(self::NET_DUE_SQL.' as due_value')
            ->get();

        $pending = InvoiceTransfer::with(['fromEmployee:id,name', 'toEmployee:id,name'])
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->where('status', InvoiceTransfer::STATUS_PENDING)
            ->get()
            ->keyBy('invoice_id');

        // اسمُ صاحب العهدة من حسابه — الاسمُ نفسه الذي تعرضه التحويلات
        $holders = static::holderNames($invoices);

        return $invoices->each(function (Invoice $invoice) use ($user, $pending, $holders) {
            $invoice->setAttribute('is_mine', static::inCustodyOf($user, $invoice));
            $invoice->setAttribute('holder', $holders[$invoice->id] ?? null);
            $invoice->setAttribute('pending', $pending->get($invoice->id));
        });
    }

    /**
     * قيدُ «كلّ التحويلات» على نطاق رؤية المستندات: تحويلٌ أحدُ طرفيه
     * ضمن من يراهم المستخدم. المندوبُ بنطاقه الشخصيّ لا يرى إلّا تحويلاته.
     */
    public static function constrainVisible(Builder $query): Builder
    {
        $userIds = DataScope::documentUserIds();

        if ($userIds === null) {
            return $query;
        }

        $userIds = $userIds ?: [0];

        return $query->where(fn ($q) => $q->whereIn('from_employee_id', $userIds)
            ->orWhereIn('to_employee_id', $userIds));
    }

    /** الفرعُ المحاسبي لعقدة العميل — أو `null` لعميلٍ بلا عقدة. */
    public static function clientBranchId(?int $nodeId): ?int
    {
        static $memo = [];

        if (! $nodeId) {
            return null;
        }

        return $memo[$nodeId] ??= Branch::findCached($nodeId)?->resolveAccountingBranchId();
    }

    /**
     * فروعُ العملاء المحاسبية — مفتاحُها معرّفُ العميل، والعميلُ بلا عقدةٍ يسقط.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, int>
     */
    public static function clientBranches(array $clientIds): array
    {
        return Client::whereIn('id', $clientIds)->pluck('branch_id', 'id')
            ->map(fn ($node) => static::clientBranchId($node ? (int) $node : null))
            ->filter()
            ->all();
    }

    /**
     * الفروعُ التي ينتمي إليها كلُّ مستخدم — مفتاحُها معرّفُ المستخدم.
     *
     * @return array<int, array<int, int>>
     */
    public static function userBranchIds(Collection $users): array
    {
        $employeeIds = $users->pluck('employee_id')->filter()->unique();

        $assigned = DB::table('employee_branch')->whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'branch_id'])
            ->groupBy('employee_id');
        $own = DB::table('employees')->whereIn('id', $employeeIds)->pluck('branch_id', 'id');

        return $users->mapWithKeys(fn (User $user) => [$user->id => collect($assigned[$user->employee_id] ?? [])
            ->pluck('branch_id')
            ->push($own[$user->employee_id] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all()])->all();
    }

    /** هل ينتمي موظفٌ بهذه الفروع إلى هذا الفرع (هو أو عقدةٌ تحته)؟ */
    public static function coversBranch(array $userBranchIds, int $branchId): bool
    {
        static $trees = [];

        /* الفرعُ نفسُه وما تحته **وما فوقه**: مندوبو الرياض مسندون إلى «منطقة
           الرياض» لا إلى «غرب الرياض» تحتها، فكان يُستبعد كلُّ مندوبٍ مسندٍ
           إلى المنطقة ولا يبقى مستلِمٌ لعميلٍ في فروعها (2026-09-26) */
        $trees[$branchId] ??= (function () use ($branchId) {
            $node = Branch::findCached($branchId);

            return $node
                ? $node->descendantBranchIds()->concat($node->ancestors()->pluck('id'))->push($branchId)
                    ->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [$branchId];
        })();

        return array_intersect($userBranchIds, $trees[$branchId]) !== [];
    }

    /**
     * زملاءُ الفرع الذين يملكون الاستلام — قائمةُ «الموظف المستلِم»: من
     * يشارك المسلِّمَ فرعاً من فروعه. مسلِّمٌ بلا فروعٍ لا يُقيَّد بها.
     *
     * @return Collection<int, User>
     */
    /**
     * زملاءُ فرع المسلِّم **الذين لا يستلمون** وسببُ كلٍّ منهم — ليعرف لماذا
     * غاب زميلُه عن القائمة بدل أن يختفي بصمت: حالتُه في شؤون الموظفين
     * («غير نشط» / «موقوف»…) أو أنّ دورَه بلا صلاحية الاستلام.
     *
     * @return Collection<int, array{name: string, why: string}>
     */
    public static function excludedBranchMates(User $sender): Collection
    {
        $mine = static::userBranchIds(collect([$sender]))[$sender->id] ?? [];

        if ($mine === []) {
            return collect();
        }

        $colleagues = User::query()
            ->where('id', '!=', $sender->id)
            ->whereNotNull('employee_id')
            ->with('employee:id,status')
            ->orderBy('name')
            ->get(['id', 'name', 'role_id', 'employee_id']);
        $theirs = static::userBranchIds($colleagues);
        $needsPermission = ClientSettingsHelper::invoiceTransferRequiresApproval();
        $active = \App\Enums\EmploymentStatus::activeValues();

        return $colleagues
            ->filter(fn (User $user) => array_intersect($theirs[$user->id] ?? [], $mine) !== [])
            ->map(function (User $user) use ($active, $needsPermission) {
                $status = $user->employee?->status;
                $statusValue = $status instanceof \BackedEnum ? $status->value : (int) $status;

                if (! in_array($statusValue, $active, true)) {
                    $label = \App\Enums\EmploymentStatus::tryFrom($statusValue)?->label() ?? 'غير معروفة';

                    return ['name' => $user->name, 'why' => "حالته «{$label}» في شؤون الموظفين"];
                }

                if ($needsPermission && ! $user->hasRolePermission(self::RECEIVE_PERMISSION)) {
                    return ['name' => $user->name, 'why' => 'دورُه لا يملك صلاحية الاستلام'];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    public static function branchMates(User $sender): Collection
    {
        $users = static::recipients($sender)->where('can_receive', true)->values();
        $mine = static::userBranchIds(collect([$sender]))[$sender->id] ?? [];

        if ($mine === []) {
            return $users;
        }

        $theirs = static::userBranchIds($users);

        return $users->filter(fn (User $user) => array_intersect($theirs[$user->id] ?? [], $mine) !== [])->values();
    }

    /** سببُ منع هذا الموظف من فواتير هؤلاء العملاء لأنه من غير فرعهم — أو `null`. */
    public static function branchBlock(User $user, array $clientIds): ?string
    {
        $branches = array_unique(array_values(static::clientBranches($clientIds)));
        $mine = static::userBranchIds(collect([$user]))[$user->id] ?? [];

        foreach ($branches as $branchId) {
            if (! static::coversBranch($mine, $branchId)) {
                $name = Branch::findCached($branchId)?->name ?? '#'.$branchId;

                return "الموظف «{$user->name}» ليس من فرع العميل ({$name}) — التسليم والاستلام بين موظفي فرع العميل فقط";
            }
        }

        return null;
    }

    /**
     * المستلِمون الممكنون لفواتير هؤلاء العملاء — {@see recipients()} مع:
     *
     *   `in_branch`       ينتمي إلى فرع كلِّ عميلٍ منهم
     *   `itinerary_date`  أقربُ زيارةٍ قادمة لأحدهم في خطّ سيره (أو `null`)
     *
     * مرتّبين: من في خطّ سيره أوّلاً ثم بالاسم.
     *
     * @param  array<int, int>  $clientIds
     * @return Collection<int, User>
     */
    public static function recipientsForClients(User $sender, array $clientIds): Collection
    {
        // «للموظف الموجود في فرعه»: زملاءُ فرع المسلِّم فقط، ثم فرعُ العميل فوقه
        $mates = static::branchMates($sender)->pluck('id')->all();
        $users = static::recipients($sender)->filter(fn (User $user) => ! $user->can_receive || in_array($user->id, $mates, true))->values();
        $branches = array_unique(array_values(static::clientBranches($clientIds)));
        $userBranches = static::userBranchIds($users);

        $itinerary = DB::table('employee_client_visits')
            ->whereIn('employee_id', $users->pluck('id'))
            ->whereIn('client_id', $clientIds ?: [0])
            ->where('visit_date', '>=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhereNotIn('visit_status', ['cancelled', 'completed', 'missed']))
            ->groupBy('employee_id')
            ->selectRaw('employee_id, MIN(visit_date) as next_date')
            ->pluck('next_date', 'employee_id');

        return $users->each(function (User $user) use ($branches, $userBranches, $itinerary) {
            $mine = $userBranches[$user->id] ?? [];
            $user->setAttribute('in_branch', collect($branches)->every(fn ($branchId) => static::coversBranch($mine, $branchId)));
            $user->setAttribute('itinerary_date', $itinerary[$user->id] ?? null);
        })->sortBy(fn (User $user) => ($user->itinerary_date ? '0'.$user->itinerary_date : '1').$user->name)->values();
    }

    /**
     * رصيدُ حساب كلِّ عميل في الأستاذ — مفتاحُه معرّفُ العميل.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, float>
     */
    public static function ledgerBalances(array $clientIds): array
    {
        return Account::ledger()->whereIn('client_id', $clientIds ?: [0])
            ->groupBy('client_id')
            ->selectRaw('client_id, SUM(balance) as total')
            ->pluck('total', 'client_id')
            ->map(fn ($total) => round((float) $total, 2))
            ->all();
    }

    /**
     * آخرُ من استلم رصيدَ كلِّ عميلٍ بتحويلٍ مقبول — مفتاحُه معرّفُ العميل.
     *
     * @return array<int, int>
     */
    private static function lastBalanceReceivers(array $clientIds): array
    {
        return InvoiceTransfer::where('kind', InvoiceTransfer::KIND_BALANCE)
            ->where('status', InvoiceTransfer::STATUS_ACCEPTED)
            ->whereIn('client_id', $clientIds ?: [0])
            ->orderBy('responded_at')->orderBy('id')
            ->get(['client_id', 'to_employee_id'])
            ->mapWithKeys(fn ($t) => [(int) $t->client_id => (int) $t->to_employee_id])
            ->all();
    }

    /**
     * العملاء الذين يملك المستخدم تسليم رصيدهم الآن: هو صاحبُ رصيدهم،
     * ورصيدُهم مدينٌ، ولا تسليمَ رصيدٍ معلّقٌ عليهم.
     *
     * لكلٍّ: `id` و`name` و`code` و`balance` (الأستاذ) و`invoice_due`
     * (مستحقّ الفواتير — للمقارنة، يختلف بالإشعارات الدائنة والافتتاحي).
     *
     * @param  array<int, int>|null  $onlyClientIds  حصرٌ على عملاء بعينهم
     * @return Collection<int, array<string, mixed>>
     */
    public static function balanceClientsOf(User $user, ?array $onlyClientIds = null): Collection
    {
        $assigned = $user->employee_id
            ? DB::table('client_employee')->where('employee_id', $user->employee_id)->pluck('client_id')
            : collect();
        $withInvoices = static::whereNetDue(Invoice::query())
            ->where(fn ($custody) => static::constrainCustody($custody, $user))
            ->whereNull('voided_at')
            ->distinct()
            ->pluck('client_id');
        $received = InvoiceTransfer::where('kind', InvoiceTransfer::KIND_BALANCE)
            ->where('status', InvoiceTransfer::STATUS_ACCEPTED)
            ->where('to_employee_id', $user->id)
            ->pluck('client_id');

        $candidates = $assigned->concat($withInvoices)->concat($received)
            ->filter()->map(fn ($id) => (int) $id)->unique();

        if ($onlyClientIds !== null) {
            $candidates = $candidates->intersect(array_map('intval', $onlyClientIds));
        }

        $candidates = $candidates->values()->all();
        $last = static::lastBalanceReceivers($candidates);
        $pending = InvoiceTransfer::where('kind', InvoiceTransfer::KIND_BALANCE)
            ->where('status', InvoiceTransfer::STATUS_PENDING)
            ->whereIn('client_id', $candidates ?: [0])
            ->pluck('client_id')->map(fn ($id) => (int) $id)->all();

        // من سُلِّم رصيدُه لغيري فليس لي، ولو بقيتُ مسؤولَه أو بقيت عندي فاتورته
        $mine = array_values(array_filter($candidates, fn ($id) => (! isset($last[$id]) || $last[$id] === (int) $user->id)
            && ! in_array($id, $pending, true)));

        $balances = static::ledgerBalances($mine);
        $invoiceDue = static::whereNetDue(Invoice::whereIn('client_id', $mine ?: [0]))
            ->whereNull('voided_at')
            ->where(fn ($type) => $type->whereNull('type')->orWhere('type', '!=', 'returned'))
            ->groupBy('client_id')
            ->selectRaw('client_id, SUM('.self::NET_DUE_SQL.') as due')
            ->pluck('due', 'client_id');
        $clients = Client::whereIn('id', $mine ?: [0])->get(['id', 'trade_name', 'code'])->keyBy('id');

        return collect($mine)
            ->filter(fn ($id) => ($balances[$id] ?? 0) > 0.009 && isset($clients[$id]))
            ->map(fn ($id) => [
                'id' => $id,
                'name' => $clients[$id]->trade_name,
                'code' => $clients[$id]->code,
                'balance' => $balances[$id],
                'invoice_due' => round((float) ($invoiceDue[$id] ?? 0), 2),
            ])
            ->sortBy('name')
            ->values();
    }

    /** هل المستخدم صاحبُ رصيد هذا العميل ويملك تسليمه الآن؟ */
    public static function holdsBalance(User $user, int $clientId): bool
    {
        return static::balanceClientsOf($user, [$clientId])->isNotEmpty();
    }

    /**
     * فواتيرُ المستخدم المستحقّة لهذا العميل — تنتقل مع رصيده حين يُستلَم.
     */
    public static function custodyInvoicesOf(User $user, int $clientId): Builder
    {
        return Invoice::query()
            ->where('client_id', $clientId)
            ->where(fn ($custody) => static::constrainCustody($custody, $user))
            ->whereRaw(self::NET_DUE_SQL.' > 0.009')
            ->whereNull('voided_at')
            ->where(fn ($type) => $type->whereNull('type')->orWhere('type', '!=', 'returned'));
    }

    /**
     * أيّامُ خطّ سير الموظف القريبة (من أسبوعٍ مضى إلى أسبوعين قادمين) —
     * لكلّ يومٍ عددُ عملائه. الموظفُ يختار يوماً من خطّ سيره هو فيُسلِّم
     * عملاءه كلَّهم أو بعضهم.
     *
     * @return Collection<int, array{date: string, clients: int}>
     */
    public static function itineraryDays(int $employeeUserId): Collection
    {
        return DB::table('employee_client_visits')
            ->where('employee_id', $employeeUserId)
            ->whereBetween('visit_date', [now()->subDays(7)->toDateString(), now()->addDays(14)->toDateString()])
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhere('visit_status', '!=', 'cancelled'))
            ->groupBy('visit_date')
            ->orderBy('visit_date')
            ->selectRaw('visit_date, COUNT(DISTINCT client_id) as clients')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->visit_date, 'clients' => (int) $row->clients]);
    }

    /**
     * عملاءُ خطّ سير المسلِّم نفسِه في يومٍ، بترتيب زياراته.
     *
     * @return array<int, int>
     */
    public static function itineraryClientIds(User $sender, string $date): array
    {
        return DB::table('employee_client_visits')
            ->where('employee_id', $sender->id)
            ->whereDate('visit_date', $date)
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhere('visit_status', '!=', 'cancelled'))
            ->orderByRaw('sequence IS NULL, sequence')
            ->orderBy('id')
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * كلُّ عملائي الذين عندي لهم شيءٌ يُسلَّم: رصيدُهم في عهدتي أو لي عندهم
     * فاتورةٌ مستحقّة — مرتّبين بالاسم.
     *
     * @return array<int, int>
     */
    public static function myClientIds(User $sender): array
    {
        $byBalance = static::balanceClientsOf($sender)->pluck('id');
        $byInvoice = static::whereNetDue(static::transferableInvoices($sender))->distinct()->pluck('client_id');

        return Client::whereIn('id', $byBalance->concat($byInvoice)->unique()->values()->all() ?: [0])
            ->orderBy('trade_name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * ما يملك المسلِّم تسليمَه لكلّ عميلٍ من هؤلاء (من خطّ سيره أو كلُّ عملائه):
     *
     *   `balance`         رصيدُ حساب العميل (الأستاذ)
     *   `invoice_due`     مستحقُّ فواتيره كلِّها — يختلف بالإشعارات الدائنة والافتتاحي
     *   `my_balance`      رصيدُه في عهدة المسلِّم فيُسلَّم «رصيداً»
     *   `my_invoice_ids`  فواتيرُ المسلِّم المستحقّة له فتُسلَّم «فواتير»
     *   `outside`         اسمُ فرع العميل إن كان المستلِمُ المختار خارجه — أو `null`
     *
     * @param  array<int, int>  $clientIds  بالترتيب المطلوب عرضُه
     * @return Collection<int, array<string, mixed>>
     */
    public static function sendableClients(User $sender, array $clientIds, ?User $receiver = null): Collection
    {
        if ($clientIds === []) {
            return collect();
        }

        $clients = Client::whereIn('id', $clientIds)->get(['id', 'trade_name', 'code'])->keyBy('id');
        $balances = static::ledgerBalances($clientIds);
        $myBalances = static::balanceClientsOf($sender, $clientIds)->keyBy('id');
        $invoiceDue = static::whereNetDue(Invoice::whereIn('client_id', $clientIds))->whereNull('voided_at')
            ->where(fn ($type) => $type->whereNull('type')->orWhere('type', '!=', 'returned'))
            ->groupBy('client_id')->selectRaw('client_id, SUM('.self::NET_DUE_SQL.') as due')
            ->pluck('due', 'client_id');
        $myInvoices = static::whereNetDue(static::transferableInvoices($sender))
            ->whereIn('client_id', $clientIds)
            ->select(['id', 'client_id'])
            ->selectRaw(self::NET_DUE_SQL.' as due_value')
            ->get()
            ->groupBy('client_id');

        // المستلِمُ من فرع كلِّ عميل — يُعلَّم العميلُ الذي خارجه
        $branches = $receiver ? static::clientBranches($clientIds) : [];
        $receiverBranches = $receiver ? (static::userBranchIds(collect([$receiver]))[$receiver->id] ?? []) : [];

        return collect($clientIds)->filter(fn ($id) => isset($clients[$id]))->values()->map(function (int $id, int $i) use (
            $clients, $balances, $myBalances, $invoiceDue, $myInvoices, $branches, $receiverBranches, $receiver
        ) {
            $mine = $myInvoices->get($id, collect());
            $branchId = $branches[$id] ?? null;

            return [
                'id' => $id,
                'order' => $i + 1,
                'name' => $clients[$id]->trade_name,
                'code' => $clients[$id]->code,
                'balance' => round((float) ($balances[$id] ?? 0), 2),
                'invoice_due' => round((float) ($invoiceDue[$id] ?? 0), 2),
                'my_balance' => $myBalances->has($id),
                'my_invoice_ids' => $mine->pluck('id')->values()->all(),
                'my_invoice_due' => round((float) $mine->sum('due_value'), 2),
                'outside' => $receiver && $branchId && ! static::coversBranch($receiverBranches, $branchId)
                    ? (Branch::findCached($branchId)?->name ?? '#'.$branchId)
                    : null,
            ];
        });
    }

    private static function constrainCustody(Builder $query, User $user): void
    {
        if ($user->employee_id) {
            $query->where('employee_id', $user->employee_id)
                ->orWhere(fn ($own) => $own->whereNull('employee_id')->where('created_by', $user->id));

            return;
        }

        $query->whereNull('employee_id')->where('created_by', $user->id);
    }
}
