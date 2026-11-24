<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * محافظ العملاء — البند ١١ من «تقرير الإصلاح والتحسين».
 *
 * **ما كان:** شاشة «محفظة العملاء» في المبيعات لم تكن محفظةً أصلاً — كانت
 * تعدّل `accounts.opening_balance` على **حساب ذمم العميل** (تحت المدينين)
 * وتكتب قيد رصيدٍ افتتاحي `OB-{id}`. أي أنها تغيّر ما *على* العميل، لا ما
 * *له* عندنا. ولم يكن لها صلةٌ بفاتورةٍ قطّ.
 *
 * **ما يطلبه التقرير:** المحفظة مبلغٌ ماليٌّ عند الشركة **للعميل** — التزامٌ
 * لا ذمّة — بحسابٍ مستقلّ لكل عميل تحت «أرصدة محافظ العملاء» في الخصوم،
 * يُخصَم منه عند سداد فاتورة.
 *
 *     الخصوم
 *     └── أرصدة محافظ العملاء
 *             ├── محفظة العميل (أحمد)
 *             └── محفظة العميل (خالد)
 *
 * وهذه الهجرة تُهيّئ الثلاثة التي يحتاجها ذلك:
 *
 *   1. مفتاح `client_wallets_parent` في «الحسابات المحاسبية» — الأب الذي
 *      تُنشأ تحته محافظ العملاء. يُترك غير مربوط عمداً: اختيار الحساب
 *      قرارُ المحاسب، ولا ثابت احتياطي في هذا النظام.
 *   2. `client_wallet_transactions` — دفتر حركة المحفظة. الرصيد يعيش في
 *      `accounts.balance` كبقيّة الحسابات، وهذا الجدول يحفظ *لماذا* تغيّر:
 *      إيداعٌ من خزينة، أو سدادُ فاتورة، أو سحب.
 *   3. `invoices.wallet_paid_amount` — كم سُدِّد من هذه الفاتورة من
 *      المحفظة (مذكورٌ في التقرير سطر ٢٥٦٧ شرطاً للبند ١١).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. مفتاح الحساب الأب ──
        if (Schema::hasTable('accounting_settings')) {
            $exists = DB::table('accounting_settings')->where('key', 'client_wallets_parent')->exists();

            if (! $exists) {
                DB::table('accounting_settings')->insertOrIgnore([
                    'key' => 'client_wallets_parent',
                    'name' => 'أرصدة محافظ العملاء',
                    'description' => 'الحساب الأب الذي تُنشأ تحته محفظةُ كل عميل — حسابٌ تحت الخصوم، فالمحفظة مالٌ للعميل عندنا لا عليه.',
                    'group' => 'العملاء والذمم',
                    'account_id' => null,
                    'account_code' => null,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── 2. دفتر حركة المحفظة ──
        if (! Schema::hasTable('client_wallet_transactions')) {
            Schema::create('client_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();

                // حساب المحفظة وقت الحركة — يبقى وإن أُعيد ربط الأب لاحقاً
                $table->unsignedBigInteger('account_id')->nullable()->index();

                /* نوع الحركة:
                     deposit      إيداع نقدي من خزينة   (الرصيد يزيد)
                     withdraw     سحب نقدي إلى خزينة    (الرصيد ينقص)
                     invoice_paid سداد فاتورة من المحفظة (الرصيد ينقص)
                     refund       ردّ سدادِ فاتورة       (الرصيد يزيد) */
                $table->enum('type', ['deposit', 'withdraw', 'invoice_paid', 'refund'])->index();

                // موجبٌ دائماً — الاتجاه من `type` لا من إشارة المبلغ،
                // على نفس اصطلاح الأرصدة الافتتاحية في هذا النظام
                $table->decimal('amount', 15, 2);

                // الرصيد بعد الحركة — كشف الحساب يُقرأ بلا إعادة حساب
                $table->decimal('balance_after', 15, 2)->default(0);

                // الخزينة في الإيداع والسحب، والفاتورة في السداد
                $table->unsignedBigInteger('treasury_account_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();

                $table->string('reference')->nullable();
                $table->text('note')->nullable();

                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        // ── 3. المسدَّد من المحفظة على الفاتورة ──
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'wallet_paid_amount')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'wallet_paid_amount')) {
                    $table->decimal('wallet_paid_amount', 15, 2)->default(0)->after('grand_total');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'wallet_paid_amount')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('wallet_paid_amount');
            });
        }

        Schema::dropIfExists('client_wallet_transactions');

        if (Schema::hasTable('accounting_settings')) {
            DB::table('accounting_settings')->where('key', 'client_wallets_parent')->delete();
        }
    }
};
