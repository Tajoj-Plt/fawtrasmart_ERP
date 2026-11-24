<?php

namespace App\Exports;

use App\Models\SalaryItem;
use App\Models\SalarySlip;
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
 * ورقةُ «القسائم» في ملفّ المسير — صفٌّ لكلّ قسيمةٍ تمرّرها الشاشة.
 *
 * لا تستعلم بنفسها: تستلم القسائمَ التي جمعها `PayrollProcessController`
 * لشاشة العرض، فلا يفترق الملفُّ عن الشاشة. وكانت تحسب البدلاتِ والعمولاتِ
 * باستعلامين لكلّ صفّ، وتكتب الأرقامَ نصوصاً منسَّقة («1,500.00») لا يجمعها
 * Excel، وتقرأ حالاتٍ قديمة (`pending`/`paid`) لا تحملها قسيمةٌ اليوم.
 */
class PayrollSlipsExport implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected Collection $slips,
        protected $payroll
    ) {}

    public function headings(): array
    {
        return [
            '#',
            'رقم القسيمة',
            'رقم الموظف',
            'اسم الموظف',
            'القسم',
            'المسمى الوظيفي',
            'الفرع',
            'من',
            'إلى',
            'الراتب الأساسي',
            'البدلات والاستحقاقات',
            'إجمالي الراتب',
            'الخصومات',
            'صافي الراتب',
            'الحالة',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $statuses = SalarySlip::statuses();
        $n = 0;

        foreach ($this->slips as $slip) {
            $employee = $slip->employee;
            $additions = (float) $slip->salaryItems->where('type', SalaryItem::TYPE_ADDITION)->sum('amount');

            $rows[] = [
                ++$n,
                $slip->code ?: (string) $slip->id,
                $employee->employee_code ?? $slip->employee_id,
                $employee ? trim($employee->first_name.' '.$employee->middle_name.' '.$employee->nickname) : '—',
                optional(optional($employee)->department)->name ?? '',
                optional(optional($employee)->job_title)->name ?? '',
                optional(optional($employee)->branch)->name ?? '',
                optional($slip->start_date)->format('Y-m-d') ?? '',
                optional($slip->end_date)->format('Y-m-d') ?? 'مفتوحة',
                round((float) $slip->basic_amount, 2),
                round($additions, 2),
                round((float) $slip->total_salary, 2),
                round((float) $slip->total_deductions, 2),
                round((float) $slip->net_salary, 2),
                $statuses[$slip->status] ?? $slip->status,
            ];
        }

        $rows[] = [
            '', '', '', 'الإجمالي ('.$this->slips->count().' قسيمة)', '', '', '', '', '',
            round((float) $this->slips->sum('basic_amount'), 2),
            round((float) $this->slips->sum(fn ($s) => $s->salaryItems->where('type', SalaryItem::TYPE_ADDITION)->sum('amount')), 2),
            round((float) $this->slips->sum('total_salary'), 2),
            round((float) $this->slips->sum('total_deductions'), 2),
            round((float) $this->slips->sum('net_salary'), 2),
            '',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $last = $this->slips->count() + 2;

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            $last => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'القسائم';
    }
}
