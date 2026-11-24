<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إعدادات الحسابات المحاسبية — ربط كل عملية بحساب من شجرة الحسابات.
 *
 * شاشة مستقلّة تماماً: لا تقرأ من متحكّم آخر ولا تُقرأ منه. النظام ما زال
 * يعمل بثوابت `App\Constants\SystemAccountIds`، وهذا الجدول طبقة إعدادات
 * تُملأ الآن ويُربط بها النظام في مرحلة تالية.
 *
 * الصلاحيتان مفصولتان عن `settings_edit_general_settings` عمداً: تلك يملكها
 * كل من يغيّر شعار المنشأة، وهذه تقرّر — متى رُبطت — إلى أي حساب يذهب كل
 * قيد. من يعدّل ألوان الواجهة يجب ألّا يستطيع تحويل كل المبيعات إلى حساب آخر.
 */
class AccountingSettingsController extends Controller
{
    /** حدّ نتائج بحث الحسابات: الشجرة فيها آلاف الحسابات. */
    private const SEARCH_LIMIT = 50;

    /**
     * أمثلة القيود — أي عملية تمسّ أي حساب، وبأي طرف.
     *
     * الشاشة كانت تعرض ثمانين اسم حساب بلا سياق: من يقرأ «حساب المبيعات»
     * لا يرى أن فاتورة البيع تجعله دائناً في مقابل حساب العميل. هذه الأمثلة
     * تُبنى من الإعدادات المضبوطة فعلاً، فيرى المستخدم قيده بحساباته هو.
     *
     * بيانات عرض لهذه الشاشة وحدها — لا يقرأها مولّد القيود ولا أي متحكّم
     * آخر، ولا تُنشئ قيداً ولا تُعدّله.
     *
     * الصيغة: عنوان العملية => [الطرف، مفتاح الإعداد، البيان].
     */
    private const OPERATIONS = [
        'sales_invoice_credit' => [
            ['debit', 'clients_account', 'total'],
            ['credit', 'sales', 'net'],
            ['credit', 'vat_payable', 'vat'],
            ['credit', 'sales_discount', 'discount'],
            ['debit', 'sales_shipping', 'shipping'],
            ['debit', 'cost_of_sales', 'cost'],
            ['credit', 'inventory', 'cost'],
        ],
        'sales_invoice_cash' => [
            ['debit', 'main_treasury', 'total'],
            ['credit', 'sales', 'net'],
            ['credit', 'vat_payable', 'vat'],
            ['credit', 'sales_discount', 'discount'],
            ['debit', 'sales_shipping', 'shipping'],
        ],
        'sales_return' => [
            ['debit', 'sales_returns', 'net'],
            ['debit', 'vat_payable', 'vat'],
            ['credit', 'clients_account', 'total'],
        ],
        'receipt_voucher' => [
            ['debit', 'main_treasury', 'received'],
            ['debit', 'allowed_discount', 'discount'],
            ['credit', 'clients_account', 'total'],
        ],
        'purchase_invoice' => [
            ['debit', 'purchases', 'net'],
            ['debit', 'vat_paid', 'vat'],
            ['debit', 'purchase_shipping', 'shipping'],
            ['credit', 'purchase_discount', 'discount'],
            ['credit', 'suppliers', 'total'],
        ],
        'payment_voucher' => [
            ['debit', 'suppliers', 'total'],
            ['credit', 'main_treasury', 'total'],
        ],
        'depreciation' => [
            ['debit', 'depreciation_expense', 'period'],
            ['credit', 'accumulated_depreciation', 'period'],
        ],

        /*
            فرقُ درج الكاشير عند إقفال جلسة نقطة بيع.

            عمليّتان لا واحدة: الزيادةُ إيرادٌ عارض والعجزُ مصروف، فطرفاهما
            متعاكسان ولا يصحّ عرضُهما صفّاً واحداً. ودمجُهما في حسابٍ واحد
            يُخفي حجمَ كلٍّ منهما في ميزان المراجعة — وهو بالضبط ما يُراد
            قياسه: كم زاد وكم نقص، لا صافي الاثنين.

            والمقابلُ خزينةُ الكاشير لا الخزينة الرئيسية بالضرورة — يُحلّ عند
            الإقفال من `TreasuryEmployee` وإلا `main_treasury`. والمعروضُ هنا
            الحالةُ الشائعة.
        */
        'pos_session_over' => [
            ['debit', 'main_treasury', 'variance'],
            ['credit', 'pos_cash_over', 'variance'],
        ],
        'pos_session_short' => [
            ['debit', 'pos_cash_short', 'variance'],
            ['credit', 'main_treasury', 'variance'],
        ],
    ];

