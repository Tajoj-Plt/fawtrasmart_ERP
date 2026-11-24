<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * تقريرُ الحضور اليومي — تصديرٌ إلى Excel.
 *
 * كان BarcodeAttendanceController يبني صفوفَ التقرير كاملةً ثم يرميها ويعيد
 * `{"message": "تم التصدير بنجاح - يتطلب تثبيت مكتبة Excel"}` — يُعلن النجاح
 * ويعترف بالنقص في الجملة نفسها، ولا ملفَ يصل. والمكتبة كانت مثبّتةً في
 * المشروع طوال الوقت (maatwebsite/excel).
 */
class AttendanceDailyExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(
        private readonly array $rows,
        private readonly string $date,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'الموظف',
            'القسم',
            'التاريخ',
            'الحضور',
            'الانصراف',
            'ساعات العمل',
            'الحالة',
            'الوسيلة',
            'ملاحظات',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setRightToLeft(true);

        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }

    public function title(): string
    {
        return 'حضور '.$this->date;
    }
}
