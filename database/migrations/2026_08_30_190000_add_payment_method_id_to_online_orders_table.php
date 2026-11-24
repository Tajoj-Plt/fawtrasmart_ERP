<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط طلب المتجر بجدول طرق الدفع فعلياً.
 *
 * `online_orders.payment_method` عمودٌ نصّي (enum PHP) فقط، و
 * `App\Models\PaymentMethod::orders()` كانت علاقةً معطوبة أصلاً — تشير إلى
 * عمود `payment_method_id` غير موجود في `online_orders`. هذا العمود هو ما
 * يُصلحها، مع تعبئة القيم القديمة بمطابقة `payment_methods.type` (يخزّن
 * نفس قيم enum: cash/card/wallet/bank_transfer/cod) حتى لا تُفقد الطلبات
 * القديمة ربطها عند الترحيل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('online_orders', 'payment_method_id')) {
            Schema::table('online_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('online_orders', 'payment_method_id')) {
                    $table->foreignId('payment_method_id')->nullable()->after('payment_method')
                        ->constrained('payment_methods')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('payment_methods')) {
            return;
        }

        // تعبئة القيم القديمة: نطابق كل قيمة enum بأول طريقة دفع نشطة من نفس النوع
        $methodsByType = DB::table('payment_methods')
            ->whereNull('deleted_at')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'type'])
            ->groupBy('type')
            ->map(fn ($group) => $group->first()->id);

        foreach ($methodsByType as $type => $paymentMethodId) {
            DB::table('online_orders')
                ->where('payment_method', $type)
                ->whereNull('payment_method_id')
                ->update(['payment_method_id' => $paymentMethodId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('online_orders', 'payment_method_id')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_method_id');
            });
        }
    }
};