    public function __construct()
    {
        $this->middleware('permission:settings.accounting_settings.view')
            ->only(['index', 'edit', 'accounts', 'codeSuggestions', 'codeCheck']);

        $this->middleware('permission:settings.accounting_settings.edit')
            ->only(['update', 'updateAccountCode']);
    }

    /**
     * جدول الإعدادات مقسَّماً بمجموعاته.
     */
    public function index()
    {
        $settings = AccountingSetting::with('account:id,name,code,is_active')
            ->orderBy('id')
            ->get();

        $byKey = $settings->keyBy('key');

        return view('sitting::accounting_settings.index', [
            'groups' => $settings->groupBy(fn (AccountingSetting $setting) => $setting->group ?? __('settings.accounting_settings.ungrouped')),
            'total' => $settings->count(),
            'mapped' => $settings->whereNotNull('account_id')->count(),
            'unmapped' => $settings->whereNull('account_id')->count(),
            'inactive' => $settings->where('is_active', false)->count(),
            'operations' => $this->operations($byKey),
            'usage' => $this->usage(),
        ]);
    }

    /**
     * بناء أمثلة القيود بالحسابات المضبوطة فعلاً.
     *
     * @param  \Illuminate\Support\Collection<string, AccountingSetting>  $byKey
     * @return array<int, array{title:string, lines:array<int, array<string, mixed>>}>
     */
    private function operations($byKey): array
    {
        $examples = [];

        foreach (self::OPERATIONS as $operation => $lines) {
            $examples[] = [
                'key' => $operation,
                'title' => __("settings.accounting_settings.operations.items.{$operation}"),
                'lines' => array_map(function (array $line) use ($byKey) {
                    [$side, $key, $note] = $line;

                    $setting = $byKey->get($key);

                    return [
                        'side' => $side,
                        'key' => $key,
                        'setting' => $setting?->name ?? $key,
                        // الحساب يُعرض كما هو مضبوط الآن: تغيير الإعداد يغيّر
                        // المثال، فيرى المستخدم أثر تعديله قبل أن يسأل عنه
                        'account' => $setting?->account
                            ? $this->label($setting->account)
                            : null,
                        'note' => __("settings.accounting_settings.operations.notes.{$note}"),
                    ];
                }, $lines),
            ];
        }

        return $examples;
    }

    /**
     * لكل مفتاح: أسماء العمليات التي تمسّ حسابه — تُعرض في صفّه.
     *
     * @return array<string, array<int, string>>
     */
    private function usage(): array
    {
        $usage = [];

        foreach (self::OPERATIONS as $operation => $lines) {
            foreach ($lines as [$side, $key, $note]) {
                $title = __("settings.accounting_settings.operations.items.{$operation}");

                if (! in_array($title, $usage[$key] ?? [], true)) {
                    $usage[$key][] = $title;
                }
            }
        }

        return $usage;
    }

    /**
     * بيانات إعداد واحد لتعبئة نموذج التعديل.
     *
     * تُقرأ من الخادم لا من صفّ الجدول: قد يكون شخص آخر غيّر الربط منذ فتح
     * الصفحة، فيُعرض عليه الحساب القديم ويحفظه فوق الجديد بلا أن يدري.
     */
    public function edit(AccountingSetting $accountingSetting): JsonResponse
    {
        $accountingSetting->load('account:id,name,code,is_active');

        return response()->json([
            'id' => $accountingSetting->id,
            'key' => $accountingSetting->key,
            'name' => $accountingSetting->name,
            'description' => $accountingSetting->description,
            'group' => $accountingSetting->group,
            'is_active' => $accountingSetting->is_active,
            'account_id' => $accountingSetting->account_id,
            'account_code' => $accountingSetting->account_code,
            'account_label' => $accountingSetting->account
                ? $this->label($accountingSetting->account)
                : null,
            'update_url' => route('AccountingSettings.update', $accountingSetting),
        ]);
    }

