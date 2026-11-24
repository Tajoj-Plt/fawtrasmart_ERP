<?php

namespace Modules\Pos\Reports;

use App\Models\Branch;
use App\Models\Category;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;

/**
 * أساسُ تقارير نقاط البيع — الفترةُ والفلاترُ والنطاقُ والتصدير، مرّةً واحدة.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * `ReportsController` بلغ 2,091 سطراً في ستّة تقارير، وكلٌّ منها يُعيد كتابة
 * الشيء نفسِه:
 *
 *   • **الفترة**: `Carbon::now()->subDays(30)` مكتوبةً في الشيفرة — ستَّ
 *     مرّات. فلا علاقةَ لما يعرضه التقرير بالفترة المحاسبية التي تعمل
 *     المنشأة عليها، ومقارنتُه بميزان المراجعة تُخرج رقمين لا يلتقيان.
 *
 *   • **الفلاتر السبعة** (الجلسة، التصنيف، المخزن، الموظف، الفرع، التاريخ،
 *     التجميع): مكرّرةٌ حرفياً في كل تقرير.
 *
 *   • **النطاق**: لا شيء. `ReportsController` كان **بلا أيّ فحص صلاحية
 *     إطلاقاً** — لا middleware ولا `hasPermission`. فتقاريرُ الربحية،
 *     بتكلفة كلِّ صنفٍ وهامشه، كانت مفتوحةً لأيّ مستخدمٍ مصادقٍ عليه. وكذلك
 *     لا حصرَ بفرع: مديرُ فرعٍ يرى أرقام الفروع كلِّها.
 *
 *   • **المرتجعات**: تُحتسب بـ`type = 'returned'` وحدها، فتدخل مرتجعاتُ
 *     وحدة المبيعات العادية في صافي مبيعات نقطة البيع. رقمٌ مغلوطٌ منهجياً.
 *
 * ═════════════════════ ما صار ═════════════════════
 *
 * كلُّ تقريرٍ يرث هذا الأساس ويصف نفسَه بثلاثة أشياء: استعلامُه الأساس،
 * وأعمدةُ تجميعه، وأعمدةُ تصديره. والباقي هنا.
 */
abstract class AbstractPosReport
{
    use RespectsAccountingPeriods;
    use ScopesToPosAccess;
    use UsesDocumentCurrency;

    public function __construct(protected Request $request) {}

    /* ═══════════════ ما يصفه كل تقرير ═══════════════ */

    /** مفتاحُ التقرير — للعنوان والتصدير. */
    abstract public function key(): string;

    abstract public function title(): string;

    /** الصلاحيةُ اللازمة لرؤيته. */
    public function permission(): string
    {
        return 'pos_view_reports';
    }

    /** أوجهُ التجميع المتاحة: مفتاح => تسمية. */
    abstract public function groupings(): array;

    /** بناءُ النتائج. */
    abstract public function rows(): Collection;

    /** أعمدةُ التصدير: تسمية => مفتاحُ الحقل في الصف. */
    abstract public function exportColumns(): array;

