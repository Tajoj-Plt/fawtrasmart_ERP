<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بياناتُ الحساب البنكي وجهةِ الطوارئ.
 *
 * ─────────────────── البنك ───────────────────
 *
 * صرفُ الرواتب اليوم **نقديٌّ من خزينة** حصراً: `PayrollDisbursementService`
 * يشترط خزينةً ويفحص رصيدها، ولا يعرف للموظف حساباً. فلا سبيل إلى تحويلٍ
 * بنكي ولا إلى ملفّ رواتب (WPS) — وكلاهما في خارطة الرواتب.
 *
 * وهذه الأعمدة أساسُ ذلك: لا تُغيّر شيئاً اليوم، ويقوم عليها التحويلُ
 * البنكي حين يُبنى.
 *
 * ─────────────────── الطوارئ ───────────────────
 *
 * لا حقلَ واحداً يُرجَع إليه إن وقع للموظف مكروهٌ في العمل.
 *
 * ─────────────────── ما لم يُضَف عمداً ───────────────────
 *
 * **رقمُ الهوية والإقامة**: صارا وثيقتين في `employee_documents` — لكلٍّ
 * رقمُها وجهتُها المُصدِرة وتاريخُ انتهائها وصورتُها، وتُنبَّه قبل أن
 * تنتهي. وعمودٌ ثانٍ لهما هنا مصدرُ حقيقةٍ ثانٍ يفترق عن الأوّل صامتاً:
 * تُجدَّد الإقامةُ في شاشة الوثائق ويبقى العمودُ على الرقم القديم.
 *
 * **الحالة الاجتماعية**: لا شيء في النظام يقرؤها — ولا تقريرَ ولا حساب.
 * وعمودٌ لا قارئَ له وزنٌ يُحمَل بلا مقابل، ويُضاف يوم يُطلب.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $columns = [
        'bank_name',
        'bank_account_holder',
        'iban',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
    ];

    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('personal_email');
            }

            if (! Schema::hasColumn('employees', 'bank_account_holder')) {
                $table->string('bank_account_holder')->nullable()->after('bank_name');
            }

            /*
                لا فهرسَ فريد على `iban`: حسابٌ مشترك بين زوجين واردٌ فعلاً،
                وفهرسٌ فريد يردّ الحفظ برسالةٍ لا يفهمها المستخدم. والتحقّق
                من صحّة الرقم شأنُ النموذج لا القاعدة.
            */
            if (! Schema::hasColumn('employees', 'iban')) {
                $table->string('iban', 34)->nullable()->after('bank_account_holder');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('iban');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 40)->nullable()->after('emergency_contact_name');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_relation')) {
                $table->string('emergency_contact_relation', 60)->nullable()->after('emergency_contact_phone');
            }
        });
    }

    public function down(): void
    {
        $existing = array_values(array_filter(
            $this->columns,
            fn (string $column) => Schema::hasColumn('employees', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
