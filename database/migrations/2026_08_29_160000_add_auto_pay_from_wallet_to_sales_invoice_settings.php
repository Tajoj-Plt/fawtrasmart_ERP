<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاح «السداد التلقائي من محفظة العميل» في إعدادات فواتير البيع.
 *
 * **لماذا مفتاحٌ جديد ولا يكفي `auto_pay_if_balance`؟** لأنّهما رصيدان
 * مختلفان لا وجهان لواحد:
 *
 *   • `auto_pay_if_balance` يقرأ **رصيد ذمّة العميل الدائن** — حسابَه
 *     تحت المدينين وقد صار سالباً (دفع أكثر ممّا عليه، أو أُنشئ له إشعارٌ
 *     دائن). وتسويتُه مقاصّةٌ يتمّها قيدُ الفاتورة وحده بلا سطرٍ زائد،
 *     لأنّ الطرفين حسابٌ واحد.
 *
 *   • هذا المفتاح يقرأ **المحفظة** — حساباً مستقلاً تحت الخصوم فيه مالٌ
 *     أودعه العميل مقدَّماً. وتسويتُه تحتاج قيداً حقيقياً (من ح/ محفظة
 *     العميل إلى ح/ ذمم العميل) لأنّ الحسابين اثنان.
 *
 * فمن دمجهما في مفتاحٍ واحد جعل تفعيلَ أحدهما تفعيلاً للآخر، وهما يقعان
 * على حسابين ويكتبان قيدين مختلفين.
 *
 * والافتراض `false`: النظام اليوم لا يمسّ المحفظة عند حفظ فاتورة بيع،
 * فتشغيلُ الترحيل وحده يجب ألّا يغيّر سلوك شاشةٍ قائمة. ومن أراده فعّله.
 *
 * @see \App\Services\Wallets\ClientWalletService::payInvoice() القيد الذي يكتبه
 */
return new class extends Migration
{
    private const COLUMN = 'auto_pay_from_wallet';

    public function up(): void
    {
        if (! Schema::hasTable('sales_invoice_settings')) {
            return;
        }

        if (Schema::hasColumn('sales_invoice_settings', self::COLUMN)) {
            return;
        }

        Schema::table('sales_invoice_settings', function (Blueprint $table) {
            $table->boolean(self::COLUMN)->default(false)->after('auto_pay_if_balance');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_invoice_settings') || ! Schema::hasColumn('sales_invoice_settings', self::COLUMN)) {
            return;
        }

        Schema::table('sales_invoice_settings', function (Blueprint $table) {
            $table->dropColumn(self::COLUMN);
        });
    }
};
