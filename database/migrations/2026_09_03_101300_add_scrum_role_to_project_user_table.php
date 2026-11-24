<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دور سكرم اختياري إضافي فوق role الحالي (manager/member/viewer) — لا يمس
 * enum role الموجود ولا التحقق الحالي عليه. ذو معنى فقط في مشاريع
 * management_type = scrum؛ يبقى null دوماً في المشاريع اليدوية (Normal).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_user')) {
            return;
        }

        Schema::table('project_user', function (Blueprint $table) {
            if (! Schema::hasColumn('project_user', 'scrum_role')) {
                $table->enum('scrum_role', ['product_owner', 'scrum_master'])->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_user')) {
            return;
        }

        Schema::table('project_user', function (Blueprint $table) {
            if (Schema::hasColumn('project_user', 'scrum_role')) {
                $table->dropColumn('scrum_role');
            }
        });
    }
};
