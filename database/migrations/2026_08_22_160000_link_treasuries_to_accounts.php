<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الخزينة بحسابها.
 *
 * صلاحيتا الإيداع والسحب تسكنان جدول `treasuries`، بينما تشير السندات
 * والتحويلات إلى `accounts` (`treasury_id` مُتحقَّق منه بـ
 * `exists:accounts,id`). ولا عمود يربط الجدولين — والربط بالاسم ملتبس:
 * في هذه القاعدة خزينتان تحملان الاسم نفسه وتُطابقان الحساب ذاته.
 *
 * فبقيت الصلاحيتان تُحفظان في شاشة الإنشاء ولا يقرؤهما شيء، إذ لا سبيل
 * من عملية على حسابٍ إلى الخزينة التي تحمل شرطها.
 *
 * السطر كان مكتوباً في `TreasuryController::store` ثم عُطّل بتعليق:
 *
 *     // $account->treasury_id = $treasury->id; // ربط الحساب بالخزينة
 *
 * الربط هنا في الاتجاه المعاكس — `treasuries.account_id` — لأن الحساب قد
 * يوجد بلا خزينة (حسابات الشجرة كلّها)، والخزينة لا تقوم بلا حساب.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('treasuries') || ! Schema::hasTable('accounts')) {
            return;
        }

        if (! Schema::hasColumn('treasuries', 'account_id')) {
            Schema::table('treasuries', function (Blueprint $table) {
                if (! Schema::hasColumn('treasuries', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->nullable()->after('id')->index();
                }
            });
        }

        /*
            ملء ما مضى: يُربط بالاسم، لكن **لا يُربط اسمٌ يقابله أكثر من
            حساب** ولا حسابٌ ادّعته أكثر من خزينة — فربطٌ خاطئ يمنح صلاحية
            خزينةٍ لأخرى، وذلك أسوأ من ألّا يوجد ربط.
        */
        $treasuries = DB::table('treasuries')->whereNull('account_id')->get(['id', 'name']);
        $claimed = [];

        foreach ($treasuries as $treasury) {
            $matches = DB::table('accounts')
                ->where('name', $treasury->name)
                ->whereNotNull('parent_id')
                ->pluck('id');

            if ($matches->count() !== 1) {
                continue;   // ملتبس — يُترك ليُربط يدوياً
            }

            $accountId = $matches->first();

            if (isset($claimed[$accountId])) {
                continue;   // حسابٌ ادّعته خزينةٌ أخرى قبلها
            }

            $claimed[$accountId] = true;

            DB::table('treasuries')->where('id', $treasury->id)->update(['account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('treasuries') && Schema::hasColumn('treasuries', 'account_id')) {
            Schema::table('treasuries', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }
    }
};
