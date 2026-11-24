<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Account;
use App\Models\Log as ModelsLog;
use App\Models\TaxSitting;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\RentalManagement\Models\Amenity;
use Modules\RentalManagement\Models\TermTemplate;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateRoles;
use Modules\RentalManagement\Support\RealEstateSettings;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class SettingsController extends RealEstateController
{
    /** أنواع سجلّات القسم — كلّ `OperationLog::record()` في العقارات يكتب بواحدٍ منها. */
    public const LOG_TYPES = [
        're_contract_log' => 'العقود', 're_payment_log' => 'الدفعات', 're_building_log' => 'المباني', 're_unit_log' => 'الوحدات',
        're_listing_log' => 'العروض', 're_relationship_log' => 'علاقات العملاء', 're_maintenance_log' => 'الصيانة',
        're_task_log' => 'المهام', 're_settings_log' => 'الإعدادات', 're_report_log' => 'التقارير', 're_owner_log' => 'الملّاك',
    ];

    public function __construct()
    {
        $this->middleware('permission.role:realestate.settings.view', ['only' => ['index']]);
        $this->middleware('permission.role:realestate.settings.edit', ['only' => ['update', 'repairAccounts', 'storeAmenity', 'destroyAmenity', 'installRoles', 'storeTerm', 'updateTerm', 'destroyTerm']]);
        $this->middleware('permission.role:realestate.logs.view', ['only' => ['logs']]);
    }

    public function index()
    {
        $values = RealEstateSettings::all();

        return view('rentalmanagement::realestate.settings.index', [
            'values' => $values,
            'accountOptions' => $this->accountOptions($values),
            'accountHealth' => RealEstateAccounts::health(),
            'taxes' => TaxSitting::orderBy('id')->get(['id', 'name', 'tax']),
            'amenities' => Amenity::orderBy('name')->get(),
            'termTemplates' => TermTemplate::orderBy('sort')->orderBy('id')->get(),
            'roles' => RealEstateRoles::status(),
        ]);
    }

    /**
     * خيارات كل حقل حساب: حسابات **نوعه المتوقَّع** فقط، والفرعية منها (القيد
     * لا يُرحَّل على حسابٍ له أبناء) إلا ما يُقبل فيه حسابٌ أب. والقيمة
     * المحفوظة حاضرةٌ في القائمة دائماً — كانت القائمة تُسقط الحساب الأب
     * المحفوظ فيظهر الحقل فارغاً ويُمحى عند الحفظ. والتصفية بالنوع تُنزل
     * الصفحة من خمس قوائم بكل الدليل إلى بضع مئات من الأسطر.
     *
     * @return array<string, Collection<int, Account>>
     */
    private function accountOptions(array $values): array
    {
        $byType = Account::query()->whereIn('account_type', ['liabilities', 'revenues', 'expenses'])
            ->withCount('children')->orderBy('code')->get(['id', 'name', 'code', 'account_type'])->groupBy('account_type');

        $options = [];

        foreach (RealEstateSettings::DEFINITIONS as $key => $definition) {
            if ($definition['type'] !== 'account') {
                continue;
            }

            $allowParent = ! empty($definition['parent']);
            $list = ($byType[$definition['account_type']] ?? collect())
                ->filter(fn (Account $a) => $allowParent || (int) $a->children_count === 0)->values();

            $saved = $values[$key] ?? null;

            if ($saved && ! $list->contains('id', $saved) && ($account = Account::find($saved, ['id', 'name', 'code', 'account_type']))) {
                $list->prepend($account);
            }

            $options[$key] = $list;
        }

        return $options;
    }

    public function update(Request $request)
    {
        $rules = [];
        $attributes = [];

        foreach (RealEstateSettings::DEFINITIONS as $key => $definition) {
            $attributes[$key] = $definition['label'];
            $rules[$key] = match ($definition['type']) {
                'account' => ['nullable', 'integer', $this->accountRule($definition)],
                'tax' => 'nullable|integer|exists:tax_sittings,id',
                // النِّسب لا تتجاوز 100؛ والأيام والساعات لها سقفٌ معقول
                'number' => 'nullable|numeric|min:0|max:'.($definition['max'] ?? 3650),
                'select' => 'nullable|'.V::in(constant(V::class.'::'.$definition['options'])),
                default => 'nullable',
            };
        }

        $before = RealEstateSettings::all();
        RealEstateSettings::save($request->validate($rules, [], $attributes));
        $after = RealEstateSettings::all();

        $changed = array_filter(array_keys($after), fn ($k) => $before[$k] != $after[$k]);

        if ($changed) {
            OperationLog::record('updated', 're_settings_log', null,
                'عُدّلت إعدادات إدارة العقارات: '.implode('، ', array_map(fn ($k) => RealEstateSettings::DEFINITIONS[$k]['label'], $changed)),
                ['old' => array_intersect_key($before, array_flip($changed)), 'new' => array_intersect_key($after, array_flip($changed))]);
        }

        return back()->with('success', 'تم حفظ الإعدادات');
    }

    /** الحساب المربوط من النوع المتوقَّع، وفرعيٌّ إن كان يُرحَّل عليه مباشرةً. */
    private function accountRule(array $definition): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($definition) {
            if (! $value) {
                return;
            }

            $account = Account::withCount('children')->find($value);
            $types = ['liabilities' => 'الخصوم', 'revenues' => 'الإيرادات', 'expenses' => 'المصروفات', 'assets' => 'الأصول', 'equity' => 'حقوق الملكية'];

            if (! $account) {
                return $fail('«'.$definition['label'].'»: الحساب المختار غير موجود في الدليل.');
            }

            if ($account->account_type !== $definition['account_type']) {
                return $fail('«'.$definition['label'].'» يجب أن يكون من حسابات '.($types[$definition['account_type']] ?? $definition['account_type']).'.');
            }

            if (RealEstateAccounts::isTreasury($account->id)) {
                return $fail('«'.$definition['label'].'»: هذا حسابُ خزينة أو بنك ولا يصلح لهذا البند.');
            }

            if (empty($definition['parent']) && $account->children_count > 0) {
                $fail('«'.$definition['label'].'»: اختر حساباً فرعياً — لا يُرحَّل على حسابٍ رئيسي له حسابات تحته.');
            }
        };
    }

    /** إصلاح الحسابات: يربط الناقص وينشئه، ويستبدل غير الصالح بالقياسي. لا يمسّ حساباً صالحاً اختاره المحاسب. */
    public function repairAccounts()
    {
        $before = collect(RealEstateAccounts::health())->keyBy('key');
        RealEstateAccounts::ensureAll();
        $after = collect(RealEstateAccounts::health());

        $fixed = $after->filter(fn ($row) => $row['status'] === 'ok' && ($before[$row['key']]['status'] ?? '') !== 'ok')->pluck('label')->all();

        if ($fixed) {
            OperationLog::record('repaired', 're_settings_log', null, 'فُحصت حسابات العقارات وأُصلح: '.implode('، ', $fixed));
        }

        return back()->with($fixed ? 'success' : 'info', $fixed ? 'أُصلحت الحسابات: '.implode('، ', $fixed) : 'كل الحسابات سليمة — لم يلزم تعديل.');
    }

    public function storeAmenity(Request $request)
    {
        Amenity::create($request->validate([
            'name' => 'required|string|max:100|unique:re_amenities,name',
            'icon' => 'nullable|string|max:40',
        ], ['name.unique' => 'هذا المرفق موجود.'], ['name' => 'اسم المرفق']));

        return back()->with('success', 'أُضيف المرفق');
    }

    public function destroyAmenity($id)
    {
        Amenity::findOrFail($id)->delete();

        return back()->with('success', 'حُذف المرفق');
    }

    /** قواعد التحقق المشتركة لقالب الشرط. */
    private function termData(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'body' => 'required|string|max:5000',
            'contract_type' => 'nullable|'.V::in(V::CONTRACT_TYPES),
            'sort' => 'nullable|integer|min:0|max:65535',
        ], [], ['title' => 'عنوان الشرط', 'body' => 'نصّ الشرط', 'contract_type' => 'نوع العقد', 'sort' => 'الترتيب']);

        $data['contract_type'] = $data['contract_type'] ?? null;
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    public function storeTerm(Request $request)
    {
        $term = TermTemplate::create($this->termData($request));
        OperationLog::record('created', 're_settings_log', null, 'أُضيف قالب شرط عقد: '.$term->title);

        return back()->with('success', 'أُضيف الشرط');
    }

    public function updateTerm(Request $request, $id)
    {
        $term = TermTemplate::findOrFail($id);
        $term->update($this->termData($request));
        OperationLog::record('updated', 're_settings_log', null, 'عُدّل قالب شرط عقد: '.$term->title);

        return back()->with('success', 'حُدّث الشرط');
    }

    public function destroyTerm($id)
    {
        $term = TermTemplate::findOrFail($id);
        $term->delete();
        OperationLog::record('deleted', 're_settings_log', null, 'حُذف قالب شرط عقد: '.$term->title);

        return back()->with('success', 'حُذف الشرط');
    }

    /** ينشئ الأدوار الستة النموذجية (أو يكمل صلاحيات الموجود منها) — لا يمسّ أدواراً أخرى. */
    public function installRoles()
    {
        $created = RealEstateRoles::install();

        OperationLog::record('roles_installed', 're_settings_log', null, 'ثُبّتت أدوار إدارة العقارات النموذجية: '.implode('، ', $created ?: ['لا جديد']));

        return back()->with('success', $created ? 'أُنشئت الأدوار: '.implode('، ', $created) : 'الأدوار موجودة — حُدّثت صلاحياتها الناقصة فقط');
    }

    /** سجلّ نشاط القسم كلّه — الأنواع التي تبدأ بـ`re_`. */
    public function logs(Request $request)
    {
        /*
            قائمةٌ صريحة لا `LIKE 're\_%'`: الشرطة المائلة ليست حرف هروبٍ في
            SQLite فلا يطابق النمطُ شيئاً هناك، و`re_%` بلا هروبٍ يبتلع
            `receipt_log` وأمثاله. والقائمة هي نفسها ما يُعرض في المرشِّح.
        */
        $logs = ModelsLog::where('type_log', 'log')->whereIn('type', array_keys(self::LOG_TYPES))
            ->when($request->filled('type') && isset(self::LOG_TYPES[$request->type]), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('user_id'), fn ($q) => $q->where('created_by', $request->user_id))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->with('user')->latest()->paginate($this->perPage($request))->withQueryString();

        return view('rentalmanagement::realestate.settings.logs', [
            'logs' => $logs,
            'users' => $this->users(),
            'types' => self::LOG_TYPES,
        ]);
    }
}
