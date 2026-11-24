<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دورٌ يعمل من التطبيق وحده — `roles.mobile_only`.
 *
 * المندوبُ الميداني لا شأن له بشاشات النظام: عملُه زياراتٌ وفواتيرُ من
 * الجوّال. وفتحُ الموقع له يعني شاشاتٍ لا يحتاجها، وبياناتٍ يراها من حيث
 * لا يلزم، وخطأً يقع من حيث لا يُراقَب.
 *
 * مطفأً — وهو الافتراض — لا يتبدّل شيء. مشتعلاً يُمنع أصحابُ الدور من
 * **الموقع** وحده: تسجيلُ الدخول يُرفض برسالةٍ صريحة، وجلسةٌ قائمةٌ تُغلَق
 * عند أوّل طلب. و**واجهةُ التطبيق تبقى مفتوحةً كما هي** — فالحارسُ على
 * مجموعة `web` لا على `api`.
 *
 * `down()` تُسقط العمود، ومعه ينفتح الموقع لمن كان محجوباً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || Schema::hasColumn('roles', 'mobile_only')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('mobile_only')->default(false)->after('unified_sidebar');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'mobile_only')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('mobile_only');
        });
    }
};
