<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SimpleDetailedMovementExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected Collection $movements) {}

    public function collection()
    {
        return $this->movements;
    }

    public function headings(): array
    {
        return ['التاريخ', 'رقم الإذن', 'كود الصنف', 'الصنف', 'الاتجاه', 'الكمية', 'المستودع', 'المسؤول'];
    }

    public function map($row): array
    {
        return [
            Carbon::parse($row['date'])->format('Y-m-d H:i'),
            $row['permit_number'],
            $row['product_code'],
            $row['product_name'],
            $row['direction'] === 'in' ? 'وارد' : 'منصرف',
            $row['quantity'],
            $row['warehouse_name'],
            $row['user_name'],
        ];
    }
}
