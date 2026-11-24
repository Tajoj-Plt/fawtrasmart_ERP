<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesExport implements FromArray, WithHeadings, WithStyles
{
    protected $fields;

    /** @var array<int>|null معرّفاتُ الموظفين في نطاق المصدِّر؛ `null` = بلا قيد */
    protected ?array $employeeIds;

    public function __construct(array $fields, ?array $employeeIds = null)
    {
        $this->fields = $fields;
        $this->employeeIds = $employeeIds;
    }

    /*
        `array()` و`headings()` كانتا قائمتين منفصلتين تماماً — كلٌّ منهما
        تكرّر شرط `in_array` بترتيبها الخاص. فانحرفتا: `headings()` لا تفحص
        `branch_id`/`email`/`status` إطلاقاً (فتغيب أعمدتُها من الترويسة رغم
        وجود بياناتها)، وتفحص `country`/`postal_code` مرّتين (فتتكرّر
        ترويستاهما رغم عمودٍ واحد من البيانات). فأيّ تصديرٍ يشمل هذه
        الحقول — وكلُّها مفعَّلة افتراضاً في الشاشة — يخرج بأعمدةٍ منزاحةٍ
        عن ترويساتها.

        القائمةُ الواحدة هنا مصدرُ الحقيقة الوحيد للترتيب وللتسمية معاً،
        فلا يمكن لإحدى الدالتين أن تنحرف عن الأخرى بعد اليوم.
    */
    private function fieldDefinitions(Collection $shiftNames): array
    {
        return [
            ['key' => 'id', 'heading' => 'المعرف', 'resolve' => fn (Employee $e) => $e->id],
            ['key' => 'created_by', 'heading' => 'أنشئ بواسطة', 'resolve' => fn (Employee $e) => $e->created_by],
            ['key' => 'job_role_id', 'heading' => 'الدور الوظيفي', 'resolve' => fn (Employee $e) => optional($e->job_role)->name ?? 'غير محدد'],
            ['key' => 'first_name', 'heading' => 'الاسم الاول', 'resolve' => fn (Employee $e) => $e->first_name],
            ['key' => 'middle_name', 'heading' => 'اسم العائلة', 'resolve' => fn (Employee $e) => $e->middle_name],
            ['key' => 'nickname', 'heading' => 'الاسم المستعار', 'resolve' => fn (Employee $e) => $e->nickname],
            ['key' => 'phone_number', 'heading' => 'رقم الهاتف', 'resolve' => fn (Employee $e) => $e->phone_number],
            ['key' => 'mobile_number', 'heading' => 'رقم الجوال', 'resolve' => fn (Employee $e) => $e->mobile_number],
            ['key' => 'country', 'heading' => 'رمز الدولة', 'resolve' => fn (Employee $e) => $e->country],
            ['key' => 'current_address_1', 'heading' => 'العنوان الحالي 1', 'resolve' => fn (Employee $e) => $e->current_address_1],
            ['key' => 'current_address_2', 'heading' => 'العنوان الحالي 2', 'resolve' => fn (Employee $e) => $e->current_address_2],
            ['key' => 'city', 'heading' => 'المدينة', 'resolve' => fn (Employee $e) => $e->city],
            ['key' => 'region', 'heading' => 'المنطقة', 'resolve' => fn (Employee $e) => $e->region],
            ['key' => 'postal_code', 'heading' => 'الرمز البريدي', 'resolve' => fn (Employee $e) => $e->postal_code],
            ['key' => 'email', 'heading' => 'البريد الإلكتروني', 'resolve' => fn (Employee $e) => $e->email],
            ['key' => 'status', 'heading' => 'الحالة', 'resolve' => function (Employee $e) {
                /*
                    كان: `$employee->status == 0 ? 'مفعل' : 'غير مفعل'` —
                    **مقلوباً**. فالنشط قيمتُه `1` لا `0`، وعليه كان التصدير
                    يصف الموظفين النشطين كلَّهم «غير مفعل» ومن لا حالة له
                    «مفعل».

                    ولم تُكتب `0` اعتباطاً: كان `updateStatus()` يحفظ
                    `! $status` — نفياً منطقياً على عمودٍ مجالُه `1`/`2` —
                    فتخرج `0` من كل تعطيل. فبُني الوصفُ على أثرِ عطلٍ لا على
                    المجال المعلن. وقد أُصلح التبديل، ويبقى هذا.

                    والتسميةُ الآن من [\App\Enums\EmploymentStatus] فتُفرِّق
                    الموقوفَ من المستقيل من منتهي الخدمة بدل أن تجمعهم في
                    «غير مفعل» واحدة.
                */
                return $e->employmentStatus()->label();
            }],
            ['key' => 'created_at', 'heading' => 'تاريخ الانشاء', 'resolve' => fn (Employee $e) => $e->created_at],
            ['key' => 'branch_id', 'heading' => 'الفرع', 'resolve' => fn (Employee $e) => optional($e->branch)->name ?? 'غير محدد'],
            ['key' => 'gender', 'heading' => 'النوع', 'resolve' => fn (Employee $e) => $e->gender == 1 ? 'ذكر' : 'انثى'],
            ['key' => 'nationality_status', 'heading' => 'حالة المواطنة', 'resolve' => fn (Employee $e) => $e->nationality_status
                ? ($e->nationality_status == 1 ? 'مواطن' : ($e->nationality_status == 2 ? 'مقيم' : 'زائر'))
                : 'غير معروف'],
            ['key' => 'department_id', 'heading' => 'القسم', 'resolve' => fn (Employee $e) => optional($e->department)->name ?? 'غير محدد'],
            ['key' => 'job_level_id', 'heading' => 'المستوى الوظيفي', 'resolve' => fn (Employee $e) => optional($e->job_level)->name ?? 'غير محدد'],
            ['key' => 'job_type_id', 'heading' => 'نوع الوظيفة', 'resolve' => fn (Employee $e) => optional($e->job_type)->name ?? 'غير محدد'],
            ['key' => 'job_title_id', 'heading' => 'المسمى الوظيفي', 'resolve' => fn (Employee $e) => optional($e->job_title)->name ?? 'غير محدد'],
            ['key' => 'birth_date', 'heading' => 'تاريخ الميلاد', 'resolve' => fn (Employee $e) => $e->date_of_birth],
            ['key' => 'personal_email', 'heading' => 'البريد الشخصي', 'resolve' => fn (Employee $e) => $e->personal_email],
            ['key' => 'shift_id', 'heading' => 'وردية الخضور', 'resolve' => fn (Employee $e) => $e->shift_id ? ($shiftNames[$e->shift_id] ?? 'غير محدد') : 'غير محدد'],
            ['key' => 'leave_policy', 'heading' => 'سياسة الإجازات', 'resolve' => fn (Employee $e) => $e->leave_policy],
            ['key' => 'direct_manager_id', 'heading' => 'المدير المباشر', 'resolve' => fn (Employee $e) => optional($e->direct_manager)->full_name ?? 'غير محدد'],
        ];
    }

    private function selectedDefinitions(): array
    {
        $shiftNames = Shift::pluck('name', 'id');

        return array_values(array_filter(
            $this->fieldDefinitions($shiftNames),
            fn (array $definition) => in_array($definition['key'], $this->fields)
        ));
    }

    public function array(): array
    {
        $definitions = $this->selectedDefinitions();

        // نطاقُ الرؤية: التصديرُ لا يُخرج من لا تُريه القائمةُ للمستخدم
        $query = Employee::query();
        if ($this->employeeIds !== null) {
            $query->whereIn('id', $this->employeeIds ?: [0]);
        }

        return $query->get()->map(function (Employee $employee) use ($definitions) {
            $data = [];

            foreach ($definitions as $definition) {
                $data[$definition['key']] = ($definition['resolve'])($employee);
            }

            return $data;
        })->toArray();
    }

    public function headings(): array
    {
        return array_map(fn (array $definition) => $definition['heading'], $this->selectedDefinitions());
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
