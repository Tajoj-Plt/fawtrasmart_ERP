<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Account;
use App\Services\Wallets\SupplierWalletService;
use App\Models\Branch;
use App\Models\DocumentTemplate;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\PaymentsProcess;
use App\Models\PurchaseInvoice;
use App\Models\Revenue;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Support\SupplierGuard;
use App\Support\DocumentPrintPage;

class SupplierManagementController extends Controller
{
    use ScopesToEmployeeHierarchy;

    /** نوع المستند في شاشة «قوالب المستندات» — كشف حساب المورّد */
    public const DOCUMENT_TYPE = 'supplier_statement';

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'supplier';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمورّد */
    public const LOG_MODULE = 'supplier';

    public function __construct()
    {
        // كان هذا الكنترولر بلا أي حراسة middleware إطلاقاً — الفحوص القديمة
        // بالاسم القديم (inv_manage_suppliers_*) داخل edit()/update()/destroy()
        // تُقيِّد النطاق فقط (كل الموردين أو مورّدو المستخدم) ولا تمنع من لا
        // يملك أياً من الاسمين، وindex/create/store/show كانت بلا فحصٍ مطلقاً.
        // الحراسة هنا من كتالوج purchases.suppliers.* الجديد — بوابة دخول
        // حقيقية، والفحوص القديمة تبقى كما هي لتضييق النطاق فوقها لا حراسةً.
        $this->middleware('permission:purchases.suppliers.view', ['only' => ['index', 'show', 'getStats']]);
        $this->middleware('permission:purchases.suppliers.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:purchases.suppliers.edit', ['only' => ['edit', 'update', 'updateStatus']]);
        $this->middleware('permission:purchases.suppliers.delete', ['only' => ['destroy']]);
        $this->middleware('permission:purchases.suppliers.view_statement', ['only' => [
            'supplierStatement', 'statementPrint', 'statementPdf', 'showJournalEntryDetails',
        ]]);
        $this->middleware('permission:purchases.suppliers.manage_opening_balance', ['only' => ['updateSupplierOpeningBalance']]);
        $this->middleware('permission:purchases.suppliers.assign_employee', ['only' => ['assignEmployee']]);
    }

    public function index(Request $request)
    {
        $query = Supplier::query();

        // جلب المستخدم الحالي
        $user = auth()->user();

        // نطاق البيانات: من أنشأ المورّد أو من نُسب إليه — نفس ما تقصّ به قوائمُ الموردين
        $query = $this->scopeSuppliers($query);

        /* فرعُ الشريط العلويّ وما يتبعه — المورِّدون بلا فرعٍ يبقون في كلّ جهة.
           ويصمت على محور الأشخاص كبقيّة شاشات القسم. */
        if (! $this->scopeIsPeopleAxis()) {
            \Modules\Purchases\Support\HeaderPickers::suppliers($query);
        }

        /*
         * قائمة «أضيف بواسطة» — من أنشأ مورّداً فعلاً، لا قائمةَ أدوارٍ ثابتة.
         *
         * كانت `User::where('role', 'manager')`: تقرأ عمود `users.role` النصّي
         * الذي قد ينحرف عن أدوار Spatie، وتحصر القائمة باسم دورٍ واحد مكتوب
         * في الشيفرة — فمن أنشأ مورّداً وهو بدورٍ آخر (أو بدورٍ عربيّ الاسم)
         * لا يظهر في القائمة أصلاً، ويتعذّر التصفية بما أنشأه.
         *
         * والقائمة الآن مشتقّة من البيانات نفسها ومقيَّدة بنطاق رؤية المستخدم:
         * لا تعرض إلا من تظهر سجلّاته له، فلا تتسرّب منها أسماء لا يراها.
         */
        $creatorIds = $this->scopeToCreator(\Modules\Purchases\Support\HeaderPickers::suppliers())
            ->whereNotNull('created_by')
            ->distinct()
            ->pluck('created_by');

        $users = User::whereIn('id', $creatorIds)->orderBy('name')->get(['id', 'name']);

        // قائمة «الموظّف المسؤول» — على نطاق الفرع نفسه، لا موظّفي المنشأة كلِّها
        $employees = \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::orderBy('first_name'))->get();

        // البحث بواسطة اسم المورد أو الرقم التعريفي
        if ($request->filled('employee_search')) {
            $query->where(function ($q) use ($request) {
                $q->where('id', $request->employee_search)
                    ->orWhere('trade_name', 'LIKE', '%'.$request->employee_search.'%');
            });
        }

        // البحث برقم المورد
        if ($request->filled('supplier_number')) {
            $query->where('number_suply', 'LIKE', '%'.$request->supplier_number.'%');
        }

