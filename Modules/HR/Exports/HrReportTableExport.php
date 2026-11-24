<?php

namespace Modules\HR\Exports;

use App\Support\OrganizationInfo;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * ورقةٌ واحدة لأي تقرير موارد بشرية — بنفس تصميم قوالب PDF المخزون
 * (`stock::storehouse.pdf.*`) الذي يستعمله `HrReportExporter::downloadPdf()`
 * أصلاً: اسم المنشأة عبر `OrganizationInfo` (مصدر `print-org-head` نفسه)،
 * عنوان التقرير، ترويسة جدول مظلَّلة محدَّدة، صفوف متناوبة التظليل،
 * وصفّ إجماليّ بعدد الصفوف. لا `WithHeadings`: العناوين صفٌّ من صفوف
 * `array()` نفسها لأن الترويسة المؤسَّسية تحتلّ الصفوف الأولى وWithHeadings
 * يضع صفّ العناوين في السطر الأول حصراً.
 */
class HrReportTableExport implements ShouldAutoSize, WithEvents, WithTitle
{
    private const HEADER_FILL = 'FFF3F2F7';

    private const BORDER_COLOR = 'FFD8D6DE';

    private const ALT_ROW_FILL = 'FFFAFAFC';

    private const DARK_TEXT = 'FF2F3349';

    private const MUTED_TEXT = 'FF6E6B7B';

    private int $headerRow = 1;

    private int $lastRow = 1;

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
    ) {}

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->build($event->sheet->getDelegate());
            },
        ];
    }

    private function build(Worksheet $sheet): void
    {
        $sheet->setRightToLeft(true);

        $columns = max(count($this->headings), 1);
        $lastColumn = $sheet->getCellByColumnAndRow($columns, 1)->getColumn();
        $range = fn (int $row) => 'A'.$row.':'.$lastColumn.$row;

        $row = 1;

        $sheet->setCellValue('A'.$row, OrganizationInfo::name());
        $sheet->mergeCells($range($row));
        $sheet->getStyle($range($row))->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => self::DARK_TEXT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $orgInfoRow = $row;

        foreach ([OrganizationInfo::address(), OrganizationInfo::identityLine()] as $line) {
            if ($line === '') {
                continue;
            }

            $row++;
            $sheet->setCellValue('A'.$row, $line);
            $sheet->mergeCells($range($row));
            $sheet->getStyle($range($row))->applyFromArray([
                'font' => ['size' => 9, 'color' => ['argb' => self::MUTED_TEXT]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $orgInfoRow = $row;
        }

        $sheet->getStyle($range($orgInfoRow))->getBorders()->getBottom()->applyFromArray([
            'borderStyle' => Border::BORDER_MEDIUM,
            'color' => ['argb' => self::DARK_TEXT],
        ]);
        $row++;

        $sheet->setCellValue('A'.$row, $this->title);
        $sheet->mergeCells($range($row));
        $sheet->getStyle($range($row))->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => self::DARK_TEXT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $sheet->setCellValue('A'.$row, 'تاريخ التصدير: '.now()->format('Y-m-d H:i'));
        $sheet->mergeCells($range($row));
        $sheet->getStyle($range($row))->applyFromArray([
            'font' => ['size' => 9, 'color' => ['argb' => self::MUTED_TEXT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row += 2;

        $this->headerRow = $row;
        foreach (array_values($this->headings) as $index => $heading) {
            $sheet->setCellValueByColumnAndRow($index + 1, $row, $heading);
        }
        $sheet->getStyle($range($row))->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::DARK_TEXT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER_COLOR]]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        foreach ($this->rows as $rowIndex => $dataRow) {
            foreach (array_values($dataRow) as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $row, (string) $value);
            }

            $style = [
                'font' => ['size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER_COLOR]]],
            ];

            if ($rowIndex % 2 === 1) {
                $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::ALT_ROW_FILL]];
            }

            $sheet->getStyle($range($row))->applyFromArray($style);
            $row++;
        }

        if ($this->rows === []) {
            $sheet->setCellValue('A'.$row, 'لا بيانات');
            $sheet->mergeCells($range($row));
            $sheet->getStyle($range($row))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER_COLOR]]],
            ]);
            $row++;
        }

        $this->lastRow = $row - 1;

        $sheet->setCellValue('A'.$row, 'عدد الصفوف: '.count($this->rows));
        $sheet->mergeCells($range($row));
        $sheet->getStyle($range($row))->applyFromArray([
            'font' => ['size' => 9, 'color' => ['argb' => self::MUTED_TEXT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER_COLOR]]],
        ]);

        $sheet->freezePane('A'.($this->headerRow + 1));
    }
}
