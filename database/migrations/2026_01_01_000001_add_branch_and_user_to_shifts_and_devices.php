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
        // إضافة الحقول لجدول shifts_pos
        Schema::table('shifts_pos', function (Blueprint $table) {
            if (! Schema::hasColumn('shifts_pos', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('description');
            }
            if (! Schema::hasColumn('shifts_pos', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('branch_id');
            }
            
            if (! SchemaGuard::hasForeignKey('shifts_pos', 'shifts_pos_branch_id_foreign')) {
                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->onDelete('set null');
            }
            
            if (! SchemaGuard::hasForeignKey('shifts_pos', 'shifts_pos_user_id_foreign')) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });

        // إضافة الحقول لجدول cashier_devices
        Schema::table('cashier_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('cashier_devices', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('description');
            }
            if (! Schema::hasColumn('cashier_devices', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('branch_id');
            }
            
            if (! SchemaGuard::hasForeignKey('cashier_devices', 'cashier_devices_branch_id_foreign')) {
                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->onDelete('set null');
            }
            
            if (! SchemaGuard::hasForeignKey('cashier_devices', 'cashier_devices_user_id_foreign')) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts_pos', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['branch_id', 'user_id']);
        });

        Schema::table('cashier_devices', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['branch_id', 'user_id']);
        });
    }
};