    /**
     * الإجمالياتُ للعرض: تسميةٌ عربية ونوعُ تنسيق.
     *
     * ═══════════ ما كان ═══════════
     *
     * الشاشةُ تطبع مفاتيحَ `totals` كما هي:
     *
     *     invoices_count · net_amount · margin_percent
     *
     * أسماءُ حقولٍ إنجليزيةٌ تحت أرقامٍ عربية، ونسبةُ الهامش تُعرض «12.50»
     * بلا علامة مئوية فتُقرأ ريالاً. والمستخدمُ لا يعرف ما «rows_count».
     *
     * ═══════════ والخريطة ═══════════
     *
     * موضعٌ واحدٌ لكل مفاتيح التقارير الستّة. وما لا تسميةَ له يُعرض بمفتاحه
     * — فالمفتاحُ الجديد يظهر ولا يختفي، ويُلاحَظ فيُسمّى.
     *
     * النوعُ يحسم التنسيق: `money` بمنزلتين ورمزِ العملة، و`qty` بثلاث،
     * و`percent` بعلامة، و`count` صحيحاً بلا كسور.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public function totalLabels(): array
    {
        return [
            'invoices_count' => ['عدد الفواتير', 'count'],
            'payments_count' => ['عدد الدفعات', 'count'],
            'sessions_count' => ['عدد الجلسات', 'count'],
            'returns_count' => ['عدد المرتجعات', 'count'],
            'unconfirmed_sessions' => ['جلسات لم تُؤكَّد', 'count'],
            'variance_sessions' => ['جلسات بفروقات', 'count'],
            'estimated_rows' => ['صفوف بتكلفة تقديرية', 'count'],

            'total_quantity' => ['إجمالي الكمية', 'qty'],
            'returns_quantity' => ['كمية المرتجعات', 'qty'],
            'net_quantity' => ['صافي الكمية', 'qty'],

            'total_amount' => ['إجمالي المبيعات', 'money'],
            'returns_amount' => ['المرتجعات', 'money'],
            'net_amount' => ['صافي المبيعات', 'money'],
            'net_revenue' => ['صافي الإيراد', 'money'],
            'net_cost' => ['صافي التكلفة', 'money'],
            'profit' => ['الربح', 'money'],
            'cash_amount' => ['التحصيل نقداً', 'money'],
            'card_amount' => ['التحصيل بالبطاقة', 'money'],
            'change_amount' => ['الباقي المُعاد', 'money'],
            'settled_amount' => ['المُسوّى', 'money'],
            'unsettled_amount' => ['غير المُسوّى', 'money'],
            'expected_total' => ['النقد المتوقَّع', 'money'],
            'actual_total' => ['النقد الفعلي', 'money'],
            'over_total' => ['فائض', 'money'],
            'short_total' => ['عجز', 'money'],
            'net_variance' => ['صافي الفرق', 'money'],
            'variance_total' => ['إجمالي الفروقات', 'money'],

            'margin_percent' => ['الهامش', 'percent'],
            'variance_rate' => ['نسبة الجلسات ذات الفرق', 'percent'],
        ];
    }

    /* ═══════════════ التنفيذ ═══════════════ */

    /**
     * ناتجُ التقرير كاملاً — للشاشة وللتصدير.
     *
     * @return array{rows: Collection, totals: array, filters: array, period: array, group_by: string}
     */
    public function run(): array
    {
        $this->authorize();

        $rows = $this->rows();

        return [
            'rows' => $this->sort($rows),
            'totals' => $this->totals($rows),
            'filters' => $this->filterOptions(),
            'applied' => $this->appliedFilters(),
            'period' => $this->scope(),
            'group_by' => $this->groupBy(),
            'groupings' => $this->groupings(),
            'currency' => $this->filterCurrency($this->request),
            'symbol' => $this->currencySymbol($this->filterCurrency($this->request)),
        ];
    }

    protected function authorize(): void
    {
        if (! hasPermission($this->permission()) && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بعرض هذا التقرير.');
        }
    }

    /** نطاقُ التقرير — الفترةُ المحاسبية أصلاً، والمدى الحرّ حين يُطلب. */
    protected function scope(): array
    {
        return $this->posReportScope($this->request);
    }

    protected function groupBy(): string
    {
        $requested = $this->request->input('group_by');

        return array_key_exists($requested, $this->groupings())
            ? $requested
            : array_key_first($this->groupings());
    }

    /* ═══════════════ الاستعلام الأساس ═══════════════ */

    /**
     * استعلامُ فواتير نقطة البيع بالفلاتر والنطاق.
     *
     * @param  string  $type  `pos` للمبيعات، `returned` للمرتجعات
     */
    protected function invoiceQuery(string $type = 'pos')
    {
        $query = DB::table('invoices')
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('pos_sessions', 'invoices.session_id', '=', 'pos_sessions.id')
            ->leftJoin('cashier_devices', 'pos_sessions.device_id', '=', 'cashier_devices.id')
            ->where('invoices.type', $type)
            /*
                الملغاةُ خارج كل تقرير.

                فاتورةٌ أُلغيت لم تقع — وحسابُها في المبيعات يُضخّمها بمبلغٍ
                لم يتحرّك، ويُضخّم معه متوسّطَ الفاتورة وعددَ المعاملات.
            */
            ->whereNull('invoices.voided_at');

        /*
            **المرتجعُ يجب أن يكون مرتجعَ نقطة بيع.**

            كان الفلترُ `type = 'returned'` وحده، فمرتجعاتُ وحدة المبيعات
            العادية — وهي بلا `session_id` — تُطرح من مبيعات نقطة البيع.
            والشرطُ هنا يقصرها على ما وُلد من جلسة.
        */
        if ($type === 'returned') {
            $query->whereNotNull('invoices.session_id');
        }

        $this->applyDateScope($query);
        $this->applyVisibilityScope($query);
        $this->applyFilters($query);

        return $query;
    }

