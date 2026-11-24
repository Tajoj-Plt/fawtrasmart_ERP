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
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'customer_target_id')) {
                $table->unsignedBigInteger('customer_target_id')->nullable()->after('status_id');
            }
            if (! Schema::hasColumn('clients', 'target_evaluated_at')) {
                $table->timestamp('target_evaluated_at')->nullable()->after('customer_target_id');
            }
            
            if (! SchemaGuard::hasForeignKey('clients', 'clients_customer_target_id_foreign')) {
                $table->foreign('customer_target_id')->references('id')->on('customer_targets')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['customer_target_id']);
            $table->dropColumn(['customer_target_id', 'target_evaluated_at']);
        });
    }
};
