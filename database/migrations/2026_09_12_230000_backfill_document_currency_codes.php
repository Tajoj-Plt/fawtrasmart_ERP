<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ختمُ عملةِ النظام على المستندات التي سبقت عمود `currency`.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  لماذا
 * ══════════════════════════════════════════════════════════════════════
 *
 * `currency` أُضيف إلى جداول مستندات المبيعات بعد أن امتلأت، فالصفوفُ
 * السابقةُ له تحمل `''` أو `null`. وشاشاتُ المستندات تبدأ على العملة
 * الافتراضية في فلترها، فتُقصي تلك الصفوفَ كلَّها: شاشةُ الإشعارات
 * الدائنة كانت تفتح على إشعارٍ واحد من ٤٦٣ — بلا خطأٍ ولا رسالة.
 *
 * الفلترُ نفسُه صار متسامحاً (`UsesDocumentCurrency::applyCurrencyFilter()`
 * يعدّ الفارغ عملةَ النظام)، لكنّ الصفوف تبقى ناقصةَ الختم في الطباعة
 * والقيد والتصدير. فهذه تُتمّها مرّةً واحدة.
 *
 * لا تمسّ صفّاً مختوماً بعملةٍ ما — ولو كانت غيرَ الافتراضية.
 */
return new class extends Migration
{
    /** الجداول التي تحمل عمود عملةِ مستند. */
    protected array $tables = [
        'invoices',
        'credit_notifications',
        'quotes',
        'periodic_invoices',
        'installments',
    ];

    public function up(): void
    {
        $code = $this->systemCurrencyCode();

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }

            DB::table($table)
                ->where(function ($query) {
                    $query->whereNull('currency')->orWhere('currency', '');
                })
                ->update(['currency' => $code]);
        }
    }

    /**
     * لا عكس: العمودُ الفارغ لم يكن بياناً بل نقصاً، وإعادتُه إلى الفراغ
     * تُعيد الشاشةَ إلى العطب — انظر «Retire, do not drop».
     */
    public function down(): void {}

    /**
     * رمزُ العملة الافتراضية من «العملات» — بلا `CurrencySettings` لأن
     * الترحيلات تعمل قبل أن يكتمل بناءُ التطبيق أحياناً.
     */
    protected function systemCurrencyCode(): string
    {
        if (Schema::hasTable('system_currencies')) {
            $code = DB::table('system_currencies')->where('is_default', true)->value('code');

            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        return 'SAR';
    }
};