    /**
     * حصرُ المدى الزمني.
     *
     * الفترةُ **علاقةٌ** حين تكون متاحة (`accounting_period_id` مختومٌ على
     * الفاتورة)، ومدًى تاريخيّ حين يكتبه المستخدم صراحةً.
     */
    protected function applyDateScope($query): void
    {
        /*
            ═══════════ ما كان ═══════════

                $query->where('invoices.accounting_period_id', $scope['period']['period_id']);

            و`period_id` **ليس رقمَ فترةٍ دائماً**. حين يختار المستخدم سنةً
            كاملة — وهو الاختيار الافتراضي — تكون قيمتُه النصَّ `"year:55"`،
            وفتراتُها الاثنتا عشرة في `period_ids`.

            فيصير الشرطُ `accounting_period_id = 'year:55'` ولا يطابق صفّاً
            واحداً. **التقاريرُ الستّة كلُّها تعود فارغةً** تحت الاختيار
            الافتراضي — بلا خطأٍ يظهر، فقط جدولٌ خاوٍ يُقرأ على أنه «لا
            مبيعات». وقد تكرّر العطلُ نفسُه في أربعة ملفّات.

            والقاعدةُ الآن في `scopeReportPeriod()` وحدها.
        */
        $this->scopeReportPeriod($query, $this->scope(), 'invoices.accounting_period_id', 'invoices.invoice_date');
    }

    /**
     * نطاقُ الرؤية — الفرعُ أوّلاً.
     *
     * `pos_branch_id` عمودٌ مختومٌ على الفاتورة لحظةَ البيع، فلا حاجةَ
     * للوصول إلى الفرع بقفزتين عبر الجلسة والجهاز في كل استعلام.
     */
    protected function applyVisibilityScope($query): void
    {
        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('invoices.pos_branch_id', $branchIds ?: [0]);
        }

