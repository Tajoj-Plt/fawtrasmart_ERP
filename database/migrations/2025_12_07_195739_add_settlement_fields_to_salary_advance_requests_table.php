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
        Schema::table('salary_advance_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_advance_requests', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('admin_notes')->comment('هل تمت تسوية السلفة');
            }
            if (! Schema::hasColumn('salary_advance_requests', 'salary_slip_id')) {
                $table->unsignedBigInteger('salary_slip_id')->nullable()->after('is_settled')->comment('رقم قسيمة الراتب التي تمت فيها التسوية');
            }
            if (! Schema::hasColumn('salary_advance_requests', 'settled_date')) {
                $table->date('settled_date')->nullable()->after('salary_slip_id')->comment('تاريخ التسوية');
            }
            if (! Schema::hasColumn('salary_advance_requests', 'settled_amount')) {
                $table->decimal('settled_amount', 10, 2)->nullable()->after('settled_date')->comment('المبلغ المسوى');
            }
            
            if (! SchemaGuard::hasForeignKey('salary_advance_requests', 'salary_advance_requests_salary_slip_id_foreign')) {
                $table->foreign('salary_slip_id')->references('id')->on('salary_slips')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_advance_requests', function (Blueprint $table) {
            $table->dropForeign(['salary_slip_id']);
            $table->dropColumn(['is_settled', 'salary_slip_id', 'settled_date', 'settled_amount']);
        });
    }
};
