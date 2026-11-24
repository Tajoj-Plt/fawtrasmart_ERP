<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Constants\SystemAccountIds;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\PosGeneralSetting;
use App\Models\StoreHouse;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Pos\Http\Controllers\Concerns\CalculatesPosTax;
use Modules\Pos\Http\Controllers\Concerns\RendersPosDocumentTemplates;
use Modules\Pos\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosSettings;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;

/**
 * إعداداتُ نقطة البيع.
 *
 * ═════════════════════ أخطرُ ما كان ═════════════════════
 *
 *     if (!canAny([
 *         'points_sale_open_sessions_all',
 *         'points_sale_open_sessions_own',
 *         ...
 *     ])) {
 *         abort(403, 'غير مصرح لك بإدارة جلسات نقاط البيع');
 *     }
 *
 * هذا المتحكّم يعدّل **الحساباتِ المحاسبية الافتراضية والبنكَ الافتراضي
 * وطرقَ الدفع وقالبَ الفاتورة** — وكان محروساً بصلاحيات **الجلسات**.
 *
 * فأيُّ كاشير يملك `points_sale_open_sessions_own` — وهي أدنى صلاحيةٍ في
 * الوحدة، يملكها كلُّ من يبيع — كان يستطيع تغييرَ حساب البنك الذي تُرحَّل
 * إليه كلُّ مبيعات الشبكة. حتى الرسالةُ نفسُها كانت تقول «إدارة جلسات».
 *
 * والصلاحيةُ الصحيحة `pos_manage_settings` كانت معرَّفةً في الكتالوج
 * **ولا تُستدعى في سطرٍ واحد**.
 *
 * ═════════════════════ والتوريث ═════════════════════
 *
 * الإعداداتُ كانت صفّاً واحداً عالمياً (`first()`، وفي موضعٍ `find(1)`).
 * وصارت تقبل صفّاً لكل فرع، مع صفٍّ عامٍّ يرثه من لا صفَّ له — انظر
 * [ResolvesPosSettings] لتفصيل التعارض مع إعدادات الفروع.
 */
class SettingsController extends Controller
{
    use CalculatesPosTax;
    use RendersPosDocumentTemplates;
    use ResolvesAccountingAccounts;
    use ResolvesPosSettings;
    use ScopesToPosAccess;
    use UsesDocumentCurrency;

