<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول شاشة «إتمام الزيارة» (المؤقّت، الإيقاف المؤقت، النتيجة، التوقيع).
 *
 * `status` الحالي عمودٌ `enum('present','absent')` — أضيق من أن يحمل
 * حالات هذه الشاشة (وصل/بدأ/متوقف/منتهٍ/ملغى)، وتوسيعه يخاطر بكل مكانٍ
 * يقرأه اليوم. لذلك مرحلة الشاشة في عمود جديد `stage` مستقل تماماً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'visit_number')) {
                $table->string('visit_number', 30)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('visits', 'visit_type')) {
                $table->string('visit_type', 30)->nullable()->default('periodic')->after('visit_number');
            }
            if (! Schema::hasColumn('visits', 'purpose')) {
                $table->string('purpose', 255)->nullable()->after('visit_type');
            }
            if (! Schema::hasColumn('visits', 'stage')) {
                $table->string('stage', 20)->nullable()->default('arrived')->after('status');
            }
            if (! Schema::hasColumn('visits', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('arrival_time');
            }
            if (! Schema::hasColumn('visits', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn('visits', 'total_paused_seconds')) {
                $table->unsignedInteger('total_paused_seconds')->default(0)->after('paused_at');
            }
            if (! Schema::hasColumn('visits', 'gps_accuracy')) {
                $table->decimal('gps_accuracy', 8, 2)->nullable()->after('distance');
            }
            if (! Schema::hasColumn('visits', 'outcome')) {
                $table->string('outcome', 20)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('visits', 'outcome_notes')) {
                $table->text('outcome_notes')->nullable()->after('outcome');
            }
            if (! Schema::hasColumn('visits', 'cancel_reason')) {
                $table->string('cancel_reason', 255)->nullable()->after('outcome_notes');
            }
            if (! Schema::hasColumn('visits', 'client_signature_path')) {
                $table->string('client_signature_path')->nullable()->after('cancel_reason');
            }
            if (! Schema::hasColumn('visits', 'employee_signature_path')) {
                $table->string('employee_signature_path')->nullable()->after('client_signature_path');
            }
            if (! Schema::hasColumn('visits', 'signed_contact_id')) {
                $table->unsignedBigInteger('signed_contact_id')->nullable()->after('employee_signature_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $columns = [
                'visit_number', 'visit_type', 'purpose', 'stage',
                'started_at', 'paused_at', 'total_paused_seconds', 'gps_accuracy',
                'outcome', 'outcome_notes', 'cancel_reason',
                'client_signature_path', 'employee_signature_path', 'signed_contact_id',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('visits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
