<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمودُ الفرع للشيكات والأصول.
 *
 * الشيكُ يُصدَر من فرعٍ ويُستلَم في فرع، والأصلُ يقف في فرعٍ ويُهلَك على
 * حسابه — لكنّ الجداول الأربعة خرجت بلا عمود فرعٍ إطلاقاً. فتبديلُ الفرع
 * في الشريط العلوي لا يجد ما يُصفّي به: شاشةُ الشيكات وشاشةُ الأصول تعرضان
 * الشيءَ نفسه في كل الفروع، ولا تقريرَ فرعٍ يستطيع أن يعدّها.
 *
 * والأربعة مستنداتٌ رئيسة لا جداولُ تفصيل:
 *
 *   `received_cheques`     شيكٌ مستلَم    ← يُستلَم في فرع
 *   `payable_cheques`      شيكٌ صادر      ← يُصدَر من فرع
 *   `cheque_books`         دفترُ شيكات    ← يُحفَظ في فرع
 *   `asset_depreciations`  الأصلُ الثابت  ← يقف في فرع
 *
 * وتُركت `asset_dep` (إعداداتُ إهلاك الأصل) بلا عمود: تتبع أصلَها عبر
 * `asset_id`، وتكرارُ الفرع فيها يفتح باب التناقض بين الأمّ والابنة.
 *
 * العمود `nullable` عمداً: «بلا فرع» حالةٌ مشروعة تعني مستنداً سبق تفعيل
 * الفروع، و`BelongsToBranch::scopeForBranch` تُبقي هذه الصفوف ظاهرةً في كل
 * الفروع فلا يختفي تاريخٌ عند التشغيل. والجداول الأربعة فارغةٌ اليوم، فلا
 * تعبئةَ رجعيّةً مطلوبة.
 */
return new class extends Migration
{
    /** المستندات الرئيسة وحدها — لا جداول التفصيل */
    private const TABLES = [
        'received_cheques',
        'payable_cheques',
        'cheque_books',
        'asset_depreciations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('branch_id')->nullable()->after('id')
                    ->comment('فرع المستند — يُختَم عند الإنشاء ولا يُعاد حسابه');
                $blueprint->index('branch_id');
            });

            /*
                المفتاحُ الأجنبي في محاولةٍ منفصلة: قواعدُ بياناتٍ قائمة قد
                تختلف فيها مُحرّكاتُ الجداول (MyISAM لا يقبل المفاتيح)، وسقوطُ
                الترحيل كلّه من أجل قيدٍ إضافيّ خسارةٌ لا داعي لها — الفهرسُ
                أعلاه هو ما يخدم التصفية فعلاً.
            */
            try {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
                });
            } catch (Throwable $e) {
                // القيد اختياري — الفهرس قائم والعمود يعمل بدونه
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                try {
                    $blueprint->dropForeign($table.'_branch_id_foreign');
                } catch (Throwable $e) {
                    // لم يُنشأ أصلاً
                }

                $blueprint->dropIndex($table.'_branch_id_index');
                $blueprint->dropColumn('branch_id');
            });
        }
    }
};
