<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * محافظ الموردين — نظير محفظة العميل في دورة المشتريات.
 *
 * **الاتجاه معكوسٌ عن محفظة العميل، وهو الوحيد الذي يستقيم.**
 *
 * محفظة العميل التزام: مالٌ للعميل عندنا. ومحفظة المورد **أصل**: مالٌ
 * لنا لديه — دفعةٌ مقدَّمة سلّمناها ولم نستهلكها بعد. ولو جُعلت التزاماً
 * لَما استقام السداد: سدادُ فاتورة شراء يجب أن **يُنقص** ما علينا للمورد
 * (طرفٌ مدين)، والطرف الدائن المقابل إمّا أصلٌ ينقص أو التزامٌ يزيد —
 * فلو كانت المحفظة التزاماً لَزاد ما علينا بدل أن ينقص، وانتقل الدَّين
 * بين حسابين ولم يُسدَّد شيء.
 *
 *     الأصول
 *     └── دفعات مقدَّمة للموردين
 *             ├── محفظة المورد (محمد)
 *             └── محفظة المورد (خالد)
 *
 * والقيود:
 *
 *     إيداع (دفعة مقدَّمة)  من ح/ محفظة المورد   إلى ح/ الخزينة
 *     استرداد نقدي        من ح/ الخزينة        إلى ح/ محفظة المورد
 *     سداد فاتورة شراء     من ح/ المورد (دائنة) إلى ح/ محفظة المورد
 *
 * فالسطر الأخير يُنقص الطرفين معاً: ما علينا للمورد، وما لنا لديه — وهو
 * معنى «السداد من المحفظة».
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── الحساب الأب ──
        if (Schema::hasTable('accounting_settings')) {
            $exists = DB::table('accounting_settings')->where('key', 'supplier_wallets_parent')->exists();

            if (! $exists) {
                DB::table('accounting_settings')->insertOrIgnore([
                    'key' => 'supplier_wallets_parent',
                    'name' => 'دفعات مقدَّمة للموردين',
                    'description' => 'الحساب الأب الذي تُنشأ تحته محفظةُ كل مورد — حسابٌ تحت الأصول، فالمحفظة مالٌ لنا لدى المورد لا عليه.',
                    'group' => 'المشتريات والموردين',
                    'account_id' => null,
                    'account_code' => null,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── دفتر حركة المحفظة ──
        if (! Schema::hasTable('supplier_wallet_transactions')) {
            Schema::create('supplier_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->unsignedBigInteger('account_id')->nullable()->index();

                /* deposit      دفعة مقدَّمة للمورد     (الرصيد يزيد)
                   withdraw     استرداد نقدي منه       (الرصيد ينقص)
                   invoice_paid سداد فاتورة شراء منها  (الرصيد ينقص)
                   refund       ردّ سدادِ فاتورة        (الرصيد يزيد) */
                $table->enum('type', ['deposit', 'withdraw', 'invoice_paid', 'refund'])->index();

                $table->decimal('amount', 15, 2);
                $table->decimal('balance_after', 15, 2)->default(0);

                $table->unsignedBigInteger('treasury_account_id')->nullable();
                // فاتورة الشراء صفٌّ في `purchase_invoices`
                $table->unsignedBigInteger('purchase_invoice_id')->nullable()->index();
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();

                $table->string('reference')->nullable();
                $table->text('note')->nullable();

                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        // ── المسدَّد من المحفظة على فاتورة الشراء ──
        if (Schema::hasTable('purchase_invoices') && ! Schema::hasColumn('purchase_invoices', 'wallet_paid_amount')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_invoices', 'wallet_paid_amount')) {
                    $table->decimal('wallet_paid_amount', 15, 2)->default(0)->after('grand_total');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'wallet_paid_amount')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropColumn('wallet_paid_amount');
            });
        }

        Schema::dropIfExists('supplier_wallet_transactions');

        if (Schema::hasTable('accounting_settings')) {
            DB::table('accounting_settings')->where('key', 'supplier_wallets_parent')->delete();
        }
    }
};