        // من لا يرى إلّا جلساته لا يرى أرقام غيره — بنفس قاعدة الشاشة
        $this->scopeToVisibleUsers($query, 'pos_sessions.user_id');
    }

    /** الفلاترُ التي يكتبها المستخدم. */
    protected function applyFilters($query): void
    {
        $r = $this->request;

        $query
            ->when($r->filled('session_id'), fn ($q) => $q->where('invoices.session_id', $r->session_id))
            ->when($r->filled('branch_id'), fn ($q) => $q->where('invoices.pos_branch_id', $r->branch_id))
            ->when($r->filled('device_id'), fn ($q) => $q->where('pos_sessions.device_id', $r->device_id))
            ->when($r->filled('shift_id'), fn ($q) => $q->where('pos_sessions.shift_id', $r->shift_id))
            ->when($r->filled('employee_id'), fn ($q) => $q->where('pos_sessions.user_id', $r->employee_id))
            ->when($r->filled('store_house_id'), fn ($q) => $q->where('invoice_items.store_house_id', $r->store_house_id))
            ->when($r->filled('client_id'), fn ($q) => $q->where('invoices.client_id', $r->client_id))
            ->when($this->filterCurrency($r), fn ($q, $c) => $q->where('invoices.currency', $c));
    }

    /**
     * دمجُ المبيعات بالمرتجعات على مفتاحٍ مشترك.
     *
     * كان كلُّ تقريرٍ يكتب هذا الدمج بيده بثلاث صيغٍ مختلفة (`keyBy`،
     * و`groupBy` ثم `first()`، ومقارنةُ نصوص) — وفي إحداها كانت الكميةُ
     * المرتجعة تُقرأ من أول صفٍّ في المجموعة فقط، فتُهمَل بقيّةُ الصفوف.
     */
    /**
     * تسميةُ الصف — من `dim_id` حين لا يُختار `dim_label` معه.
     *
     * ═══════ لماذا لا يُختار معه دائماً؟ ═══════
     *
     * هذه النسخةُ من MySQL ترفض تحت `ONLY_FULL_GROUP_BY` أن يُختار
     * `DATE(col)` مع `GROUP BY DATE(col)` — حتى في أبسط استعلامٍ معزول،
     * فهي لا تُقرّ بأن التعبير المُختار هو نفسُ تعبير التجميع.
     *
     * والحلُّ المحمول: التجميعُ بالاسم المستعار (`GROUP BY dim_id`) — يعمل
     * على MySQL وMariaDB وsqlite معاً — واختيارُ التعبير مرّةً واحدة. فتبقى
     * التسميةُ تُملأ هنا.
     */
    protected function ensureLabels($rows)
    {
        return $rows->map(function ($row) {
            if (! isset($row->dim_label) || $row->dim_label === null) {
                $row->dim_label = $row->dim_id;
            }

            return $row;
        });
    }

    protected function mergeReturns($sales, $returns, callable $keyOf)
    {
        $sales = $this->ensureLabels($sales);

        $returnsByKey = $returns->keyBy($keyOf);

        return $sales->map(function ($row) use ($returnsByKey, $keyOf) {
            $match = $returnsByKey->get($keyOf($row));

            $row->returns_count = (int) ($match->returns_count ?? 0);
            $row->returns_quantity = (float) ($match->returns_quantity ?? 0);
            $row->returns_amount = (float) ($match->returns_amount ?? 0);

            $row->net_quantity = (float) ($row->total_quantity ?? 0) - $row->returns_quantity;
            $row->net_amount = (float) ($row->total_amount ?? 0) - $row->returns_amount;

            return $row;
        });
    }

    /** صيغةُ الكمية بالوحدة الأساسية — الصيغةُ نفسُها في كل تقرير. */
    protected function baseQuantityExpression(): string
    {
        return 'SUM(CAST(invoice_items.quantity AS DECIMAL(18,4)) * COALESCE(invoice_items.conversion_factor, 1))';
    }

    protected function sort($rows)
    {
        $sortBy = $this->request->input('sort_by', 'net_amount');
        $direction = $this->request->input('sort_direction', 'desc');

        // ترتيبٌ بحقلٍ غير موجود يُرجع نتيجةً عشوائية بلا خطأ
        $first = $rows->first();

        if ($first && ! property_exists($first, $sortBy) && ! isset($first->{$sortBy})) {
            $sortBy = 'net_amount';
        }

        return $direction === 'asc'
            ? $rows->sortBy($sortBy)->values()
            : $rows->sortByDesc($sortBy)->values();
    }

    protected function totals($rows): array
    {
        return [
            'invoices_count' => (int) $rows->sum('invoices_count'),
            'total_quantity' => round((float) $rows->sum('total_quantity'), 3),
            'total_amount' => round((float) $rows->sum('total_amount'), 2),
            'returns_count' => (int) $rows->sum('returns_count'),
            'returns_quantity' => round((float) $rows->sum('returns_quantity'), 3),
            'returns_amount' => round((float) $rows->sum('returns_amount'), 2),
            'net_quantity' => round((float) $rows->sum('net_quantity'), 3),
            'net_amount' => round((float) $rows->sum('net_amount'), 2),
            'rows_count' => $rows->count(),
        ];
    }

    /* ═══════════════ خيارات الفلاتر ═══════════════ */

    /**
     * قوائمُ الفلاتر — مقصورةً على ما للمستخدم رؤيتُه.
     *
     * وكانت تُبنى بأربعة استعلاماتٍ من نوع
     * `DB::table('invoices')->distinct()->pluck(...)` ثم استعلامٍ ثانٍ لكل
     * منها — ثمانيةُ استعلاماتٍ لملء أربع قوائم منسدلة.
     */
    protected function filterOptions(): array
    {
        $branchIds = $this->accessibleBranchIds();
        $userIds = $this->visibleUserIds();

        return [
            /*
                ═══════════ قوائمُ الفلاتر تتبع القاعدة نفسَها ═══════════

                كانت الصفوفُ مقيَّدةً بشجرة الإدارة، **والمنسدلاتُ فوقها
                مفتوحة**: الجلساتُ والأجهزةُ بالفرع وحده، والموظفون
                والمخازنُ بلا أيّ قيد.

                فيقرأ الكاشيرُ في «الكاشير» أسماءَ زملائه كلِّهم، وفي
                «الجلسة» أرقامَ جلساتهم، وفي «المخزن» مخازنَ لا يبلغها. ولا
                يحتاج أن ينقر: القائمةُ نفسُها هي التسريب — من فيها، وكم
                عددُهم، ومتى عملوا.

                والصفوفُ تبقى محروسةً على كل حال، فالاختيارُ لا يُخرج له
                بيانات. لكنّ **وجودَ الاسم في القائمة معلومة**.

                والقاعدةُ واحدة: `visibleUserIds()` — `null` لغير الموظّف
                ولمن يملك `data_scope_all`، وإلّا هو ومن يديرهم.
            */
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('name')->get(['id', 'name']),

            // أجهزتي وأجهزةُ مرؤوسيّ — وغيرُ المُسنَد، فليس عهدةَ أحد
            'devices' => $this->accessibleDevices()->get(['id', 'device_name']),

            'sessions' => $this->scopeToVisibleUsers(PosSession::query())
                ->with('user:id,name')
                ->latest('started_at')->limit(200)
                ->get(['id', 'session_number', 'user_id', 'started_at']),

            'employees' => User::whereNotNull('employee_id')
                ->when($userIds !== null, fn ($q) => $q->whereIn('id', $userIds))
                ->orderBy('name')->get(['id', 'name']),

            /*
                التصنيفاتُ تبقى مفتوحة: كتالوجُ أصنافٍ لا بياناتِ موظّف —
                كقائمة العملات. وحجبُها يُخفي تصنيفاً باع منه الكاشيرُ فعلاً.
            */
            'categories' => Category::orderBy('name')->get(['id', 'name']),

            'storeHouses' => $this->accessibleStorehouses(['id', 'name'])->get(),

            'currencies' => $this->activeCurrencies(),
        ];
    }

    /** ما اختاره المستخدم فعلاً — لعرضه فوق التقرير ولحمله في التصدير. */
    protected function appliedFilters(): array
    {
        return array_filter($this->request->only([
            'session_id', 'branch_id', 'device_id', 'shift_id', 'employee_id',
            'store_house_id', 'client_id', 'category_id', 'currency',
            'date_from', 'date_to', 'group_by', 'sort_by', 'sort_direction',
        ]), fn ($v) => $v !== null && $v !== '');
    }

    /* ═══════════════ التصدير ═══════════════ */

    /**
     * تصديرُ CSV — بثًّا لا تجميعاً.
     *
     * وتُفحَص صلاحيةُ التصدير مستقلّةً عن صلاحية العرض: من يقرأ تقريراً على
     * الشاشة ليس بالضرورة من يُخرجه ملفّاً.
     */
    public function export()
    {
        $this->authorize();

        if (! hasPermission('pos.reports.export') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بتصدير التقارير.');
        }

        $result = $this->run();
        $columns = $this->exportColumns();

        $filename = $this->key().'_'.now()->format('Y_m_d_His').'.csv';

        return response()->stream(function () use ($result, $columns) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            // ترويسةٌ تحمل نطاق التقرير — ملفٌّ بلا فترةٍ لا يُقرأ بعد أسبوع
            fputcsv($file, [$this->title()]);
            fputcsv($file, ['الفترة', $result['period']['start'] ?? 'كل الفترات', 'إلى', $result['period']['end'] ?? '']);
            fputcsv($file, ['العملة', $result['currency'] ?? 'الكل']);
            fputcsv($file, []);

            fputcsv($file, array_keys($columns));

            foreach ($result['rows'] as $row) {
                fputcsv($file, array_map(
                    fn ($field) => is_callable($field) ? $field($row) : ($row->{$field} ?? ''),
                    array_values($columns)
                ));
            }

            fputcsv($file, []);
            fputcsv($file, ['الإجماليات']);

            foreach ($result['totals'] as $label => $value) {
                fputcsv($file, [$label, $value]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
