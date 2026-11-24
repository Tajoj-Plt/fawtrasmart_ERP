<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات التسجيل لدى هيئة الزكاة والضريبة والجمارك (المرحلة الثانية).
 *
 * الجدول كان يحمل `client_id` و`client_secret` — نموذجَ ربطٍ لا تعرفه
 * الهيئة. الربط الفعلي شهادةٌ رقمية: يُولَّد مفتاحٌ خاصّ وطلبُ توقيع (CSR)،
 * ويُستبدَل برمز OTP من بوابة «فاتورة» بشهادة امتثال، ثم بشهادة إنتاج بعد
 * اجتياز فحوص الامتثال. فهذه الأعمدة ما يُحفَظ من تلك الرحلة.
 *
 * والمفتاح والشهادات والأسرار نصوصٌ `text` لا `string`: التعمية بـ`APP_KEY`
 * تُطيل القيمة أضعافاً، والشهادة وحدها تتجاوز ألف محرف.
 *
 * `building_number` و`district` هنا لا في «إعدادات الحساب»: عنوان الهيئة
 * مفصَّلٌ (رقم مبنى وحيّ) وشاشة الحساب لا تحملهما، والشارع والمدينة والرمز
 * البريدي تبقى من مصدرها هناك.
 *
 * العمودان القديمان `client_id` و`client_secret` و`api_base_url` يبقون: لا
 * يُحذَف عمودٌ من قاعدةٍ قائمة.
 */
return new class extends Migration
{
    private const TABLE = 'electronic_invoice_settings';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = [
            // ── عنوان المنشأة المفصَّل ──
            'building_number' => fn (Blueprint $t) => $t->string('building_number', 10)->nullable(),
            'district' => fn (Blueprint $t) => $t->string('district')->nullable(),

            // ── ما تصدره المنشأة: خانتان، ضريبية ثم مبسّطة ──
            'invoice_types' => fn (Blueprint $t) => $t->string('invoice_types', 4)->default('1100'),

            // ── بنودٌ بلا ضريبة: تصنيفها وسببه كما تطلبهما الهيئة ──
            'zero_tax_category' => fn (Blueprint $t) => $t->string('zero_tax_category', 1)->nullable(),
            'zero_tax_reason_code' => fn (Blueprint $t) => $t->string('zero_tax_reason_code', 40)->nullable(),
            'zero_tax_reason' => fn (Blueprint $t) => $t->string('zero_tax_reason')->nullable(),

            // ── هوية الوحدة المُصدِرة والشهادات ──
            'egs_serial' => fn (Blueprint $t) => $t->string('egs_serial', 64)->nullable(),
            'credentials_environment' => fn (Blueprint $t) => $t->string('credentials_environment', 20)->nullable(),
            'private_key' => fn (Blueprint $t) => $t->text('private_key')->nullable(),
            'csr' => fn (Blueprint $t) => $t->text('csr')->nullable(),
            'compliance_certificate' => fn (Blueprint $t) => $t->text('compliance_certificate')->nullable(),
            'compliance_secret' => fn (Blueprint $t) => $t->text('compliance_secret')->nullable(),
            'compliance_request_id' => fn (Blueprint $t) => $t->string('compliance_request_id', 64)->nullable(),
            'production_certificate' => fn (Blueprint $t) => $t->text('production_certificate')->nullable(),
            'production_secret' => fn (Blueprint $t) => $t->text('production_secret')->nullable(),
            'production_request_id' => fn (Blueprint $t) => $t->string('production_request_id', 64)->nullable(),
            'certificate_expires_at' => fn (Blueprint $t) => $t->timestamp('certificate_expires_at')->nullable(),
            'onboarded_at' => fn (Blueprint $t) => $t->timestamp('onboarded_at')->nullable(),
        ];

        foreach ($columns as $name => $define) {
            if (Schema::hasColumn(self::TABLE, $name)) {
                continue;
            }

            Schema::table(self::TABLE, function (Blueprint $table) use ($define) {
                $define($table);
            });
        }
    }

    public function down(): void
    {
        // لا تُسقَط الأعمدة: فيها المفتاح الخاصّ وشهادة الإنتاج، وضياعهما
        // يعني إعادة التسجيل لدى الهيئة برمز OTP جديد
    }
};
