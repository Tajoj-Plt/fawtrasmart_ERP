<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            ['name' => 'كاش', 'description' => 'الدفع نقدًا عند الاستلام', 'status' => 'active', 'is_online' => false, 'type' => 'normal'],
            ['name' => 'شيك', 'description' => 'الدفع عن طريق الشيك', 'status' => 'active', 'is_online' => true, 'type' => 'normal'],
            ['name' => 'تحويل بنكي', 'description' => 'الدفع عن طريق التحويل البنكي', 'status' => 'active', 'is_online' => true, 'type' => 'normal'],
            ['name' => 'أون لاين', 'description' => 'الدفع عبر الإنترنت', 'status' => 'active', 'is_online' => true, 'type' => 'normal'],
            ['name' => 'أخرى', 'description' => 'طرق دفع أخرى', 'status' => 'active', 'is_online' => true, 'type' => 'normal'],

            ['name' => 'Paypal Standard', 'description' => 'طرق دفع أخرى', 'status' => 'active', 'is_online' => true, 'type' => 'electronic'],
            ['name' => 'Paypal Express', 'description' => 'طرق دفع أخرى', 'status' => 'active', 'is_online' => true, 'type' => 'electronic'],
            ['name' => 'Square', 'description' => 'طرق دفع أخرى', 'status' => 'active', 'is_online' => true, 'type' => 'electronic'],
            ['name' => 'Tamara Pay', 'description' => 'طرق دفع أخرى', 'status' => 'active', 'is_online' => true, 'type' => 'electronic'],
        ];

        // المحذوفُ ناعماً يُعدّ موجوداً: صاحبُ النظام حذف «شيك» عمداً،
        // وزرعٌ يتجاهل `deleted_at` يعيده إليه في كل تحديث
        $existing = PaymentMethod::withTrashed()->pluck('name')->all();
        $seeded = 0;

        foreach ($paymentMethods as $method) {
            if (in_array($method['name'], $existing, true)) {
                continue;
            }

            PaymentMethod::create($method);
            $seeded++;
        }

        $this->command?->info($seeded > 0
            ? "تمّ زرع {$seeded} من طرق الدفع."
            : 'طرق الدفع مزروعةٌ مسبقاً — لا جديد.');
    }
}
