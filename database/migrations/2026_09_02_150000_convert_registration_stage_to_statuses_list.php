<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مرحلة التسجيل تصير قائمةً قابلة للتعديل من الإعدادات — مثل حالات
 * العملاء تماماً — لا عمودَ enum ثابتاً بقيمتين مقفلتين في الكود.
 *
 * تُخزَّن صفوفها في جدول `statuses` المشترك تحت `section = 'registration_stage'`
 * (نفس نمط `section = 'supply-order'` سابقاً)، و`clients.registration_stage_id`
 * يشير إليها بدل عمود الـ enum القديم.
 *
 * @see docs/CRM_Customer_Status_Gap_Analysis_Plan.md القسم 3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'registration_stage_id')) {
                $table->unsignedBigInteger('registration_stage_id')->nullable()->after('status_id');
            }
        });

        // زرع المرحلتين الافتراضيتين — قابلتان للتعديل والحذف والإضافة
        // عليهما من الإعدادات فوراً، لا محميتان كحالتَي نشط/موقوف
        $stageIds = [];
        foreach (['follow_up' => 'متابعة', 'under_review' => 'تحت المراجعة'] as $key => $name) {
            $existing = DB::table('statuses')->where('section', 'registration_stage')->where('name', $name)->first();

            $stageIds[$key] = $existing
                ? $existing->id
                : DB::table('statuses')->insertGetId([
                    'section' => 'registration_stage',
                    'name' => $name,
                    'color' => '#ff9f43',
                    'state' => 1,
                    'sort_order' => 0,
                    'is_default' => false,
                    'is_deletable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        // ترحيل قيم عمود الـ enum القديم إلى المعرّف الجديد قبل حذفه
        if (Schema::hasColumn('clients', 'registration_stage')) {
            foreach ($stageIds as $key => $id) {
                DB::table('clients')->where('registration_stage', $key)->update(['registration_stage_id' => $id]);
            }

            Schema::table('clients', function (Blueprint $table) {
                if (Schema::hasColumn('clients', 'registration_stage')) {
                    $table->dropColumn('registration_stage');
                }
            });
        }

        Schema::table('clients', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('clients', 'clients_registration_stage_id_foreign')) {
                $table->foreign('registration_stage_id')->references('id')->on('statuses')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['registration_stage_id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->enum('registration_stage', ['follow_up', 'under_review'])->nullable()->after('status_id');
        });

        $stageNameToKey = ['متابعة' => 'follow_up', 'تحت المراجعة' => 'under_review'];
        foreach (DB::table('statuses')->where('section', 'registration_stage')->get() as $stage) {
            if (isset($stageNameToKey[$stage->name])) {
                DB::table('clients')->where('registration_stage_id', $stage->id)
                    ->update(['registration_stage' => $stageNameToKey[$stage->name]]);
            }
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('registration_stage_id');
        });
    }
};
