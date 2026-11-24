<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Constants\Banks;
use App\Http\Controllers\Controller;
use App\Models\PayrollBankFile;
use App\Models\PayrollRun;
use App\Services\PayrollBankFileService;
use App\Support\OperationLog;
use App\Support\PayrollSettings;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Exports\HrReportTableExport;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * ملف الرواتب البنكي: توليد وتحميل من عمليات الصرف المصروفة.
 *
 * الصلاحيات `bank_files.*` (عرض/تفاصيل/إنشاء/تحميل)، وإعدادُ صيغة الملف
 * `payroll_settings.manage`. الخطة مسير الرواتب.
 * الآيبان يظهر هنا فقط — التقارير لا تذكره.
 */
class PayrollBankFileController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct(private PayrollBankFileService $files)
    {
        $this->middleware('permission.role:bank_files.index', ['only' => ['index']]);
        $this->middleware('permission.role:bank_files.show', ['only' => ['show']]);
        $this->middleware('permission.role:bank_files.create', ['only' => ['store']]);
        $this->middleware('permission.role:bank_files.download', ['only' => ['download']]);
        // صيغةُ الملف ورمزُ البنك إعدادٌ من إعدادات الرواتب
        $this->middleware('permission.role:payroll_settings.manage', ['only' => ['settings', 'updateSettings']]);
    }

    public function index()
    {
        if (Schema::hasTable('payroll_bank_files')) {
            $query = PayrollBankFile::query()->with('generator')->orderByDesc('id');
            $visibleIds = $this->visibleFileIds();
            if ($visibleIds !== null) {
                $query->whereIn('id', $visibleIds);
            }
            $files = $query->paginate(20);
        } else {
            $files = new LengthAwarePaginator([], 0, 20);
        }

        return view('hr::salaries.bank_files.index', [
            'files' => $files,
            'bankFileSettings' => $this->files->settings(),
            'ibanIssues' => $this->employeesWithoutValidIban(),
        ]);
    }

    /**
     * الموظفون النشطون (ضمن نطاق المستخدم) الذين سيُستبعدون من الملف لأن آيبانهم
     * ناقصٌ أو غير صالح — بالقاعدة نفسها التي يستبعد بها التوليدُ (`ibanRejectReason`)،
     * فيعرفهم المستخدم ويصلحهم **قبل** أن يولّد الملف لا بعده.
     *
     * @return array{count:int, rows:array<int, array{id:int, name:string, reason:string}>}
     */
    private function employeesWithoutValidIban(): array
    {
        if (! Schema::hasColumn('employees', 'iban')) {
            return ['count' => 0, 'rows' => []];
        }

        $query = \App\Models\Employee::query()->whereIn('status', [1, 3])->orderBy('first_name');
        $ids = $this->accessibleEmployeeIds();
        if ($ids !== null) {
            $query->whereIn('id', $ids ?: [0]);
        }

        $rows = [];
        foreach ($query->get(['id', 'first_name', 'middle_name', 'iban']) as $employee) {
            $reason = $this->files->ibanRejectReason(\App\Constants\Banks::normalize($employee->iban ?? null));
            if ($reason) {
                $rows[] = [
                    'id' => (int) $employee->id,
                    'name' => trim($employee->first_name.' '.$employee->middle_name),
                    'reason' => $reason,
                ];
            }
        }

        return ['count' => count($rows), 'rows' => $rows];
    }

    public function settings()
    {
        return view('hr::salaries.bank_files.settings', [
            'bankFileSettings' => $this->files->settings(),
            'banks' => Banks::forCountry(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'bank_export_format' => 'required|in:csv,xlsx',
            'bank_export_bank_code' => 'nullable|string|max:8',
        ]);

        if ($validated['bank_export_bank_code']
            && ! array_key_exists($validated['bank_export_bank_code'], Banks::forCountry())) {
            return redirect()->back()->withErrors([
                'bank_export_bank_code' => 'البنك ليس في قائمة بنوك المملكة',
            ])->withInput();
        }

        try {
            PayrollSettings::put([
                'bank_export_format' => $validated['bank_export_format'],
                'bank_export_bank_code' => $validated['bank_export_bank_code'] ?: null,
            ]);

            // يعود إلى الشاشة نفسها: صاحبُ `payroll_settings.manage` قد لا يملك `bank_files.index`
            return redirect()->route('hr.bank-files.settings')->with('success', 'تم تحديث إعدادات الملف البنكي');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'HR.PayrollBankFile', 'settings');

            return redirect()->back()->with('error', 'حدث خطأ أثناء حفظ الإعدادات')->withInput();
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
        ]);

        if (! $this->files->tablesReady()) {
            return redirect()->back()->with('error', 'جداول الملف البنكي غير جاهزة');
        }

        $query = PayrollRun::query()
            ->with(['employee', 'slip'])
            ->where('status', PayrollRun::STATUS_DISBURSED)
            ->whereDate('period_from', '>=', $validated['period_from'])
            ->whereDate('period_to', '<=', $validated['period_to'])
            ->orderBy('id');
        $this->scopeAnyQueryToEmployees($query, 'payroll_runs.employee_id');

        $runs = $query->get();
        $file = $this->files->generate(
            $runs,
            $validated['period_from'],
            $validated['period_to'],
            Auth::id()
        );

        if (! $file) {
            $preview = $this->files->preview($runs);
            $skipped = count($preview['skipped']);

            return redirect()->back()->with(
                'error',
                $skipped > 0
                    ? "لا صفوف قابلة للتصدير ({$skipped} مستبعدة: بلا آيبان أو بلا صافٍ)."
                    : 'لا رواتب مصروفة في هذه الفترة.'
            )->withInput();
        }

        return redirect()->route('hr.bank-files.show', $file->id)
            ->with('success', 'أُنشئ ملف التحويل البنكي');
    }

    public function show($id)
    {
        $file = PayrollBankFile::query()->with('generator')->findOrFail($id);
        $this->authorizeFile($file);

        return view('hr::salaries.bank_files.show', compact('file'));
    }

    public function download($id)
    {
        $file = PayrollBankFile::query()->findOrFail($id);
        $this->authorizeFile($file);
        $name = $file->filename ?: ('payroll_bank_'.$file->id.'.'.$file->format);

        if ($file->format === PayrollBankFile::FORMAT_XLSX) {
            $rows = [];
            foreach ($file->includedRows() as $row) {
                $rows[] = $this->files->csvValues($row);
            }

            return Excel::download(
                new HrReportTableExport('ملف الرواتب البنكي', $this->files->headings(), $rows),
                pathinfo($name, PATHINFO_FILENAME).'.xlsx'
            );
        }

        return response()->streamDownload(function () use ($file) {
            echo $this->files->toCsv($file);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * معرّفاتُ الملفات الظاهرة في القائمة بنفس قاعدة `authorizeFile()`:
     * يظهر الملفّ لمن يشمل نطاقُه كلَّ موظفٍ فيه. `null` = بلا قيد.
     *
     * الموظفون محفوظون داخل `payload` (JSON) لا في عمودٍ مستقل، فالتصفيةُ
     * تُحسب هنا ثم تُمرَّر معرّفاتٍ إلى الاستعلام ليبقى الترقيمُ صحيحاً.
     *
     * @return array<int>|null
     */
    private function visibleFileIds(): ?array
    {
        $accessible = $this->accessibleEmployeeIds();
        if ($accessible === null) {
            return null;
        }

        $allowed = array_flip(array_map('intval', $accessible));
        $ids = [];

        PayrollBankFile::query()->select(['id', 'payload'])->orderBy('id')
            ->chunk(200, function ($chunk) use ($allowed, &$ids) {
                foreach ($chunk as $file) {
                    $employeeIds = collect(array_merge($file->includedRows(), $file->skippedRows()))
                        ->pluck('employee_id')->filter()->map(fn ($id) => (int) $id)->unique();

                    if ($employeeIds->every(fn ($id) => isset($allowed[$id]))) {
                        $ids[] = (int) $file->id;
                    }
                }
            });

        return $ids;
    }

    /**
     * حراسةُ الملفّ المفرد بنطاق الموظفين.
     *
     * الملفُّ يُولَّد بنطاق مُنشئه، لكنّ `show/{id}` و`download/{id}` تُطلبان
     * بمعرّفٍ مباشر — فمن نطاقُه فرعٌ واحد كان يفتح ملفَّ المنشأة كلِّها
     * بأسماء موظفيها وآيباناتهم ورواتبهم. فالملفُّ يُفتح لمن يشمل نطاقُه
     * **كلَّ** موظفٍ فيه (المُدرَجين والمستبعَدين).
     */
    private function authorizeFile(PayrollBankFile $file): void
    {
        $employeeIds = collect(array_merge($file->includedRows(), $file->skippedRows()))
            ->pluck('employee_id')
            ->filter()
            ->unique();

        foreach ($employeeIds as $employeeId) {
            if (! $this->canAccessEmployee($employeeId)) {
                abort(403, 'هذا الملف يضمّ موظفين خارج نطاق الموظفين المصرّح لك بالاطّلاع عليهم.');
            }
        }
    }
}
