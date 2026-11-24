<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * الفرع الرئيسي — منشأة تُنصَّب حديثاً بلا أي فرع، وأغلب الشاشات
 * (العملاء، الموظفون، الفواتير) تفترض وجود فرع واحد على الأقل مربوط بالمستخدم.
 */
class MainBranchSeeder extends Seeder
{
    public function run(): void
    {
        if (Branch::where('is_main', true)->exists()) {
            $this->command?->info('  الفرع الرئيسي موجود مسبقاً — تُخطّي.');

            return;
        }

        Branch::create([
            'name' => 'الفرع الرئيسي',
            'code' => Branch::generateTreeCode(null),
            'phone' => '0000000000',
            'mobile' => '0000000000',
            'address1' => 'العنوان الرئيسي',
            'city' => 'الرياض',
            'country' => 'المملكة العربية السعودية',
            'is_main' => true,
            'status' => 0,
        ]);

        $this->command?->info('  أُنشئ الفرع الرئيسي.');
    }
}
