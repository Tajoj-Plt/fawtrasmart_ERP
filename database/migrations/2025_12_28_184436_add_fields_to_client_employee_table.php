<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_employee', function (Blueprint $table) {
            // رقم الموظف (Employee Number/Code)
            if (! Schema::hasColumn('client_employee', 'employee_number')) {
                $table->string('employee_number')->nullable()->after('employee_id');
            }

            // هل هو الموظف الأساسي/الرئيسي
            if (! Schema::hasColumn('client_employee', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('employee_number');
            }

            // ربط الفاتورة (آخر فاتورة مرتبطة)
            if (! Schema::hasColumn('client_employee', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('is_primary');
            }

            // إضافة index للبحث السريع
            if (! SchemaGuard::hasIndex('client_employee', 'client_employee_is_primary_index')) {
                $table->index('is_primary');
            }
            if (! SchemaGuard::hasIndex('client_employee', 'client_employee_invoice_id_index')) {
                $table->index('invoice_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_employee', function (Blueprint $table) {
            $table->dropColumn(['employee_number', 'is_primary', 'invoice_id']);
        });
    }
};
