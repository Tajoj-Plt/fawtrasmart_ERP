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
        Schema::table('client_employee_notes', function (Blueprint $table) {
            // رقم جوال الموظف
            if (! Schema::hasColumn('client_employee_notes', 'employee_phone')) {
                $table->string('employee_phone')->nullable()->after('employee_position');
            }

            // حالة الموظف (رئيسي/افتراضي)
            if (! Schema::hasColumn('client_employee_notes', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('employee_phone');
            }

            // ربط الفاتورة
            if (! Schema::hasColumn('client_employee_notes', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('is_primary');
            }

            // إضافة indexes
            if (! SchemaGuard::hasIndex('client_employee_notes', 'client_employee_notes_is_primary_index')) {
                $table->index('is_primary');
            }
            if (! SchemaGuard::hasIndex('client_employee_notes', 'client_employee_notes_invoice_id_index')) {
                $table->index('invoice_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_employee_notes', function (Blueprint $table) {
            $table->dropColumn(['employee_phone', 'is_primary', 'invoice_id']);
        });
    }
};