    public function __construct()
    {
        /*
            ═══════════════ بوّابةُ الوحدة ═══════════════

            الدخولُ لمن يملك **أيَّ** صلاحيةِ نقاط بيع، لا لمن يملك صلاحيةً
            بعينها.

            كان الفحصُ بصلاحيةٍ واحدة، وهو يقلب المقصود: كاشيرٌ يملك البيعَ
            وحده يُردّ من باب الشاشة التي هي عمله، ومشرفٌ يملك التقارير
            وحدها كذلك. والحراسةُ الدقيقة موضعُها الدالّة لا الباب.

            والبادئاتُ ثلاثٌ لا واحدة (`pos.` و`pos_` و`points_sale_`):
            إغفالُ إحداها يردّ كلَّ من صلاحياتُه بالنمط القديم.
        */
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            $allowed = collect(PosPermissions::PREFIXES)
                ->contains(fn ($prefix) => $user?->hasAnyPermissionWithPrefix($prefix));

            if (! $allowed) {
                abort(403, 'لا تملك أي صلاحية على وحدة نقاط البيع.');
            }

            return $next($request);
        });

        /*
            ═══════════════ حراسةُ كل دالّة ═══════════════

            مطابقةٌ حرفياً لما يفرضه `Modules/Pos/routes/web.php`. والتكرارُ
            مقصود: الحارسُ على المسار وحده يسقط متى نُودي المتحكّمُ من مسارٍ
            جديد يُنسى تزويدُه، أو من وحدةٍ أخرى تستدعي الدالّة مباشرةً.
        */
        $this->middleware('permission.role:pos_manage_settings', ['only' => [
            'index',
            'general',
            'store',
            'getActivePaymentMethods',
            'saveActivePaymentMethods',
            'getCategoriesByType',
        ]]);
    }

    public function index()
    {
        return view('pos::settings.index', [
            'branches' => $this->branchOptions(),
        ]);
    }

    public function general(Request $request)
    {
        // فرعٌ محدَّد أو الإعدادُ العام — ومن لا يختار الفرعَ يرى فرعَه وحده
        $branchId = $this->resolveSettingsBranch($request);

        $posSettings = PosGeneralSetting::forBranch($branchId);

        return view('pos::settings.general', [
            'posSettings' => $posSettings,
            'branchId' => $branchId,
            'branches' => $this->branchOptions(),
            'canSelectBranch' => $this->canSelectBranch(),

            // العملاءُ المرتبطون بحسابٍ فقط — عميلٌ بلا حساب يكسر قيدَ الفاتورة
            'customers' => Client::whereHas('account')->orderBy('trade_name')->get(['id', 'trade_name']),

            /*
                المستودعاتُ المتاحةُ لنقطة البيع — مع حسابها المحاسبي.

                يُعرض الحسابُ بجانب الاسم لأنّ اختيارَ المستودع **يختار
                حسابَ المخزون معه**: هو الطرفُ الدائن في كل قيد تكلفة.
                ومستودعٌ بلا حسابٍ يوقف الترحيل، فيُعلَّم في القائمة قبل
                أن يُكتشف عند أوّل فاتورة.
            */
            'storeHouses' => $this->storeHouseOptions(),

            /*
                ═══════════ الفروعُ التي لا يسري عليها هذا الصفّ ═══════════

                `PosGeneralSetting::forBranch()` تُرجع **صفَّ الفرع كاملاً**
                إن وُجد، ولا تنزل إلى العام. فمسؤولٌ يفتح «الإعداد العام»
                ويختار مستودعاً ويحفظ ويراه محفوظاً — بينما كلُّ فرعٍ له
                صفُّه الخاصّ يمضي على مستودعه القديم، وشاشةُ البيع تعرض
                كمّياتِ مخزنٍ آخر بلا ما يدلّ على السبب.

                وهو أسوأُ من ألّا يُحفظ: الشاشةُ تَعِد بضبطٍ لا أثرَ له.
                فتُعرَض القائمةُ صراحةً مع ما يُطبَّق فعلاً في كلٍّ منها.
            */
            'branchOverrides' => $this->branchOverrides(),
            'settingsAreOwn' => $branchId !== null
                && (int) ($posSettings->branch_id ?? 0) === (int) $branchId,
            'paymentMethods' => $this->activePaymentMethods(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'taxes' => $this->availableTaxes(),
            'currencies' => $this->activeCurrencies(),
            'defaultCurrency' => $this->defaultCurrency(),

            /*
                قوالبُ الطباعة المتاحة — لاختيار الافتراضي ومعاينته.

                النوعان مختلفان: الفاتورةُ `simplified_invoice` (فاتورةٌ
                ضريبية مبسَّطة في اصطلاح الهيئة)، والمرتجعُ `sales_return`.
                فقالبُ أحدهما لا يصلح للآخر، ولذلك قائمتان لا واحدة.
            */
            'invoiceTemplates' => $this->posTemplates(self::TEMPLATE_INVOICE),
            'returnTemplates' => $this->posTemplates(self::TEMPLATE_RETURN),

            /*
                ═══════════ الحسابات: تُحرَّر هنا وتُحفَظ هناك ═══════════

                كان القسمُ يعرض ثلاثةَ أعمدةٍ خاصّةٍ بنقطة البيع
                (`profit_account_id` و`loss_account_id`
                و`default_bank_account_id`) — واثنان منها **لا يُقرآن في أيّ
                مكان من النظام**: عمودان ميتان يضبطهما المستخدم فلا يُنتجان
                أثراً.

                وفي الوقت نفسه كانت الحساباتُ الحقيقيةُ التي تُبنى عليها قيودُ
                نقطة البيع — المبيعاتُ ومردوداتُها والتكلفةُ والضريبةُ والخزينة
                وفرقُ الدرج — تُضبط في شاشةٍ أخرى، ولا يصل بينهما إلا سطرُ
                تنبيهٍ يقول «تُضبط هناك».

                فصار القسمُ يعرض **مفاتيح `accounting_settings` نفسَها**
                قابلةً للتحرير: التعديلُ هنا يكتب في الجدول ذاته الذي تقرأه
                شاشةُ «الحسابات المحاسبية» وتقرأه مولّداتُ القيود — مصدرٌ
                واحد لا نسختان تفترقان.
            */
            'accountKeys' => $this->posAccountKeys(),
            'accountOptions' => $this->postableAccountOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'default_customer_id' => 'nullable|exists:clients,id',

            /*
                مستودعُ نقطة البيع — يُقرأ منه المعروضُ ويُخصم منه المباع،
                وحسابُه (`accounts.storehouse_id`) هو الطرفُ الدائن في قيد
                تكلفة المبيعات. كان يُشتقّ من مخزون المندوب
                (`default_warehouses`) — انظر [PosCustody].
            */
            'store_house_id' => 'nullable|exists:store_houses,id',
            /*
                معرّفُ قالبٍ من `document_templates`، أو `default` للافتراضي
                المعيَّن في شاشة القوالب، أو `builtin` لقالب النظام المدمج.

                كانت `in:thermal,electronic` — وهما وصفَا شكلٍ لا قالبان،
                ولا يقرؤهما سطرٌ واحد في الطباعة.
            */
            'invoice_template' => 'required|string|max:32',
            'return_template' => 'nullable|string|max:32',
            'active_payment_method_ids' => 'nullable|array',
            'active_payment_method_ids.*' => 'exists:payment_methods,id',
            'default_payment_method_id' => 'nullable|exists:payment_methods,id',
            'allowed_categories_type' => 'nullable|in:all,only,except',
            'allowed_categories_ids' => 'nullable|array',
            'allowed_categories_ids.*' => 'exists:categories,id',
            // مفاتيحُ «الحسابات المحاسبية» — تُحفظ في جدولها لا في أعمدة
            // نقطة البيع. والمفاتيحُ مقيَّدةٌ بالكتالوج فلا يُكتب مفتاحٌ
            // اخترعه الطلب.
            /*
                حساباتُ نقاط البيع — أعمدةٌ في `pos_general_settings` لا
                مفاتيحُ في الجدول المشترك. وكلٌّ يُبنى اسمُه من الكتالوج
                فلا يُكتب عمودٌ اخترعه الطلب.
            */
            'sales_account_id' => 'nullable|exists:accounts,id',
            'sales_returns_account_id' => 'nullable|exists:accounts,id',
            'cost_of_sales_account_id' => 'nullable|exists:accounts,id',
            'discount_account_id' => 'nullable|exists:accounts,id',
            'vat_account_id' => 'nullable|exists:accounts,id',
            'default_bank_account_id' => 'nullable|exists:accounts,id',
            'cash_over_account_id' => 'nullable|exists:accounts,id',
            'cash_short_account_id' => 'nullable|exists:accounts,id',
            // حدُّ الخصم — لم يكن له عمودٌ أصلاً، فالخصمُ كان بلا سقف
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',

            // ضوابطُ التشغيل — كانت مفاتيحَ فرعيّةً بلا عمودٍ عامّ ولا واجهة
            'max_refund_amount' => 'nullable|numeric|min:0',
        ], [
            'invoice_template.required' => 'قالب الفاتورة مطلوب',
            'max_discount_percent.max' => 'حد الخصم لا يمكن أن يتجاوز 100%',
        ]);

        // الافتراضيةُ يجب أن تكون من ضمن المفعَّلة — وإلّا فطريقةٌ لا تظهر في الشاشة
        if (! empty($validated['default_payment_method_id']) && ! empty($validated['active_payment_method_ids'])) {
            if (! in_array($validated['default_payment_method_id'], $validated['active_payment_method_ids'])) {
                return back()->withInput()->withErrors([
                    'default_payment_method_id' => 'طريقة الدفع الافتراضية يجب أن تكون من ضمن الطرق المفعَّلة.',
                ]);
            }
        }

        $branchId = $this->resolveSettingsBranch($request);

        $data = $validated;
        $data['branch_id'] = $branchId;

        // الـ checkboxes الغائبةُ من الطلب تعني «مطفأة» لا «بلا تغيير»
        foreach ([
            'enable_departments',
            'show_product_images',
            'show_print_window_after_confirm',
            'enable_auto_settlement',
            'enable_sales_settlement',

            // ضابطان يفرضهما التشغيل فعلاً — انظر [ResolvesPosSettings]
            'require_shift_open',
            'enforce_shift_window',
            'allow_cash_refund',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        if (empty($data['active_payment_method_ids'])) {
            $data['active_payment_method_ids'] = null;
            $data['default_payment_method_id'] = null;
        }

        if (($data['allowed_categories_type'] ?? 'all') === 'all') {
            $data['allowed_categories_ids'] = null;
        }

        try {
            $settings = PosGeneralSetting::where('branch_id', $branchId)->first();

            if ($settings) {
                $settings->update($data);
            } else {
                $settings = PosGeneralSetting::create($data);
            }

            $this->forgetPosSettings();

            OperationLog::record(
                'updated',
                'pos_settings',
                $settings,
                'تعديل إعدادات نقطة البيع'.($branchId ? " — فرع #{$branchId}" : ' — الإعداد العام')
            );

            return back()->with('success', 'تم حفظ الإعدادات بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Settings', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء حفظ الإعدادات.');
        }
    }

    /* ═══════════════════════ نقاط AJAX ═══════════════════════ */

    public function getCategoriesByType(Request $request)
    {
        $type = $request->get('type', 'all');
        $selectedIds = (array) $request->get('selected_ids', []);

        $query = Category::select('id', 'name')->orderBy('name');

        $categories = match ($type) {
            'only' => $selectedIds ? $query->whereIn('id', $selectedIds)->get() : collect(),
            'except' => $selectedIds ? $query->whereNotIn('id', $selectedIds)->get() : $query->get(),
            default => $query->get(),
        };

        return response()->json($categories);
    }

    public function getActivePaymentMethods(Request $request)
    {
        $activeIds = (array) $request->get('active_ids', []);

        if (! $activeIds) {
            return response()->json([]);
        }

        return response()->json($this->activePaymentMethods()->whereIn('id', $activeIds)->values());
    }

    public function saveActivePaymentMethods(Request $request)
    {
        $validated = $request->validate([
            'active_payment_method_ids' => 'required|array|min:1',
            'active_payment_method_ids.*' => 'exists:payment_methods,id',
        ]);

        $branchId = $this->resolveSettingsBranch($request);

        $settings = PosGeneralSetting::where('branch_id', $branchId)->first()
            ?: PosGeneralSetting::create(['branch_id' => $branchId]);

        $settings->update(['active_payment_method_ids' => $validated['active_payment_method_ids']]);

        // افتراضيةٌ خرجت من قائمة المفعَّلة تُمسَح، وإلّا صارت طريقةً لا تظهر
        if ($settings->default_payment_method_id
            && ! in_array($settings->default_payment_method_id, $validated['active_payment_method_ids'])) {
            $settings->update(['default_payment_method_id' => null]);
        }

        $this->forgetPosSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ طرق الدفع المفعَّلة بنجاح',
            'payment_methods' => $this->activePaymentMethods()->whereIn('id', $validated['active_payment_method_ids'])->values(),
            'default_payment_method_id' => $settings->fresh()->default_payment_method_id,
        ]);
    }

    /* ═══════════════════════ مساعدات ═══════════════════════ */

    /**
     * أيُّ فرعٍ تُعرَض إعداداتُه؟
     *
     * من لا يختار الفرعَ يرى فرعَه هو ولا يعدّل غيره. و`null` = الإعدادُ
     * العام، ولا يصل إليه إلّا من يختار الفرع بحرية.
     */
    private function resolveSettingsBranch(Request $request): ?int
    {
        if (! $this->canSelectBranch()) {
            return $this->currentEffectiveBranchId();
        }

        $requested = $request->input('branch_id');

        return $requested !== null && $requested !== '' ? (int) $requested : null;
    }

    /**
     * طرقُ الدفع المفعَّلة.
     *
     * `status` و`is_active` عمودان موجودان معاً في الجدول — الأولُ نصٌّ
     * افتراضُه `'active'` والثاني بولياني — فالاكتفاءُ بأحدهما يُقصي صفوفاً
     * صحيحة بحسب أيّهما مُلئ.
     */
    private function activePaymentMethods()
    {
        return PaymentMethod::where(fn ($q) => $q->where('status', 'active')->orWhere('is_active', 1))
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_online', 'account_id']);
    }

    /**
     * حساباتُ نقاط البيع الافتراضية — أعمدةٌ في `pos_general_settings`.
     *
     * ═══════════════ ما كان ═══════════════
     *
     * القسمُ كان يحرّر مفاتيحَ «الحسابات المحاسبية» المشتركة مباشرةً. وهي
     * تخدم المنشأةَ كلَّها: مبيعاتُ الجملة والتجزئة ونقاط البيع في حسابٍ
     * واحد. فمن أراد قراءةَ أداء نقاط البيع من ميزان المراجعة لزمه تقريرٌ
     * لكل سؤال، ومن غيّر الربط هنا غيّره على الوحدات جميعاً.
     *
     * ═══════════════ وما لا يُضبط هنا ═══════════════
     *
     * **الخزينةُ** ليست إعداداً بل **عهدةُ الكاشير**، تُحلّ من
     * `treasury_employees`. وحقلٌ لها يوهم بخيارٍ ليس خياراً، ويُغري بضبطٍ
     * يجمع نقدَ الكاشيرين في عهدةٍ واحدة فلا يُعرف على مَن الفرق.
     *
     * **وحسابُ المخزون** لا حقلَ له كذلك، لكن لسببٍ آخر: هو يتبع
     * **المستودعَ** المختار في تبويب «البيع» (`accounts.storehouse_id`).
     * فاختيارُ المستودع يختار حسابَه معه، وضبطُهما منفصلَين يتيح قيداً
     * يُدائن حسابَ مخزنٍ ويُخصم من مخزنٍ آخر.
     *
     * **والبنكُ له حقل**: تحصيلُ البطاقات لا يتبع موظّفاً بل يورَّد إلى بنكٍ
     * تختاره المنشأة — وحسابُ طريقة الدفع يتقدّم عليه إن رُبط.
     *
     * والترتيبُ ترتيبُ القيد لا الأبجدية.
     *
     * @return array<int, array{key:string, shared:string, label:string, hint:string, required:bool}>
     */
    private function posAccountKeys(): array
    {
        return [
            ['key' => 'sales_account_id', 'shared' => 'sales',
                'label' => 'حساب المبيعات',
                'hint' => 'الإيرادُ الدائن في قيد كل فاتورة بيع', 'required' => true],

            ['key' => 'sales_returns_account_id', 'shared' => 'sales_returns',
                'label' => 'مردودات المبيعات',
                'hint' => 'الطرفُ المدين في قيد المرتجع', 'required' => true],

            ['key' => 'cost_of_sales_account_id', 'shared' => 'cost_of_sales',
                'label' => 'تكلفة المبيعات',
                'hint' => 'تكلفةُ البضاعة المباعة بمتوسّط التكلفة — مقابلُها حسابُ مستودع نقطة البيع', 'required' => true],

            ['key' => 'discount_account_id', 'shared' => 'allowed_discount',
                'label' => 'الخصم المسموح به',
                'hint' => 'الخصمُ الممنوح للعميل — مصروفٌ بطبيعة مدينة', 'required' => false],

            ['key' => 'vat_account_id', 'shared' => 'vat_payable',
                'label' => 'ضريبة القيمة المضافة',
                'hint' => 'الضريبةُ المحصَّلة من العملاء ومستحقّةٌ للهيئة', 'required' => false],

            ['key' => 'default_bank_account_id', 'shared' => 'pos_default_bank',
                'label' => 'البنك الافتراضي',
                'hint' => 'يُرحَّل إليه تحصيلُ البطاقات — وحسابُ طريقة الدفع يتقدّم عليه', 'required' => false],

            ['key' => 'cash_over_account_id', 'shared' => 'pos_cash_over',
                'label' => 'فائض درج الكاشير',
                'hint' => 'دائنٌ حين يزيد النقدُ الفعلي عن المتوقَّع — إيرادٌ عارض', 'required' => false],

            ['key' => 'cash_short_account_id', 'shared' => 'pos_cash_short',
                'label' => 'عجز درج الكاشير',
                'hint' => 'مدينٌ حين ينقص النقدُ الفعلي عن المتوقَّع — مصروف', 'required' => false],
        ];
    }

    /**
     * الحساباتُ القابلة للترحيل — أوراقُ الشجرة وحدها.
     *
     * الحسابُ الأب مجموعٌ لا يُرحَّل إليه، واختيارُه يُنتج قيداً على حسابٍ
     * تجميعيّ يكسر ميزان المراجعة.
     *
     * @return Collection<int, Account>
     */
    private function postableAccountOptions()
    {
        return Account::query()
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get(['id', 'name', 'code']);
    }

    /**
     * الفروعُ التي تملك صفَّ إعداداتٍ خاصّاً — ومستودعُ كلٍّ منها.
     *
     * صفُّ الفرع يتقدّم على العام تقدّماً **كاملاً** لا حقلاً بحقل، فما
     * يُضبط في «الإعداد العام» لا يبلغ فرعاً له صفُّه. وهذه القائمةُ تجعل
     * ذلك مرئيّاً في الشاشة بدل أن يُكتشف من فرقٍ في الكمّيات.
     *
     * @return Collection<int, object>
     */
    private function branchOverrides(): Collection
    {
        $accessible = $this->accessibleBranchIds();

        $query = PosGeneralSetting::query()
            ->join('branches', 'branches.id', '=', 'pos_general_settings.branch_id')
            ->leftJoin('store_houses', 'store_houses.id', '=', 'pos_general_settings.store_house_id')
            ->whereNotNull('pos_general_settings.branch_id')
            ->orderBy('branches.name');

        if ($accessible !== null) {
            $query->whereIn('pos_general_settings.branch_id', $accessible ?: [0]);
        }

        return $query->get([
            'pos_general_settings.branch_id',
            'branches.name as branch_name',
            'pos_general_settings.store_house_id',
            'store_houses.name as store_house_name',
        ]);
    }

    /**
     * المستودعاتُ المتاحةُ للاختيار، مع حسابِ المخزون المربوط بكلٍّ منها.
     *
     * ═══════════════ لماذا الحسابُ في القائمة ═══════════════
     *
     * اختيارُ المستودع يختار **حسابَ المخزون** معه: الحسابُ الذي عمودُه
     * `storehouse_id` يشير إلى المستودع هو الطرفُ الدائن في كل قيد تكلفة
     * مبيعات. فمستودعٌ بلا حسابٍ يُوقف ترحيلَ التكلفة عند أوّل فاتورة —
     * برسالةٍ صحيحة لكن في أسوأ لحظة: الزبونُ واقفٌ والفاتورةُ تتراجع.
     *
     * وعرضُه هنا يقدّم الاكتشافَ إلى لحظة الاختيار.
     *
     * والمعطَّلُ لا يُعرض: مستودعٌ أُغلق لا يُباع منه.
     */
    private function storeHouseOptions()
    {
        return StoreHouse::query()
            ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id'])
            ->map(function ($storeHouse) {
                $storeHouse->inventory_account = Account::where('storehouse_id', $storeHouse->id)
                    ->value('name');

                return $storeHouse;
            });
    }

    private function bankAccountOptions()
    {
        $parentId = $this->accountingAccountId('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT);

        return Account::where('parent_id', $parentId)
            ->orderBy('code')
            ->get(['id', 'name', 'code']);
    }

    private function branchOptions()
    {
        $query = Branch::query()->orderBy('name');

        $ids = $this->accessibleBranchIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get(['id', 'name']);
    }
}
