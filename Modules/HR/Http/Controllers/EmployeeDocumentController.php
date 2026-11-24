<?php

namespace Modules\HR\Http\Controllers;

use App\Enums\EmployeeDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * وثائقُ الموظف الرسمية.
 *
 * **الحراسة على ثلاث طبقات** كما في بقيّة الوحدة:
 *
 *   • `permission.role:` في هذا الباني — القراءةُ مفصولةٌ عن الكتابة، لأن
 *     من يطّلع على رقم الإقامة ليس بالضرورة من يعدّله.
 *   • `plan:` على مجموعة المسارات (ملفّ المسارات).
 *   • ونطاقُ الرؤية عبر [ScopesToAccessibleEmployees]: الوثائقُ تخصّ
 *     موظفاً، فمن لا يرى الموظفَ لا يرى وثائقَه. ويُفحص السجلُّ المفرد
 *     كذلك — لا القائمةُ وحدها — لأن `{document}` تُطلب بمعرّفٍ مباشر.
 */
class EmployeeDocumentController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:employee_documents.index', ['only' => ['index', 'download']]);
        $this->middleware('permission.role:employee_documents.create', ['only' => ['store']]);
        $this->middleware('permission.role:employee_documents.edit', ['only' => ['update']]);
        $this->middleware('permission.role:employee_documents.destroy', ['only' => ['destroy']]);
    }

    /** وثائقُ موظفٍ بعينه. */
    public function index($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $this->authorizeEmployeeScope($employee->id);

        $documents = EmployeeDocument::where('employee_id', $employee->id)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get();

        return view('hr::employee.documents.index', [
            'employee' => $employee,
            'documents' => $documents,
            'types' => EmployeeDocumentType::options(),
        ]);
    }

    public function store(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $this->authorizeEmployeeScope($employee->id);

        $data = $this->validated($request);

        try {
            DB::beginTransaction();

            $data['employee_id'] = $employee->id;
            $data['created_by'] = Auth::id();
            $data += $this->storeFile($request, $employee);

            $document = EmployeeDocument::create($data);

            OperationLog::record(
                'created',
                'hr_log',
                $employee,
                'أُضيفت وثيقة **'.$document->displayTitle().'** للموظف **'.$employee->full_name.'**'
            );

            DB::commit();

            return back()->with('success', 'تمت إضافة الوثيقة.');
        } catch (\Throwable $exception) {
            DB::rollBack();
            OperationLog::failure($exception, 'HR.EmployeeDocument', 'store');

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function update(Request $request, $employeeId, $documentId)
    {
        $document = $this->findScoped($employeeId, $documentId);

        $data = $this->validated($request);

        try {
            DB::beginTransaction();

            /*
                الملفُّ لا يُمحى بتعديلٍ لم يرفع بديلاً: تعديلُ رقمِ وثيقةٍ
                يجب ألّا يُفقد صورتَها.
            */
            $data += $this->storeFile($request, $document->employee);

            $document->update($data);

            OperationLog::record(
                'updated',
                'hr_log',
                $document->employee,
                'عُدِّلت وثيقة **'.$document->displayTitle().'**'
            );

            DB::commit();

            return back()->with('success', 'تم تحديث الوثيقة.');
        } catch (\Throwable $exception) {
            DB::rollBack();
            OperationLog::failure($exception, 'HR.EmployeeDocument', 'update');

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function destroy($employeeId, $documentId)
    {
        $document = $this->findScoped($employeeId, $documentId);
        $title = $document->displayTitle();
        $employee = $document->employee;

        try {
            $document->delete();

            OperationLog::record('deleted', 'hr_log', $employee, 'حُذفت وثيقة **'.$title.'**');

            return back()->with('success', 'تم حذف الوثيقة.');
        } catch (\Throwable $exception) {
            OperationLog::failure($exception, 'HR.EmployeeDocument', 'destroy');

            return back()->with('error', $exception->getMessage());
        }
    }

    public function download($employeeId, $documentId)
    {
        $document = $this->findScoped($employeeId, $documentId);

        if (! $document->file_path || ! is_file(public_path($document->file_path))) {
            return back()->with('error', 'لا ملفَّ مرفقاً بهذه الوثيقة.');
        }

        return response()->download(
            public_path($document->file_path),
            $document->original_name ?: basename($document->file_path)
        );
    }

    /**
     * الوثيقةُ بشرط أن تخصّ هذا الموظف وأن يكون في نطاق المستخدم.
     *
     * الشرطُ على `employee_id` ليس زينة: بدونه يبلغ من يملك معرّفَ وثيقةٍ
     * ما ليس في نطاقه بتبديل معرّف الموظف في العنوان.
     */
    private function findScoped($employeeId, $documentId): EmployeeDocument
    {
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->with('employee')
            ->findOrFail($documentId);

        $this->authorizeEmployeeScope($document->employee_id);

        return $document;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(EmployeeDocumentType::options()))],
            'title' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            // وثيقةٌ تنتهي قبل أن تُصدر خطأُ إدخالٍ لا حالةٌ ممكنة
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ], [
            'type.required' => 'نوع الوثيقة مطلوب.',
            'type.in' => 'نوعُ وثيقةٍ غير معروف.',
            'expiry_date.after_or_equal' => 'تاريخ الانتهاء لا يسبق تاريخ الإصدار.',
            'file.mimes' => 'صيغة الملف غير مدعومة: PDF أو صورة.',
            'file.max' => 'حجم الملف يتجاوز 5 ميغابايت.',
        ]);
    }

    /**
     * حفظُ الملفّ المرفق — بنمط `EmployeeController::storeEmployeeAttachments()`
     * نفسِه: مجلَّدٌ لكل موظف تحت `public/assets/uploads`.
     *
     * @return array<string, mixed> فارغةٌ حين لا ملفَّ في الطلب
     */
    private function storeFile(Request $request, Employee $employee): array
    {
        if (! $request->hasFile('file')) {
            return [];
        }

        $file = $request->file('file');

        if (! $file->isValid()) {
            return [];
        }

        $folder = 'assets/uploads/employee_documents/'.$employee->id;

        if (! is_dir(public_path($folder))) {
            @mkdir(public_path($folder), 0755, true);
        }

        $extension = $file->getClientOriginalExtension();
        $storedName = uniqid('doc_', true).($extension ? '.'.$extension : '');
        $file->move(public_path($folder), $storedName);

        return [
            'file_path' => $folder.'/'.$storedName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => @filesize(public_path($folder.'/'.$storedName)) ?: null,
        ];
    }
}