    /**
     * حفظ إعداد: الاسم والوصف والحساب والحالة.
     *
     * الحساب يُحدَّد بالمعرّف أو بالكود، لكن `account_code` المحفوظ يُشتقّ من
     * الحساب الذي استُقرّ عليه لا من نصّ الطلب: لو خُزّن ما كتبه المستخدم
     * لأمكن أن يحمل الصفّ كوداً لا يخصّ حسابه، فيقرأ التقرير كوداً ويقرأ
     * القيد حساباً آخر.
     */
    public function update(Request $request, AccountingSetting $accountingSetting)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            // أحدهما يكفي: المستخدم إمّا اختار من القائمة (المعرّف) أو كتب
            // الكود مباشرةً. الحسم في `resolveAccount` لا هنا، كي تكون رسالة
            // «لا حساب بهذا الكود» غير رسالة «لم تختر حساباً»
            'account_id' => ['nullable', 'integer'],
            'account_code' => ['nullable', 'string', 'max:10'],
        ], [], [
            'name' => __('settings.accounting_settings.fields.name'),
            'description' => __('settings.accounting_settings.fields.description'),
            'account_id' => __('settings.accounting_settings.fields.account'),
            'account_code' => __('settings.accounting_settings.fields.account_code'),
        ]);

        $account = $this->resolveAccount($data);

        $before = [
            'account_id' => $accountingSetting->account_id,
            'account_code' => $accountingSetting->account_code,
        ];

        $accountingSetting->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'account_id' => $account->id,
            'account_code' => $account->code !== null ? (string) $account->code : null,
        ]);

        if ($before['account_id'] !== $accountingSetting->account_id) {
            $this->recordAccountChange($accountingSetting, $before, $account);
        }

        // الخريطة مخزَّنة في الكاش ويقرأها مولّد القيود، فتُبطَل عند كل حفظ
        // وإلا بقيت القيود تذهب إلى الحساب القديم حتى انتهاء مدّة الكاش
        \App\Support\AccountingSettings::forget();

        return redirect()->route('AccountingSettings.index')
            ->with('success', __('settings.accounting_settings.updated', ['name' => $accountingSetting->name]));
    }

    /**
     * صاحب كودٍ بعينه — هل هو شاغر، ولمن هو إن كان محجوزاً.
     *
     * الرفض بعد الحفظ يقول «الكود مستخدم» ولا يقول لمن، فيعيد المستخدم
     * المحاولة برقم آخر يصطدم بدوره. هنا يُعرف صاحب الكود أثناء الكتابة،
     * فيُختار غيره قبل الإرسال.
     *
     * `is_active` لا يُصفَّى هنا عمداً: قيد التفرّد في القاعدة لا يفرّق بين
     * حساب مفعَّل ومعطَّل، فالكود المحجوز لحساب معطَّل محجوز فعلاً — وإخفاؤه
     * يُنتج رسالة «الكود شاغر» ثم رفضاً عند الحفظ.
     */
    public function codeCheck(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));
        $except = (int) $request->query('except', 0);

        if ($code === '' || ! preg_match('/^\d{1,10}$/', $code)) {
            return response()->json(['code' => $code, 'valid' => false, 'found' => false]);
        }

        $account = Account::where('code', $code)->first(['id', 'name', 'code', 'is_active']);

        return response()->json([
            'code' => $code,
            'valid' => true,
            'found' => (bool) $account,
            'is_self' => $account && $except > 0 && $account->id === $except,
            'account' => $account ? [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'is_active' => (bool) $account->is_active,
                'label' => $this->label($account),
            ] : null,
        ]);
    }

    /**
     * أكواد مقترحة للحساب — من موقعه في الشجرة.
     *
     * شجرة الحسابات هرمية: الابن يرث بادئة أبيه (11 تحته 111 و112، و112 تحته
     * 1121). من يغيّر كوداً يدوياً يكسر هذا الترتيب بسهولة، فتُعرض عليه
     * أكواد صالحة مشتقّة من موضع حسابه بدل أن يخمّن.
     *
     * المنطق نفسه الذي تولّد به شاشة شجرة الحسابات أكوادها (أكبر كود بين
     * الأشقاء + 1، وأول ابن يأخذ بادئة أبيه) — كي لا تقترح هذه الشاشة نمطاً
     * تخالفه تلك.
     *
     * إرشادية لا إجبارية: 19% من أبناء الشجرة الحالية لا تتبع بادئة أبيها،
     * وفرضُها الآن يمنع تعديلات مشروعة على بيانات قائمة.
     */
    public function codeSuggestions(AccountingSetting $accountingSetting): JsonResponse
    {
        $account = $accountingSetting->account;

        if (! $account) {
            return response()->json([
                'error' => __('settings.accounting_settings.errors.no_account_to_recode'),
                'suggestions' => [],
            ]);
        }

        $parent = $account->parent_id ? Account::find($account->parent_id) : null;

        $siblings = Account::where('parent_id', $account->parent_id)
            ->where('id', '!=', $account->id)
            ->whereNotNull('code')
            ->orderByRaw('CAST(code AS UNSIGNED)')
            ->get(['id', 'code', 'name']);

        // الأكواد كلّها مرّةً واحدة: كل مرشَّح يُفحص ضدّ هذه المجموعة، وفحصه
        // باستعلام مفرد يعني عشرات الاستعلامات لبناء أربعة اقتراحات
        $taken = Account::whereNotNull('code')->pluck('code')
            ->mapWithKeys(fn ($code) => [(string) $code => true])
            ->all();

        return response()->json([
            'account' => [
                'code' => $account->code,
                'name' => $account->name,
            ],
            'parent' => $parent ? [
                'code' => $parent->code,
                'name' => $parent->name,
            ] : null,
            'prefix' => $parent && is_numeric($parent->code) ? (string) $parent->code : null,
            'siblings' => $siblings->take(12)->map(fn (Account $sibling) => [
                'code' => $sibling->code,
                'name' => $sibling->name,
            ])->values(),
            'siblings_total' => $siblings->count(),
            'suggestions' => $this->buildSuggestions($account, $parent, $siblings, $taken),
        ]);
    }

    /**
     * بناء الاقتراحات: كل مرشَّح رقم فريد لا يتجاوز عشر خانات.
     *
     * @param  \Illuminate\Support\Collection<int, Account>  $siblings
     * @param  array<string, bool>  $taken
     * @return array<int, array{code:string, reason:string}>
     */
    private function buildSuggestions(Account $account, ?Account $parent, $siblings, array $taken): array
    {
        // الشجرة الحالية فيها أكواد بطول 34 خانة، وجمع 1 على عددٍ بذلك الحجم
        // يفيض من `int` إلى `float` فينكسر كل الحساب. الحدّ عشر خانات — وهو
        // أصلاً أقصى ما تقبله شاشة شجرة الحسابات، فما تجاوزه لا يصلح أساساً
        // لاقتراح كود جديد
        $usable = fn ($code) => is_string($code) || is_int($code)
            ? (bool) preg_match('/^\d{1,10}$/', (string) $code)
            : false;

        $prefix = $parent && $usable($parent->code) && strlen((string) $parent->code) <= 9
            ? (string) $parent->code
            : '';

        $numericSiblings = $siblings->filter(fn (Account $sibling) => $usable($sibling->code));

        $suggestions = [];

        $add = function (?string $code, string $reason, array $replace = []) use (&$suggestions, $taken) {
            if ($code === null || $code === '' || strlen($code) > 10) {
                return;
            }

            // الكود المأخوذ لا يُقترح، ولا يُقترح مرشَّح مكرّر في القائمة نفسها
            if (isset($taken[$code]) || isset($suggestions[$code])) {
                return;
            }

            $suggestions[$code] = [
                'code' => $code,
                'reason' => __("settings.accounting_settings.suggest.reasons.{$reason}", $replace),
            ];
        };

        // حساب جذر: أصغر رقم شاغر لا أكبر+1، ولا بناء على الأشقاء إطلاقاً.
        // بين الجذور كود شاذّ طوله ثماني خانات (12411712)، والبناء عليه يقترح
        // رقماً بطوله لحساب رئيسي رقمه خانة واحدة
        if ($parent === null) {
            $add($this->firstFree(1, $taken), 'next_root');
        } elseif ($prefix !== '') {
            // الأشقاء الملتزمون ببادئة الأب وحدهم يُبنى عليهم الاقتراح.
            // شجرة هذا النظام فيها أشقاء شاذّون — تحت الأب «121» أشقاء أكوادهم
            // 6 و7 و3426 — والبناء على أكبرهم يقترح 3427 وهو رقم لا صلة له
            // بموضع الحساب. الملتزمون هم 1212 و1213، فالاقتراح 1214
            $inPrefix = $numericSiblings
                ->filter(fn (Account $sibling) => str_starts_with((string) $sibling->code, $prefix));

            // البادئة أولاً: هذا هو المطلوب أصلاً — كود يتبع موضعه في الشجرة
            $add($this->firstGap($prefix, $inPrefix, $taken), 'gap_in_parent', ['prefix' => $prefix]);

            if ($inPrefix->isNotEmpty()) {
                $last = (int) $inPrefix->max(fn (Account $sibling) => (int) $sibling->code);
                $add($this->firstFree($last + 1, $taken), 'after_siblings', ['code' => $last]);
            } else {
                // لا شقيق ملتزم: أول فرع تحت الأب — عين ما تفعله شاشة الشجرة
                $add($this->firstFree((int) ($prefix.'001'), $taken), 'first_child', ['prefix' => $prefix]);
            }
        } elseif ($numericSiblings->isNotEmpty()) {
            // أب بلا كود رقمي: لا بادئة تُتبع، فيبقى التالي بعد آخر شقيق
            $last = (int) $numericSiblings->max(fn (Account $sibling) => (int) $sibling->code);
            $add($this->firstFree($last + 1, $taken), 'after_siblings', ['code' => $last]);
        }

        return array_values(array_slice($suggestions, 0, 4));
    }

    /**
     * أول رقم حرّ ابتداءً من `$from` — مع سقف يمنع الدوران بلا نهاية.
     *
     * @param  array<string, bool>  $taken
     */
    private function firstFree(int $from, array $taken): ?string
    {
        // ما تجاوز عشر خانات مرفوض أصلاً، فلا يُدار عليه ألف مرّة بلا طائل
        if ($from < 1 || strlen((string) $from) > 10) {
            return null;
        }

        for ($candidate = $from, $guard = 0; $guard < 1000; $candidate++, $guard++) {
            if (! isset($taken[(string) $candidate]) && strlen((string) $candidate) <= 10) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * أول رقم شاغر ضمن بادئة الأب، بطول الأشقاء نفسه.
     *
     * الطول يُؤخذ من الأشقاء لا يُفترض: أب كوده 12 قد يكون أبناؤه 121 وقد
     * يكونون 12001، واقتراح طول مخالف يُنتج كوداً شاذّاً وسط إخوته.
     *
     * @param  \Illuminate\Support\Collection<int, Account>  $siblings
     * @param  array<string, bool>  $taken
     */
    private function firstGap(string $prefix, $siblings, array $taken): ?string
    {
        $lengths = $siblings
            ->map(fn (Account $sibling) => (string) $sibling->code)
            ->filter(fn (string $code) => str_starts_with($code, $prefix) && strlen($code) > strlen($prefix))
            ->map(fn (string $code) => strlen($code) - strlen($prefix));

        $suffixLength = $lengths->isNotEmpty()
            ? (int) $lengths->mode()[0]
            : 1;

        $max = min((10 ** $suffixLength) - 1, 999);

        for ($i = 1; $i <= $max; $i++) {
            $candidate = $prefix.str_pad((string) $i, $suffixLength, '0', STR_PAD_LEFT);

            if (strlen($candidate) <= 10 && ! isset($taken[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * الحساب المقصود من الطلب: بالمعرّف إن اختير من القائمة، وإلا بالكود.
     *
     * الكود مقبول بديلاً كي يعمل الإدخال اليدوي بلا جافاسكربت أيضاً — من كتب
     * `411` وحفظ يجب أن يُربط بصاحبه، لا أن يُقال له «لم تختر حساباً».
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveAccount(array $data): Account
    {
        $account = null;

        if (! empty($data['account_id'])) {
            $account = Account::find($data['account_id']);

            if (! $account) {
                throw ValidationException::withMessages([
                    'account_id' => __('settings.accounting_settings.errors.account_inactive'),
                ]);
            }
        } elseif (($code = trim((string) ($data['account_code'] ?? ''))) !== '') {
            $account = Account::where('code', $code)->first();

            // خطأ الكود غير الموجود يُعرض على حقل الكود لا على حقل البحث،
            // وإلا بحث المستخدم عن خطئه في الحقل الخطأ
            if (! $account) {
                throw ValidationException::withMessages([
                    'account_code' => __('settings.accounting_settings.errors.code_not_found', ['code' => $code]),
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'account_id' => __('settings.accounting_settings.errors.account_required'),
            ]);
        }

        // الحساب المعطَّل يمرّ من الاستعلام — ومنعه هنا كي تظهر رسالة تشرح
        // السبب وتسمّي الحساب، بدل «الحساب غير موجود» وهو موجود
        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'account_id' => __('settings.accounting_settings.errors.account_disabled_named', [
                    'code' => $account->code ?? '—',
                    'account' => $account->name,
                ]),
            ]);
        }

        return $account;
    }

    /**
     * تغيير كود الحساب نفسه في شجرة الحسابات.
     *
     * إجراء منفصل عن حفظ الإعداد عمداً: حفظ الإعداد يمسّ صفّاً في طبقة
     * الإعدادات وحدها، وهذا يمسّ `accounts` — أي شجرة الحسابات التي تقرأ
     * منها كل التقارير والقيود. خلطهما يجعل تصحيح اسم إعداد يغيّر الشجرة.
     *
     * القواعد هي قواعد شاشة شجرة الحسابات نفسها (`unique` و`max:10`) كي لا
     * تقبل هذه الشاشة كوداً ترفضه تلك.
     */
    public function updateAccountCode(Request $request, AccountingSetting $accountingSetting)
    {
        $account = $accountingSetting->account;

        if (! $account) {
            throw ValidationException::withMessages([
                'code' => __('settings.accounting_settings.errors.no_account_to_recode'),
            ]);
        }

        // صاحب الكود يُذكر بالاسم في الرسالة: «الكود مستخدم» وحدها تجعل
        // المستخدم يجرّب رقماً بعد رقم بلا أن يعرف بمن اصطدم
        $owner = Account::where('code', trim((string) $request->input('code')))
            ->where('id', '!=', $account->id)
            ->first(['id', 'name', 'code']);

        $data = $request->validate([
            'code' => [
                'required',
                'numeric',
                'digits_between:1,10',
                Rule::unique('accounts', 'code')->ignore($account->id),
            ],
            // تأكيد صريح: الكود يظهر في كل تقرير وكل كشف حساب، وتغييره
            // بضغطة واحدة سهوَاً أسوأ من خطوة زائدة
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => __('settings.accounting_settings.errors.confirm_required'),
            // `resources/lang/ar/validation.php` غير مترجَم في المشروع، فرسائل
            // القواعد تخرج بالإنجليزية وسط شاشة عربية. تُعطى هنا بدل تعديل
            // ملفّ التحقّق العام الذي تشترك فيه كل الشاشات
            'code.unique' => $owner
                ? __('settings.accounting_settings.errors.code_taken_by', [
                    'code' => $owner->code,
                    'account' => $owner->name,
                ])
                : __('settings.accounting_settings.errors.code_taken'),
            'code.numeric' => __('settings.accounting_settings.errors.code_invalid'),
            'code.digits_between' => __('settings.accounting_settings.errors.code_invalid'),
            'code.required' => __('settings.accounting_settings.errors.code_invalid'),
        ], [
            'code' => __('settings.accounting_settings.fields.account_code'),
        ]);

        $oldCode = $account->code;
        $newCode = (int) $data['code'];

        if ((int) $oldCode === $newCode) {
            return redirect()->route('AccountingSettings.index')
                ->with('success', __('settings.common.no_changes'));
        }

        DB::transaction(function () use ($account, $newCode) {
            $account->update(['code' => $newCode]);

            // كل إعداد يشير إلى هذا الحساب يحمل نسخةً من كوده — لو حُدّث صفّ
            // واحد لتناقض الجدول مع نفسه، وحسابٌ واحد قد تشير إليه عدّة
            // إعدادات (المخزون والبضاعة الجاهزة يشتركان في الحساب نفسه)
            AccountingSetting::where('account_id', $account->id)
                ->update(['account_code' => (string) $newCode]);
        });

        $this->recordCodeChange($accountingSetting, $account, $oldCode, $newCode);

        return redirect()->route('AccountingSettings.index')
            ->with('success', __('settings.accounting_settings.code_updated', [
                'name' => $account->name,
                'from' => $oldCode ?? '—',
                'to' => $newCode,
            ]));
    }

    /**
     * بحث في شجرة الحسابات بالكود أو بالاسم — يغذّي منتقي الحساب.
     *
     * الشجرة فيها آلاف الحسابات، فلا تُحمَّل كلّها في قائمة منسدلة داخل
     * ثمانين صفّاً؛ يُبحَث عند الكتابة ويُعاد خمسون نتيجة.
     */
    public function accounts(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $exactCode = trim((string) $request->query('code', ''));

        $query = Account::query()
            ->select(['id', 'name', 'code', 'account_type', 'is_active'])
            ->where('is_active', true);

        // بحث بالكود تماماً — يخدم حقل «كود الحساب» في نموذج التعديل: من كتب
        // 411 يريد الحساب 411 وحده، لا كل ما يبدأ بـ411
        if ($exactCode !== '') {
            $account = (clone $query)->where('code', $exactCode)->first();

            return response()->json([
                'results' => $account ? [[
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'label' => $this->label($account),
                ]] : [],
            ]);
        }

        if ($term !== '') {
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', '%'.$term.'%')
                    ->orWhere('code', 'like', $term.'%');
            });

            // مطابقة الكود تماماً تتصدّر: من كتب «44» يريد الحساب 44 أولاً
            if (ctype_digit($term)) {
                $query->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$term]);
            }
        }

        $accounts = $query->orderBy('code')->limit(self::SEARCH_LIMIT)->get();

        return response()->json([
            'results' => $accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => $this->label($account),
            ])->values(),
        ]);
    }

    /**
     * تسجيل تغيير كود حساب في الشجرة — أخطر من تغيير ربط إعداد، فيُسجَّل مثله.
     */
    private function recordCodeChange(AccountingSetting $setting, Account $account, $oldCode, int $newCode): void
    {
        $description = __('settings.accounting_settings.audit.code_changed', [
            'account' => $account->name,
            'from' => $oldCode ?? '—',
            'to' => $newCode,
            'key' => $setting->key,
        ]);

        Log::warning('تغيير كود حساب في شجرة الحسابات', [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'from_code' => $oldCode,
            'to_code' => $newCode,
            'from_setting' => $setting->key,
            'user_id' => auth()->id(),
        ]);

        $this->writeActivityLog('accounting_settings.account_code_changed', $description);
    }

    /**
     * «44 - المبيعات».
     */
    private function label(Account $account): string
    {
        return trim(($account->code ?? '—').' - '.$account->name);
    }

    /**
     * تسجيل تغيير الربط: من غيّر أي إعداد، من أي حساب إلى أي حساب.
     *
     * يُكتب في سجلّ التطبيق دائماً (وهو ما تفعله بقية شاشات الإعدادات)، وفي
     * `activity_logs` إن كان الجدول موجوداً — فهو جدول قديم بلا هجرة في
     * المستودع، فقد يغيب في تركيب أو في قاعدة الاختبارات.
     *
     * @param  array{account_id:int|null, account_code:string|null}  $before
     */
    private function recordAccountChange(AccountingSetting $setting, array $before, Account $account): void
    {
        $description = __('settings.accounting_settings.audit.changed', [
            'name' => $setting->name,
            'key' => $setting->key,
            'from' => $before['account_code'] ?? ($before['account_id'] ?? '—'),
            'to' => $setting->account_code ?? $account->id,
        ]);

        Log::info('تغيير إعداد حساب محاسبي', [
            'key' => $setting->key,
            'from_account_id' => $before['account_id'],
            'from_account_code' => $before['account_code'],
            'to_account_id' => $setting->account_id,
            'to_account_code' => $setting->account_code,
            'user_id' => auth()->id(),
        ]);

        $this->writeActivityLog('accounting_settings.account_changed', $description);
    }

    /**
     * كتابة سطر في سجلّ النشاط إن كان الجدول موجوداً.
     *
     * `activity_logs` جدول قديم بلا هجرة في المستودع، فقد يغيب في تركيب أو
     * في قاعدة الاختبارات. وفشل التسجيل لا يُسقط الطلب: التغيير وقع فعلاً،
     * وإسقاطه بعده يوهم المستخدم أنه لم يقع.
     */
    private function writeActivityLog(string $type, string $description): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        try {
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => $type,
                'activity_description' => $description,
                'activity_date' => now(),
                'ip_address' => request()->ip(),
                'module_affected' => 'Sitting',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('تعذّر الكتابة في سجلّ النشاط', [
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}