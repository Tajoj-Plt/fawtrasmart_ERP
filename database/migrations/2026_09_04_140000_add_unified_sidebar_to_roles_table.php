<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
        «القائمة الجانبية الموحّدة» — خاصيّةٌ على مستوى الدور لا النظام.

        كل وحدةٍ تعرض قائمتها الجانبية وحدها (`purchases::layouts.sidebar`
        وأخواتها)، فمن انتقل إلى إدارة العملاء فقد من أمامه شاشاتِ
        المبيعات حتى يعود عبر الترويسة. وهذا صحيحٌ لمن يعمل داخل وحدةٍ
        واحدة، ومُعطِّلٌ لمن يعمل عبر الوحدات كلها في يومه.

        فصار الاختيار للدور: عمودٌ واحد يقرؤه [App\Support\UnifiedSidebar]
        قبل كل استدعاءِ قائمة. مطفأً (الافتراض) لا يتغيّر شيء — القوائم
        كما هي وحدةً وحدة. مشتعلاً تُعرَض القائمة الجامعة
        (`layouts.sidebar`) في كل شاشات النظام، وهي مسوَّرةٌ بالصلاحيات
        بنداً بنداً فلا تكشف لصاحب الدور ما ليس له.

        على `roles` لا على `users`: الصلاحيات تُقرأ من `users.role_id`
        (انظر `User::hasRolePermission`)، فيكون المفتاح حيث الصلاحيات.
    */
    public function up(): void
    {
        if (Schema::hasColumn('roles', 'unified_sidebar')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'unified_sidebar')) {
                $table->boolean('unified_sidebar')->default(false)->after('guard_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('roles', 'unified_sidebar')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('unified_sidebar');
        });
    }
};
