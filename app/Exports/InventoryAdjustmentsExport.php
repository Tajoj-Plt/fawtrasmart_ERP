<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryAdjustmentsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $adjustments;

    protected $selectedFields;

    public function __construct($adjustments, $selectedFields = [])
    {
        $this->adjustments = $adjustments;
        $this->selectedFields = $selectedFields;
    }

    public function collection()
    {
        return $this->adjustments;
    }

    public function headings(): array
    {
        $headings = [];

        if (in_array('reference_number', $this->selectedFields)) {
            $headings[] = 'رقم المرجع';
        }

        if (in_array('storehouse', $this->selectedFields)) {
            $headings[] = 'المستودع';
        }

        if (in_array('date', $this->selectedFields)) {
            $headings[] = 'التاريخ';
        }

        if (in_array('type', $this->selectedFields)) {
            $headings[] = 'نوع الجرد';
        }

        if (in_array('status', $this->selectedFields)) {
            $headings[] = 'الحالة';
        }

        if (in_array('items_count', $this->selectedFields)) {
            $headings[] = 'عدد المنتجات';
        }

        if (in_array('created_by', $this->selectedFields)) {
            $headings[] = 'تم الإنشاء بواسطة';
        }

        if (in_array('note', $this->selectedFields)) {
            $headings[] = 'الملاحظات';
        }

        if (in_array('calculation_type', $this->selectedFields)) {
            $headings[] = 'نوع الحساب';
        }

        return $headings;
    }

    public function map($adjustment): array
    {
        $row = [];

        if (in_array('reference_number', $this->selectedFields)) {
            $row[] = $adjustment->reference_number;
        }

        if (in_array('storehouse', $this->selectedFields)) {
            $row[] = $adjustment->storeHouse->name ?? '';
        }

        if (in_array('date', $this->selectedFields)) {
            $row[] = $adjustment->created_at->format('Y-m-d H:i:s');
        }

        if (in_array('type', $this->selectedFields)) {
            $typeMap = [
                'full' => 'جرد شامل',
                'partial' => 'جرد دوري',
                'spot' => 'جرد مفاجئ',
                'category' => 'جرد حسب الصنف',
            ];
            $row[] = $typeMap[$adjustment->type] ?? $adjustment->type;
        }

        if (in_array('status', $this->selectedFields)) {
            $statusMap = [
                'draft' => 'مسودة',
                'completed' => 'مكتمل',
                'adjusted' => 'تمت التسوية',
            ];
            $row[] = $statusMap[$adjustment->status] ?? $adjustment->status;
        }

        if (in_array('items_count', $this->selectedFields)) {
            $row[] = $adjustment->items->count();
        }

        if (in_array('created_by', $this->selectedFields)) {
            $row[] = $adjustment->creator->name ?? '';
        }

        if (in_array('note', $this->selectedFields)) {
            $row[] = $adjustment->note;
        }

        if (in_array('calculation_type', $this->selectedFields)) {
            $calcTypeMap = [
                'dated' => 'حسب تاريخ الجرد',
                'undated' => 'غير مرتبط بالتاريخ',
            ];
            $row[] = $calcTypeMap[$adjustment->calculation_type] ?? $adjustment->calculation_type;
        }

        return $row;
    }
}
