<?php

namespace App\Imports;

use App\Models\WarehousePermits;
use App\Models\StoreHouse;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StorePermitsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $storeHouse = StoreHouse::where('name', $row['المستودع'])->first();

        if (!$storeHouse) {
            throw new \Exception('المستودع غير موجود: ' . $row['المستودع']);
        }

        $permissionType = $this->getPermissionType($row['نوع_الإذن']);

        return new WarehousePermits([
            'permission_type' => $permissionType,
            'permission_date' => $this->transformDate($row['تاريخ_الإذن']),
            'store_houses_id' => $storeHouse->id,
            'sub_account' => $row['الحساب_الفرعي'],
            'grand_total' => $row['القيمة_الإجمالية'],
            'details' => $row['التفاصيل'],
            'status' => $this->getStatus($row['الحالة']),
            'created_by' => Auth::id(),
            'number' => $this->generateNumber()
        ]);
    }

    private function transformDate($value)
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Exception $e) {
            return Carbon::now()->format('Y-m-d');
        }
    }

    private function getPermissionType($type)
    {
        $types = [
            'إذن استلام' => 1,
            'إذن صرف' => 2,
            'إذن تحويل' => 3
        ];

        return $types[$type] ?? 1;
    }

    private function getStatus($status)
    {
        $statuses = [
            'معلق' => 0,
            'مكتمل' => 1,
            'ملغي' => 2
        ];

        return $statuses[$status] ?? 0;
    }

    private function generateNumber()
    {
        $count = WarehousePermits::count();
        return str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    }
}
