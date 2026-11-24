<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('general_client_settings', 'visit_proximity_radius_km')) {
                $table->decimal('visit_proximity_radius_km', 5, 3)->nullable()->after('route_settings');
            }
            if (! Schema::hasColumn('general_client_settings', 'default_new_client_status_id')) {
                $table->unsignedBigInteger('default_new_client_status_id')->nullable()->after('visit_proximity_radius_km');
            }
            if (! Schema::hasColumn('general_client_settings', 'monthly_collection_target')) {
                $table->decimal('monthly_collection_target', 10, 2)->nullable()->after('default_new_client_status_id');
            }
            if (! Schema::hasColumn('general_client_settings', 'classification_thresholds')) {
                $table->json('classification_thresholds')->nullable()->after('monthly_collection_target');
            }
            if (! Schema::hasColumn('general_client_settings', 'max_upload_size_kb')) {
                $table->unsignedInteger('max_upload_size_kb')->nullable()->after('classification_thresholds');
            }
            if (! Schema::hasColumn('general_client_settings', 'bad_debt_writeoff_approval_threshold')) {
                $table->decimal('bad_debt_writeoff_approval_threshold', 12, 2)->nullable()->after('max_upload_size_kb');
            }
            if (! Schema::hasColumn('general_client_settings', 'notify_employee_on_assignment_change')) {
                $table->boolean('notify_employee_on_assignment_change')->default(false)->after('bad_debt_writeoff_approval_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            $table->dropColumn([
                'visit_proximity_radius_km',
                'default_new_client_status_id',
                'monthly_collection_target',
                'classification_thresholds',
                'max_upload_size_kb',
                'bad_debt_writeoff_approval_threshold',
                'notify_employee_on_assignment_change',
            ]);
        });
    }
};
