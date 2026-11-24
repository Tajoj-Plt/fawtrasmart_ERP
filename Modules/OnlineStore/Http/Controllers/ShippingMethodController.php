<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\ShippingMethodType;
use App\Models\Account;
use App\Models\Store\ShippingMethod;
use App\Support\AccountingSettings;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * Controller لإدارة طرق الشحن
 */
class ShippingMethodController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.shipping_methods.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:online_store.shipping_methods.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.shipping_methods.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:online_store.shipping_methods.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.shipping_methods.toggle_status', ['only' => ['toggleStatus']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'name' => 'اسم طريقة الشحن',
        'code' => 'الكود',
        'description' => 'الوصف',
        'logo' => 'الشعار',
        'type' => 'نوع الشحن',
        'cost' => 'تكلفة الشحن',
        'min_order_amount' => 'الحد الأدنى للطلب',
        'free_shipping_threshold' => 'حد الشحن المجاني',
        'supports_express' => 'يدعم الشحن السريع',
        'estimated_days_min' => 'أقل مدة توصيل',
        'estimated_days_max' => 'أقصى مدة توصيل',
        'sort_order' => 'الترتيب',
        'is_active' => 'حالة التفعيل',
    ];

    /**
     * طرق شحن نطاق المستخدم: الفرع النشط وما تحته. الفرع الرئيسي = الكل لصاحب
     * `data_scope_all` وغير الموظف ومدير المقرّ فقط ({@see HeaderBranchScope}).
     */
    private function scopedMethods(): Builder
    {
        return ShippingMethod::query()->forBranch(HeaderBranchScope::nodeId());
    }

    /** 404 لطريقة شحنٍ خارج النطاق — كانت تُفتح وتُعدَّل وتُحذف برقمها من أي فرع. */
    private function ensureInScope(ShippingMethod $shippingMethod): void
    {
        abort_unless($this->scopedMethods()->whereKey($shippingMethod->getKey())->exists(), 404);
    }

    private function rules(?ShippingMethod $shippingMethod = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('shipping_methods', 'code')->ignore($shippingMethod?->id)],
            'description' => 'nullable|string|max:2000',
            'logo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
            // قيمةٌ خارج الأنواع المعرَّفة كانت تُسقط الحفظ بخطأ 500 وتترك المعاملة مفتوحة
            'type' => ['required', Rule::in(array_column(ShippingMethodType::cases(), 'value'))],
            'cost' => 'required|numeric|min:0|max:99999999',
            'min_order_amount' => 'nullable|numeric|min:0|max:99999999',
            'free_shipping_threshold' => 'nullable|numeric|min:0|max:99999999',
            'supports_express' => 'nullable|boolean',
            'estimated_days_min' => 'nullable|integer|min:0|max:365',
            'estimated_days_max' => 'nullable|integer|min:0|max:365|gte:estimated_days_min',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    private const MESSAGES = [
        'estimated_days_max.gte' => 'أقصى مدة توصيل يجب ألا تقل عن أقل مدة توصيل.',
    ];

    /** كودٌ فريد من الاسم — الكود المولَّد لم يكن يُفحص، فاسمان متشابهان = خطأ قاعدة بيانات. */
    private function uniqueCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($name) ?: 'shipping', 40, '');
        $code = $base;
        $i = 2;

        while (ShippingMethod::where('code', $code)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }

    /**
     * عرض قائمة طرق الشحن
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        // التحقق من أن جميع طرق الشحن لديها حسابات
        $this->ensureShippingMethodsHaveAccounts();

        $query = $this->scopedMethods();

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فلترة حسب النوع
        if (is_string($request->type) && ShippingMethodType::tryFrom($request->type)) {
            $query->where('type', $request->type);
        }

        // فلترة حسب الحالة
        if (in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('is_active', $request->status === 'active');
        }

        $shippingMethods = $query->ordered()->paginate(15)->withQueryString();
        $types = ShippingMethodType::cases();

        // بطاقات الأرقام على كل طرق النطاق لا على صفحة الجدول الحالية
        $stats = [
            'total' => $this->scopedMethods()->count(),
            'active' => $this->scopedMethods()->where('is_active', true)->count(),
            'free' => $this->scopedMethods()->where('type', ShippingMethodType::FREE->value)->count(),
        ];

        return view('onlinestore::shipping-methods.index', compact('shippingMethods', 'types', 'stats'));
    }

    /**
     * عرض صفحة إنشاء طريقة شحن جديدة
     */
    public function create()
    {
        $types = ShippingMethodType::cases();

        return view('onlinestore::shipping-methods.create', compact('types'));
    }

    /**
     * حفظ طريقة شحن جديدة
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), self::MESSAGES, self::ATTRIBUTES);

        // توليد الكود إذا لم يتم إدخاله — فريداً دائماً
        if (empty($validated['code'])) {
            $validated['code'] = $this->uniqueCode($validated['name']);
        }

        // رفع الشعار
        if ($request->hasFile('logo')) {
            $validated['logo'] = $this->uploadImage('assets/uploads/shipping', $request->file('logo'));
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['supports_express'] = $request->boolean('supports_express');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        // تُختم بالفرع النشط لمن أنشأها — كانت تُختم بالفرع الرئيسي دائماً فلا يراها
        // منشئُها الواقف على فرعٍ آخر
        $validated['branch_id'] = current_branch_id() ?: null;

        DB::beginTransaction();
        try {
            // إنشاء طريقة الشحن
            $shippingMethod = ShippingMethod::create($validated);

            // إنشاء حساب لشركة الشحن
            $account = $this->createShippingCompanyAccount($shippingMethod);

            // ربط الحساب بطريقة الشحن
            $shippingMethod->update(['account_id' => $account->id]);

            DB::commit();

            return redirect()->route('shipping_methods.index')
                ->with('success', 'تم إضافة طريقة الشحن بنجاح');
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده: التسجيل يكتب عبر نفس اتصال
            // قاعدة البيانات، فإن كُتب داخل معاملةٍ لم تُنهَ بعد يُلغى سجلّه
            // هو نفسه حين تُلغى المعاملة بأثرٍ رجعي (تحقّقتُ من هذا مباشرةً)
            DB::rollBack();

            OperationLog::failure($e, 'OnlineStore.ShippingMethod', 'store');

            // رسالة القاعدة الخام لا تُعرض للمستخدم — التفاصيل في سجل الأخطاء
            $known = $e->getMessage() === 'حساب شركات الشحن الرئيسي غير موجود';

            return redirect()->back()
                ->withInput()
                ->with('error', $known
                    ? 'تعذّر إنشاء حساب شركة الشحن: حساب «شركات الشحن» الرئيسي غير مضبوط في الإعدادات المحاسبية.'
                    : 'حدث خطأ أثناء إضافة طريقة الشحن. راجع سجل الأخطاء أو حاول مرة أخرى.');
        }
    }

    /**
     * عرض تفاصيل طريقة شحن
     */
    public function show(ShippingMethod $shippingMethod)
    {
        $this->ensureInScope($shippingMethod);

        // محاولة تحميل علاقة الحساب بشكل آمن
        $accountBalance = 0;
        try {
            if (Schema::hasColumn('shipping_methods', 'account_id') && method_exists($shippingMethod, 'account')) {
                $shippingMethod->loadMissing(['account']);
                $accountBalance = $shippingMethod->account ? $shippingMethod->account->balance : 0;
            }
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.ShippingMethod', 'show', $shippingMethod);

            // تجاهل الخطأ - العلاقة غير متوفرة
            $accountBalance = 0;
        }

        return view('onlinestore::shipping-methods.show', compact('shippingMethod', 'accountBalance'));
    }

    /**
     * عرض صفحة تعديل طريقة شحن
     */
    public function edit(ShippingMethod $shippingMethod)
    {
        $this->ensureInScope($shippingMethod);

        $types = ShippingMethodType::cases();

        return view('onlinestore::shipping-methods.edit', compact('shippingMethod', 'types'));
    }

    /**
     * تحديث طريقة شحن
     */
    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $this->ensureInScope($shippingMethod);

        $validated = $request->validate($this->rules($shippingMethod), self::MESSAGES, self::ATTRIBUTES);

        // كودٌ فارغ في التعديل لا يمسح الكود الحالي (العمود فريدٌ ومطلوب)
        if (empty($validated['code'])) {
            $validated['code'] = $shippingMethod->code ?: $this->uniqueCode($validated['name'], $shippingMethod->id);
        }
        if (! array_key_exists('sort_order', $validated) || $validated['sort_order'] === null) {
            $validated['sort_order'] = $shippingMethod->sort_order ?? 0;
        }

        // رفع الشعار الجديد
        if ($request->hasFile('logo')) {
            $this->deleteOldImage($shippingMethod->logo);
            $validated['logo'] = $this->uploadImage('assets/uploads/shipping', $request->file('logo'));
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['supports_express'] = $request->boolean('supports_express');

        DB::beginTransaction();
        try {
            $shippingMethod->update($validated);

            DB::commit();

            return redirect()->route('shipping_methods.index')
                ->with('success', 'تم تحديث طريقة الشحن بنجاح');
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();

            OperationLog::failure($e, 'OnlineStore.ShippingMethod', 'update');

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث طريقة الشحن. راجع سجل الأخطاء أو حاول مرة أخرى.');
        }
    }

    /**
     * حذف طريقة شحن
     */
    public function destroy(ShippingMethod $shippingMethod)
    {
        $this->ensureInScope($shippingMethod);

        // التحقق من عدم وجود طلبات مرتبطة
        if ($shippingMethod->onlineOrders()->exists()) {
            return redirect()->route('shipping_methods.index')
                ->with('error', 'لا يمكن حذف طريقة الشحن «'.$shippingMethod->name.'» لوجود طلبات مرتبطة بها. عطّلها بدل حذفها.');
        }

        // حساب شركة الشحن عليه حركات محاسبية — لا تُحذف الطريقة ويبقى الحساب يتيماً
        if ($shippingMethod->account_id
            && DB::table('journal_entry_details')->where('account_id', $shippingMethod->account_id)->exists()) {
            return redirect()->route('shipping_methods.index')
                ->with('error', 'لا يمكن حذف طريقة الشحن «'.$shippingMethod->name.'» لأن على حساب شركة الشحن قيوداً محاسبية. عطّلها بدل حذفها.');
        }

        $this->deleteOldImage($shippingMethod->logo);
        $shippingMethod->delete();

        return redirect()->route('shipping_methods.index')
            ->with('success', 'تم حذف طريقة الشحن بنجاح');
    }

    /**
     * تبديل حالة التفعيل
     */
    public function toggleStatus(ShippingMethod $shippingMethod)
    {
        $this->ensureInScope($shippingMethod);

        $shippingMethod->update(['is_active' => ! $shippingMethod->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $shippingMethod->is_active,
            'message' => $shippingMethod->is_active ? 'تم تفعيل طريقة الشحن' : 'تم إيقاف طريقة الشحن',
        ]);
    }

    /**
     * التحقق من أن جميع طرق الشحن لديها حسابات
     * وإنشاء حسابات للشركات التي ليس لديها
     */
    private function ensureShippingMethodsHaveAccounts()
    {
        try {
            // جلب طرق الشحن التي ليس لديها حساب (كان يُحمَّل الجدول كله ويُكتب
            // في السجل ستة أسطر عند كل فتحٍ للصفحة)
            $shippingMethodsWithoutAccount = ShippingMethod::whereNull('account_id')->get();

            if ($shippingMethodsWithoutAccount->isEmpty()) {
                return;
            }

            DB::beginTransaction();

            foreach ($shippingMethodsWithoutAccount as $shippingMethod) {
                try {
                    // إنشاء حساب لشركة الشحن
                    $account = $this->createShippingCompanyAccount($shippingMethod);

                    // ربط الحساب بطريقة الشحن - التحديث المباشر
                    $shippingMethod->account_id = $account->id;
                    $shippingMethod->save();

                    \Log::info('✅ تم إنشاء حساب لشركة شحن', [
                        'shipping_method_id' => $shippingMethod->id,
                        'shipping_method_name' => $shippingMethod->name,
                        'account_id' => $account->id,
                        'account_name' => $account->name,
                        'account_code' => $account->code,
                    ]);
                } catch (\Exception $itemException) {
                    OperationLog::failure($itemException, 'OnlineStore.ShippingMethod', 'ensureShippingMethodsHaveAccounts', $shippingMethod);

                    \Log::error('❌ فشل إنشاء حساب لطريقة شحن', [
                        'shipping_method_id' => $shippingMethod->id,
                        'shipping_method_name' => $shippingMethod->name,
                        'error' => $itemException->getMessage(),
                        'trace' => $itemException->getTraceAsString(),
                    ]);
                    throw $itemException; // إعادة رمي الخطأ لإلغاء التعاملات
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();

            OperationLog::failure($e, 'OnlineStore.ShippingMethod', 'ensureShippingMethodsHaveAccounts');

            \Log::error('❌ خطأ في إنشاء حسابات لشركات الشحن', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * إنشاء حساب لشركة الشحن
     */
    private function createShippingCompanyAccount(ShippingMethod $shippingMethod)
    {
        // الحصول على حساب شركات الشحن الرئيسي
        $parentAccount = Account::find(AccountingSettings::id('shipping_companies', SystemAccountIds::SHIPPING_COMPANIES));

        if (! $parentAccount) {
            throw new \Exception('حساب شركات الشحن الرئيسي غير موجود');
        }

        // إنشاء كود الحساب
        $lastAccountCode = Account::where('parent_id', AccountingSettings::id('shipping_companies', SystemAccountIds::SHIPPING_COMPANIES))
            ->orderBy('code', 'desc')
            ->value('code');

        $nextCode = $lastAccountCode ? intval($lastAccountCode) + 1 : intval($parentAccount->code) * 10 + 1;

        // إنشاء الحساب
        $account = Account::create([
            'name' => 'شركة شحن: '.$shippingMethod->name,
            'code' => (string) $nextCode,
            'parent_id' => AccountingSettings::id('shipping_companies', SystemAccountIds::SHIPPING_COMPANIES),
            'type' => 'sub',
            'account_type' => Account::LIABILITIES,
            'balance_type' => Account::CREDIT,
            'balance' => 0,
            'is_active' => true,
            'is_system' => false,
        ]);

        return $account;
    }

    /**
     * رفع الصورة
     */
    private function uploadImage($folder, $image)
    {
        $path = public_path($folder);
        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }
        // الامتداد من نوع الملف الفعلي لا من اسمه — ملفٌ باسم `x.php` لا يُحفظ `.php`
        $fileExtension = $image->extension() ?: 'png';
        $fileName = time().rand(1, 99).'.'.$fileExtension;
        $image->move($path, $fileName);

        return $folder.'/'.$fileName;
    }

    /**
     * حذف الصورة القديمة
     */
    private function deleteOldImage($imagePath)
    {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }
}