        // البحث بالبريد الإلكتروني
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%'.$request->email.'%');
        }

        // البحث برقم الجوال
        if ($request->filled('mobile')) {
            $query->where('mobile', 'LIKE', '%'.$request->mobile.'%');
        }

        // البحث برقم الهاتف
        if ($request->filled('phone')) {
            $query->where('phone', 'LIKE', '%'.$request->phone.'%');
        }

        // البحث بالعنوان
        if ($request->filled('address')) {
            $query->where('full_address', 'LIKE', '%'.$request->address.'%');
        }

        // البحث بالرمز البريدي
        if ($request->filled('postal_code')) {
            $query->where('postal_code', 'LIKE', '%'.$request->postal_code.'%');
        }

        // فلتر العملة حُذف: العملة واحدةٌ من «العملات» فلا معنى للتصفية بها

        // البحث بالحالة
        if ($request->filled('status')) {
            $status = $request->status == 'active' ? 1 : 0;
            $query->where('status', $status);
        }

        // البحث بالرقم الضريبي
        if ($request->filled('tax_number')) {
            $query->where('tax_number', 'LIKE', '%'.$request->tax_number.'%');
        }

        // البحث بالسجل التجاري
        if ($request->filled('commercial_registration')) {
            $query->where('commercial_registration', 'LIKE', '%'.$request->commercial_registration.'%');
        }

        // البحث بواسطة المستخدم الذي أضاف
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // البحث بواسطة الموظّف المسؤول عن المورّد
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // عدد السجلات في الصفحة يتبع اختيار المستخدم، وكان ثابتاً عند 10
        // فلا يفعل مُنتقي «عرض» شيئاً
        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        /* البطاقاتُ على المجموعة المصفّاة نفسِها التي يعرضها الجدول — كانت
           تُحسب مرّةً عند أوّل تحميلٍ بلا تصفية ولا تتبدّل بعدها: تُوقف
           مورّداً فيبقى «متوقّف = 0»، وتصفّي فتبقى الأرقامُ على الكلّ. */
        $stats = $this->summary(clone $query);

        // الرصيد يُعرَض في الجدول، فيُحمَّل الحساب مسبقاً بدل استعلامٍ لكل صفّ
        $suppliers = $query->with('account')->orderBy('created_at', 'desc')->paginate($perPage);

        // للحفاظ على parameters البحث في روابط الترقيم
        $suppliers->appends($request->all());

        // إذا كان الطلب عبر AJAX، إرجاع البيانات فقط
        if ($request->ajax()) {
            return response()->json([
                'html' => view('purchases::purchases.supplier_management.partials.suppliers_table', compact('suppliers'))->render(),
                'pagination' => view('purchases::purchases.supplier_management.partials.pagination', compact('suppliers'))->render(),
                // الشاشة تحدّث عدّاد النتائج من هذا المفتاح — كان غائباً فيبقى
                // العدّاد على رقم أول تحميل مهما تغيّرت التصفية
                'total' => $suppliers->total(),
                'stats' => $stats,
            ]);
        }

        // حالة حدّ اشتراك فوترة لميزة «الموردون» — وهي ميزةٌ **معرَّفة فعلاً**
        // في كتالوج فوترة (سقفٌ متزامن لعدد الموردين)، فالشارة تظهر والفحص
        // في store() يمنع فعلاً عند بلوغ السقف — بخلاف مفاتيح المشتريات
        // الأخرى التي لم تُعرَّف بعد (انظر تعليق UsageReporter::catalog()).
        $subscriptionLimit = app(SubscriptionGate::class)->limit('suppliers');

        return view('purchases::purchases.supplier_management.index', compact('suppliers', 'users', 'employees', 'stats', 'subscriptionLimit'));
    }

    public function create()
    {
        // معاينة رقم المورّد التالي وفق «تسلسل أرقام المستندات» — لا
        // `max(id) + 1` الذي يتجاهل البادئة وعدد الخانات وإعادة التصفير
        // المضبوطة في شاشة الإعدادات (قسم supplier في DocumentSections)
        $nextNumber = peek_document_number(self::NUMBER_SECTION);
        $branches = Branch::orderBy('name')->get();
        // «الموظّف المسؤول» — على فرع الشريط العلويّ وما يتبعه، كبقيّة
        // قوائم الموظفين في الوحدة
        // موظّفُ المستخدم الحالي يبقى في القائمة ولو كان خارج أقسام
        // الإعدادات: هو المحدَّدُ مسبقاً والمنسوبُ إليه المورّد عند الحفظ
        $employees = \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::orderBy('first_name'), array_filter([auth()->user()->employee_id]))->get();

        return view('purchases::purchases.supplier_management.create', compact('nextNumber', 'branches', 'employees'));
    }

    private function generateNextCode(string $lastChildCode): string
    {
        // استخراج الرقم الأخير من الكود
        $lastNumber = intval(substr($lastChildCode, -1));
        // زيادة الرقم الأخير بمقدار 1
        $newNumber = $lastNumber + 1;

        // إعادة بناء الكود مع الرقم الجديد
        return substr($lastChildCode, 0, -1).$newNumber;
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);

        // فحص "خاصة/كل" أُزيل مع الانتقال إلى purchases.suppliers.edit —
        // انظر التعليق المطابق في update()
        $nextNumber = Supplier::max('id');
        $branches = Branch::orderBy('name')->get();
        // المسؤولُ الحاليّ يبقى في القائمة ولو صار خارج أقسام الإعدادات،
        // وإلا فقدت الشاشةُ التعيينَ القائم بمجرّد فتحها
        $employees = \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::orderBy('first_name'), array_filter([$supplier->employee_id]))->get();

        return view('purchases::purchases.supplier_management.edit', compact('supplier', 'nextNumber', 'branches', 'employees'));
    }

    public function store(SupplierRequest $request)
    {
        try {
            // اشتراك فوترة وحدوده — «الموردون» سقفٌ متزامن معرَّفٌ في كتالوج
            // فوترة، فهذا فحصٌ فعليّ يمنع عند بلوغه لا استثناءٌ دائم السماح.
            // ويُفحص **قبل** فتح المعاملة: لا معنى لفتح معاملةٍ تُرَدّ فوراً.
            $subscriptionGate = app(SubscriptionGate::class);
            if (! $subscriptionGate->allows('suppliers')) {
                return back()->withInput()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «الموردون». راجع لوحة تحكّم فوترة للترقية.');
            }
            $supplierQuota = $subscriptionGate->check('suppliers');
            if (! $supplierQuota['allowed']) {
                return back()->withInput()->with('error', $supplierQuota['reason']);
            }

            DB::beginTransaction();

            // التحقق من البيانات
            $validated = $request->validated();
            // يستهلك الرقم فعلياً هنا — نفس ما تفعله شاشات المشتريات الأخرى
            // عبر next_document_number(self::NUMBER_SECTION)
            $nextNumber = next_document_number(self::NUMBER_SECTION);

            // معالجة الحقول التي لا تقبل NULL

            // العملةُ من إعدادات النظام لا من الطلب: الحقلُ في الشاشة
            // للقراءة فقط، و`??` كانت تقبل ما يُرسَل يدوياً متجاوزاً ذلك
            $validated['currency'] = \App\Support\CurrencySettings::code();

            // الفرع يُعيّن تلقائياً من فرع المستخدم الحالي — إلا لمن يملك
            // صلاحية اختيار فرع آخر صراحة — نفس نمط PurchaseRequestsController::store()
            $validated['branch_id'] = \App\Support\Scopes::chosenBranchId(auth()->user(), $validated['branch_id'] ?? null);

            // الموظّفُ المسؤول: من أضاف المورّدَ يُنسَب إليه مباشرةً ما لم
            // يُختَر غيرُه — فيراه في نطاقه ويُحاسَب عليه في التقارير
            // (طلب المستخدم 2026-09-21). المستخدمُ بلا موظّفٍ (المدير) يترك
            // الحقلَ كما أُرسل.
            $validated['employee_id'] = $validated['employee_id'] ?? null ?: auth()->user()->employee_id;

            // إنشاء المورد
            $supplier = Supplier::create(
                array_merge($validated, [
                    'number_suply' => $nextNumber,
                    'created_by' => auth()->id(),
                ])
            );

            // إنشاء الحساب المحاسبي
            $this->createSupplierAccount($supplier);

            // معالجة المرفقات
            $this->handleAttachment($request, $supplier);

            // إضافة جهات الاتصال
            $this->addContacts($request, $supplier);

            // تسجيل اشعار نظام
            ModelsLog::create([
                'type' => 'Supplier',
                'type_id' => $supplier->id,
                'type_log' => 'log',
                'description' => 'تم اضافة مورد جديد **'.$supplier->trade_name.'**',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('SupplierManagement.index')->with('success', 'تم إضافة المورد بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Supplier Store Error: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }
    // دالة جلب حركة الحساب (نفس المنطق المستخدم في supplierStatement)

    // استخدام نفس الدوال الموجودة في supplierStatement

    public function update(SupplierRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = auth()->user();
            $supplier = Supplier::findOrFail($id);
            $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);

            // فحص "خاصة/كل" أُزيل مع الانتقال إلى purchases.suppliers.edit
            // (صلاحية ثنائية واحدة) — الفهرس هو ما يُقيَّد بنطاق البيانات
            // (Scopes::applyDataScope في index())، لا التعديل نفسه

            $validated = $request->validated();

            // معالجة الحقول التي لا تقبل NULL
            $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
            // العملةُ من الإعدادات لا من الطلب — كما في store()
            $validated['currency'] = \App\Support\CurrencySettings::code();
            $validated['updated_by'] = auth()->id();

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحةً ويملك
            // صلاحية ذلك — نفس نمط PurchaseRequestsController::update()
            $validated['branch_id'] = \App\Support\Scopes::chosenBranchId($user, $validated['branch_id'] ?? null, $supplier->branch_id);

            // الاحتفاظ بالقيم القديمة إذا كانت الجديدة فارغة
            foreach ($validated as $key => $value) {
                if (is_null($value) && $supplier->$key) {
                    $validated[$key] = $supplier->$key;
                }
            }

            // حفظ البيانات القديمة للمقارنة
            $oldTradeName = $supplier->trade_name;
            $oldOpeningBalance = $supplier->opening_balance ?? 0;

            // تحديث بيانات المورد
            $supplier->fill($validated);
            $supplier->save();

            // معالجة الحساب المحاسبي
            $this->handleSupplierAccount($supplier, $oldTradeName, $oldOpeningBalance);

            // معالجة المرفقات
            if ($request->hasFile('attachments')) {
                $this->handleAttachment($request, $supplier);
            }

            // تحديث جهات الاتصال
            if ($request->has('contacts')) {
                $supplier->contacts()->delete();
                $this->addContacts($request, $supplier);
            }

            // تسجيل اشعار نظام
            ModelsLog::create([
                'type' => 'Supplier',
                'type_id' => $supplier->id,
                'type_log' => 'log',
                'description' => 'تم تحديث المورد **'.$supplier->trade_name.'**',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('SupplierManagement.index')->with('success', 'تم تحديث المورد بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Supplier Update Error: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            // التحقق من صحة البيانات
            $request->validate([
                'status' => 'required|in:0,1',
            ]);

            // العثور على المورد
            $supplier = Supplier::findOrFail($id);
            $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);

            // حفظ الحالة القديمة للمقارنة
            $oldStatus = $supplier->status;

            // مورّد له رصيدٌ — بأي اتجاه — لا يُوقَف، ريثما يُسوَّى
            if ((int) $request->status === 0) {
                try {
                    SupplierGuard::assertDeactivatable($supplier);
                } catch (\Exception $e) {
                    if ($request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => $e->getMessage(),
                        ], 422);
                    }

                    return redirect()->back()->with('error', $e->getMessage());
                }
            }

            // تحديث الحالة
            $supplier->status = $request->status;
            $supplier->updated_at = now();
            $supplier->save();

            // تحديد نص الرسالة حسب الحالة الجديدة
            $statusText = $request->status == 1 ? 'تم تفعيل' : 'تم إيقاف';
            $message = "{$statusText} المورد \"{$supplier->trade_name}\" بنجاح";

            // إذا كان الطلب عبر AJAX
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'supplier_id' => $supplier->id,
                    'new_status' => $supplier->status,
                    'supplier_name' => $supplier->trade_name,
                ]);
            }

            // إذا لم يكن AJAX، إعادة توجيه عادية
            return redirect()->route('SupplierManagement.index')->with('success', $message);

        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات المرسلة غير صحيحة',
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors());

        } catch (ModelNotFoundException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'المورد غير موجود',
                ], 404);
            }

            return redirect()->route('SupplierManagement.index')->with('error', 'المورد غير موجود');

        } catch (\Exception $e) {
            // تسجيل الخطأ في اللوق
            Log::error('خطأ في تحديث حالة المورد: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى',
                ], 500);
            }

            return redirect()->back()->with('error', 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى');
        }
    }

    // إضافة دالة للحصول على إحصائيات الموردين (اختيارية)
    public function getStats()
    {
        try {
            // الأعدادُ على فرع الشريط العلويّ — نفسُ ما يعرضه الجدول
            $activeCount = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()))->where('status', 1)->count();
            $inactiveCount = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()))->where('status', 0)->count();
            $totalCount = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()))->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'active' => $activeCount,
                    'inactive' => $inactiveCount,
                    'total' => $totalCount,
                ],
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierManagement', 'getStats');

            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات',
            ], 500);
        }
    }

    private function createSupplierAccount($supplier)
    {
        // الحساب الأب «الموردون» كما رُبط في شاشة «الحسابات المحاسبية»،
        // والثابت البرمجي احتياطٌ إن لم يُربط المفتاح بعد
        $creditorsAccount = AccountingSettings::account('suppliers');

        // إذا لم يتم العثور على الحساب الأب (في حال كان محذوفًا)
        if (! $creditorsAccount) {
            Log::warning('لم يتم العثور على حساب الموردون الرئيسي المضبوط في «الحسابات المحاسبية»');
            // إنشاء حساب الموردون الرئيسي إذا لم يكن موجودًا
            $creditorsAccount = Account::create([
                'id' => AccountingSettings::id('suppliers'), // تثبيت نفس المعرف
                'name' => 'الموردون',
                'code' => '2100',
                'account_type' => 'liability', // نوع الحساب: خصوم
                'balance_type' => 'credit', // نوع الرصيد: دائن
                'is_active' => true,
                'parent_id' => null,
            ]);
        }

        // إنشاء حساب المورد الجديد كحساب فرعي
        $supplierAccount = new Account;
        $supplierAccount->name = $supplier->trade_name;
        $supplierAccount->supplier_id = $supplier->id;

        // تعيين نوع الحساب المالي ليكون مطابقًا للحساب الأب
        $supplierAccount->account_type = $creditorsAccount->account_type;

        // توليد كود جديد للحساب
        $newCode = $this->generateSupplierAccountCode($creditorsAccount->id, $creditorsAccount->code);

        $supplierAccount->code = $newCode;
        $supplierAccount->balance_type = 'credit';
        $supplierAccount->parent_id = $creditorsAccount->id;
        $supplierAccount->is_active = true;

        // تعيين الرصيد الافتتاحي ونوعه
        $supplierAccount->opening_balance = 0;  // الرصيد الافتتاحي يتم تحديثه لاحقاً من خلال دالة updateSupplierOpeningBalance
        $supplierAccount->opening_balance_type = $creditorsAccount->balance_type;

        $supplierAccount->save();
    }

    private function handleSupplierAccount($supplier, $oldTradeName, $oldOpeningBalance)
    {
        // البحث عن الحساب المحاسبي للمورد
        $supplierAccount = Account::ledger()->where('supplier_id', $supplier->id)->first();

        if ($supplierAccount) {
            // تحديث اسم الحساب إذا تغير الاسم التجاري
            if ($oldTradeName !== $supplier->trade_name) {
                $supplierAccount->name = $supplier->trade_name;
            }

            // التأكد من أن الحساب فرعي لحساب الدائنون
            $creditorsAccount = Account::where('name', 'الدائنون')->first();
            if ($creditorsAccount && $supplierAccount->parent_id != $creditorsAccount->id) {
                $supplierAccount->parent_id = $creditorsAccount->id;

                // تحديث الكود ليكون فرعياً من كود الحساب الأب
                $newCode = $this->generateSupplierAccountCode($creditorsAccount->id, $creditorsAccount->code);
                $supplierAccount->code = $newCode;
            }

            // التعامل مع تغيير الرصيد الافتتاحي
            $newOpeningBalance = $supplier->opening_balance ?? 0;
            if ($oldOpeningBalance != $newOpeningBalance) {
                $this->updateOpeningBalance($supplier, $supplierAccount, $oldOpeningBalance, $newOpeningBalance);
            }

            $supplierAccount->save();
        } else {
            // إذا لم يكن هناك حساب محاسبي، إنشاء واحد جديد
            $this->createSupplierAccount($supplier);
        }
    }

    private function updateOpeningBalance($supplier, $supplierAccount, $oldBalance, $newBalance)
    {
        // تحديث الرصيد الافتتاحي ونوعه
        $supplierAccount->opening_balance = $newBalance;
        // تحديد نوع الرصيد الافتتاحي ليكون مطابقًا لنوع الحساب
        $supplierAccount->opening_balance_type = $supplierAccount->balance_type;
        $supplierAccount->save();

        // سيتم إنشاء أو تحديث قيد الرصيد الافتتاحي تلقائيًا من خلال نموذج الحساب
    }

    /**
     * الرصيد الافتتاحي للمورد — دائنٌ دائماً، بلا اختيار اتجاه.
     *
     * خلاف العميل (رصيده الافتتاحي دائماً **عليه لنا**، مدينٌ هو)، رصيد
     * المورد الافتتاحي دائماً **له علينا** — بضاعةٌ استُلمت ولم تُسدَّد
     * قيمتها بعد. لا حالة عكسية تحتاج خياراً هنا بقرار صريح من المستخدم —
     * موردٌ سُلِّمت له دفعةٌ مقدَّمة يُسجَّل عبر محفظته من شاشتها لا من هذا
     * النموذج.
     */
    /**
     * تعيين المورّد لموظّفٍ مسؤول — من صفحة العرض.
     *
     * القائمةُ في الشاشة مقيَّدةٌ بموظّفي نطاق المستخدم وأقسام الإعدادات
     * ({@see HeaderPickers::employees()})، ويُعاد الفحصُ هنا لأن الطلبَ قد
     * يُرسَل مباشرةً. قيمةٌ فارغة تُلغي التعيين.
     */
    public function assignEmployee(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);

        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ], [
            'employee_id.exists' => 'الموظّف المختار غير موجود.',
        ]);

        // «بلا موظّف» تصل سلسلةً فارغة من النموذج — تُقرأ إلغاءً للتعيين
        $employeeId = ($validated['employee_id'] ?? null) !== null && $validated['employee_id'] !== '' ? (int) $validated['employee_id'] : null;

        /* المسموحُ هو ما عرضته النافذةُ نفسُها: موظّفو النطاق والأقسام مع
           المسؤول الحاليّ ولو كان خارجهما — كان الفحصُ بلا «الإبقاء» فيرفض
           إعادةَ اختيار المسؤول الحاليّ بعد تبديل فرع الشريط. */
        $allowed = \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::query(), array_filter([$supplier->employee_id]));

        if ($employeeId && ! $allowed->whereKey($employeeId)->exists()) {
            return redirect()->back()->with('error', $this->assignRejectionReason($employeeId));
        }

        $previous = $supplier->employee?->full_name;
        $supplier->update(['employee_id' => $employeeId, 'updated_by' => auth()->id()]);
        $supplier->load('employee');

        ModelsLog::create([
            'type' => 'Supplier',
            'type_id' => $supplier->id,
            'type_log' => 'log',
            'description' => $employeeId
                ? 'تم تعيين المورد **'.$supplier->trade_name.'** للموظف **'.$supplier->employee?->full_name.'**'.($previous ? ' بدلاً من **'.$previous.'**' : '')
                : 'تم إلغاء تعيين الموظف المسؤول عن المورد **'.$supplier->trade_name.'**',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('SupplierManagement.show', $supplier->id)
            ->with('success', $employeeId ? 'تم تعيين المورد للموظف بنجاح' : 'تم إلغاء تعيين الموظف المسؤول');
    }

    /**
     * لماذا رُفض الموظّف؟ — «خارج نطاقك» وحدَها لا تقول للمستخدم ما يفعل.
     */
    protected function assignRejectionReason(int $employeeId): string
    {
        $employee = \App\Models\Employee::with('department', 'branch')->find($employeeId);
        $departments = \Modules\Purchases\Services\PurchaseSettings::employeeDepartments();

        if ($employee && $departments !== null && ! in_array((int) $employee->department_id, $departments, true)) {
            return 'الموظّف «'.$employee->full_name.'» ليس ضمن الأقسام المحدَّدة في إعدادات المشتريات'
                .($employee->department ? ' (قسمه: '.$employee->department->name.')' : ' (بلا قسم)').'.';
        }

        $nodeIds = \Modules\Purchases\Support\HeaderPickers::nodeIds();

        if ($employee && $nodeIds !== null && $employee->branch_id && ! in_array((int) $employee->branch_id, $nodeIds, true)) {
            return 'الموظّف «'.$employee->full_name.'» تابع لـ«'.($employee->branch->name ?? '#'.$employee->branch_id).'» خارج الفرع المختار في الشريط العلوي. بدّل الفرع في الشريط إلى الرئيسي أو إلى فرعه ثم أعد المحاولة.';
        }

        return 'الموظّف المختار خارج نطاق رؤيتك.';
    }

    public function updateSupplierOpeningBalance(Request $request, $id)
    {
        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_date' => ['nullable', 'date'],
        ], [
            'opening_balance.required' => 'أدخل مبلغ الرصيد الافتتاحي.',
            'opening_balance.numeric' => 'مبلغ الرصيد الافتتاحي يجب أن يكون رقماً.',
            'opening_balance.min' => 'المبلغ لا يكون سالباً.',
        ]);

        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($id);
            $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);

            // الحصول على الحساب المرتبط بالمورد
            $Account = Account::ledger()->where('supplier_id', $id)->first();

            if (! $Account) {
                DB::rollBack();

                return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا المورد');
            }

            $amount = (float) $validated['opening_balance'];
            $date = $validated['opening_balance_date'] ?? null;

            // ** الفترة المحاسبية ** — تاريخٌ داخل فترة مقفلة يُردّ قبل الكتابة،
            // فالقيد الافتتاحي قيدٌ في اليومية كغيره
            if ($date && ($blocked = \App\Support\AccountingPeriodGuard::blockReason($date))) {
                DB::rollBack();

                return redirect()->back()->withInput()->with('error', $blocked);
            }

            $previousAmount = (float) ($Account->opening_balance ?? 0);

            $Account->opening_balance = $amount;
            $Account->opening_balance_type = Account::CREDIT;
            $Account->save();

            // الجدولان يتطابقان: التقارير تقرأ من `suppliers` والقيد يُبنى من `accounts`
            $supplier->opening_balance = $amount;
            $supplier->opening_balance_date = $date ?: ($supplier->opening_balance_date ?: now()->toDateString());
            $supplier->save();

            // أيّ رصيدٍ سابقٍ عبر المحفظة (من قبل إزالة خيار الاتجاه) يُصفَّى:
            // الاتجاه الآن دائنٌ دائماً، فلا يبقى أثرٌ لتسجيلٍ سابقٍ عكسه
            SupplierWalletService::clearOpeningBalance($supplier);

            if ($Account->opening_balance != 0) {
                $Account->createOrUpdateOpeningBalanceJournalEntry();
            } else {
                $Account->deleteExistingOpeningBalanceEntries();
            }

            // أثرٌ في سجلّ النشاطات: تغييرُ رصيدٍ افتتاحي يُحرّك الدفاتر،
            // فلا يجوز أن يمرّ بلا اسمِ من غيّره وقيمتَيه قبلُ وبعد
            OperationLog::record(
                'updated',
                'Purchases.SupplierManagement',
                $supplier->id,
                sprintf(
                    'تم تحديث الرصيد الافتتاحي للمورد **%s** من %s إلى %s',
                    $supplier->trade_name,
                    number_format($previousAmount, 2),
                    number_format($amount, 2),
                ),
            );

            DB::commit();

            return redirect()->route('SupplierManagement.show', $id)->with('success', 'تم تحديث الرصيد الافتتاحي بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierManagement', 'updateSupplierOpeningBalance');

            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function generateSupplierAccountCode($parentAccountId, $parentCode)
    {
        // البحث عن آخر حساب فرعي تحت حساب الدائنون
        $lastChild = Account::where('parent_id', $parentAccountId)
            ->orderBy('code', 'desc')
            ->first();

        // إذا لم يوجد أي حساب فرعي، ابدأ بـ 1
        if (! $lastChild) {
            return $parentCode.'01'; // استخدام تنسيق موحد مع أصفار بادئة
        }

        // استخراج الرقم من آخر الكود وزيادته
        $lastNumber = (int) substr($lastChild->code, strlen($parentCode));
        $newNumber = $lastNumber + 1;
        $newCode = $parentCode.str_pad($newNumber, 2, '0', STR_PAD_LEFT); // ضمان طول الرقم بخانتين

        // التأكد من أن الكود غير مستخدم
        while (Account::where('code', $newCode)->exists()) {
            $newNumber++;
            $newCode = $parentCode.str_pad($newNumber, 2, '0', STR_PAD_LEFT);
        }

        return $newCode;
    }

    /**
     * معالجة المرفقات للمورد
     */
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);

        // فحص "خاصة/كل" أُزيل مع الانتقال إلى purchases.suppliers.delete —
        // انظر التعليق المطابق في update()

        // مورّد له فواتير أو قيود أو رصيد لا يُحذف — لا مفتاح أجنبي يمنع
        // ذلك في القاعدة، فتبقى مستنداته يتيمةً تشير إلى مورّد لا وجود له
        try {
            SupplierGuard::assertDeletable($supplier);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierManagement', 'destroy');

            return redirect()->back()->with(['error' => $e->getMessage()]);
        }

        ModelsLog::create([
            'type' => 'purchase_log',
            'type_id' => $supplier->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم حذف المورد  **'.$supplier->trade_name.'**',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);
        $supplier->delete();

        return redirect()->route('SupplierManagement.index')->with('success', 'تم حذف المورد بنجاح');
    }

    private function handleAttachment(Request $request, Supplier $supplier)
    {
        if ($request->hasFile('attachments')) {
            $file = $request->file('attachments');
            if ($file->isValid()) {
                // حذف الملف القديم إذا كان موجودًا
                if ($supplier->attachments && file_exists(public_path('assets/uploads/suppliers/'.$supplier->attachments))) {
                    unlink(public_path('assets/uploads/suppliers/'.$supplier->attachments));
                }

                // رفع الملف الجديد
                $filename = time().'_'.$file->getClientOriginalName();
                $file->move(public_path('assets/uploads/suppliers'), $filename);
                $supplier->attachments = $filename;
                $supplier->save();
            }
        }
    }

    private function addContacts(Request $request, Supplier $supplier)
    {
        if ($request->has('contacts') && is_array($request->contacts)) {
            foreach ($request->contacts as $contact) {
                if (! empty($contact['first_name'])) {
                    $supplier->contacts()->create([
                        'first_name' => $contact['first_name'],
                        'last_name' => $contact['last_name'] ?? null,
                        'phone' => $contact['phone'] ?? null,
                        'mobile' => $contact['mobile'] ?? null,
                        'email' => $contact['email'] ?? null,
                    ]);
                }
            }
        }
    }

    public function show($id)
    {
        $supplier = Supplier::with(['account', 'employee'])->findOrFail($id);
        $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);

        /* قوائمُ المستندات بنطاق المستخدم — كانت تعرض فواتيرَ المنشأة
           كلِّها ومدفوعاتِها لمن لا يرى في فهرس الفواتير إلّا سجلّاته،
           فكان ملفُّ المورّد باباً خلفياً على ما تحجبه الفهارس.
           و`authorizeCreatorScope` أعلاه يحرس صفَّ المورّد وحدَه لا ما
           تحته.

           ودفترُ الحساب والرصيدُ أدناه يبقيان بلا قيد عمداً: رصيدُ
           المورّد حقيقةٌ محاسبيةٌ عنه هو، لا حصيلةُ ما يراه هذا
           المستخدم — وتقييدُه يُخرج رقماً خاطئاً لا رقماً مقيَّداً. */
        $scopeDocuments = fn ($query) => $this->scopeToBranchNode($this->scopeToCreator($query));

        /* المدفوعاتُ عمودُ منشئها `employee_id` لا `created_by` — الجدولُ
           `payments_process` لا يملك الثاني أصلاً، فتقييدُه به يرمي
           «Unknown column». وهو العمودُ نفسُه الذي يُقيَّد به فهرسُ
           المدفوعات ({@see PurchasePaymentController}). */
        $scopePayments = fn ($query) => $this->scopeToBranchNode($this->scopeToCreator($query, 'employee_id'));

        // جلب فواتير الشراء (فقط الفواتير العادية، مع استبعاد المرتجعات)
        $purchaseInvoices = $scopeDocuments(
            PurchaseInvoice::where('supplier_id', $id)->where('type', 'invoice')
        )->latest()->paginate(10, ['*'], 'invoices');

        // جلب فواتير المرتجعات
        $returnInvoices = $scopeDocuments(
            PurchaseInvoice::where('supplier_id', $id)->where('type', 'Return')
        )->with(['originalInvoice'])->latest()->paginate(10, ['*'], 'returns');

        // جلب المدفوعات
        $payments = $scopePayments(PaymentsProcess::where('supplier_id', $id))
            ->with(['purchase_invoice'])->latest()->paginate(10, ['*'], 'payments');

        $logs = ModelsLog::where('type', 'Supplier')
            ->where('type_id', $id)
            ->whereHas('Supplier')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        /* الكشفُ المحاسبي (حركةُ الحساب ورصيدُه ومحفظتُه) لمن يرى الكلَّ
           وحدَه. رصيدُ المورّد حقيقةٌ واحدة لا تُجزَّأ: تقييدُ حركاته
           بنطاق مستخدمٍ يُخرج رصيداً لا يقابله شيءٌ في دفتر الأستاذ —
           فالأصدق حجبُ الكشف عمّن نطاقُه جزئيٌّ لا اختلاقُ رقمٍ له. وتبقى
           له قوائمُ المستندات وإحصاءاتُها أعلاه بنطاقه. */
        $canSeeLedger = $this->accessibleUserIds() === null;

        // جلب حركة الحساب للمورد
        $accountMovements = [];
        if ($canSeeLedger && $supplier->account) {
            $account = $supplier->account;
            $accountId = $account->id;

            // جلب العمليات المالية
            $transactions = $this->getTransactions($accountId);
            $transfers = $this->getTransfers($accountId);
            $expenses = $this->getExpenses($accountId);
            $revenues = $this->getRevenues($accountId);

            // معالجة العمليات وحساب الرصيد
            $accountMovements = $this->processOperations($transactions, $transfers, $expenses, $revenues, $account);

            // ترتيب العمليات من الأحدث للأقدم
            usort($accountMovements, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }

        /* إحصاءاتُ رأس الصفحة — نظيرُ ما تعرضه شاشة «عرض العميل».
         *
         * كانت الشاشة تفتح على بطاقة اسمٍ ورصيدٍ ثمّ جدولِ «عنوان/قيمة»،
         * فلا يُعرف من أوّل نظرةٍ كم اشترينا من هذا المورد ولا كم عليه
         * ولا متى آخر تعامل — وهي الأسئلة التي تُفتح الشاشةُ من أجلها.
         *
         * تُحسَب استعلاماتٍ مجمَّعة لا بتحميل الفواتير كلِّها: المورّد قد
         * يحمل آلاف الفواتير، والمرقِّمُ أعلاه يجلب عشرةً منها فقط. */
        /* وبنطاق المستندات نفسِه: رقمُ البطاقة يجمع ما تسرده الجداول
           تحتها، فلو بقي عارياً لقرأ المستخدمُ إجماليَّ المنشأة فوق
           جدولٍ يعرض سجلّاته وحدَها. `wallet_balance` وحدَها تبقى
           حقيقةً محاسبيةً غيرَ مقيَّدة. */
        $invoiceAggregate = $scopeDocuments(
            PurchaseInvoice::where('supplier_id', $id)->where('type', 'invoice')
        )->selectRaw('COUNT(*) AS c, COALESCE(SUM(grand_total), 0) AS total, COALESCE(SUM(due_value), 0) AS due, MAX(date) AS last_date')
            ->first();

        $stats = [
            'total_purchases' => (float) ($invoiceAggregate->total ?? 0),
            'invoice_count' => (int) ($invoiceAggregate->c ?? 0),
            'due_value' => (float) ($invoiceAggregate->due ?? 0),
            'last_purchase' => $invoiceAggregate->last_date ?? null,
            'returns_total' => (float) $scopeDocuments(
                PurchaseInvoice::where('supplier_id', $id)->where('type', 'Return')
            )->sum('grand_total'),
            'paid_total' => (float) $scopePayments(
                PaymentsProcess::where('supplier_id', $id)
            )->sum('amount'),
            'wallet_balance' => $canSeeLedger ? SupplierWalletService::balance($supplier) : null,
        ];

        // قائمةُ «تعيين لموظّف» — موظّفو النطاق مع الموظّف الحاليّ إن كان خارجه
        $assignableEmployees = hasPermission('purchases.suppliers.assign_employee')
            ? \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::orderBy('first_name'), array_filter([$supplier->employee_id]))->get()
            : collect();

        return view('purchases::purchases.supplier_management.show', compact(
            'assignableEmployees',
            'supplier',
            'purchaseInvoices',
            'returnInvoices',
            'payments',
            'logs',
            'accountMovements',
            'stats',
            'canSeeLedger'
        ));
    }

    /**
     * عرض كشف حساب المورد
     */
    public function supplierStatement(Request $request, $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);
        $this->assertLedgerVisible();
            $account = Account::ledger()->where('supplier_id', $id)->first();

            if (! $account) {
                return redirect()->back()->with('error', __('purchase_cycle.supplier.no_account'));
            }

            // جلب تاريخ البداية والنهاية من الطلب
            $fromDate = $request->query('from_date');
            $toDate = $request->query('to_date');

            // بناء الاستعلام للقيود المحاسبية
            $journalEntriesQuery = JournalEntryDetail::where('account_id', $account->id)
                ->with('journalEntry:id,date,description,reference_number,status');

            // تطبيق فلاتر التاريخ إذا تم توفيرها
            if ($fromDate) {
                $journalEntriesQuery->whereHas('journalEntry', function ($q) use ($fromDate) {
                    $q->whereDate('date', '>=', Carbon::parse($fromDate));
                });
            }

            if ($toDate) {
                $journalEntriesQuery->whereHas('journalEntry', function ($q) use ($toDate) {
                    $q->whereDate('date', '<=', Carbon::parse($toDate));
                });
            }

            /* من الأقدم إلى الأحدث على **تاريخ القيد** ثمّ رقمه — لا على
               `created_at` السطر: القيدُ المؤرَّخ بأثرٍ رجعي كان يقع في غير
               موضعه فيختلّ الرصيدُ الجاري. وهو ترتيبُ المطبوعة نفسُه
               ({@see \App\Services\DocumentTemplateDataService}) فتتطابق الشاشةُ
               والورقةُ صفّاً بصفّ. */
            $journalEntries = $journalEntriesQuery
                ->get()
                ->sortBy(fn ($detail) => [$detail->journalEntry->date ?? '', $detail->id])
                ->values();

            // حساب الرصيد والعمليات
            $operations = $this->buildStatementOperations($account, $journalEntries, $fromDate, $toDate);

            // الترقيم
            $perPage = 20;
            $currentPage = $request->query('page', 1);
            $operationsPaginator = new LengthAwarePaginator(
                $operations->forPage($currentPage, $perPage)->values(),
                $operations->count(),
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            // الرصيد النهائي = رصيد آخر عملية زمنياً (آخر صفّ)
            $finalBalance = $operations->isNotEmpty() ? $operations->last()['balance'] : $account->balance;

            // حساب إجماليات الفترة للهيدر
            $totalDebit = $journalEntries->sum('debit');
            $totalCredit = $journalEntries->sum('credit');

            $companyName = config('app.name', 'الشركة');

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'supplier' => $supplier,
                    'account' => $account,
                    'operations' => $operationsPaginator->items(),
                    'finalBalance' => $finalBalance,
                    'pagination' => (string) $operationsPaginator->links(),
                ]);
            }

            return view('purchases::purchases.supplier_management.statement', [
                'supplier' => $supplier,
                'account' => $account,
                'operationsPaginator' => $operationsPaginator,
                'finalBalance' => $finalBalance,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
                'companyName' => $companyName,
                // قوالب كشف الحساب المصمَّمة في «قوالب المستندات» — قائمة
                // «المطبوعات» تُبنى منها لا من زرَّي طباعة مكتوبين في الشاشة
                'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
                'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
                // المعاينة والتنزيل يتبعان «طريقة الطباعة» في إعدادات الحساب
                'prefersPdf' => PrintSettings::prefersPdf(),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierManagement', 'supplierStatement');

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * معاينة كشف الحساب بقالبٍ من «قوالب المستندات».
     *
     * الشاشة كانت تطبع بقالبٍ مكتوبٍ داخل `statement.blade.php`: لا يعرفه
     * محرّر القوالب، ولا يتبع ألوان «مظهر النظام»، ولا يقرأ بيانات المنشأة
     * من إعداداتها. صارت المطبوعة تُبنى من القوالب المزروعة لنوع
     * `supplier_statement`، وللمستخدم أن يصمّم غيرها من الشاشة نفسها.
     */
    public function statementPrint(Request $request, $id, $template = 'default')
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);
        $this->assertLedgerVisible();

        $html = $this->renderStatementTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderStatementTemplate($id, $fallback->id) : '';
        }

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $supplier,
            __('purchase_cycle.log.printed', [
                'document' => __('purchase_cycle.supplier.statement'),
                'code' => $supplier->trade_name,
            ]),
        );

        return response(DocumentPrintPage::wrap($html));
    }

    /** تنزيل كشف الحساب PDF بالقالب المختار، وإلا الافتراضي للنوع */
    public function statementPdf(Request $request, $id, $template = null)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorizeCreatorScope($supplier->created_by, $supplier->employee_id);
        $this->assertLedgerVisible();

        $html = $this->renderStatementTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderStatementTemplate($id, $fallback->id) : null;
        }

        if ($html === null) {
            return redirect()->back()->with('error', __('purchase_cycle.messages.no_template'));
        }

        OperationLog::record(
            'exported',
            self::LOG_MODULE,
            $supplier,
            __('purchase_cycle.log.exported', [
                'document' => __('purchase_cycle.supplier.statement'),
                'code' => $supplier->trade_name,
            ]),
        );

        return Pdf::loadHTML($html)->download(($supplier->number_suply ?: $supplier->id).'-statement.pdf');
    }

    /**
     * يصيّر قالب كشف حساب من الإعدادات — أو `null` إن لم يكن `$template`
     * معرّفَ قالبٍ صالح لهذا النوع.
     */
    private function renderStatementTemplate($supplierId, $template): ?string
    {
        if (! is_numeric($template)) {
            return null;
        }

        $model = DocumentTemplate::where('id', (int) $template)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->first();

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->getDocumentData(self::DOCUMENT_TYPE, $supplierId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'render_template', $supplierId);

            return null;
        }
    }

    /**
     * أعداد بطاقات ملخّص شاشة الموردين.
     *
     * @return array<string, int>
     */
    /**
     * بطاقاتُ الشاشة على الاستعلام المصفّى نفسِه الذي يعرضه الجدول.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Supplier>  $base  نطاقُ الرؤية وفرعُ الشريط وفلاترُ البحث، قبل الترقيم
     * @return array{total: int, active: int, inactive: int, due: int}
     */
    private function summary($base): array
    {
        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 1)->count(),
            'inactive' => (clone $base)->where('status', 0)->count(),
            /* «عليه مستحقّات» = رصيدُ حسابه الدفتريّ دائنٌ — لا «له فاتورةٌ
               لم تُسدَّد»: فاتورةٌ رُدّت كاملةً بمرتجعٍ تبقى `due_value`
               عليها كما هي، وكان المورّدُ يُعدّ مستحقّاً ورصيدُه صفر، فيخالف
               العددُ هنا تقريرَ الأرصدة وأعمارِ الديون. وحسابُ المحفظة يُستبعد. */
            'due' => (clone $base)->whereHas('account', fn ($q) => $q->ledger()->where('balance', '>', 0))->count(),
        ];
    }

    /**
     * بناء قائمة العمليات مع الأرصدة الجارية
     */
    private function buildStatementOperations($account, $journalEntries, $fromDate = null, $toDate = null)
    {
        $operations = collect();
        $balanceType = $account->balance_type ?? 'credit'; // الموردون عادة دائنون

        $running = 0;
        $processed = [];

        foreach ($journalEntries as $entry) {
            $debit = (float) ($entry->debit ?? 0);
            $credit = (float) ($entry->credit ?? 0);

            if ($balanceType === 'debit') {
                $running += ($debit - $credit);
            } else {
                $running += ($credit - $debit);
            }

            $processed[] = [
                'id' => $entry->id,
                'date' => $entry->journalEntry->date,
                'reference_number' => $entry->journalEntry->reference_number,
                'description' => $entry->description ?? $entry->journalEntry->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
                'journal_entry_id' => $entry->journal_entry_id,
            ];
        }

        /* بالترتيب الزمني كما حُسب الرصيدُ الجاري — كان يُعكَس فتبدأ
           الصفحةُ الأولى بآخر حركة ويقع أوّلُ حركةٍ في آخر صفحة، بينما
           الرصيدُ في كلّ صفٍّ محسوبٌ من الأقدم. فصار كشفُ الحساب يُفتح على
           أوّل حركةٍ ويتراكم رصيدُه نزولاً، كالمطبوعة. */
        foreach ($processed as $item) {
            $operations->push($item);
        }

        return $operations;
    }

    /**
     * الكشفُ المحاسبي (حركات الحساب، الرصيد، القيود) لمن يرى الكلَّ وحدَه.
     *
     * رصيدُ المورّد حقيقةٌ واحدة لا تُجزَّأ بنطاق مستخدم — فالأصدق حجبُه
     * عمّن نطاقُه جزئيٌّ لا اختلاقُ رقمٍ له. `show()` تحجب اللسانَ بالشرط
     * نفسِه؛ وكانت شاشةُ الكشف وطباعتُه وقيودُه تُفتح بالرابط مباشرةً.
     */
    private function assertLedgerVisible(): void
    {
        abort_if($this->accessibleUserIds() !== null, 403,
            'كشف الحساب المحاسبي يُعرض كاملاً لمن يملك رؤية بيانات المشتريات كلِّها — نطاقُك مقيَّد.');
    }

    /**
     * عرض تفاصيل قيد محاسبي
     */
    public function showJournalEntryDetails($journalEntryId)
    {
        try {
            // القيدُ جزءٌ من الكشف المحاسبي — بشرطه نفسِه (رؤية الكل)
            $this->assertLedgerVisible();

            $journalEntry = JournalEntry::with('details.account')
                ->findOrFail($journalEntryId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $journalEntry->id,
                    'date' => $journalEntry->date,
                    'reference_number' => $journalEntry->reference_number,
                    'description' => $journalEntry->description,
                    'status' => $journalEntry->status,
                    'details' => $journalEntry->details->map(function ($detail) {
                        return [
                            'account_id' => $detail->account_id,
                            'account_name' => $detail->account->name,
                            'account_code' => $detail->account->code,
                            'description' => $detail->description,
                            'debit' => $detail->debit,
                            'credit' => $detail->credit,
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierManagement', 'showJournalEntryDetails');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    private function getTreasury($id)
    {
        return Account::findOrFail($id);
    }

    private function processOperations($transactions, $transfers, $expenses, $revenues, $treasury)
    {
        $currentBalance = $treasury->opening_balance ?? 0;
        $allOperations = [];
        $allItems = [];

        // إضافة المعاملات
        foreach ($transactions as $transaction) {
            $allItems[] = [
                'type' => 'transaction',
                'data' => $transaction,
                'date' => $transaction->journalEntry->date ?? $transaction->created_at,
            ];
        }

        // إضافة المصروفات
        foreach ($expenses as $expense) {
            $allItems[] = [
                'type' => 'expense',
                'data' => $expense,
                'date' => $expense->date ?? $expense->created_at,
            ];
        }

        // إضافة الإيرادات
        foreach ($revenues as $revenue) {
            $allItems[] = [
                'type' => 'revenue',
                'data' => $revenue,
                'date' => $revenue->date ?? $revenue->created_at,
            ];
        }

        // ترتيب العمليات حسب التاريخ
        usort($allItems, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // معالجة العمليات
        foreach ($allItems as $item) {
            switch ($item['type']) {
                case 'transaction':
                    $transaction = $item['data'];
                    $amount = $transaction->debit > 0 ? $transaction->debit : $transaction->credit;
                    $isDebit = $transaction->debit > 0;

                    // للموردين:
                    // Debit (مدين) = دفعة للمورد = تقليل الدين على الشركة
                    // Credit (دائن) = فاتورة شراء = زيادة الدين على الشركة

                    if ($isDebit) {
                        $operationType = 'دفعة';
                        $currentBalance -= $amount;  // الدفعة تقلل الدين
                        $deposit = 0;
                        $withdraw = $amount;
                    } else {
                        $operationType = 'فاتورة';
                        $currentBalance += $amount;  // الفاتورة تزيد الدين
                        $deposit = $amount;
                        $withdraw = 0;
                    }

                    $allOperations[] = [
                        'operation' => $transaction->description ?? $operationType,
                        'deposit' => $deposit,
                        'withdraw' => $withdraw,
                        'balance_after' => $currentBalance,
                        'journalEntry' => $transaction->journalEntry->id ?? null,
                        'date' => $transaction->journalEntry->date ?? $transaction->created_at,
                        'invoice' => $transaction->journalEntry->invoice ?? null,
                        'client' => $transaction->journalEntry->client ?? null,
                        'type' => $operationType,
                        'reference_number' => $transaction->journalEntry->reference_number ?? null,
                    ];
                    break;

                case 'expense':
                    $expense = $item['data'];
                    // سند صرف للمورد = دفعة = تقليل الدين
                    $currentBalance -= $expense->amount;

                    $allOperations[] = [
                        'operation' => 'سند صرف: '.$expense->description,
                        'deposit' => 0,
                        'withdraw' => $expense->amount,
                        'balance_after' => $currentBalance,
                        'date' => $expense->date ?? $expense->created_at,
                        'invoice' => null,
                        'client' => $expense->client ?? null,
                        'type' => 'سند صرف',
                        'reference_number' => $expense->expense_number ?? null,
                    ];
                    break;

                case 'revenue':
                    $revenue = $item['data'];
                    // سند قبض من المورد = استرداد = زيادة الدين (نادر)
                    $currentBalance += $revenue->amount;

                    $allOperations[] = [
                        'operation' => 'سند قبض: '.$revenue->description,
                        'deposit' => $revenue->amount,
                        'withdraw' => 0,
                        'balance_after' => $currentBalance,
                        'date' => $revenue->date ?? $revenue->created_at,
                        'invoice' => null,
                        'client' => null,
                        'type' => 'سند قبض',
                        'reference_number' => $revenue->revenue_number ?? null,
                    ];
                    break;
            }
        }

        return $allOperations;
    }

    private function getBranches()
    {
        return Branch::all();
    }

    private function getTransactions($id)
    {
        return JournalEntryDetail::where('account_id', $id)
            ->with([
                'journalEntry' => function ($query) {
                    $query->with('invoice', 'client');
                },
            ])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getTransfers($id)
    {
        return JournalEntry::whereHas('details', function ($query) use ($id) {
            $query->where('account_id', $id);
        })
            ->with(['details.account'])
            ->where('description', 'تحويل المالية')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getExpenses($id)
    {
        return Expense::where('treasury_id', $id)
            ->with(['expenses_category', 'vendor', 'employee', 'branch', 'client'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getRevenues($id)
    {
        return Revenue::where('treasury_id', $id)
            ->with(['account', 'paymentVoucher', 'treasury', 'bankAccount', 'journalEntry'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function updateBalance($currentBalance, $amount, $type)
    {
        // For suppliers: debit (إيداع) reduces debt, credit (سحب) increases debt
        return $type === 'إيداع' ? $currentBalance - $amount : $currentBalance + $amount;
    }

    private function paginateOperations($allOperations)
    {
        $perPage = 15;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedOperations = array_slice($allOperations, $offset, $perPage);

        return new LengthAwarePaginator($paginatedOperations, count($allOperations), $perPage, $currentPage, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
