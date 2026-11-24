<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->description ?? '',
            $product->category ? $product->category->name : '',
            $product->serial_number ?? '',
            $product->brand ?? '',
            $product->barcode ?? '',
            $product->product_code ?? '',
            $product->purchase_price ?? 0,
            $product->sale_price ?? 0,
            $product->quantity ?? 0,
            $product->track_inventory ?? '',
            $product->status == 'active' ? 'نشط' : ($product->status == 'stopped' ? 'متوقف' : 'غير نشط'),
            $product->type ?? '',
            $product->created_at ? $product->created_at->format('Y-m-d H:i:s') : '',
            $product->updated_at ? $product->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }

    public function headings(): array
    {
        return [
            'المعرف',
            'الاسم',
            'الوصف',
            'التصنيف',
            'الرقم التسلسلي',
            'العلامة التجارية',
            'الباركود',
            'كود المنتج',
            'سعر الشراء',
            'سعر البيع',
            'الكمية',
            'تتبع المخزون',
            'الحالة',
            'النوع',
            'تاريخ الإنشاء',
            'تاريخ التحديث',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
