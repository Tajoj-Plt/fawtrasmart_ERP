<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manufactur_orders', function (Blueprint $table) {
            // تحديث حالات الـ status
            if (DB::connection()->getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE manufactur_orders MODIFY status ENUM('pending', 'materials_received', 'completed', 'cancelled') DEFAULT 'pending' COMMENT 'حالة أمر التصنيع: pending=جديد, materials_received=تم استلام المواد, completed=مكتمل, cancelled=ملغي'");
            }
            
            // إضافة حقل المستودع
            if (! Schema::hasColumn('manufactur_orders', 'storehouse_id')) {
                $table->foreignId('storehouse_id')
                      ->nullable()
                      ->after('status')
                      ->constrained('store_houses')
                      ->nullOnDelete()
                      ->comment('المستودع المستخدم لاستلام المواد');
            }
            
            if (! Schema::hasColumn('manufactur_orders', 'materials_received_at')) {
                $table->timestamp('materials_received_at')
                      ->nullable()
                      ->after('storehouse_id')
                      ->comment('تاريخ استلام المواد من المستودع');
            }
            
            if (! Schema::hasColumn('manufactur_orders', 'completed_at')) {
                $table->timestamp('completed_at')
                      ->nullable()
                      ->after('materials_received_at')
                      ->comment('تاريخ إنهاء أمر التصنيع');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manufactur_orders', function (Blueprint $table) {
            $table->dropForeign(['storehouse_id']);
            $table->dropColumn(['storehouse_id', 'materials_received_at', 'completed_at']);
            
            // إعادة الـ enum للحالة القديمة
            if (DB::connection()->getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE manufactur_orders MODIFY status ENUM('active', 'in_progress', 'completed', 'cancelled') DEFAULT 'active'");
            }
        });
    }
};
