<?php

namespace App\Exports;

use App\Enums\EmploymentStatus;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * ورقةُ «الموظفون» في ملفّ المسير — الموظفون المحفوظون فيه كما تعرضهم
 * شاشتُه، ومعهم رقمُ قسيمة كلٍّ منهم في المسير إن وُجدت.
 */
class PayrollMembersSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected Collection $members,
        protected Collection $slips
    ) {}

    public function headings(): array
    {
        return ['#', 'رقم الموظف', 'اسم الموظف', 'القسم', 'المسمى الوظيفي', 'الفرع', 'الحالة الوظيفية', 'قسيمة في المسير', 'صافي الراتب'];
    }

    public function array(): array
    {
        $byEmployee = $this->slips->groupBy('employee_id');
        $rows = [];
        $n = 0;

        foreach ($this->members as $employee) {
            $own = $byEmployee->get($employee->id, collect());
            $status = EmploymentStatus::tryFrom((int) $employee->status);

            $rows[] = [
                ++$n,
                $employee->employee_code ?? $employee->id,
                trim($employee->first_name.' '.$employee->middle_name.' '.$employee->nickname),
                optional($employee->department)->name ?? '',
                optional($employee->job_title)->name ?? '',
                optional($employee->branch)->name ?? '',
                $status ? $status->label() : (string) $employee->status,
                $own->isEmpty() ? 'لا توجد' : $own->map(fn ($s) => $s->code ?: '#'.$s->id)->implode('، '),
                $own->isEmpty() ? '' : round((float) $own->sum('net_salary'), 2),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'الموظفون';
    }
}
