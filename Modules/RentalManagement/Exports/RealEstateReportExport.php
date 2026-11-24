<?php

namespace Modules\RentalManagement\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** تصديرٌ عامّ لأيّ تقريرٍ من `ReportService` — الأعمدة والصفوف وسطر الإجمالي. */
class RealEstateReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(protected string $title, protected array $report) {}

    public function headings(): array
    {
        return array_column($this->report['columns'], 'label');
    }

    public function array(): array
    {
        $keys = array_keys($this->report['columns']);
        $rows = array_map(fn ($row) => array_map(fn ($k) => $row[$k] ?? '', $keys), $this->report['rows']);

        if (! empty($this->report['totals'])) {
            $rows[] = array_map(fn ($k, $i) => $this->report['totals'][$k] ?? ($i === 0 ? 'الإجمالي' : ''), $keys, array_keys($keys));
        }

        return $rows;
    }

    public function title(): string
    {
        return mb_substr(str_replace(['/', '\\', '?', '*', ':', '[', ']'], ' ', $this->title), 0, 31);
    }
}
