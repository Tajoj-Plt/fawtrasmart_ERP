<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حالةُ اعتماد العقد — صلاحيةُ `contracts.approve` («اعتماد العقد»).
 *
 * لم يكن للعقد حالةُ اعتماد: يُحفظ فيُقرأ منه الراتب فوراً. والعمودُ اسمُه
 * `approval_status` لا `status`: للعقد مُحوِّلُ `status` (ساري/منتهٍ من
 * التواريخ) تقرؤه الشاشاتُ والتقارير، وعمودٌ بالاسم نفسِه يطغى عليه.
 *
 *   • `draft`     العقدُ الجديد حتى يُعتمد.
 *   • `approved`  معتمد — ومعه `approved_at` و`approved_by` (users.id).
 *
 * العقودُ القائمة كلُّها تُعلَّم «معتمدة» بتاريخ إنشائها — فلا يتغيّر لها شيء.
 * ومسيرُ الرواتب ونهايةُ الخدمة يقرآن العقودَ كما كانا: الاعتمادُ هنا حالةٌ
 * إدارية تُعرض وتُحرس، لا شرطٌ على قراءة الراتب.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'approval_status')) {
                $table->string('approval_status', 20)->default('draft')->after('created_by');
            }
            if (! Schema::hasColumn('contracts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('contracts', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            }
        });

        // القائمُ معتمدٌ كما كان يُعامَل — بتاريخ إنشائه وبمنشئه
        DB::table('contracts')
            ->where(fn ($q) => $q->whereNull('approved_at')->orWhere('approval_status', 'draft'))
            ->update([
                'approval_status' => 'approved',
                'approved_at' => DB::raw('COALESCE(approved_at, created_at, CURRENT_TIMESTAMP)'),
                'approved_by' => DB::raw('COALESCE(approved_by, created_by)'),
            ]);
    }

    public function down(): void
    {
        // تقاعدٌ لا إسقاط — الأعمدةُ تبقى (انظر اصطلاح «Retire, do not drop»)
    }
};
