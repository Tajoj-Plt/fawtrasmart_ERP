<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * تصديرُ شاشة أداء الموظفين.
 *
 * يتبع تقسيمَ الشاشة نفسه: الانضباط أولاً لكلّ موظف، ثم النشاط التجاري
 * لأصحابه وحدهم — ومن لا نشاطَ تجاريَّ له تُترك خلاياه فارغةً لا أصفاراً،
 * تماماً كما في الجدول.
 */
class EmployeePerformanceExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(private readonly iterable $rows) {}

    public function array(): array
    {
        $out = [];

        foreach ($this->rows as $row) {
            $commercial = $row['has_commercial_activity'] ?? false;

            $out[] = [
                $row['employee_name'] ?? '',
                $row['job_title'] ?? '',
                $row['branch_name'] ?? '',
                $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '',
                $row['present_days'] ?? 0,
                $row['working_days'] ?? 0,
                $row['late_days'] ?? 0,
                $row['late_minutes'] ?? 0,
                $row['absent_days'] ?? 0,
                $row['overtime_minutes'] ?? 0,
                $row['leave_days_taken'] ?? 0,
                $row['leave_pending'] ?? 0,
                $row['deductions_amount'] ?? 0,
                $commercial ? ($row['sales'] ?? 0) : '',
                $commercial && $row['target'] !== null ? $row['target'] : '',
                $commercial && $row['target'] !== null ? $row['achievement_percentage'].'%' : '',
            ];
        }

        return $out;
    }

    public function headings(): array
    {
        return [
            'الموظف', 'المسمى الوظيفي', 'الفرع',
            'نسبة الحضور', 'أيام الحضور', 'أيام العمل',
            'أيام التأخير', 'دقائق التأخير', 'أيام الغياب', 'دقائق إضافية',
            'إجازات مستهلكة', 'إجازات معلّقة', 'الخصميات',
            'المبيعات', 'الهدف', 'نسبة التحقيق',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setRightToLeft(true);

        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
