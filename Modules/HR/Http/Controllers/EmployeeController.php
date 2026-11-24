<?php

namespace Modules\HR\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Exports\EmployeesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Mail\TestMail;
use App\Models\Account;
use App\Models\Branch;
use App\Models\DefaultWarehouses;
use App\Models\Department;
use App\Models\Ecosystem\RewardRedemption;
use App\Models\Employee;
use App\Models\EmployeeAttachment;
use App\Models\EmployeeGroup;
use App\Models\FunctionalLevels;
use App\Models\JopTitle;
use App\Models\Log as ModelsLog;
use App\Models\Role;
use App\Models\Shift;
use App\Models\SmtpSetting;
use App\Models\StoreHouse;
use App\Models\Treasury;
use App\Models\TreasuryEmployee;
use App\Models\TypesJobs;
use App\Models\User;
use App\Services\EndOfServiceBenefitService;
use App\Support\HrSettings;
use App\Support\OperationLog;
use App\Support\PayrollAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Services\ContractService;
use Modules\HR\Support\EmployeeDeletionGuard;

class EmployeeController extends Controller
{
    use ScopesToAccessibleEmployees;

    /**
     * سببُ فشل إرسال بيانات الدخول في هذا الطلب — إن فشل.
     *
     * كان الفشل يُبتلع في `laravel.log` وحده: يُحفَظ الموظف بكلمة مرورٍ
     * جديدة لا يعرفها أحد، ويُقال للمسؤول «تم بنجاح». فيُظنّ الحسابُ
     * جاهزاً وهو مُقفَلٌ فعلياً. صار يُرفع مع رسالة النجاح كتحذير.
     */
    private ?string $credentialsMailError = null;

