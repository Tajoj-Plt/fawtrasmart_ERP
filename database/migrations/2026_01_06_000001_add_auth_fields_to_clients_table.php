<?php

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
            // حقل التحقق من رقم الهاتف (غير موجود)
            if (!Schema::hasColumn('clients', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }
            
            // حقول حذف الحساب (غير موجودة)
            if (!Schema::hasColumn('clients', 'deleted_reason')) {
                $table->text('deleted_reason')->nullable()->after('last_login_at');
            }
            if (!Schema::hasColumn('clients', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $columns = ['phone_verified_at', 'deleted_reason', 'deleted_at'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
