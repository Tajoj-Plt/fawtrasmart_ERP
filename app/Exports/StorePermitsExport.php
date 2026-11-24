<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StorePermitsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $permits;

    public function __construct($permits)
    {
        $this->permits = $permits;
    }

    public function collection()
    {
        return $this->permits;
    }

    public function headings(): array
    {
        return [
            'رقم الإذن',
            'نوع الإذن',
            'تاريخ الإذن',
            'المستودع',
            'الحساب الفرعي',
            'القيمة الإجمالية',
            'التفاصيل',
            'الحالة',
        ];
    }

    public function map($permit): array
    {
        $status = '';
        switch ($permit->status) {
            case 0:
                $status = 'معلق';
                break;
            case 1:
                $status = 'مكتمل';
                break;
            case 2:
                $status = 'ملغي';
                break;
        }

        return [
            $permit->id,
            $this->getPermissionType($permit->permission_type),
            $permit->permission_date,
            $permit->storeHouse->name ?? '',
            $permit->sub_account,
            $permit->grand_total,
            $permit->details,
            $status,
        ];
    }

    private function getPermissionType($type)
    {
        switch ($type) {
            case 1:
                return 'إذن استلام';
            case 2:
                return 'إذن صرف';
            case 3:
                return 'إذن تحويل';
            default:
                return '';
        }
    }
}