    public function __construct()
    {
        /*
            كان المتحكّم يحرس ثلاثةَ أفعالٍ فقط، وما عداها مفتوحٌ لكل من
            سجّل دخوله — وفيه ما يرفع الامتياز فعلاً:

              • `login_to` تسجّل الدخول **بحساب موظّفٍ آخر**.
              • `updatePassword` تُبدّل كلمةَ مرور موظّفٍ آخر.
              • `add_new_role` تمنح دوراً وظيفياً.
              • `toggleSystemAccess` تفتح وصولَ النظام أو تُغلقه.

            وإخفاءُ أزرارها من الشاشة لا يحمي شيئاً: المسارُ يُطلب بعنوانه.
            فالحراسةُ هنا شرطُ أن يكون لإخفاء الأزرار معنى.
        */

        /*
            كلُّ فعلٍ بصلاحيته (`employees.*`)، وحسابُ الدخول مفصولٌ عن ملفّ
            الموظف (`employee_accounts.*`). و«على أيّ موظفين» يحدّده نطاقُ
            الرؤية (`data_scope_*`) في كل فعلٍ بعينه — لا صلاحيةُ «هو».
        */
        $this->middleware('permission.role:employees.index', ['only' => ['index', 'search']]);
        $this->middleware('permission.role:employees.show', ['only' => [
            'show', 'downloadAttachment', 'downloadAttachmentsZip', 'ledgerAccountFormContext',
        ]]);
        $this->middleware('permission.role:employees.export', ['only' => ['export', 'export_view']]);
        $this->middleware('permission.role:employees.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:employees.edit', ['only' => [
            'edit', 'update', 'updateStatus', 'uploadImage', 'deleteAttachment',
            'provisionLedgerAccountsForEmployee', 'syncEmployeeLedgerAccountsFromProfile',
        ]]);
        // والحذفُ النهائي لا يقع إلا على موظفٍ بلا أثر — حارسُه الحقيقي `EmployeeDeletionGuard`
        $this->middleware('permission.role:employees.destroy', ['only' => ['delete']]);
        // إنهاءُ الخدمة مفصولٌ عن التعديل عمداً — أثرُه لا يُسترجَع بنقرة
        $this->middleware('permission.role:employees.terminate', ['only' => ['terminate']]);
        // الأرشفةُ والاستردادُ — و`archive` باقيةٌ في القائمة رغم تعليق مسارها: الحراسةُ تسبق عودتَه
        $this->middleware('permission.role:employees.archive', ['only' => ['archive', 'archived', 'restore']]);

        // حسابُ الدخول — أفعالٌ ترفع الامتياز أو تمسّ الدخول، كلٌّ بصلاحيته
        $this->middleware('permission.role:employee_accounts.access', ['only' => ['toggleSystemAccess']]);
        $this->middleware('permission.role:employee_accounts.password', ['only' => ['updatePassword', 'send_email']]);
        $this->middleware('permission.role:employee_accounts.roles', ['only' => ['employee_management', 'add_new_role']]);
        $this->middleware('permission.role:employee_accounts.login_as', ['only' => ['login_to']]);
        // «إدارة الورديات» القديمة (قالبٌ ساكن) — شاشةُ ورديات
        $this->middleware('permission.role:shifts.index', ['only' => ['manage_shifts']]);
        $this->middleware('permission.role:shifts.create', ['only' => ['add_shift']]);

        $this->middleware('permission.role:hr_dashboard.index', ['only' => ['dashboardhr']]);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $query = Employee::query()->with(['job_title', 'direct_manager', 'department', 'branch', 'job_role']);

        // قائمةُ الموظفين نفسُها تخضع للنطاق: عمودُ المفتاح هنا `id` لا `employee_id`.
        $this->scopeToEmployees($query, 'id');

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'LIKE', "%{$term}%")
                    ->orWhere('middle_name', 'LIKE', "%{$term}%")
                    ->orWhere('nickname', 'LIKE', "%{$term}%")
                    ->orWhere('email', 'LIKE', "%{$term}%")
                    ->orWhere('employee_code', 'LIKE', "%{$term}%")
                    ->orWhere('id', $term);
            });
        }

        // حالاتُ الموظف الست كلها [\App\Enums\EmploymentStatus] لا `0`/`1` وحدهما
        $validStatusValues = array_map(
            fn (EmploymentStatus $case) => (string) $case->value,
            EmploymentStatus::cases()
        );
        if ($request->filled('status') && in_array((string) $request->input('status'), $validStatusValues, true)) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('type') && in_array((string) $request->input('type'), ['1', '2'], true)) {
            $query->where('employee_type', (int) $request->input('type'));
        }

        if ($request->filled('role')) {
            $query->where('Job_role_id', (int) $request->input('role'));
        }

        if ($request->filled('manager_id')) {
            $query->where('direct_manager_id', (int) $request->input('manager_id'));
        }

        if ($request->filled('job_title_id')) {
            $query->where('job_title_id', (int) $request->input('job_title_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }

        if ($request->filled('job_type_id')) {
            $query->where('job_type_id', (int) $request->input('job_type_id'));
        }

        if ($request->filled('job_level_id')) {
            $query->where('job_level_id', (int) $request->input('job_level_id'));
        }

        $employees = $query->orderBy('id', 'DESC')->paginate($perPage)->appends($request->query());

        // كل الموظفين اللي عندهم أبناء = اللي يصلحون كمدير أب في الفلترة
        $managerIds = Employee::whereNotNull('direct_manager_id')->distinct()->pluck('direct_manager_id');
        $managers = $this->scopeToEmployees(Employee::whereIn('id', $managerIds), 'id')
            ->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'nickname']);
        $job_titles = JopTitle::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $job_types = TypesJobs::orderBy('name')->get(['id', 'name']);
        $job_levels = FunctionalLevels::orderBy('name')->get(['id', 'name']);
        $job_roles = Role::orderBy('name')->get(['id', 'name']);
        $employment_statuses = EmploymentStatus::cases();

        return view('hr::employee.index', compact(
            'employees', 'managers', 'job_titles', 'departments',
            'job_types', 'job_levels', 'job_roles', 'employment_statuses'
        ));
    }

    public function send_email($id)
    {
        $this->authorizeEmployeeScope($id);

        $employee = User::where('employee_id', $id)->first();

        if (! $employee) {
            return redirect()
                ->back()
                ->with(['error' => 'الموظف غير موجود']);
        }

        if (empty($employee->email)) {
            return redirect()
                ->back()
                ->with(['error' => 'لا يوجد بريد إلكتروني مسجَّل لهذا الموظف، فلا وجهة للإرسال.']);
        }

        try {
            // توليد كلمة مرور جديدة عشوائية
            $newPassword = $this->generateRandomPassword();

            $details = [
                'name' => $employee->name,
                'email' => $employee->email,
                'password' => $newPassword, // إرسال كلمة المرور الجديدة مباشرة
            ];

            /*
                الإرسالُ **قبل** الحفظ عمداً.

                كانت كلمة المرور تُبدَّل وتُحفَظ أوّلاً ثم يُحاوَل الإرسال؛
                فإن سقط الإرسال — خطأ مصادقة SMTP مثلاً — بقي الحسابُ على
                كلمةٍ عشوائية لا يعرفها أحد: لا الموظفُ الذي لم يصله بريد،
                ولا المرسِلُ الذي لم تُعرَض له. فتُقفِل الزرَّ الذي غرضُه
                الفتح. لا تُكتب الكلمة الجديدة إلا بعد أن يخرج البريد فعلاً.
            */
            Mail::to($employee->email)->send(new TestMail($details));

            $employee->password = Hash::make($newPassword);
            $employee->save();
            OperationLog::record('logged', 'hr_log', $employee, 'تم ارسال بيانات الدخول **'.$employee->name.'**');

            return redirect()
                ->back()
                ->with(['success' => 'تم  ارسال البريد بنجاح .']);
        } catch (\Exception $exception) {
            OperationLog::failure($exception, 'HR.Employee', 'send_email');

            return redirect()
                ->back()
                ->with(['error' => $this->describeMailFailure($exception)]);
        }
    }

    /**
     * يترجم عطبَ البريد إلى جملةٍ تدلّ على موضع الإصلاح لا على مكتبة الإرسال.
     *
     * رسالةُ Symfony الخام («Failed to authenticate on SMTP server …
     * 535 5.7.8») صحيحةٌ ولا تقول للمسؤول أين يذهب. وأشيعُ أسبابها هنا
     * واحد: النظامُ يعمل بإعدادات `.env` النموذجية لأن شاشة SMTP لم
     * تُملأ قط، فيُصادَق ببريدٍ وكلمةٍ ليسا لهذه المنشأة.
     */
    private function describeMailFailure(\Throwable $exception): string
    {
        $raw = $exception->getMessage();
        $sender = config('mail.mailers.smtp.username') ?: '—';
        $host = config('mail.mailers.smtp.host') ?: '—';
        $source = SmtpSetting::query()->exists()
            ? 'شاشة إعدادات البريد (SMTP)'
            : 'ملف البيئة .env — لم تُحفَظ أي إعدادات في شاشة SMTP بعد';

        if (str_contains($raw, 'authenticate') || str_contains($raw, '535')) {
            return 'رفض خادم البريد بيانات الدخول (535): المستخدم «'.$sender.'» على «'.$host.'». '
                .'مصدر الإعدادات الحالي: '.$source.'. '
                .'صحّح اسم المستخدم وكلمة المرور من الإعدادات ← البريد (SMTP)، ثم أعد المحاولة. '
                .'لم تُغيَّر كلمة مرور الموظف.';
        }

        return 'تعذّر إرسال البريد عبر «'.$host.'» ('.$source.'): '.$raw.' — لم تُغيَّر كلمة مرور الموظف.';
    }

    /**
     * دالة لتوليد كلمة مرور عشوائية
     */
    private function generateRandomPassword($length = 10)
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }

    /**
     * كلمةُ المرور الأوّلية لحساب موظف: هاتفُه، فجوّالُه، فعشوائيّةٌ.
     *
     * والعشوائيّةُ ليست تجميلاً: `Hash::make('')` تُنتج هاشاً صحيحاً
     * للسلسلة الفارغة، و`AuthController::login()` لا يفرض كلمةَ مرورٍ على
     * الطلب أصلاً — فحسابٌ كلمتُه فارغةٌ يدخله من يعرف البريد وحده.
     *
     * وصار الفراغُ ممكناً منذ أن صار رقما التواصل اختياريَّين في تعديل
     * السجلّات القديمة، فالحارسُ هنا لا هناك. وحسابٌ بكلمةٍ عشوائية لا
     * يُدخَل حتى تُرسَل بياناتُه أو تُعاد كلمتُه — وذلك أسلمُ من فراغ.
     */
    private function initialPasswordFor(?string $phone, ?string $mobile): string
    {
        $candidate = trim((string) ($phone ?: $mobile));

        return $candidate !== '' ? $candidate : $this->generateRandomPassword();
    }

    private function sendCredentialsEmail(User $user, string $plainPassword): bool
    {
        $this->credentialsMailError = null;

        if (empty($user->email)) {
            $this->credentialsMailError = 'لا يوجد بريد إلكتروني مسجَّل لهذا الموظف.';

            return false;
        }

        $details = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $plainPassword,
        ];

        try {
            Mail::to($user->email)->send(new TestMail($details));

            OperationLog::record('logged', 'hr_log', $user->employee_id ?? $user->id, 'تم إرسال بيانات الدخول تلقائياً للموظف **'.$user->name.'**');

            return true;
        } catch (\Exception $e) {
            \Log::error('فشل إرسال بريد بيانات الدخول للموظف '.$user->email.': '.$e->getMessage());

            $this->credentialsMailError = $this->describeMailFailure($e);

            return false;
        }
    }

    public function create()
    {
        $shifts = Shift::select('id', 'name')->get();
        $branches = $this->branchesWithTreeLabelsForEmployeeForms();
        $job_types = TypesJobs::select('id', 'name')->get();
        $job_levels = FunctionalLevels::select('id', 'name')->get();
        $job_titles = JopTitle::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();
        $job_roles = Role::select('id', 'name')->get();
        // منتقي «المدير المباشر» من نطاق الرؤية
        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name'), 'id')->get();
        $advance_parent_accounts = Account::where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'parent_id']);
        // كل الحسابات النشطة في شجرة الحسابات — يمكن اختيار أي واحد كحساب سلف للموظف
        $existing_advance_accounts = Account::where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'name', 'code']);
        $current_advance_parent_id = $this->resolveEmployeeAdvanceParentId();

        /*
            رقمُ الموظف من شاشة «الترقيم المتسلسل» — معاينةٌ بلا استهلاك.

            `peek` لا `next`: المستخدم قد يفتح النموذج ويتركه، واستهلاكُ
            الرقم عند العرض يُحدث ثغرةً في التسلسل بلا موظفٍ يقابلها.
            الاستهلاكُ عند الحفظ في `store()` — وهو نمطُ العقود
            (`ContractsController`) وأذون المخزن نفسُه.
        */
        try {
            $nextEmployeeCode = peek_document_number('employee');
        } catch (\Throwable $e) {
            // إعداداتُ ترقيمٍ معطوبة يجب ألّا تُغلق شاشةَ إضافة موظف —
            // كما تفعل `GeneratesDocumentNumbers::previewDocumentNumber()`
            $nextEmployeeCode = null;
        }

        return view('hr::employee.create', compact(
            'employees', 'job_roles', 'departments', 'job_titles', 'job_levels',
            'job_types', 'branches', 'shifts', 'nextEmployeeCode',
            'advance_parent_accounts', 'existing_advance_accounts', 'current_advance_parent_id'
        ));
    }

    public function store(EmployeeRequest $request)
    {
        // التحقق من فرادة البريد قبل بدء المعاملة حتى لا تبقى معاملة معلّقة
        // عند فشل التحقق (ValidationException لا يجب أن تُلتقط كخطأ عام).
        $request->validate(
            [
                // الإلزامُ من «إعدادات الموظفين» كما في `EmployeeRequest` —
                // ولا يُكتب `required` هنا مرّةً ثانيةً، فنسختان تفترقان
                'email' => [
                    HrSettings::employeeFieldRequired('email') ? 'required' : 'nullable',
                    'email',
                    'unique:users,email',
                ],
            ],
            [
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
                'email.email' => 'يجب أن يكون البريد الإلكتروني صالحًا.',
                'email.required' => 'حقل البريد الإلكتروني مطلوب.',
            ],
        );

        // «إنشاء عقد للموظف» (اختياري) — يُتحقَّق منه قبل المعاملة كالبريد،
        // ويُنشأ العقدُ داخلها بعد حفظ الموظف. انظر `ContractService`.
        $contractInput = app(ContractService::class)->validateEmployeeContract($request);

        try {
            DB::beginTransaction();

            $employee_data = $request->except(
                '_token',
                'allow_system_access',
                'send_credentials',
                // مصفوفةُ الفروع المختارة — `employees.branch_id` وحدَه عمود،
                // ويُملأ بأوّلها في `EmployeeRequest::prepareForValidation()`
                'branch_ids',
                // مواقعُ الفروع — لا عمودَ لها في `employees`، تُزامَن عبر
                // `syncEmployeeBranches()` أدناه لا عبر `create()`/`update()`
                'allowed_branches',
                // لا عمودَ `password` في `employees` — الكلمةُ تخصّ حسابَ
                // الدخول في `users`، وتمريرُها إلى النموذج يُفشل الحفظ
                'password',
                'password_confirmation',
                'reset_password',
                'groups',
                'directions',
                'create_default_treasury',
                'default_treasury_name',
                'create_default_storehouse',
                'default_storehouse_names',
                // معرّفاتُ تعييناتِ المستودعات — لا عمودَ لها في `employees` كذلك
                'default_storehouse_ids',
                'advance_parent_account_id',
                'existing_advance_account_id',
                'financial_date_mode'
            );

            if ($request->input('financial_date_mode', 'default') !== 'custom') {
                $employee_data['custom_financial_month'] = null;
                $employee_data['custom_financial_day'] = null;
            }

            $employee_data['created_by'] = Auth::id();

            /*
                الاستهلاكُ الفعلي للرقم — داخل المعاملة وعند الحفظ وحده.

                ولا يُقبل رقمٌ من الطلب: حقلُ النموذج للعرض فقط (`readonly`)،
                ولو قُرئ منه لَمَلَك المستخدمُ فرضَ أيّ رقم على التسلسل.
                و`DocumentNumberService::next()` تعمل تحت قفلٍ وتتخطّى
                المحجوز فعلاً، فمستخدمان يفتحان الشاشة معاً لا يتصادمان.

                والابتلاعُ عند الإخفاق مقصود: خللٌ في إعدادات الترقيم يجب
                ألّا يمنع تسجيل موظف — يُحفظ بلا رقم كالقائمين قبله،
                ويُملأ لاحقاً.
            */
            try {
                $employee_data['employee_code'] = next_document_number('employee');
            } catch (\Throwable $e) {
                $employee_data['employee_code'] = null;
                OperationLog::failure($e, 'HR.Employee', 'employee_code');
            }

            $isUserType = (int) $request->input('employee_type') === 2;
            $createDefaultTreasury = $isUserType && $request->boolean('create_default_treasury');
            $createDefaultStorehouse = $isUserType && $request->boolean('create_default_storehouse');

            /*
                كانت هذه القيم تُضبط على كائن Employee مؤقّت (`new Employee`)
                ثم يُستدعى عليه `->create($employee_data)` — وهذا يُنشئ سجلّاً
                *جديداً* بالكامل من `$employee_data` وحدها، فلا يمسّ الكائن
                المؤقّت الذي ضُبطت عليه هذه القيم إطلاقاً. فتُحفظ صورة
                الموظف وخانتا «السماح بالدخول» و«إرسال بيانات الدخول» بصمت
                رغم تفعيلها في النموذج.
            */
            if ($request->hasFile('employee_photo')) {
                $employee_data['employee_photo'] = $this->UploadImage('assets/uploads/employee', $request->employee_photo);
            }

            /*
                حسابُ الدخول له صلاحياتُه لا صلاحيةُ الإضافة: فتحُ الدخول
                (`employee_accounts.access`) وكلمةُ المرور وإرسالُها
                (`employee_accounts.password`). من لا يملكهما يُضيف الموظفَ
                ودخولُه مغلقٌ على كلمته الافتراضية — ولو أُرسلت الحقولُ يدوياً.
            */
            $canAccess = hasPermission('employee_accounts.access');
            $canPassword = hasPermission('employee_accounts.password');

            $employee_data['allow_system_access'] = $canAccess && $request->has('allow_system_access') ? 1 : 0;
            $employee_data['send_credentials'] = $canPassword && $request->has('send_credentials') ? 1 : 0;

            // الدورُ يمنح صلاحياتِ الحساب كلَّها — بصلاحيته وحدها (`employee_accounts.roles`)
            $canRoles = hasPermission('employee_accounts.roles');
            if (! $canRoles) {
                $employee_data['Job_role_id'] = null;
            }

            $new_employee = Employee::create($employee_data);

            $this->storeEmployeeAttachments($new_employee, $request);

            $advanceParentInput = $request->input('advance_parent_account_id');
            $advanceParentInputId = is_numeric($advanceParentInput) ? (int) $advanceParentInput : null;
            $existingAdvanceInput = $request->input('existing_advance_account_id');
            $existingAdvanceInputId = is_numeric($existingAdvanceInput) ? (int) $existingAdvanceInput : null;

            $liabilityAccount = $this->createEmployeeLiabilityAccount($new_employee);
            $advanceAccount = $this->resolveAdvanceAccountForEmployee($new_employee, $existingAdvanceInputId, $advanceParentInputId);
            $this->linkEmployeeAccounts($new_employee, $liabilityAccount, $advanceAccount);

            if ($createDefaultTreasury) {
                $treasuryName = $this->resolveDefaultResourceName(
                    $request->input('default_treasury_name'),
                    $new_employee,
                    'خزينة'
                );

                $this->createDefaultTreasuryForEmployee($new_employee, $treasuryName);
            }

            if ($createDefaultStorehouse && $request->has('default_storehouse_names')) {
                foreach ($request->default_storehouse_names as $name) {
                    $storehouseName = $this->resolveDefaultResourceName($name, $new_employee, 'مستودع');
                    $this->createDefaultStorehouseForEmployee($new_employee, $storehouseName);
                }
            }

            // عقدُ الموظف وبنودُ راتبه — في المعاملة نفسِها كالخزينة والمستودع
            if ($contractInput) {
                app(ContractService::class)->createForEmployee($new_employee, $contractInput);
            }

            /*
                ترتيبُ من يملك كلمةَ المرور — المكتوبُ أوّلاً:

                  ١. ما كُتب في حقل الكلمة، إن كُتب شيء.
                  ٢. عشوائيّةٌ تُولَّد، إن طُلب «إرسالُ بيانات الدخول» والحقلُ فارغ.
                  ٣. رقمُ الهاتف (فالجوّال) — الافتراضُ القديم.

                و«الإرسالُ» ليس مصدراً للكلمة بل قناةً لها: أيّاً كانت
                المعتمدةُ فهي التي تُرسَل، فلا تصل الموظفَ كلمةٌ غيرُ العاملة.
                كان الإرسالُ يتقدّم فيولّد عشوائيّةً تُهمِل المكتوب، فاضطُرّ
                النموذجُ إلى تعطيل حقلَي الكلمة كي لا يُهمَل مكتوبٌ بصمت —
                فامتنع الجمعُ بين الطريقتين. والحقلان الآن مفتوحان.
            */
            $shouldSendCredentials = $canPassword && $request->has('send_credentials');
            $passwordWasTyped = $canPassword && $request->filled('password');
            $initialPassword = $passwordWasTyped
                ? (string) $request->input('password')
                : ($shouldSendCredentials
                    ? $this->generateRandomPassword()
                    : $this->initialPasswordFor($request->phone_number, $request->mobile_number));

            $user = User::create([
                'name' => $new_employee->full_name,
                'email' => $request->email,
                'phone' => $request->phone_number,
                'role' => 'employee',
                'employee_id' => $new_employee->id,
                'branch_id' => $request->branch_id,

                'password' => Hash::make($initialPassword),
            ]);

            // حفظ الفروع المختارة + إلحاق كل أسلافها تلقائياً، مع مواقعها المختارة وأسلافها
            $this->syncEmployeeBranches(
                (int) $new_employee->id,
                $this->selectedBranchIds($request),
                (array) $request->input('allowed_branches', [])
            );

            /*
                كانت $user->assignRole($role->name) وحدها — تكتب في جدول
                Spatie القديم (model_has_roles) ولا تمسّ users.role_id، وهو
                المصدر الوحيد الذي يقرأه فحص الصلاحيات الفعلي
                (hasRolePermission، CheckPermissionRole). فيُنشأ المستخدم
                بلا دورٍ فعلي رغم اختيار "الدور الوظيفي" في النموذج.
                assignPrimaryRole() تكتب role_id والاسمين معاً.
            */
            $role = $canRoles ? \Spatie\Permission\Models\Role::find($request->Job_role_id) : null;

            if ($role) {
                $user->assignPrimaryRole($role);
            }

            OperationLog::record('created', 'hr_log', $new_employee, 'تم إضافة موظف جديد **'.$new_employee->full_name.'**');

            DB::commit();

            /*
                البريدُ بعد `commit` لا قبله: الإرسالُ فعلٌ لا يُلغى، وإرساله
                داخل المعاملة يعني أن تراجُعاً بعده يترك الموظفَ حاملاً بيانات
                دخولٍ لحسابٍ لم يُنشأ قط.
            */
            if ($shouldSendCredentials) {
                $this->sendCredentialsEmail($user, $initialPassword);
            }

            $flash = ['success' => 'تم إضافة الموظف بنجاح !!'];

            if ($this->credentialsMailError !== null) {
                /*
                    الكلمةُ المعتمدةُ قد تكون المكتوبةَ وقد تكون عشوائيّة، فحالُ
                    الحساب بعد فشل الإرسال يختلف: المكتوبةُ يعرفها من كتبها
                    فلا شيء ضائع، والعشوائيّةُ لم تصل أحداً.
                */
                $flash['warning'] = 'حُفِظ الموظف، لكن لم تُرسَل بيانات الدخول: '.$this->credentialsMailError
                    .($passwordWasTyped
                        ? ' الحسابُ الآن بكلمة المرور التي كتبتَها وهي تعمل — أبلغْها الموظفَ، أو أعد الإرسال من صفحة الموظف بعد ضبط إعدادات البريد.'
                        : ' الحسابُ الآن بكلمة مرورٍ عشوائية لم تصل أحداً — بعد ضبط إعدادات البريد اضغط «إرسال بيانات الدخول» من صفحة الموظف.');
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إضافة الموظف بنجاح !!',
                    'warning' => $flash['warning'] ?? null,
                    'redirect' => route('employee.show', $new_employee->id),
                ]);
            }

            return redirect()
                ->route('employee.show', $new_employee->id)
                ->with($flash);
        } catch (\Exception $exception) {
            OperationLog::failure($exception, 'HR.Employee', 'store');

            DB::rollback();

            \Log::error('خطأ في إضافة موظف: '.$exception->getMessage());
            \Log::error('Stack trace: '.$exception->getTraceAsString());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء إضافة الموظف: '.$exception->getMessage(),
                    'error_details' => $exception->getMessage(),
                ], 500);
            }

            return redirect()
                ->route('employee.create')
                ->withInput()
                ->with(['error' => $exception->getMessage()]);
        }
    }

    public function edit($id)
    {
        $shifts = Shift::select('id', 'name')->get();
        $branches = $this->branchesWithTreeLabelsForEmployeeForms();
        $jobTypes = TypesJobs::select('id', 'name')->get();
        $jobLevels = FunctionalLevels::select('id', 'name')->get();
        $jobTitles = JopTitle::select('id', 'name')->get();
        $job_roles = Role::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();
        $employee = Employee::findOrFail($id);
        $this->authorizeEmployeeScope($employee->id);

        // منتقي «المدير المباشر» من نطاق الرؤية — ويبقى المديرُ الحاليّ فيه ولو خرج منه
        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name'), 'id')->get();
        if ($employee->direct_manager_id && ! $employees->contains('id', $employee->direct_manager_id)) {
            $currentManager = Employee::select('id', 'first_name', 'middle_name')->find($employee->direct_manager_id);
            if ($currentManager) {
                $employees->push($currentManager);
            }
        }

        $defaultTreasuryAssignment = TreasuryEmployee::with('treasury')->where('employee_id', $id)->first();
        // مستودعات المتجر الإلكتروني تُسنَد إلى الموظف من شاشاتها (تظهر له بالإسناد)؛ فلا تُعامَل هنا عهدةً
        // له: إعادة تسميتها أو مسح إسنادها عند حفظ بيانات الموظف يفسد ظهورها لمن أُسندت إليه.
        $defaultStorehouseAssignments = DefaultWarehouses::with(['storehouse' => function ($query) {
            $query->with('account');
        }])->where('employee_id', $id)
            ->whereHas('storehouse', fn ($w) => $w->where(fn ($x) => $x->where('is_store_storehouse', false)->orWhereNull('is_store_storehouse')))
            ->get();
        $defaultTreasuryName = $defaultTreasuryAssignment && $defaultTreasuryAssignment->treasury
            ? $defaultTreasuryAssignment->treasury->name
            : '';

        /*
            الصفُّ في الشاشة يحمل معرّف تعيينه لا اسمَه وحده: المزامنةُ في
            `update()` تُطابق على المعرّف، فالاسمُ المفروض (`مستودع <الموظف>`)
            يتكرّر بين مستودعات الموظف الواحد ولا يصلح هويّةً.
        */
        $defaultStorehouseRows = $defaultStorehouseAssignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'name' => $assignment->storehouse ? $assignment->storehouse->name : '',
            ];
        })->values()->all();

        $advance_parent_accounts = Account::where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'parent_id']);
        $existing_advance_accounts = Account::where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'name', 'code']);
        $current_advance_parent_id = $this->resolveEmployeeAdvanceParentId();
        if (Schema::hasColumn('employees', 'employee_advance_account_id') && $employee->employee_advance_account_id) {
            $existingAdvanceParent = Account::where('id', $employee->employee_advance_account_id)->value('parent_id');
            if ($existingAdvanceParent) {
                $current_advance_parent_id = (int) $existingAdvanceParent;
            }
        }
        /*
            ما يُعاد ملؤه في الشاشة يتبع شجرة الفروع لا `parent_id IS NULL` وحدها:

              • «الفروع» — العقد المختارة صراحةً (جذور اختيار المستخدم في الشجرة:
                أي عقدة أبُها غير محفوظٍ معها).
              • «المواقع» — ذرّياتها المحفوظة تحت تلك العقد.

            وكان التقسيمُ على جذور الجدول العالمي فقط فيُخفى كلُّ منطقة/مجموعة/
            منفذ اختير في القائمة.
        */
        $employeeBranchIds = DB::table('employee_branch')
            ->where('employee_id', $id)
            ->pluck('branch_id')
            ->map(fn ($bid) => (int) $bid)
            ->all();

        $assignedSet = array_fill_keys($employeeBranchIds, true);
        $parentById = $employeeBranchIds
            ? DB::table('branches')->whereIn('id', $employeeBranchIds)->pluck('parent_id', 'id')
            : collect();

        $rootBranchIds = [];
        $allowedBranches = [];
        foreach ($employeeBranchIds as $bid) {
            $parentId = $parentById[$bid] ?? null;
            if (! $parentId || ! isset($assignedSet[(int) $parentId])) {
                $rootBranchIds[] = $bid;
            } else {
                $allowedBranches[] = $bid;
            }
        }

        // Determine the employee's primary branch from the associated user record.
        $userRecord = User::where('employee_id', $id)->first();
        $branchId = $userRecord ? $userRecord->branch_id : null;

        /*
            الفروعُ المختارة، والأساسيُّ أوّلها — كي يوافق العرضُ ما
            يقرأه الخادمُ عند الحفظ (`branch_ids[0]`). وموظفٌ قديمٌ بلا
            صفوفٍ في `employee_branch` يقع على فرع حساب دخوله وحده.
        */
        $branchIds = $rootBranchIds;
        if ($branchId && in_array((int) $branchId, $branchIds, true)) {
            $branchIds = array_merge([(int) $branchId], array_values(array_diff($branchIds, [(int) $branchId])));
        } elseif (! $branchIds && $branchId) {
            $branchIds = [(int) $branchId];
        }

        // وشاشةُ التعديل كذلك: من لا يراها لا تُملأ له حقولُها
        $this->hideSensitiveAttributes($employee);

        return view('hr::employee.edit', compact(
            'employee', 'employees', 'job_roles', 'departments', 'jobTitles',
            'jobLevels', 'jobTypes', 'branches', 'shifts',
            'defaultTreasuryAssignment',
            'defaultStorehouseAssignments', 'defaultTreasuryName', 'defaultStorehouseRows',
            'advance_parent_accounts', 'existing_advance_accounts', 'current_advance_parent_id'
        ))->with([
            'allowedBranches' => $allowedBranches,
            'branchId' => $branchId,
            'branchIds' => $branchIds,
        ]);
    }

    public function show($id)
    {
        $employee = Employee::with([
            'department', 'job_role', 'job_level', 'job_type', 'job_title',
            'branch', 'direct_manager', 'attachments',
            'treasuryEmployee.treasury',
        ])->findOrFail($id);

        $this->authorizeEmployeeScope($employee->id);

        $user = User::where('employee_id', $id)->first()
             ?? User::where('email', $employee->email)->first();

        /*
            ما يُعرض في الشاشة يتبع شجرة الفروع لا `parent_id IS NULL` وحدها —
            نفسُ منطق شاشة التعديل:

              • «فروع الموظف» — قمم اختيار المستخدم (عقدة أبُها غير محفوظٍ معها).
              • «المواقع» — ذرّياتها المحفوظة تحت تلك العقد.
        */
        $employeeBranchIds = DB::table('employee_branch')
            ->where('employee_id', $id)
            ->pluck('branch_id')
            ->map(fn ($bid) => (int) $bid)
            ->all();

        $assignedSet = array_fill_keys($employeeBranchIds, true);
        $branchMeta = $employeeBranchIds
            ? DB::table('branches')->whereIn('id', $employeeBranchIds)->get(['id', 'name', 'parent_id'])->keyBy('id')
            : collect();

        $employeeBranches = collect();
        $allowedBranches = collect();
        foreach ($employeeBranchIds as $bid) {
            $row = $branchMeta->get($bid);
            if (! $row) {
                continue;
            }
            $parentId = $row->parent_id !== null ? (int) $row->parent_id : null;
            if (! $parentId || ! isset($assignedSet[$parentId])) {
                $employeeBranches->push($row);
            } else {
                $allowedBranches->push($row);
            }
        }

        // الفرع الافتراضي من employee_branch (is_main = 1) أو من المستخدم
        $primaryBranch = null;
        $primaryRecord = DB::table('employee_branch')
            ->where('employee_id', $id)
            ->where('is_main', 1)
            ->first();
        if ($primaryRecord) {
            $primaryBranch = DB::table('branches')->where('id', $primaryRecord->branch_id)->first();
        } elseif ($user && $user->branch_id) {
            $primaryBranch = DB::table('branches')->where('id', $user->branch_id)->first();
        }

        // موظفٌ قديمٌ بلا صفوفٍ في الجدول: فرعُ حسابه وحده هو قائمةُ فروعه
        if ($employeeBranches->isEmpty() && $primaryBranch) {
            $employeeBranches = collect([(object) [
                'id' => $primaryBranch->id,
                'name' => $primaryBranch->name,
                'parent_id' => $primaryBranch->parent_id ?? null,
            ]]);
        }

        $accessBranchesCount = count($employeeBranchIds) ?: $employeeBranches->count();

        // الحسابات المحاسبية المرتبطة بالموظف
        $liabilityAccount = null;
        $advanceAccount = null;
        if (Schema::hasColumn('employees', 'salary_payable_account_id') && $employee->salary_payable_account_id) {
            $liabilityAccount = Account::with('parent')->find($employee->salary_payable_account_id);
        }
        if (Schema::hasColumn('employees', 'employee_advance_account_id') && $employee->employee_advance_account_id) {
            $advanceAccount = Account::with('parent')->find($employee->employee_advance_account_id);
        }

        // مستودعات المتجر الإلكتروني تُسنَد إلى الموظف من شاشاتها (تظهر له بالإسناد)؛ فلا تُعامَل هنا عهدةً
        // له: إعادة تسميتها أو مسح إسنادها عند حفظ بيانات الموظف يفسد ظهورها لمن أُسندت إليه.
        $defaultStorehouses = DefaultWarehouses::with(['storehouse.account'])
            ->where('employee_id', $id)
            ->whereHas('storehouse', fn ($w) => $w->where(fn ($x) => $x->where('is_store_storehouse', false)->orWhereNull('is_store_storehouse')))
            ->get();

        $logs = ModelsLog::where('type', 'hr_log')
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        /*
            **حالةُ الصلاحيات الفعلية لحساب الدخول، لا الدور المختار في النموذج.**

            كانت الشاشة تعرض «الدور الوظيفي» من `employees.Job_role_id` وحده،
            وهو حقلٌ في سجلّ الموظف لا أثر له في فحص الصلاحيات. والفحص
            (`User::hasRolePermission()`) يقرأ `users.role_id` من حساب الدخول.
            فحين ينحرف الاثنان — حسابٌ بلا دور، أو دورٌ بلا صلاحيات — تقول
            الشاشةُ «مدير مبيعات» ويقول النظامُ للموظف «ليس لديك صلاحية»،
            ولا شيء في الصفحة يشرح التناقض. هذه الأسطر تُظهره صريحاً.
        */
        $loginRole = $user?->primaryRole;
        $loginPermissionsCount = $loginRole ? $loginRole->permissions()->count() : 0;

        /*
            البياناتُ الحسّاسة تُسقَط **قبل** بلوغ القالب لمن لا يملك
            `employees.sensitive` — لا يُكتفى بإخفاء كتلتها في الشاشة.

            فإخفاءُ الكتلة يمنع العينَ ولا يمنع الوصول: تبقى القيمُ في
            المتغيّر الممرَّر، فيبلغها من قرأ مصدرَ الصفحة أو استدعى القالبَ
            من طريقٍ آخر. وهنا لا تُحمَّل أصلاً.
        */
        $this->hideSensitiveAttributes($employee);

        /*
            موانعُ الحذف تُحسب هنا لا عند الضغط: المودالُ يقول قبل التأكيد
            **ما** الذي يمنع الحذف بالضبط، فلا يُطلب من المستخدم أن يضغط
            ليعرف. والحارسُ نفسُه يُعاد تشغيله في `delete()` — فالشاشةُ
            إخبارٌ لا حراسة.
        */
        $deletionGuard = EmployeeDeletionGuard::for($employee);
        $deletionBlockers = $deletionGuard->blockers();
        $deletionReason = $deletionGuard->reason();

        // مكافآت «كتالوج المكافآت» (استبدال النقاط) — آخر الطلبات وحالةُ صرفها مع الراتب
        $rewardRedemptions = Schema::hasTable('reward_redemptions')
            ? RewardRedemption::query()
                ->with('reward:id,name,reward_type')
                ->where('user_type', 'employee')
                ->where('employee_id', $employee->id)
                ->latest('id')
                ->limit(20)
                ->get()
            : collect();

        return view('hr::employee.show', compact(
            'rewardRedemptions',
            'employee', 'user', 'logs', 'employeeBranches', 'allowedBranches', 'primaryBranch',
            'accessBranchesCount',
            'liabilityAccount', 'advanceAccount', 'defaultStorehouses',
            'loginRole', 'loginPermissionsCount',
            'deletionBlockers', 'deletionReason'
        ));
    }

    public function update(EmployeeRequest $request, $id)
    {
        // خارج المعاملة: رفضُ النطاق 403 صريحٌ لا رسالةُ خطأٍ عامّة
        $this->authorizeEmployeeScope((int) $id);

        // «إنشاء عقد للموظف» — قبل المعاملة كي تعود أخطاؤه رسائلَ حقول لا خطأً عامّاً
        $contractInput = app(ContractService::class)->validateEmployeeContract($request);

        try {
            DB::beginTransaction();

            /*
                `employee_photo` مستثناةٌ هنا: `$request->except()` يُرجع
                الملفّات مع المدخلات، فتصل كائنَ `UploadedFile`. ولو بقيت
                لَمَحاها `fill()` فوق اسمَ الملفّ المرفوع أدناه — ورميُ كائنٍ
                إلى عمودٍ نصّي يُفشل الحفظ كلَّه. فاسمُها يُسنَد على الكائن
                وحده بعد الرفع.
            */
            $employee_data = $request->except(
                '_token',
                'allow_system_access',
                'send_credentials',
                'employee_photo',
                // كما في الإضافة: مصفوفةُ الفروع ليست عموداً في `employees`
                'branch_ids',
                'password',
                'password_confirmation',
                'reset_password',
                'groups',
                'directions',
                'create_default_treasury',
                'default_treasury_name',
                'create_default_storehouse',
                'default_storehouse_names',
                'advance_parent_account_id',
                'existing_advance_account_id',
                'financial_date_mode'
            );

            /*
                البياناتُ البنكية لا تُكتب لمن لا يملك
                `employees.sensitive`.

                وهذا حارسٌ مزدوجٌ لا زائد: كتلةُ الحقول لا تُعرَض له أصلاً
                فلا تصل في الطلب، لكنّ طلباً مصوغاً يدوياً يصل. **والأخطرُ
                عكسُه**: لو عُرضت الحقولُ فارغةً لمن لا يراها لَمَحا حفظُ
                النموذج حسابَ الموظف بلا أن يقصد.
            */
            if (! hasPermission('employees.sensitive')) {
                foreach (Employee::sensitiveAttributes() as $attribute) {
                    unset($employee_data[$attribute]);
                }
            }

            if ($request->input('financial_date_mode', 'default') !== 'custom') {
                $employee_data['custom_financial_month'] = null;
                $employee_data['custom_financial_day'] = null;
            }

            $employee = Employee::findOrFail($id);

            $isUserType = (int) $request->input('employee_type') === 2;
            $createDefaultTreasury = $isUserType && $request->boolean('create_default_treasury');
            $createDefaultStorehouse = $isUserType && $request->boolean('create_default_storehouse');

            $defaultTreasuryAssignment = TreasuryEmployee::with('treasury')->where('employee_id', $employee->id)->first();
            // مستودعات المتجر خارج عهدة الموظف — انظر ملاحظة العرض أعلاه
            $defaultStorehouseAssignments = DefaultWarehouses::with(['storehouse' => function ($query) {
                $query->with('account');
            }])->where('employee_id', $employee->id)
                ->whereHas('storehouse', fn ($w) => $w->where(fn ($x) => $x->where('is_store_storehouse', false)->orWhereNull('is_store_storehouse')))
                ->get();
            // التحقق من البريد الإلكتروني فقط إذا كان مختلفًا عن البريد الإلكتروني الحالي
            if ($request->email !== $employee->email) {
                $existingEmployee = Employee::where('email', $request->email)->where('id', '!=', $id)->first();
                if ($existingEmployee) {
                    if ($request->ajax() || $request->wantsJson()) {
                        DB::rollback();

                        return response()->json([
                            'success' => false,
                            'message' => 'البريد الإلكتروني مستخدم بالفعل.',
                            'errors' => ['email' => ['البريد الإلكتروني مستخدم بالفعل.']],
                        ], 422);
                    }

                    DB::rollback();

                    return redirect()
                        ->route('employee.edit', $id)
                        ->with(['error' => 'البريد الإلكتروني مستخدم بالفعل.']);
                }
            }

            if ($request->hasFile('employee_photo')) {
                $employee->employee_photo = $this->UploadImage('assets/uploads/employee', $request->employee_photo);
            }

            // فتحُ الدخول وكلمةُ المرور بصلاحيتيهما — ومن لا يملكهما لا يغيّر حفظُه شيئاً منهما
            $canAccess = hasPermission('employee_accounts.access');
            $canPassword = hasPermission('employee_accounts.password');

            if ($canAccess) {
                $employee->allow_system_access = $request->has('allow_system_access') ? 1 : 0;
            }

            /*
                `send_credentials` سجلٌّ لما جرى لا إعدادٌ يحكم ما سيجري: يُرفع
                متى أُرسلت بيانات الدخول ولا يُصفَّر بحفظةٍ لم يُؤشَّر فيها.

                كان يُصفَّر، والشاشةُ تُعيد تأشيرَه منه — فبقي مؤشَّراً بعد أوّل
                إرسال، وكلُّ حفظةٍ تالية (حقلُ الكلمة فارغٌ دائماً) تولّد عشوائيّةً
                جديدةً وتُرسلها فتُبطل الكلمةَ المعمول بها. والمربّعُ الآن إجراءٌ
                يُؤشَّر عمداً في حفظته، فتصفيرُه هنا يمحو خبرَ الإرسال بلا فائدة.
            */
            if ($canPassword && $request->has('send_credentials')) {
                $employee->send_credentials = 1;
            }

            // من لا يملك `employee_accounts.roles` لا يغيّر دورَ الحساب — يبقى كما هو
            if (! hasPermission('employee_accounts.roles')) {
                unset($employee_data['Job_role_id']);
            }

            // حفظ الإيميل القديم قبل التحديث للبحث عن المستخدم لاحقاً
            $oldEmail = $employee->email;

            $employee->update($employee_data);

            $this->storeEmployeeAttachments($employee, $request);

            $advanceParentInput = $request->input('advance_parent_account_id');
            $advanceParentInputId = is_numeric($advanceParentInput) ? (int) $advanceParentInput : null;
            $existingAdvanceInput = $request->input('existing_advance_account_id');
            $existingAdvanceInputId = is_numeric($existingAdvanceInput) ? (int) $existingAdvanceInput : null;
            // الحقل معروض في النموذج: الفراغ = الأب الافتراضي «سلف الموظفين»،
            // لا «اترك الأب كما هو» — وإلا خيار «-- افتراضي --» لا يفعل شيئاً.
            $applyAdvanceParentChoice = $request->has('existing_advance_account_id')
                || $request->has('advance_parent_account_id');

            $this->syncEmployeeLiabilityAccounts(
                $employee,
                $advanceParentInputId,
                $existingAdvanceInputId,
                $applyAdvanceParentChoice
            );

            // الدورُ يُسنَد أسفلَه — بعد أن يُعرَف حسابُ الدخول يقيناً.
            // انظر التعليق عند `assignPrimaryRole()` هناك.

            // إزالة ربط مجموعات المناطق/الاتجاهات القديم (يُدار عبر شجرة الفروع فقط)
            EmployeeGroup::where('employee_id', $id)->delete();

            if ($createDefaultTreasury) {
                $treasuryName = $this->resolveDefaultResourceName(
                    $request->input('default_treasury_name'),
                    $employee,
                    'خزينة'
                );

                if ($defaultTreasuryAssignment) {
                    $this->updateDefaultTreasuryForEmployee($employee, $defaultTreasuryAssignment, $treasuryName);
                } else {
                    $this->createDefaultTreasuryForEmployee($employee, $treasuryName);
                }
            } elseif ($defaultTreasuryAssignment) {
                $this->removeDefaultTreasuryForEmployee($employee, $defaultTreasuryAssignment);
            }

            if ($createDefaultStorehouse) {
                /*
                    مزامنة المستودعات الافتراضية: تحديث الموجود، إنشاء المفقود،
                    حذف ما تمت إزالته من الفورم — **والمطابقة على المعرّف**.

                    كانت على اسم المستودع، والاسمُ ليس هويّة: `create` و`update`
                    كلاهما يفرض `مستودع <اسم الموظف>` اسماً للمستودع، فحقلُ الاسم
                    في الشاشة لا يُعيد تسميةَ شيء. فأيُّ اختلافٍ بين المكتوب وبين
                    المخزَّن — حرفٌ واحدٌ يكفي — كان يُقرأ «مستودعٌ جديد»: يُنشأ
                    مستودعٌ وحسابُ شجرةٍ وتعيينٌ جديد، ويُفَكّ تعيينُ القديم فيبقى
                    يتيماً في قائمة المستودعات. ومستودعان لموظفٍ واحدٍ يحملان
                    الاسمَ المفروضَ نفسَه كانا ينهاران إلى مفتاحٍ واحدٍ في
                    `keyBy`، فيُحذف تعيينُ الباقي عند كلّ حفظ.

                    فالصفُّ يحمل الآن معرّف تعيينه في `default_storehouse_ids[]`،
                    والصفُّ المُضاف حديثاً يحمله فارغاً. والمطابقةُ على
                    `$defaultStorehouseAssignments` وحدها — وهي مقصورةٌ على هذا
                    الموظف — فمعرّفٌ غريبٌ أو محذوفٌ لا يُطابِق شيئاً.
                */
                $submittedIds = array_values((array) $request->input('default_storehouse_ids', []));
                $submittedRows = [];

                foreach (array_values((array) $request->input('default_storehouse_names', [])) as $index => $rawName) {
                    $name = trim((string) $rawName);
                    $assignmentId = isset($submittedIds[$index]) && is_numeric($submittedIds[$index])
                        ? (int) $submittedIds[$index]
                        : null;

                    // صفٌّ جديدٌ فارغٌ يُتجاهل؛ أمّا صفٌّ قائمٌ فُرِّغ اسمُه فيبقى
                    // مستودعُه على اسمه المعنوَن بصاحبه — لا يُحذف بفراغٍ ساهٍ.
                    if ($name === '' && ! $assignmentId) {
                        continue;
                    }

                    $submittedRows[] = [
                        'id' => $assignmentId,
                        'name' => $this->resolveDefaultResourceName($name, $employee, 'مستودع'),
                    ];
                }

                $existingById = $defaultStorehouseAssignments->keyBy('id');
                // احتياطُ النماذج القديمة المفتوحة قبل هذا التغيير: لا معرّفات فيها.
                $existingByName = $defaultStorehouseAssignments
                    ->filter(fn ($a) => $a->storehouse && $a->storehouse->name !== null)
                    ->keyBy(fn ($a) => $a->storehouse->name);
                $matchedAssignmentIds = [];

                foreach ($submittedRows as $row) {
                    $assignment = null;

                    if ($row['id'] !== null) {
                        $assignment = $existingById->get($row['id']);
                    } elseif ($existingByName->has($row['name'])) {
                        $assignment = $existingByName->get($row['name']);
                    }

                    if ($assignment && ! in_array($assignment->id, $matchedAssignmentIds, true)) {
                        $this->updateDefaultStorehouseForEmployee($employee, $assignment, $row['name']);
                        $matchedAssignmentIds[] = $assignment->id;
                    } elseif (! $assignment) {
                        $this->createDefaultStorehouseForEmployee($employee, $row['name']);
                    }
                }

                // احذف أي مستودعات افتراضية موجودة سابقاً ما عاد لها صفٌّ في الفورم
                foreach ($defaultStorehouseAssignments as $assignment) {
                    if (! in_array($assignment->id, $matchedAssignmentIds, true)) {
                        $this->removeDefaultStorehouseForEmployee($employee, $assignment);
                    }
                }
            } elseif ($defaultStorehouseAssignments->count() > 0) {
                // المستخدم عطّل المستودعات الافتراضية → احذف كل التعيينات السابقة
                foreach ($defaultStorehouseAssignments as $assignment) {
                    $this->removeDefaultStorehouseForEmployee($employee, $assignment);
                }
            }

            // تحديث بيانات المستخدم
            $userData = [
                'name' => $employee->full_name,
                'email' => $request->email,
                'phone' => $request->phone_number,
                'branch_id' => $request->branch_id,
                'role' => 'employee',
            ];

            /*
                نفسُ ترتيب الإنشاء، وفارقٌ واحد: **حقلٌ فارغٌ لا يغيّر شيئاً**.

                فمن يفتح موظفاً ليصحّح اسمه أو يضيف صورته لا يُعيد تعيين
                دخوله بلا قصد — والكلمةُ لا تُعاد كتابتُها في النموذج لأنّها
                مخزَّنةٌ مُعمّاةً لا تُقرأ.

                و«إرسالُ بيانات الدخول» قناةٌ لا مصدر: المكتوبُ يتقدّم، فإن
                كُتبت كلمةٌ صارت هي العاملةَ وهي المرسَلة؛ وإن تُرك الحقلُ
                فارغاً وطُلب الإرسالُ وُلِّدت عشوائيّةٌ وأُرسلت. فالطريقتان
                معاً مُتاحتان، والمعتمدةُ آخرُ ما عُيِّن.

                كان الإرسالُ يتقدّم على المكتوب فيُهمله، فعُطّل حقلا الكلمة في
                النموذج متى أُشِّر على الإرسال لئلّا يُهمَل مكتوبٌ بصمت — وهو
                ما كان يُقفل تعديلَ الكلمة يدوياً كلّما أُريد إرسالُها.
            */
            $shouldSendCredentials = $canPassword && $request->has('send_credentials');
            $generatedPassword = null;
            $passwordWasTyped = $canPassword && $request->filled('password');

            if ($passwordWasTyped) {
                $generatedPassword = (string) $request->input('password');
                $userData['password'] = Hash::make($generatedPassword);
            } elseif ($shouldSendCredentials) {
                $generatedPassword = $this->generateRandomPassword();
                $userData['password'] = Hash::make($generatedPassword);
            } elseif ($canPassword && $request->has('reset_password')) {
                $userData['password'] = Hash::make(
                    $this->initialPasswordFor($request->phone_number, $request->mobile_number)
                );
            }

            // البحث عن المستخدم بـ employee_id أولاً ثم بالإيميل القديم كحل احتياطي
            $userToUpdate = User::where('employee_id', $id)->first()
                         ?? User::where('email', $oldEmail)->first();

            if ($userToUpdate) {
                $userToUpdate->fill(array_merge($userData, ['employee_id' => $id]));
                $userToUpdate->save();
            } else {
                /*
                    إنشاءُ حسابٍ لموظفٍ لم يكن له حساب.

                    `users.password` عمودٌ NOT NULL بلا قيمةٍ افتراضية، و$userData
                    لا تحمل كلمةَ مرورٍ إلا إذا طُلب إرسالُ بيانات الدخول أو
                    إعادةُ التعيين. فمن يفتح موظفاً بلا حسابٍ ويحفظ تعديلاً
                    عادياً — لا يمسّ الدخول أصلاً — كان يصطدم بـ1364 ويفشل
                    الحفظُ كلُّه.

                    نتبع ما يفعله store() عند الإضافة حرفياً: رقمُ الجوال كلمةَ
                    مرورٍ أوّلية ما لم تُولَّد واحدةٌ عشوائية. والشرطُ على الإنشاء
                    وحده — حسابٌ قائمٌ لا تُمسّ كلمةُ مروره من هنا أبداً.
                */
                $userData['password'] ??= Hash::make(
                    $this->initialPasswordFor($request->phone_number, $request->mobile_number)
                );

                $userToUpdate = User::create(array_merge($userData, ['employee_id' => $id]));
            }

            /*
                **الدورُ يُسنَد هنا، لا في أوّل الدالّة.**

                كان الإسنادُ يجري على `$user` المُلتَقط قبل هذه الكتلة؛ فإن لم
                يكن للموظف حسابُ دخولٍ بعد — حُذف حسابه، أو أُنشئ سجلُّه من
                مسارٍ لم يُنشئ له حساباً — كان `$user` فارغاً فيُتخطّى الإسناد
                بصمت، ثم يُنشَأ الحسابُ هنا بلا `role_id` إطلاقاً. والنتيجةُ
                ما يراه الموظف عند دخوله: «ليس لديك صلاحية» في كل شاشة، رغم
                أن «الدور الوظيفي» مختارٌ في نموذجه ودورُه مليءٌ بالصلاحيات —
                لأن الفحص (`hasRolePermission`) يقرأ `users.role_id` وحده.

                وفوق ذلك كان `$userData['role'] = 'employee'` يُكتب **بعد**
                الإسناد فيمحو اسمَ الدور الذي كتبه `assignPrimaryRole()`.
                بترتيبه أخيراً صار حفظُ التعديل يوافق حفظَ الإضافة تماماً.
            */
            if ($employee->Job_role_id) {
                $role = \Spatie\Permission\Models\Role::find($employee->Job_role_id);

                if ($role) {
                    $userToUpdate->assignPrimaryRole($role);
                }
            }

            /*
             * Synchronize branch permissions for the employee.  We always delete
             * existing records to avoid stale entries, then re‑insert the primary
             * branch with `is_main = 1` and any additional branches with
             * `is_main = 0`.  This ensures that the primary branch persists in the
             * list of branches and avoids duplication.
             */
            // حفظ الفروع المختارة + إلحاق كل أسلافها تلقائياً، مع مواقعها المختارة وأسلافها
            $this->syncEmployeeBranches(
                (int) $employee->id,
                $this->selectedBranchIds($request),
                (array) $request->input('allowed_branches', [])
            );

            if ($contractInput) {
                app(ContractService::class)->createForEmployee($employee->fresh(), $contractInput);
            }

            OperationLog::record('updated', 'hr_log', $employee, 'تم تعديل موظف **'.$employee->full_name.'**');

            DB::commit();

            // كما في الإضافة: البريدُ بعد `commit`، لا داخل المعاملة.
            if ($shouldSendCredentials && $userToUpdate && $generatedPassword) {
                $this->sendCredentialsEmail($userToUpdate, $generatedPassword);
            }

            $flash = ['success' => 'تم تحديث الموظف بنجاح !!'];

            if ($this->credentialsMailError !== null) {
                // كما في الإضافة: المكتوبةُ يعرفها من كتبها، والعشوائيّةُ لم تصل أحداً.
                $flash['warning'] = 'حُفِظ التعديل، لكن لم تُرسَل بيانات الدخول: '.$this->credentialsMailError
                    .($passwordWasTyped
                        ? ' الحسابُ الآن بكلمة المرور التي كتبتَها وهي تعمل — أبلغْها الموظفَ، أو أعد الإرسال من صفحة الموظف بعد ضبط إعدادات البريد.'
                        : ' الحسابُ الآن بكلمة مرورٍ عشوائية لم تصل أحداً — بعد ضبط إعدادات البريد اضغط «إرسال بيانات الدخول» من صفحة الموظف.');
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث الموظف بنجاح !!',
                    'warning' => $flash['warning'] ?? null,
                    'redirect' => route('employee.show', $id),
                ]);
            }

            return redirect()
                ->route('employee.show', $id)
                ->with($flash);
        } catch (\Exception $exception) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($exception, 'HR.Employee', 'update');

            DB::rollback();

            \Log::error('خطأ في تعديل موظف: '.$exception->getMessage());
            \Log::error('Stack trace: '.$exception->getTraceAsString());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث الموظف: '.$exception->getMessage(),
                    'error_details' => $exception->getMessage(),
                ], 500);
            }

            // كان يُعاد التوجيه إلى employee.index، وهي صفحة لا تعرض session('error')
            // إطلاقاً — فتُفقد الرسالة. البقاء في نفس نموذج التعديل يُظهرها فعلياً.
            return redirect()
                ->route('employee.edit', $id)
                ->withInput()
                ->with(['error' => $exception->getMessage()]);
        }
    }

    /**
     * حذفُ موظفٍ نهائياً — ولا يقع إلا على موظفٍ بلا أثر.
     *
     * **ما كان**: هذا المسارُ كان يؤرشف لا يحذف، ثم يعود بـ`back()` إلى
     * `employee.show` — و`show()` تستعمل `findOrFail()` التي لا ترى
     * المؤرشَف، فتُردّ صفحةُ 404 بعد «تمت الأرشفة بنجاح». وزرُّ الشاشة
     * مكتوبٌ عليه «حذف» وهو يؤرشف.
     *
     * **وما صار**: حذفٌ حقيقي، يسبقه `EmployeeDeletionGuard` — فإن كان
     * للموظف ارتباطٌ أو حركة رُفض الحذف وسُمّي المانعُ باسمه، وإن خلا
     * مُحيت معه بياناتُ إعداده وحسابُ دخوله وحده.
     *
     * ومن لا يُحذف يُعطَّل أو تُنهى خدمتُه — والأرشفةُ (`archive()` أدناه)
     * معلَّقةٌ بطلبٍ حالياً، لا مسارَ يبلغها.
     */
    public function delete($id)
    {
        // `withTrashed`: يُحذف المؤرشَفُ أيضاً من شاشة الأرشيف
        $employee = Employee::withTrashed()->findOrFail($id);

        $this->authorizeEmployeeScope($employee->id);

        /*
            وجهةُ الرفض ليست `back()` ولا `show()` دائماً: `show()` تستعمل
            `findOrFail()` فلا ترى المؤرشَف — وهي عينُ الـ404 التي كانت.
            فمن جاء من الأرشيف يُردّ إليه.
        */
        $failure = $employee->trashed()
            ? redirect()->route('employee.archived')
            : redirect()->route('employee.show', $employee->id);

        $guard = EmployeeDeletionGuard::for($employee);

        if (! $guard->allowsDeletion()) {
            /*
                رفضٌ برسالةٍ لا باستثناءِ مفتاحٍ أجنبي: الشلّالُ كان سيمحو
                ما مُنع من أجله بلا اعتراض، والقيدُ `RESTRICT` كان سيُخرج
                نصَّ SQL على المستخدم.
            */
            return $failure->with(['error' => $guard->reason()]);
        }

        try {
            DB::beginTransaction();

            $fullName = $employee->full_name;

            $guard->purge();
            $employee->forceDelete();

            OperationLog::record('deleted', 'hr_log', $id, 'تم حذف موظف نهائياً  **'.$fullName.'**');

            DB::commit();

            // إلى القائمة لا `back()`: بطاقةُ الموظف لم تعد موجودة، والعودةُ
            // إليها هي عينُ الـ404 التي كانت
            return redirect()
                ->route('employee.index')
                ->with(['success' => 'تم حذف الموظف **'.$fullName.'** نهائياً.']);
        } catch (\Exception $exception) {
            DB::rollback();

            OperationLog::failure($exception, 'HR.Employee', 'delete');

            return $failure->with(['error' => 'تعذّر حذف الموظف: '.$exception->getMessage()]);
        }
    }

    /**
     * أرشفةُ موظف — **معلَّقةٌ بطلبٍ حالياً**: لا مسارَ يبلغها.
     *
     * السطرُ في `Modules/HR/routes/web.php` معلَّقٌ لا محذوف، ومودالُ
     * القائمة كذلك، وشاشةُ الأرشيف واستردادُها باقيان يعملان. فرفعُ
     * التعليق عن السطرين يُعيد الإجراء كما كان.
     *
     * وتبقى الدالّةُ هنا محروسةً باختبارها: إن عاد المسارُ يوماً عاد إلى
     * أرشفةٍ لا إلى الحذف الصلب الذي كان يمحو شجرةَ المرؤوسين.
     *
     * **ما كان يقع فعلاً** قبل الأرشفة: `$employee->delete()` حذفاً صلباً
     * على جدولٍ تشير إليه إحدى وثلاثون علاقة، خمسٌ وعشرون منها
     * `ON DELETE CASCADE`. فتُمحى معه عقودُه وخصمياتُه وسجلُّ حضوره
     * وتسوياتُ أهدافه ونقاطُه، و**تُمحى شجرةُ مرؤوسيه كلُّها** عبر
     * `direct_manager_id`. ويُقال للمستخدم «تم بنجاح».
     *
     * و`SoftDeletes` يجعلها تحديثاً لا حذفاً، فلا يُطلق شلّالاً واحداً.
     * وما يُحذف حقاً يمرّ على `delete()` أعلاه، وهي لا تأذن إلا لمن لا
     * شيء يُمحى معه.
     */
    public function archive($id)
    {
        $this->authorizeEmployeeScope((int) $id);

        try {
            $employee = Employee::findOrFail($id);

            DB::beginTransaction();

            $employee->delete();

            /*
                ويُقطع الدخول مع الأرشفة: حسابُ موظفٍ مؤرشَف يبقى صالحاً لو
                تُرك، و`AuthController` يمنعه لأن ملفَّه لم يعد يُعثر عليه —
                لكنّ إسقاط الإذن صراحةً يجعل الأمر ظاهراً في بطاقة الموظف
                لا مستنتَجاً من غياب صفّ.
            */
            if ($employee->allow_system_access) {
                $employee->forceFill(['allow_system_access' => 0])->saveQuietly();
            }

            OperationLog::record('deleted', 'hr_log', $id, 'تمت أرشفة موظف  **'.$employee->full_name.'**');

            DB::commit();

            /*
                إلى القائمة لا `back()`: المؤرشَفُ لا تراه `show()` —
                `findOrFail()` تردّ 404 — فالعودةُ إلى بطاقته بعد أرشفتها
                كانت تُخرج صفحةَ خطأ بدل رسالة النجاح.
            */
            return redirect()
                ->route('employee.index')
                ->with(['success' => 'تمت أرشفة الموظف — تُستردّ بياناتُه من شاشة المؤرشفين.']);
        } catch (\Exception $exception) {
            OperationLog::failure($exception, 'HR.Employee', 'archive');

            DB::rollback();

            return redirect()
                ->back()
                ->with(['error' => $exception->getMessage()]);
        }
    }

    /**
     * إسقاطُ البيانات الحسّاسة من نموذجٍ سيُعرَض.
     *
     * `setAttribute(null)` لا `makeHidden`: الأخيرةُ تُخفي عند التحويل إلى
     * JSON وحده، والقالبُ يقرأ `$employee->iban` مباشرةً فيراها كما هي.
     *
     * ويُنادى قبل العرض لا قبل الحفظ: النموذجُ هنا للقراءة، ولا يُحفظ
     * بعده — ولو حُفظ لمسح القيم.
     */
    private function hideSensitiveAttributes(Employee $employee): void
    {
        if (hasPermission('employees.sensitive')) {
            return;
        }

        foreach (Employee::sensitiveAttributes() as $attribute) {
            $employee->setAttribute($attribute, null);
        }

        $employee->syncOriginal();
    }

    /** قائمةُ الموظفين المؤرشفين — لا يظهرون في القائمة العادية. */
    public function archived(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $query = Employee::onlyTrashed()
            ->with(['job_title', 'department', 'branch']);

        // نطاقُ الرؤية نفسُه: الأرشيف لا يفتح ما يُغلقه غيرُه
        $this->scopeToEmployees($query, 'id');

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'LIKE', "%{$term}%")
                    ->orWhere('middle_name', 'LIKE', "%{$term}%")
                    ->orWhere('employee_code', 'LIKE', "%{$term}%")
                    ->orWhere('email', 'LIKE', "%{$term}%");
            });
        }

        $employees = $query->orderByDesc('deleted_at')->paginate($perPage)->appends($request->query());

        return view('hr::employee.archived', compact('employees'));
    }

    /** استردادُ موظفٍ مؤرشَف — بحاله وعلاقاتِه كما كانت. */
    public function restore($id)
    {
        $employee = Employee::onlyTrashed()->find($id);

        if (! $employee) {
            return redirect()
                ->route('employee.archived')
                ->with(['error' => 'لا يوجد موظف مؤرشَف بهذا المعرّف.']);
        }

        $this->authorizeEmployeeScope($employee->id);

        try {
            DB::beginTransaction();

            $employee->restore();

            OperationLog::record('updated', 'hr_log', $employee, 'تم استرداد موظف  **'.$employee->full_name.'**');

            DB::commit();

            /*
                ولا يُعاد إذنُ الدخول تلقائياً: قُطع عند الأرشفة بقرار،
                فإعادتُه قرارٌ آخر يُتّخذ من بطاقة الموظف.
            */
            return redirect()
                ->route('employee.show', $employee->id)
                ->with(['success' => 'تم استرداد الموظف. إذنُ الدخول للنظام يبقى مقطوعاً حتى يُعاد من البطاقة.']);
        } catch (\Exception $exception) {
            DB::rollback();
            OperationLog::failure($exception, 'HR.Employee', 'restore');

            return redirect()->route('employee.archived')->with(['error' => $exception->getMessage()]);
        }
    }

    public function updatePassword(Request $request, $id)
    {
        // الحدُّ الأدنى من «إعدادات الموظفين» — كان 8 مثبَّتاً هنا وفي
        // `EmployeeRequest` معاً، فنسختان تفترقان مع أوّل تغيير
        $minLength = HrSettings::integer('employee_validation_rules', 'password_min_length', 8) ?? 8;

        $request->validate(
            [
                'password' => 'required|string|min:'.$minLength.'|confirmed',
            ],
            [
                'password.required' => 'يرجى إدخال كلمة المرور الجديدة.',
                'password.min' => 'كلمة المرور يجب أن تحتوي على '.$minLength.' أحرف على الأقل.',
                'password.confirmed' => 'تأكيد كلمة المرور لا يتطابق.',
            ],
        );

        $this->authorizeEmployeeScope((int) $id);

        try {
            /*
                الحسابُ يُطلَب بـ`employee_id` أوّلاً والبريدُ احتياطٌ — كما في
                `update()` و`toggleSystemAccess()`. كان البحثُ بالبريد وحده،
                فموظفٌ بلا بريدٍ محفوظ (صار ذلك ممكناً بإعداد `require_email`)
                يُردّ بـ«لا يوجد حساب مستخدم مرتبط بهذا الموظف» وحسابُه قائم —
                فلا سبيل إلى تغيير كلمته أبداً.
            */
            $employee = Employee::findOrFail($id);
            $user = User::where('employee_id', $employee->id)->first();

            if (! $user && filled($employee->email)) {
                $user = User::where('email', $employee->email)->first();
            }

            if (! $user) {
                return redirect()
                    ->back()
                    ->with(['error' => 'لا يوجد حساب مستخدم مرتبط بهذا الموظف.']);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            OperationLog::record('updated', 'hr_log', $id, 'تم تعديل كلمة السر ل **'.$user->name.'**');

            return redirect()
                ->back()
                ->with(['success' => 'تم تغيير كلمة المرور بنجاح.']);
        } catch (\Exception $exception) {
            OperationLog::failure($exception, 'HR.Employee', 'updatePassword');

            return redirect()
                ->back()
                ->with(['error' => $exception->getMessage()]);
        }
    }

    public function login_to($id)
    {
        /*
            **الحارسُ كان مقلوبَ الموضوع.**

            «الدخول كموظف» انتحالُ هوية: يُنفّذه مسؤولٌ على حساب موظّف.
            وكان الشرطُ `! Auth::user()->isEmployee()` يعني: امنعْ كلَّ من
            ليس هو نفسُه موظّفاً — أي امنعِ المسؤولَ بالضبط. فحسابُ مديرٍ
            يملك `employee_accounts.login_as` يُردّ بـ403 لأنّ حسابَه غير
            مرتبطٍ بسجلّ موظف، بينما موظّفٌ عاديٌّ لا يملك الصلاحية كان
            يمرّ من هذا السطر بلا اعتراض.

            وقبلَه كان `! $user->role === 'employee'` — تُقيَّم
            `(! $user->role) === 'employee'` فتكون false دائماً، فلا حارسَ
            أصلاً. فالتصحيحُ الأول أصلح الأولوية وأخطأ الموضوع.

            و`employee_id` ليس مقياسَ امتيازٍ في هذا النظام أصلاً: حسابُ
            المالك نفسُه مرتبطٌ بسجلّ موظف. المقياسُ الوحيد هو الصلاحية —
            وهي مفروضةٌ على المسار، وتُعاد هنا صراحةً لأنّ الفعل يرفع
            الامتياز فلا يُترك لحارسٍ واحد.
        */
        $actor = Auth::user();

        if (! $actor || ! $actor->hasRolePermission('employee_accounts.login_as')) {
            abort(403, 'غير مسموح لك بالدخول بحساب موظّف آخر.');
        }

        $user = User::findOrFail($id);

        if ((int) $user->id === (int) $actor->id) {
            return redirect()
                ->back()
                ->with(['error' => 'أنت مسجّلٌ بهذا الحساب أصلاً.']);
        }

        // التحقق من صلاحية الدخول بناءً على employee_type
        $employee = Employee::where('id', $user->employee_id)->first();

        if (! $employee) {
            return redirect()
                ->back()
                ->with(['error' => 'الموظف غير موجود.']);
        }

        // لا يُنتحَل إلا من يقع في نطاق رؤية الفاعل
        $this->authorizeEmployeeScope($employee->id);

        // إذا كان employee_type = 1 فهذا يعني أن الموظف ليس لديه صلاحية دخول
        if ($employee->employee_type == 1) {
            return redirect()
                ->back()
                ->with(['error' => 'هذا الموظف ليس لديه صلاحية دخول للنظام.']);
        }

        /*
            يُكتب قبل `logout()`: بعدَه يصير الفاعلُ هو المنتحَلَ هويتُه،
            فيُنسَب الانتحالُ إلى الضحية. وبلا هذا السطر لا يبقى في النظام
            أثرٌ لأقوى فعلٍ فيه: من دخل بحساب من، ومتى.
        */
        OperationLog::record(
            'logged',
            'hr_log',
            $employee->id,
            'دخل **'.$actor->name.'** بحساب الموظف **'.$user->name.'**'
        );

        Auth::logout();
        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function employee_management()
    {
        return view('hr::employee.employees_management');
    }

    public function manage_shifts()
    {
        return view('hr::employee.manage_shifts');
    }

    public function add_shift()
    {
        return view('hr::employee.add_shift');
    }

    public function add_new_role()
    {
        return view('hr::employee.add_new_role');
    }

    public function export_view()
    {
        return view('hr::employee.export');
    }

    public function export(Request $request)
    {
        $fields = $request->input('fields', []);

        if (empty($fields)) {
            return redirect()
                ->back()
                ->with(['error' => 'يرجى تحديد الحقول للتصدير!']);
        }

        // موظفو القائمة وحدهم: النطاقُ وفرعُ الشريط — كما تعرضهم شاشةُ الموظفين
        return Excel::download(new EmployeesExport($fields, $this->listEmployeeIds()), 'departments.xlsx');
    }

    private function resolveDefaultResourceName(?string $providedName, Employee $employee, string $prefix): string
    {
        $providedName = trim((string) $providedName);
        if ($providedName !== '') {
            return $providedName;
        }

        $fallback = $this->getEmployeeDisplayName($employee);

        return trim($prefix.' '.$fallback);
    }

    private function getEmployeeDisplayName(?Employee $employee): string
    {
        if (! $employee) {
            return 'الموظف';
        }

        $fullName = trim((string) $employee->full_name);

        if ($fullName !== '') {
            return $fullName;
        }

        $parts = array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->nickname,
        ], static function ($value) {
            return ! empty($value);
        });

        $name = trim(implode(' ', $parts));

        return $name !== '' ? $name : 'الموظف';
    }

    private function createDefaultTreasuryForEmployee(Employee $employee, string $name): void
    {
        $employeeDisplayName = $this->getEmployeeDisplayName($employee);
        $treasury = new Treasury;
        $treasury->name = $name;
        $treasury->type = 0;
        $treasury->status = 1;
        $treasury->description = 'خزينة افتراضية للموظف '.$employeeDisplayName;
        $treasury->deposit_permissions = 1;
        $treasury->withdraw_permissions = 1;
        $treasury->value_of_deposit_permissions = $employee->id;
        $treasury->value_of_withdraw_permissions = $employee->id;
        $treasury->save();

        // حساب الشجرة يبقى معنوَناً باسم الموظف دوماً، بصرف النظر عن أي عنوانٍ
        // مخصَّص يكتبه المستخدم للخزينة نفسها — فيبقى قابلاً للتتبّع بصاحبه.
        $account = new Account;
        $account->name = 'خزينة '.$employeeDisplayName;
        $account->type_accont = 0;
        $account->is_active = 1;
        $account->parent_id = 13;
        $account->balance_type = 'debit';
        $account->balance = 0;
        $account->code = $this->generateAccountCode($account->id, 13);
        $account->save();

        $defaultTreasury = TreasuryEmployee::create([
            'treasury_id' => $account->id,
            'employee_id' => $employee->id,
        ]);

        $defaultTreasury->loadMissing('treasury', 'employee');
        $treasuryAccountName = $defaultTreasury->treasury ? $defaultTreasury->treasury->name : $name;
        $treasuryEmployeeName = $this->getEmployeeDisplayName($defaultTreasury->employee ?? $employee);

        OperationLog::record('created', 'finance_log', $treasury->id, sprintf('تم إنشاء خزينة افتراضية **%s** للموظف **%s**', $name, $employeeDisplayName));

        OperationLog::record('assigned', 'product_log', $defaultTreasury, sprintf(
            'تم تعيين الخزينة **%s** كخزينة افتراضية للموظف **%s**',
            $treasuryAccountName,
            $treasuryEmployeeName
        ));
    }

    private function updateDefaultTreasuryForEmployee(Employee $employee, TreasuryEmployee $assignment, string $name): void
    {
        $account = $assignment->treasury;

        if (! $account) {
            $assignment->delete();
            $this->createDefaultTreasuryForEmployee($employee, $name);

            return;
        }

        $employeeDisplayName = $this->getEmployeeDisplayName($employee);
        $oldName = $account->name;
        // حساب الشجرة يبقى باسم الموظف دوماً — لا يتبع عنوان الخزينة المخصَّص
        $accountName = 'خزينة '.$employeeDisplayName;

        if ($account->name !== $accountName) {
            $account->name = $accountName;
            $account->save();
        }

        $treasuryRecord = Treasury::where('value_of_deposit_permissions', $employee->id)
            ->where('value_of_withdraw_permissions', $employee->id)
            ->orderByDesc('id')
            ->first();

        if (! $treasuryRecord && $oldName !== null) {
            $treasuryRecord = Treasury::where('name', $oldName)->orderByDesc('id')->first();
        }
        if ($treasuryRecord) {
            $treasuryRecord->name = $name;
            $treasuryRecord->description = 'خزينة افتراضية للموظف '.$employeeDisplayName;
            $treasuryRecord->value_of_deposit_permissions = $employee->id;
            $treasuryRecord->value_of_withdraw_permissions = $employee->id;
            $treasuryRecord->save();
        }

        OperationLog::record('updated', 'finance_log', $treasuryRecord ? $treasuryRecord->id : $account->id, sprintf(
            'تم تحديث الخزينة الافتراضية من **%s** إلى **%s** للموظف **%s**',
            $oldName,
            $name,
            $employeeDisplayName
        ));

        OperationLog::record('updated', 'product_log', $assignment, sprintf(
            'تم تحديث تعيين الخزينة الافتراضية **%s** للموظف **%s**',
            $name,
            $employeeDisplayName
        ));
    }

    private function removeDefaultTreasuryForEmployee(Employee $employee, TreasuryEmployee $assignment): void
    {
        $assignment->loadMissing('treasury');
        $account = $assignment->treasury;
        $employeeDisplayName = $this->getEmployeeDisplayName($employee);
        $treasuryName = $account ? $account->name : 'الخزينة';

        $assignmentId = $assignment->id;
        $assignment->delete();

        OperationLog::record('unassigned', 'finance_log', $account ? $account->id : $employee->id, sprintf('تم إلغاء تعيين الخزينة الافتراضية **%s** للموظف **%s**', $treasuryName, $employeeDisplayName));

        OperationLog::record('removed', 'product_log', $assignmentId, sprintf('تم إزالة تعيين الخزينة الافتراضية عن الموظف **%s**', $employeeDisplayName));
    }

    private function createDefaultStorehouseForEmployee(Employee $employee, string $name): void
    {
        $employeeDisplayName = $this->getEmployeeDisplayName($employee);
        /*
            اسمُ المستودع هو ما كُتب في الشاشة. وكان مفروضاً `مستودع <الموظف>`
            مهما كُتب، فحقلُ «اسم المستودع» لا أثر له — يدعو النموذجُ إلى
            تسميةٍ ثمّ يُهملها بصمت. و`$name` آتٍ من `resolveDefaultResourceName()`
            فالفراغُ يعود إلى `مستودع <الموظف>` من تلقائه.

            وحسابُ الشجرة أدناه يبقى معنوَناً بصاحبه على كلّ حال — انظر تعليقَه.
        */
        $storehouse = new StoreHouse;
        $storehouse->name = $name;
        $storehouse->shipping_address = null;
        $storehouse->branch_id = $employee->branch_id;

        $storehouse->major = 0;
        $storehouse->view_permissions = 1;
        $storehouse->crate_invoices_permissions = 1;
        $storehouse->edit_stock_permissions = 1;
        $storehouse->value_of_view_permissions = $employee->id;
        $storehouse->value_of_crate_invoices_permissions = $employee->id;
        $storehouse->value_of_edit_stock_permissions = $employee->id;
        $storehouse->save();

        /*
            حسابُ الشجرة يتبع اسمَ مستودعه. كان معنوَناً باسم الموظف دوماً
            ليبقى متتبَّعاً بصاحبه — وذاك كافٍ يوم كان اسمُ المستودع نفسُه هو
            اسمَ الموظف. أمّا وقد صارت الأسماءُ متمايزةً والمستودعاتُ عدداً،
            فموظفٌ بمستودعين يُخلّف حسابَين شقيقَين بالاسم نفسه حرفاً بحرف،
            لا يُعرف أيُّهما لأيّ مستودع إلا بمطابقة `storehouse_id` يدوياً.
            والتتبّعُ بالصاحب محفوظٌ على كلّ حال: الحسابُ مربوطٌ بمستودعه،
            والمستودعُ بصاحبه.
        */
        $ledgerParentId = $this->resolveStorehouseLedgerParentId();
        $account = new Account;
        $account->name = $name;
        $account->type_accont = 0;
        $account->is_active = 1;
        $account->parent_id = $ledgerParentId;
        $account->balance_type = 'debit';
        $account->balance = 0;

        // Auto-generate code based on parent
        $account->code = $this->generateAccountCode($account->id, $ledgerParentId);

        $account->storehouse_id = $storehouse->id;
        $account->save();

        $storehouse->account_id = $account->id;
        $storehouse->save();

        $defaultWarehouse = DefaultWarehouses::create([
            'storehouse_id' => $storehouse->id,
            'employee_id' => $employee->id,
        ]);

        $defaultWarehouse->loadMissing('employee', 'storehouse');
        $storehouseNameResolved = $defaultWarehouse->storehouse ? $defaultWarehouse->storehouse->name : $name;
        $storehouseEmployeeName = $this->getEmployeeDisplayName($defaultWarehouse->employee ?? $employee);

        OperationLog::record('created', 'storehouse_log', $storehouse, sprintf('تم إنشاء مستودع افتراضي **%s** للموظف **%s**', $name, $employeeDisplayName));

        OperationLog::record('assigned', 'product_log', $defaultWarehouse, sprintf(
            'تم تعيين المستودع **%s** كمستودع افتراضي للموظف **%s**',
            $storehouseNameResolved,
            $storehouseEmployeeName
        ));
    }

    /**
     * إذا كان المستودع بلا حساب في الشجرة (أو الحساب المرتبط غير موجود) يُنشأ حساب فرعي ويُربط بنفس منطق إنشاء المستودع الافتراضي.
     */
    /**
     * $accountName اسمُ المستودع نفسه: حسابُ الشجرة يتبع اسمَ مستودعه، فتغييرُ
     * اسم المستودع يُعيد تسميةَ حسابه معه — وإلا لتمايزت المستودعاتُ في شاشتها
     * وبقيت حساباتُها متطابقةَ الأسماء في الشجرة.
     */
    private function ensureStorehouseLedgerAccount(StoreHouse $storehouse, string $accountName): void
    {
        $parentId = $this->resolveStorehouseLedgerParentId();
        $account = null;
        if ($storehouse->account_id) {
            $account = Account::find($storehouse->account_id);
        }
        if (! $account) {
            $account = Account::where('storehouse_id', $storehouse->id)->orderBy('id')->first();
        }

        if (! $account) {
            $account = new Account;
            $account->name = $accountName;
            $account->type_accont = 0;
            $account->is_active = 1;
            $account->parent_id = $parentId;
            $account->balance_type = 'debit';
            $account->balance = 0;
            $account->code = $this->generateAccountCode($account->id, $parentId);
            $account->storehouse_id = $storehouse->id;
            $account->save();

            $storehouse->account_id = $account->id;
            $storehouse->save();

            OperationLog::record('created', 'finance_log', $account, sprintf(
                'تم إنشاء حساب محاسبي في الشجرة للمستودع **%s** (ربط تلقائي — كان المستودع بلا حساب)',
                $accountName
            ));

            return;
        }

        if ((int) $storehouse->account_id !== (int) $account->id) {
            $storehouse->account_id = $account->id;
            $storehouse->save();
        }
        if ((int) ($account->storehouse_id ?? 0) !== (int) $storehouse->id) {
            $account->storehouse_id = $storehouse->id;
        }
        if ($account->name !== $accountName) {
            $account->name = $accountName;
        }
        $account->save();
    }

    private function resolveStorehouseLedgerParentId(): int
    {
        $resolved = $this->resolveAccountId(null, 'المخزون', 16);

        return Account::where('id', $resolved)->exists() ? $resolved : 16;
    }

    private function updateDefaultStorehouseForEmployee(Employee $employee, DefaultWarehouses $assignment, string $name): void
    {
        $storehouse = $assignment->storehouse;

        if (! $storehouse) {
            $assignment->delete();
            $this->createDefaultStorehouseForEmployee($employee, $name);

            return;
        }

        $storehouse->loadMissing('account');

        $employeeDisplayName = $this->getEmployeeDisplayName($employee);
        $oldName = $storehouse->name;
        // نفس قاعدة الإنشاء: الاسمُ ما كُتب في الشاشة. والشاشةُ تُعيد ملءَ
        // الحقل تلقائياً متى كان الاسمُ هو المشتقُّ من اسم الموظف لا اسماً
        // مخصَّصاً — فتغييرُ اسم الموظف يظلّ يجرّ معه أسماءَ مستودعاته الافتراضية.
        if ($storehouse->name !== $name) {
            $storehouse->name = $name;
        }

        $storehouse->branch_id = $employee->branch_id;
        $storehouse->value_of_view_permissions = $employee->id;
        $storehouse->value_of_crate_invoices_permissions = $employee->id;
        $storehouse->value_of_edit_stock_permissions = $employee->id;
        $storehouse->save();

        $this->ensureStorehouseLedgerAccount($storehouse->fresh(), $name);

        OperationLog::record('updated', 'storehouse_log', $storehouse, sprintf(
            'تم تحديث المستودع الافتراضي من **%s** إلى **%s** للموظف **%s**',
            $oldName,
            $name,
            $employeeDisplayName
        ));

        OperationLog::record('updated', 'product_log', $assignment, sprintf(
            'تم تحديث تعيين المستودع الافتراضي **%s** للموظف **%s**',
            $name,
            $employeeDisplayName
        ));
    }

    private function removeDefaultStorehouseForEmployee(Employee $employee, DefaultWarehouses $assignment): void
    {
        $assignment->loadMissing('storehouse');
        $storehouse = $assignment->storehouse;
        $employeeDisplayName = $this->getEmployeeDisplayName($employee);
        $storehouseName = $storehouse ? $storehouse->name : 'المستودع';

        $assignmentId = $assignment->id;
        $assignment->delete();

        OperationLog::record('unassigned', 'storehouse_log', $storehouse ? $storehouse->id : $employee->id, sprintf('تم إلغاء تعيين المستودع الافتراضي **%s** للموظف **%s**', $storehouseName, $employeeDisplayName));

        OperationLog::record('removed', 'product_log', $assignmentId, sprintf('تم إزالة تعيين المستودع الافتراضي عن الموظف **%s**', $employeeDisplayName));
    }

    private function generateAccountCode(?int $accountId, int $parentId): string
    {
        $parent = Account::find($parentId);

        if (! $parent) {
            // Fallback: generate a random code and ensure uniqueness
            $candidate = $accountId ? (string) $accountId : (string) rand(1000, 9999);
            while (Account::where('code', $candidate)->exists()) {
                $candidate = (string) rand(1000, 9999);
            }

            return $candidate;
        }

        $parentCode = $parent->code ?: (string) $parent->id;

        // Find the last child code that starts with the parent code prefix
        $lastChild = Account::where('parent_id', $parentId)
            ->where('code', 'LIKE', $parentCode.'%')
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->first();

        if ($lastChild && ! empty($lastChild->code) && is_numeric($lastChild->code)) {
            $candidate = (string) ($lastChild->code + 1);
        } else {
            // If no children, start with parent code appended with 01
            $candidate = $parentCode.'01';
        }

        // Ensure uniqueness: keep incrementing until we find a unique code
        while (Account::where('code', $candidate)->exists()) {
            $candidate = (string) ((int) $candidate + 1);
        }

        return $candidate;
    }

    private function createEmployeeLiabilityAccount(Employee $employee): Account
    {
        $employeeName = $this->getEmployeeDisplayName($employee);

        $parentId = $this->resolveEmployeeLiabilityParentId();
        if (! $parentId) {
            throw new \RuntimeException(PayrollAccounts::missingMessage(PayrollAccounts::PAYABLES_PARENT, 'إضافة موظف'));
        }

        return $this->createEmployeeChartAccount(
            $employeeName,
            $parentId,
            'credit',
            sprintf('تم إنشاء حساب التزامات للموظف **%s** ضمن الخصوم', $employeeName)
        );
    }

    private function createEmployeeAdvanceAccount(Employee $employee, ?int $explicitParentId = null): Account
    {
        $employeeName = $this->getEmployeeDisplayName($employee);
        $accountName = 'سلف '.$employeeName;

        $parentId = ($explicitParentId && Account::where('id', $explicitParentId)->exists())
            ? (int) $explicitParentId
            : $this->resolveEmployeeAdvanceParentId();
        if (! $parentId) {
            throw new \RuntimeException(PayrollAccounts::missingMessage(PayrollAccounts::ADVANCES_PARENT).' أو اختر حساباً أباً للسلف في النموذج.');
        }

        return $this->createEmployeeChartAccount(
            $accountName,
            $parentId,
            'debit',
            sprintf('تم إنشاء حساب سلف للموظف **%s**', $employeeName)
        );
    }

    /**
     * الأبُ لحسابات مستحقات الموظفين — من «إعدادات الحسابات المحاسبية» (`employee_payables_parent`) وحدها.
     *
     * كان وراءها التماسٌ بالكود (215) ثم بالاسم ثم معرّفٌ مكتوبٌ نصّاً (3038)
     * ثم «الخصوم المتداولة»: فموظفٌ يُضاف والإعدادُ غير مربوط يقع حسابُه حيث
     * لم يختر المحاسب. لا احتياط الآن — `null` ويُرفض الإنشاء برسالةٍ تدلّ على الشاشة.
     */
    private function resolveEmployeeLiabilityParentId(): ?int
    {
        return PayrollAccounts::id(PayrollAccounts::PAYABLES_PARENT);
    }

    /** الأبُ لحسابات سلف الموظفين — من «إعدادات الحسابات المحاسبية» (`employee_advances_parent`) وحدها، بلا احتياط. */
    private function resolveEmployeeAdvanceParentId(): ?int
    {
        return PayrollAccounts::id(PayrollAccounts::ADVANCES_PARENT);
    }

    private function resolveAccountId(?string $code, ?string $name, int $fallbackId): int
    {
        if ($code !== null && $code !== '') {
            $byCode = Account::where('code', $code)->value('id');
            if ($byCode) {
                return (int) $byCode;
            }
        }

        if ($name !== null && $name !== '') {
            $byName = Account::where('name', $name)->value('id');
            if ($byName) {
                return (int) $byName;
            }
        }

        return $fallbackId;
    }

    private function createEmployeeChartAccount(string $name, int $parentId, string $balanceType, string $logDescription): Account
    {
        $account = new Account;
        $account->name = $name;
        $account->type_accont = 0;
        $account->is_active = 1;
        $account->parent_id = $parentId;
        $account->balance_type = $balanceType;
        $account->balance = 0;
        $account->code = $this->generateAccountCode($account->id, $parentId);
        $account->save();

        OperationLog::record('created', 'finance_log', $account, $logDescription);

        return $account;
    }

    /**
     * يحدد حساب السلف للموظف:
     * - يأخذ الحساب الذي اختاره المستخدم كـ "أب" ويُنشئ تحته حساب سلف للموظف
     * - إذا لم يختر شيئاً → يستخدم الحساب الأب الافتراضي (سلف الموظفين)
     */
    private function resolveAdvanceAccountForEmployee(Employee $employee, ?int $existingAdvanceAccountId, ?int $advanceParentInputId): Account
    {
        $parentId = null;
        if ($existingAdvanceAccountId && Account::where('id', $existingAdvanceAccountId)->exists()) {
            $parentId = $existingAdvanceAccountId;
        } elseif ($advanceParentInputId && Account::where('id', $advanceParentInputId)->exists()) {
            $parentId = $advanceParentInputId;
        }

        return $this->createEmployeeAdvanceAccount($employee, $parentId);
    }

    /**
     * عامّة لا خاصّة: يستدعيها أيضاً `App\Console\Commands\BackfillEmployeeAccounts`
     * لربط الموظفين القائمين بلا حساب — بدل إعادة كتابة منطق إنشاء الحساب
     * وحلّ الأب نفسه في أمرٍ منفصل.
     */
    /**
     * @param  bool  $applyAdvanceParentChoice  true عندما يرسل النموذج حقل الأب
     *                                          (حتى لو فارغاً → الأب الافتراضي).
     *                                          false في الـ backfill وغيره: لا تُغيّر الأب إن لم يُمرَّر.
     */
    public function syncEmployeeLiabilityAccounts(
        Employee $employee,
        ?int $advanceParentInputId = null,
        ?int $existingAdvanceAccountId = null,
        bool $applyAdvanceParentChoice = false
    ): void {
        $employeeName = $this->getEmployeeDisplayName($employee);
        $hasAccountIdCol = Schema::hasColumn('employees', 'salary_payable_account_id');
        $hasAdvanceIdCol = Schema::hasColumn('employees', 'employee_advance_account_id');

        $existingAccountId = $hasAccountIdCol ? $employee->salary_payable_account_id : null;
        $liabilityAccount = $existingAccountId ? Account::find($existingAccountId) : null;
        if ($liabilityAccount) {
            if ($liabilityAccount->name !== $employeeName) {
                $liabilityAccount->name = $employeeName;
                $liabilityAccount->save();
            }
        } else {
            $liabilityAccount = $this->createEmployeeLiabilityAccount($employee);
            if ($hasAccountIdCol) {
                $employee->forceFill(['salary_payable_account_id' => $liabilityAccount->id])->save();
            }
        }

        // الحساب المختار في «الحساب الأب لحساب السلف» = أب حساب سلف الموظف،
        // لا حساب السلف نفسه — يُنشأ/يُنقل «سلف {الموظف}» تحته.
        $effectiveParentId = null;
        if ($existingAdvanceAccountId && Account::where('id', $existingAdvanceAccountId)->exists()) {
            $effectiveParentId = $existingAdvanceAccountId;
        } elseif ($advanceParentInputId && Account::where('id', $advanceParentInputId)->exists()) {
            $effectiveParentId = $advanceParentInputId;
        } elseif ($applyAdvanceParentChoice) {
            $effectiveParentId = $this->resolveEmployeeAdvanceParentId();
        }

        $advanceName = 'سلف '.$employeeName;
        $existingAdvanceId = $hasAdvanceIdCol ? $employee->employee_advance_account_id : null;
        $advanceAccount = $existingAdvanceId ? Account::find($existingAdvanceId) : null;
        if ($advanceAccount) {
            if ($advanceAccount->name !== $advanceName) {
                $advanceAccount->name = $advanceName;
                $advanceAccount->save();
            }

            if ($effectiveParentId
                && $effectiveParentId !== (int) $advanceAccount->parent_id
            ) {
                $oldParentId = (int) $advanceAccount->parent_id;
                $advanceAccount->parent_id = $effectiveParentId;
                $advanceAccount->code = $this->generateAccountCode($advanceAccount->id, $effectiveParentId);
                $advanceAccount->save();

                OperationLog::record('updated', 'finance_log', $advanceAccount, sprintf(
                    'تم نقل حساب سلف الموظف **%s** من الحساب الأب %d إلى %d',
                    $employeeName,
                    $oldParentId,
                    $effectiveParentId
                ));
            }
        } else {
            $advanceAccount = $this->createEmployeeAdvanceAccount($employee, $effectiveParentId);
            if ($hasAdvanceIdCol) {
                $employee->forceFill(['employee_advance_account_id' => $advanceAccount->id])->save();
            }
        }
    }

    private function linkEmployeeAccounts(Employee $employee, Account $liabilityAccount, Account $advanceAccount): void
    {
        $payload = [];
        if (Schema::hasColumn('employees', 'salary_payable_account_id')) {
            $payload['salary_payable_account_id'] = $liabilityAccount->id;
        }
        if (Schema::hasColumn('employees', 'employee_advance_account_id')) {
            $payload['employee_advance_account_id'] = $advanceAccount->id;
        }

        if (! empty($payload)) {
            $employee->forceFill($payload)->save();
        }
    }

    /**
     * بيانات نموذج ربط حسابات السلف (مستخدم من شاشة المديرين وغيرها).
     */
    public function ledgerAccountFormContext(): array
    {
        return [
            'advance_parent_accounts' => Account::where('is_active', 1)
                ->orderBy('code')
                ->get(['id', 'name', 'code', 'parent_id']),
            'existing_advance_accounts' => Account::where('is_active', 1)
                ->orderBy('code')
                ->get(['id', 'name', 'code']),
            'current_advance_parent_id' => $this->resolveEmployeeAdvanceParentId(),
        ];
    }

    /**
     * إنشاء حسابات الرواتب والسلف لموظف جديد (مثل store).
     */
    public function provisionLedgerAccountsForEmployee(
        Employee $employee,
        ?int $existingAdvanceAccountId = null,
        ?int $advanceParentInputId = null
    ): void {
        $liabilityAccount = $this->createEmployeeLiabilityAccount($employee);
        $advanceAccount = $this->resolveAdvanceAccountForEmployee(
            $employee,
            $existingAdvanceAccountId,
            $advanceParentInputId
        );
        $this->linkEmployeeAccounts($employee, $liabilityAccount, $advanceAccount);
    }

    /**
     * مزامنة حسابات الشجرة عند تعديل بيانات الموظف/المدير.
     */
    public function syncEmployeeLedgerAccountsFromProfile(
        Employee $employee,
        ?int $advanceParentInputId = null,
        ?int $existingAdvanceAccountId = null
    ): void {
        $applyChoice = $advanceParentInputId !== null || $existingAdvanceAccountId !== null;
        $this->syncEmployeeLiabilityAccounts(
            $employee,
            $advanceParentInputId,
            $existingAdvanceAccountId,
            $applyChoice
        );
    }

    // Helper Function

    /*
        كان `move($folder, …)` بمسارٍ نسبيّ — أي نسبةً إلى مجلّد عمل عمليّة
        PHP لا إلى جذر المشروع. فالصورة تهبط حيث لا يبلغها `asset()`:
        تحت الجذر في نشرٍ، وفي مكانٍ ثالثٍ تحت `artisan serve`.

        و`public_path()` صريحٌ لا يحتمل ذلك — وهو ما تفعله
        `storeEmployeeAttachments()` و`downloadAttachment()` أصلاً في هذا
        الملفّ نفسه، فصار الرفعان على قاعدةٍ واحدة.
    */
    public function uploadImage($folder, $image)
    {
        $fileExtension = $image->getClientOriginalExtension();
        $fileName = time().rand(1, 99).'.'.$fileExtension;

        if (! is_dir(public_path($folder))) {
            @mkdir(public_path($folder), 0755, true);
        }

        $image->move(public_path($folder), $fileName);

        return $fileName;
    } // end of uploadImage

    /**
     * حفظ مرفقات الموظف من الطلب (multi-file). الملفات تُخزن تحت assets/uploads/employee_attachments/{employee_id}.
     */
    private function storeEmployeeAttachments(Employee $employee, Request $request): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $files = (array) $request->file('attachments');
        $folder = 'assets/uploads/employee_attachments/'.$employee->id;

        if (! is_dir(public_path($folder))) {
            @mkdir(public_path($folder), 0755, true);
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $storedName = uniqid('att_', true).($extension ? '.'.$extension : '');
            $file->move(public_path($folder), $storedName);

            EmployeeAttachment::create([
                'employee_id' => $employee->id,
                'label' => null,
                'file_path' => $folder.'/'.$storedName,
                'original_name' => $originalName,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => @filesize(public_path($folder.'/'.$storedName)) ?: null,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    public function downloadAttachment($attachmentId)
    {
        $attachment = EmployeeAttachment::findOrFail($attachmentId);
        $this->authorizeEmployeeScope($attachment->employee_id);
        $absolutePath = public_path($attachment->file_path);

        if (! is_file($absolutePath)) {
            return redirect()->back()->with(['error' => 'الملف غير موجود على القرص.']);
        }

        return response()->download($absolutePath, $attachment->original_name);
    }

    public function downloadAttachmentsZip($id)
    {
        $employee = Employee::with('attachments')->findOrFail($id);
        $this->authorizeEmployeeScope($employee->id);

        if ($employee->attachments->isEmpty()) {
            return redirect()->back()->with(['error' => 'لا توجد مرفقات لهذا الموظف.']);
        }

        if (! class_exists(\ZipArchive::class)) {
            return redirect()->back()->with(['error' => 'إضافة ZipArchive غير مفعّلة على الخادم.']);
        }

        $zipFileName = 'employee_'.$employee->id.'_attachments_'.time().'.zip';
        $zipPath = storage_path('app/'.$zipFileName);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with(['error' => 'تعذر إنشاء ملف ZIP.']);
        }

        $usedNames = [];
        foreach ($employee->attachments as $att) {
            $absolutePath = public_path($att->file_path);
            if (! is_file($absolutePath)) {
                continue;
            }
            $entryName = $att->original_name;
            $i = 1;
            while (isset($usedNames[$entryName])) {
                $info = pathinfo($att->original_name);
                $entryName = ($info['filename'] ?? 'file').'_'.$i.(isset($info['extension']) ? '.'.$info['extension'] : '');
                $i++;
            }
            $usedNames[$entryName] = true;
            $zip->addFile($absolutePath, $entryName);
        }
        $zip->close();

        return response()
            ->download($zipPath, $zipFileName)
            ->deleteFileAfterSend(true);
    }

    public function deleteAttachment($attachmentId)
    {
        $this->authorizeEmployeeScope(EmployeeAttachment::whereKey($attachmentId)->value('employee_id'));

        try {
            $attachment = EmployeeAttachment::findOrFail($attachmentId);
            $employeeId = $attachment->employee_id;
            $absolutePath = public_path($attachment->file_path);

            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            $attachment->delete();

            OperationLog::record('deleted', 'hr_log', $employeeId, 'تم حذف مرفق للموظف');

            return redirect()->back()->with(['success' => 'تم حذف المرفق بنجاح.']);
        } catch (\Exception $exception) {
            OperationLog::failure($exception, 'HR.Employee', 'deleteAttachment');

            return redirect()->back()->with(['error' => $exception->getMessage()]);
        }
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        // البحث عن الموظفين الذين تحتوي أسماؤهم على النص المُدخل
        // لا عمودَ `name` في `employees` — كان البحثُ يسقط بخطأ SQL
        $employees = $this->scopeToEmployees(Employee::query(), 'id')
            ->where(fn ($q) => $q->where('first_name', 'LIKE', "%$query%")->orWhere('middle_name', 'LIKE', "%$query%"))
            ->select('id', 'first_name') // جلب الحقول المطلوبة فقط
            ->limit(10) // تحديد عدد النتائج
            ->get();

        return response()->json($employees);
    }

    public function updateStatus($id)
    {
        $employee = Employee::find($id);
        $employee && $this->authorizeEmployeeScope($employee->id);

        if (! $employee) {
            return redirect()
                ->route('employee.index')
                ->with(['error' => 'الموظف غير موجود!']);
        }

        /*
            من انتهت خدمتُه لا يُعاد بزرّ تبديل: العودةُ توظيفٌ جديد له
            تاريخُه، وإلغاءُ الإنهاء قرارٌ يلزمه سببٌ وصلاحية. فيُمنع هنا
            صراحةً بدل أن يمحو التبديلُ تاريخَ الإنهاء وسببَه بنقرة.
        */
        if ($employee->hasEndedService()) {
            return redirect()
                ->route('employee.show', $id)
                ->with(['error' => 'الموظف «'.$employee->employmentStatus()->label().'» — لا تُبدَّل حالتُه من هنا.']);
        }

        try {
            /*
                التبديلُ بين حالتين صريحتين.

                **ما كان**: `$employee->status = ! $employee->status;` —
                نفيٌ منطقيّ على عمودٍ مجالُه `1 نشط` و`2 غير نشط`. فالنتيجة:

                    1 → !1 = false → يُحفظ 0
                    2 → !2 = false → يُحفظ 0   ← لا يعود نشطاً أبداً
                    0 → !0 = true  → يُحفظ 1

                أي أن تعطيل النشط يكتب `0` لا `2` — قيمةً خارج المجال —
                و**الموظفُ المعطَّل (2) لا يمكن تفعيلُه إطلاقاً**: يبقى
                يُكتب `0` مهما نُقر. ومن هنا جاءت `0` في قواعد قائمة، وعليها
                بُني في `EmployeesExport` فحصٌ مقلوب (`status == 0 ? 'مفعل'`).
            */
            $employee->status = $employee->isActive()
                ? EmploymentStatus::INACTIVE->value
                : EmploymentStatus::ACTIVE->value;

            // Save the changes to the database
            $employee->save();

            $statusText = $employee->isActive() ? 'تم تفعيل الموظف' : 'تم تعطيل الموظف';

            OperationLog::record('updated', 'hr_log', $employee, $statusText.' **'.$employee->first_name.'**');

            return redirect()
                ->route('employee.show', $id)
                ->with(['success' => 'تم تحديث حالة الموظف بنجاح!']);
        } catch (\Exception $exception) {
            OperationLog::failure($exception, 'HR.Employee', 'updateStatus');

            return redirect()
                ->route('employee.show', $id)
                ->with(['error' => $exception->getMessage()]);
        }
    }

    /**
     * إنهاءُ خدمة موظف — استقالةً أو إنهاءً من المنشأة.
     *
     * **ولماذا إجراءٌ مستقلٌّ عن التعطيل؟** لأنّ التعطيل حالةٌ يُرجَع منها
     * بنقرة، والإنهاءُ ينهي علاقةً: يُبنى عليه حسابُ مكافأة نهاية الخدمة،
     * ويُرجَع إليه في نزاعٍ عمّالي، ويُقطع عنده الراتب. فله صلاحيتُه
     * (`employees.terminate`) وبياناتُه الملزِمة.
     *
     * ولا يُصرف لمن أُنهيت خدمتُه بعد ذلك: حالتُه ليست `1`، وفحصُ الأهلية
     * في `PayrollDisbursementService` يردّها.
     */
    public function terminate(Request $request, $id)
    {
        $employee = Employee::find($id);
        $employee && $this->authorizeEmployeeScope($employee->id);

        if (! $employee) {
            return redirect()->route('employee.index')->with(['error' => 'الموظف غير موجود!']);
        }

        if ($employee->hasEndedService()) {
            return redirect()->route('employee.show', $id)
                ->with(['error' => 'خدمةُ هذا الموظف منتهيةٌ أصلاً ('.$employee->employmentStatus()->label().').']);
        }

        $endingValues = array_map(fn (EmploymentStatus $s) => $s->value, EmploymentStatus::ending());

        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:'.implode(',', $endingValues)],
            'termination_date' => ['required', 'date'],
            // آخرُ يومِ عملٍ لا يسبق قرارَ الإنهاء ولا تاريخَ التعيين
            'last_working_day' => ['nullable', 'date', 'after_or_equal:termination_date'],
            'termination_reason' => ['required', 'string', 'max:2000'],
        ], [
            'status.required' => 'اختر نوع إنهاء الخدمة: استقالة أو إنهاء.',
            'status.in' => 'نوعُ إنهاءٍ غير معروف.',
            'termination_date.required' => 'تاريخ إنهاء الخدمة مطلوب.',
            'last_working_day.after_or_equal' => 'آخر يوم عمل لا يسبق تاريخ الإنهاء.',
            'termination_reason.required' => 'سببُ إنهاء الخدمة مطلوب — يُرجَع إليه لاحقاً.',
        ]);

        try {
            DB::beginTransaction();

            $effectiveEndDate = $validated['last_working_day'] ?? $validated['termination_date'];

            $employee->update([
                'status' => $validated['status'],
                'termination_date' => $validated['termination_date'],
                'last_working_day' => $effectiveEndDate,
                'termination_reason' => $validated['termination_reason'],
                // يُقطع الدخولُ مع الإنهاء مباشرةً، لا يُترك لتبديلٍ يدويٍّ لاحق
                'allow_system_access' => 0,
            ]);

            // العقدُ ينتهي بانتهاء الخدمة: لا يُمدَّد فوق آخر يوم عمل
            $activeContract = $employee->activeContract;

            if ($activeContract && (is_null($activeContract->end_date) || $activeContract->end_date->gt($effectiveEndDate))) {
                $activeContract->update(['end_date' => $effectiveEndDate]);
            }

            OperationLog::record(
                'updated',
                'hr_log',
                $employee,
                'تم إنهاء خدمة الموظف **'.$employee->full_name.'** ('
                    .$employee->employmentStatus()->label().') بتاريخ '.$validated['termination_date']
            );

            DB::commit();

            try {
                app(EndOfServiceBenefitService::class)->syncDraftFor($employee->fresh());
            } catch (\Throwable $eosError) {
                OperationLog::failure($eosError, 'HR.Employee', 'terminate.eos');
            }

            return redirect()->route('employee.show', $id)
                ->with(['success' => 'تم تسجيل إنهاء الخدمة.']);
        } catch (\Exception $exception) {
            DB::rollback();
            OperationLog::failure($exception, 'HR.Employee', 'terminate');

            return redirect()->route('employee.show', $id)->with(['error' => $exception->getMessage()]);
        }
    }

    /**
     * تفعيل/تعطيل السماح بالدخول إلى النظام — مستقلٌّ عن حالة الموظف
     * (status) وعن خانتي الإضافة/التعديل، لضبطه مباشرةً من صفحة الملف.
     */
    /*
        «السماح بالدخول إلى النظام» كان يقلب خانةً في `employees` ولا شيء
        غير ذلك — ولا يدخل الموظفُ بخانة.

        فالدخولُ يمرّ بـ`AuthController::login()`: يبحث عن **صفٍّ في
        `users`** ببريدٍ وكلمة مرور، ثمّ يقرأ `allow_system_access` بعد أن
        يجد الحساب. فمن لا حسابَ له — كلُّ موظفٍ أُنشئ من مسارٍ لا يمرّ
        بـ`store()`: البذور، أو استيراد، أو حسابٌ حُذف — يُفعَّل له الإذنُ
        فلا يتغيّر شيء: «بيانات الاعتماد غير صحيحة» عند كل محاولة.

        فالتفعيلُ هنا يُنشئ الحسابَ إن لم يكن، على نمط `store()` نفسه:
        رقمُ الجوال كلمةَ مرورٍ أوّلية، والدورُ الوظيفي يُسنَد صراحةً —
        وبدونه يدخل الموظفُ إلى «ليس لديك صلاحية» في كل شاشة، لأنّ
        `hasRolePermission()` تقرأ `users.role_id` وحده.

        وكلمةُ مرورِ حسابٍ **قائم** لا تُمسّ من هنا أبداً: التفعيلُ إذنٌ
        لا إعادةُ تعيين.
    */
    public function toggleSystemAccess($id)
    {
        $employee = Employee::find($id);
        $employee && $this->authorizeEmployeeScope($employee->id);

        if (! $employee) {
            return redirect()
                ->route('employee.index')
                ->with(['error' => 'الموظف غير موجود!']);
        }

        $enabling = ! $employee->allow_system_access;

        try {
            DB::beginTransaction();

            $notice = null;

            if ($enabling) {
                $account = User::where('employee_id', $employee->id)->first()
                    ?? User::where('email', $employee->email)->first();

                if (! $account) {
                    if (empty($employee->email)) {
                        DB::rollBack();

                        return redirect()
                            ->route('employee.show', $id)
                            ->with(['error' => 'لا يمكن تفعيل الدخول: لا بريد إلكتروني مسجَّل لهذا الموظف — أضِفه من «تعديل الموظف» أولاً.']);
                    }

                    // الجوّالُ بديلُ الهاتف: كلاهما مطلوبٌ في نموذج الموظف،
                    // والفراغُ لا يقع إلا في سجلٍّ قديمٍ سبق النموذج
                    $initialPassword = (string) ($employee->phone_number ?: $employee->mobile_number);

                    if ($initialPassword === '') {
                        DB::rollBack();

                        return redirect()
                            ->route('employee.show', $id)
                            ->with(['error' => 'لا يمكن تفعيل الدخول: رقمُ الجوال هو كلمةُ المرور الأولية، ولا رقمَ مسجَّلاً لهذا الموظف — أضِفه من «تعديل الموظف» أولاً.']);
                    }

                    $account = User::create([
                        'name' => $employee->full_name,
                        'email' => $employee->email,
                        'phone' => $initialPassword,
                        'role' => 'employee',
                        'employee_id' => $employee->id,
                        'branch_id' => $employee->branch_id,
                        'password' => Hash::make($initialPassword),
                    ]);

                    $notice = 'أُنشئ حسابُ دخولٍ للموظف: البريد «'.$employee->email.'» وكلمةُ المرور الأولية هي رقمُ جوّاله.';
                } elseif (! $account->employee_id) {
                    // حسابٌ ببريد الموظف لكنّه غيرُ مربوطٍ بملفّه — والربطُ
                    // شرطُ `isEmployee()` في فحص الدخول
                    $account->employee_id = $employee->id;
                    $account->save();
                }

                // الدورُ يُسنَد في الحالتين: حسابٌ بلا `role_id` يدخل ولا يرى شيئاً
                if ($employee->Job_role_id && ! $account->role_id) {
                    $role = \Spatie\Permission\Models\Role::find($employee->Job_role_id);

                    if ($role) {
                        $account->assignPrimaryRole($role);
                    }
                }
            }

            $employee->allow_system_access = $enabling;
            $employee->save();

            $statusText = $employee->allow_system_access ? 'تم تفعيل الدخول إلى النظام لـ' : 'تم تعطيل الدخول إلى النظام عن';

            OperationLog::record('updated', 'hr_log', $employee, $statusText.' **'.$employee->first_name.'**');

            DB::commit();

            return redirect()
                ->route('employee.show', $id)
                ->with([
                    'success' => 'تم تحديث صلاحية الدخول إلى النظام بنجاح!'.($notice ? ' '.$notice : ''),
                ]);
        } catch (\Exception $exception) {
            DB::rollBack();

            OperationLog::failure($exception, 'HR.Employee', 'toggleSystemAccess');

            return redirect()
                ->route('employee.show', $id)
                ->with(['error' => $exception->getMessage()]);
        }
    }

    public function dashboardhr()
    {
        return view('hr::dashboard.hr.index');
    }

    /**
     * فروع مع تسمية مسار الشجرة لعرض واضح عند اختيار الفرع الأساسي أو عدة فروع إضافية.
     */
    /**
     * يحفظ صلاحيات فروع الموظف: كل فرعٍ مختار + كل أسلافه (آباء وأجداد) تلقائياً،
     * ويلحق المواقع المختارة تحت تلك الفروع وأسلافها أيضاً.
     *
     * `is_main = 1` يبقى لفرعٍ واحدٍ فقط — أوّلِ المختارة — لأنّ قارئيه
     * (`edit()` و`show()`) يتوقّعون صفّاً واحداً، وهو نفسُه المكتوب في
     * `employees.branch_id` و`users.branch_id`. أمّا بقيّةُ المختارة فتُحفظ
     * `is_main = 0`: الوصولُ إليها قائم، والأساسيّةُ لواحد.
     */
    private function syncEmployeeBranches(int $employeeId, array $branchIds, array $allowedBranchIds = []): void
    {
        DB::table('employee_branch')->where('employee_id', $employeeId)->delete();

        $now = now();
        $records = [];
        $seen = [];

        $appendBranchAndAncestors = function (int $branchId, int $isMain) use (&$records, &$seen, $employeeId, $now): void {
            if (! $branchId || isset($seen[$branchId])) {
                return;
            }
            $records[] = [
                'employee_id' => $employeeId,
                'branch_id' => $branchId,
                'is_main' => $isMain,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $seen[$branchId] = true;

            $branch = Branch::withoutGlobalScopes()->find($branchId);
            if (! $branch) {
                return;
            }
            foreach ($branch->ancestors() as $ancestor) {
                $aid = (int) $ancestor->id;
                if (isset($seen[$aid])) {
                    continue;
                }
                $records[] = [
                    'employee_id' => $employeeId,
                    'branch_id' => $aid,
                    'is_main' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $seen[$aid] = true;
            }
        };

        $isMain = 1;
        foreach ($branchIds as $bid) {
            $bid = (int) $bid;
            if (! $bid) {
                continue;
            }
            $appendBranchAndAncestors($bid, $isMain);
            $isMain = 0;
        }

        foreach ($allowedBranchIds as $bid) {
            $bid = (int) $bid;
            if (! $bid) {
                continue;
            }
            $appendBranchAndAncestors($bid, 0);
        }

        if (! empty($records)) {
            DB::table('employee_branch')->insert($records);
        }
    }

    /**
     * الفروعُ المختارة في النموذج، بترتيب إرسالها.
     *
     * `branch_ids[]` هو حقلُ الشاشة الحالي؛ و`branch_id` احتياطٌ لأيّ طلبٍ
     * قديمٍ ما زال يرسل فرعاً واحداً (تكاملٌ خارجي، أو رابطٌ محفوظ).
     */
    private function selectedBranchIds(Request $request): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('branch_ids', []))
        )));

        if ($ids) {
            return $ids;
        }

        return $request->branch_id ? [(int) $request->branch_id] : [];
    }

    private function branchesWithTreeLabelsForEmployeeForms(): Collection
    {
        $all = Branch::withoutGlobalScopes()->orderBy('name')->get();
        $byId = $all->keyBy('id');

        foreach ($all as $branch) {
            $branch->setAttribute('tree_label', $branch->lineagePathLabel(' ← '));
            $branch->setAttribute('type_label', $branch->displayTypeLabel());

            // الجذر: تتبع الـ parent_id حتى نصل إلى عقدة جذرها null
            $current = $branch;
            while ($current->parent_id !== null && isset($byId[$current->parent_id])) {
                $current = $byId[$current->parent_id];
            }
            $branch->setAttribute('root_id', (int) $current->id);
        }

        return $all->sortBy(fn (Branch $b) => (string) $b->getAttribute('tree_label'))->values();
    }
}
