<?php

/**
 * سيناريوهات المحافظ — تُنفَّذ على القاعدة الحيّة داخل معاملة تُلغى في
 * آخرها، فلا يبقى منها صفٌّ واحد.
 *
 * **ليست اختبار PHPUnit** ولا تُدرَج في `phpunit.xml`: المحافظ تقوم على
 * شجرة حسابات مزروعة وحسابَي أب مربوطَين في «الحسابات المحاسبية»، وقاعدةُ
 * الاختبارات `sqlite :memory:` تبدأ فارغة. فتُشغَّل على قاعدة التطوير
 * نفسِها — وهي آمنة لأنّ `DB::rollBack()` في `finally` يُلغي كلّ ما كتبته.
 * وهو نفس موضع `tests/SalesReports/` وسببُه.
 *
 *   php artisan tinker --execute="app()->setLocale('ar'); require 'tests/Wallets/wallet_scenarios.php';"
 *
 * تغطّي: إيداع العميل ١٠٠٠ ← سداد ٧٠٠ ← استرداد ١٠٠، ودفعة المورد ٢٠٠٠ ←
 * تسوية ١٥٠٠ ← استرداد ٢٠٠، والفاتورة الأكبر من الرصيد، ومنعَ التكرار،
 * ومنعَ الرصيد السالب، وتوازنَ كلّ قيد.
 */

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\Wallets\ClientWalletService;
use App\Services\Wallets\SupplierWalletService;
use App\Support\TreasuryAccounts;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

$check = function (string $label, $expected, $actual) use (&$pass, &$fail) {
    $ok = is_float($expected) || is_int($expected)
        ? abs((float) $expected - (float) $actual) < 0.005
        : $expected === $actual;

    if ($ok) {
        $pass++;
        echo "  [ok]   {$label}: {$actual}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$label}: expected {$expected}, got {$actual}\n";
    }
};

/**
 * رصيد حسابٍ **مشتقّاً من قيوده** لا من عموده المخزَّن.
 *
 * العمود قد يحمل انحرافاً سابقاً (وُجد على حساب المورد #3391 فرقُ ١٠
 * ريالات قبل هذا العمل)، و`updateBalancesHierarchically` تصحّحه عند أوّل
 * حركةٍ تمسّ الحساب. فقياسُ أثر المحفظة من العمود يقيس التصحيح معها،
 * والمشتقُّ من القيود لا يتأثّر به.
 */
$ledgerBalance = function (array $accountIds): float {
    $row = DB::table('journal_entry_details')
        ->whereIn('account_id', $accountIds)
        ->selectRaw('ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) c')
        ->first();

    // الحسابات هنا دائنةُ الطبيعة (ذمم مورد) — والموجب يعني أنّ علينا له
    return round((float) $row->c - (float) $row->d, 2);
};

/** توازن قيود حركةٍ بعينها */
$assertBalanced = function (int $journalEntryId) use ($check) {
    $row = DB::table('journal_entry_details')
        ->where('journal_entry_id', $journalEntryId)
        ->selectRaw('ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) c, COUNT(*) n')
        ->first();

    $check("قيد #{$journalEntryId} متوازن (مدين {$row->d} = دائن {$row->c}، أسطر {$row->n})", (float) $row->d, (float) $row->c);
};

DB::beginTransaction();

try {
    $user = \App\Models\User::first();
    \Illuminate\Support\Facades\Auth::loginUsingId($user->id);

    $treasury = TreasuryAccounts::cash()->first();
    if (! $treasury) {
        throw new \Exception('لا خزينة نقدية في الشجرة.');
    }

    /* لا يُزوَّر رصيد الخزينة: `Account::updateBalancesHierarchically()`
       تشتقّ الرصيد من القيود فتكتب فوق أيّ رقمٍ نضعه. فتُقاس الفروق من
       الرصيد الحقيقي. */

    echo "\n══════ العميل ══════\n";

    $client = Client::query()->whereHas('accounts')->first() ?: Client::first();
    echo "العميل: #{$client->id} {$client->display_name}\n";

    $treasuryBefore = (float) $treasury->fresh()->balance;

    // ── إيداع 1,000 ──
    $t1 = ClientWalletService::deposit($client, 1000, $treasury, 'اختبار: إيداع');
    $check('رصيد المحفظة بعد إيداع 1,000', 1000.0, ClientWalletService::balance($client));
    $assertBalanced($t1->journal_entry_id);
    $check('الخزينة زادت 1,000', $treasuryBefore + 1000, (float) $treasury->fresh()->balance);

    // القيد: من ح/ الخزينة إلى ح/ محفظة العميل
    $d1 = DB::table('journal_entry_details')->where('journal_entry_id', $t1->journal_entry_id)->get();
    $walletAccountId = ClientWalletService::accountFor($client)->id;
    $check('الإيداع: المحفظة دائنة', 1000.0, (float) $d1->firstWhere('account_id', $walletAccountId)->credit);
    $check('الإيداع: الخزينة مدينة', 1000.0, (float) $d1->firstWhere('account_id', $treasury->id)->debit);

    /* ذمّةُ العميل تُقرأ **هنا** لا قبل الإيداع: إعادةُ حساب الأرصدة
       تصحّح أيَّ انحرافٍ سابقٍ في العمود المخزَّن، فقياسُ الفرق من رقمٍ
       سبقها يقيس التصحيح مع الحركة. */
    $clientAccountIds = Account::ledger()->where('client_id', $client->id)->pluck('id')->all();
    // حساب العميل مدينُ الطبيعة، فالفرق معكوس عن دالّة المورد
    $clientLedger = fn () => -$ledgerBalance($clientAccountIds);
    $clientAccountBefore = $clientLedger();

    // ── فاتورة 700 تُسدَّد من المحفظة ──
    $invoice = Invoice::create([
        'client_id' => $client->id,
        'code' => 'TEST-W-'.uniqid(),
        'type' => 'normal',
        'invoice_date' => now()->toDateString(),
        'issue_date' => now()->toDateString(),
        'grand_total' => 700,
        'subtotal' => 700,
        'due_value' => 700,
        'payment_status' => 2,
        'is_paid' => false,
        'created_by' => $user->id,
    ]);

    $t2 = ClientWalletService::payInvoice($invoice, 700, 'اختبار: سداد فاتورة');
    $check('رصيد المحفظة بعد سداد 700', 300.0, ClientWalletService::balance($client));
    $assertBalanced($t2->journal_entry_id);

    $invoice->refresh();
    $check('مستحقّ الفاتورة صفر', 0.0, (float) $invoice->due_value);
    $check('حالة الفاتورة = 1 (مكتملة)', 1, (int) $invoice->payment_status);
    $check('المسدَّد من المحفظة على الفاتورة', 700.0, (float) $invoice->wallet_paid_amount);

    $d2 = DB::table('journal_entry_details')->where('journal_entry_id', $t2->journal_entry_id)->get();
    $check('السداد: المحفظة مدينة', 700.0, (float) $d2->firstWhere('account_id', $walletAccountId)->debit);
    $check('السداد: لا أثر للخزينة في القيد', true, $d2->firstWhere('account_id', $treasury->id) === null);
    $check('الخزينة لم تتغيّر بالسداد', $treasuryBefore + 1000, (float) $treasury->fresh()->balance);
    $check('ذمّة العميل نقصت 700', $clientAccountBefore - 700, $clientLedger());

    // ── منع التكرار: سدادٌ ثانٍ لنفس الفاتورة ──
    try {
        ClientWalletService::payInvoice($invoice, 700, 'اختبار: تكرار');
        $check('السداد المكرَّر مرفوض', 'رُفض', 'مرّ!');
    } catch (\Throwable $e) {
        $check('السداد المكرَّر مرفوض', 'رُفض', 'رُفض');
        echo "         └─ {$e->getMessage()}\n";
    }
    $check('الرصيد لم يتأثّر بالمحاولة المكرَّرة', 300.0, ClientWalletService::balance($client));

    // ── استرداد 100 ──
    $t3 = ClientWalletService::withdraw($client, 100, $treasury->fresh(), 'اختبار: استرداد');
    $check('رصيد المحفظة بعد استرداد 100', 200.0, ClientWalletService::balance($client));
    $assertBalanced($t3->journal_entry_id);
    $check('الخزينة نقصت 100', $treasuryBefore + 900, (float) $treasury->fresh()->balance);

    // ── منع الرصيد السالب ──
    try {
        ClientWalletService::withdraw($client, 5000, $treasury->fresh(), 'اختبار: سحب فوق الرصيد');
        $check('السحب فوق الرصيد مرفوض', 'رُفض', 'مرّ!');
    } catch (\Throwable $e) {
        $check('السحب فوق الرصيد مرفوض', 'رُفض', 'رُفض');
    }
    $check('الرصيد النهائي للعميل', 200.0, ClientWalletService::balance($client));

    // ── مطابقة الرصيد لمجموع الحركات ──
    $ledgerSum = (float) DB::table('client_wallet_transactions')
        ->where('client_id', $client->id)
        ->selectRaw("ROUND(SUM(CASE WHEN type IN ('deposit','refund') THEN amount ELSE -amount END), 2) s")
        ->value('s');
    $check('الرصيد = مجموع الحركات', $ledgerSum, ClientWalletService::balance($client));

    echo "\n══════ المورد ══════\n";

    $supplier = Supplier::first();
    echo "المورد: #{$supplier->id} {$supplier->trade_name}\n";

    $treasuryBefore2 = (float) $treasury->fresh()->balance;

    // ── دفعة مقدَّمة 2,000 ──
    $s1 = SupplierWalletService::deposit($supplier, 2000, $treasury->fresh(), 'اختبار: دفعة مقدَّمة');
    $check('رصيد محفظة المورد بعد 2,000', 2000.0, SupplierWalletService::balance($supplier));
    $assertBalanced($s1->journal_entry_id);
    $check('الخزينة نقصت 2,000', $treasuryBefore2 - 2000, (float) $treasury->fresh()->balance);

    $supplierWalletId = SupplierWalletService::accountFor($supplier)->id;
    $sd1 = DB::table('journal_entry_details')->where('journal_entry_id', $s1->journal_entry_id)->get();
    $check('الدفعة: المحفظة مدينة', 2000.0, (float) $sd1->firstWhere('account_id', $supplierWalletId)->debit);
    $check('الدفعة: الخزينة دائنة', 2000.0, (float) $sd1->firstWhere('account_id', $treasury->id)->credit);

    /* ذمّةُ المورد تُقاس من قيودها لا من عمودها المخزَّن — انظر
       `$ledgerBalance` أعلاه. */
    $supplierAccountIds = Account::ledger()->where('supplier_id', $supplier->id)->pluck('id')->all();
    $supplierAccountBefore = $ledgerBalance($supplierAccountIds);

    // ── فاتورة شراء 1,500 تُسوَّى من المحفظة ──
    $pInvoice = PurchaseInvoice::create([
        'supplier_id' => $supplier->id,
        'code' => 'TEST-PW-'.uniqid(),
        'type' => 'invoice',
        'date' => now()->toDateString(),
        'grand_total' => 1500,
        'subtotal' => 1500,
        'due_value' => 1500,
        'payment_status' => 'unpaid',
        'is_paid' => false,
        'created_by' => $user->id,
    ]);

    $s2 = SupplierWalletService::payInvoice($pInvoice, 1500, 'اختبار: تسوية فاتورة شراء');
    $check('رصيد المحفظة بعد تسوية 1,500', 500.0, SupplierWalletService::balance($supplier));
    $assertBalanced($s2->journal_entry_id);

    $pInvoice->refresh();
    $check('مستحقّ فاتورة الشراء صفر', 0.0, (float) $pInvoice->due_value);
    $check('حالة فاتورة الشراء = paid', 'paid', $pInvoice->payment_status);
    $check('المسدَّد من المحفظة', 1500.0, (float) $pInvoice->wallet_paid_amount);

    $sd2 = DB::table('journal_entry_details')->where('journal_entry_id', $s2->journal_entry_id)->get();
    $check('التسوية: المحفظة دائنة', 1500.0, (float) $sd2->firstWhere('account_id', $supplierWalletId)->credit);
    $check('التسوية: لا أثر للخزينة', true, $sd2->firstWhere('account_id', $treasury->id) === null);
    $check('الخزينة لم تتغيّر بالتسوية', $treasuryBefore2 - 2000, (float) $treasury->fresh()->balance);
    $check('ذمّة المورد نقصت 1,500', $supplierAccountBefore - 1500, $ledgerBalance($supplierAccountIds));

    // ── استرداد 200 ──
    $s3 = SupplierWalletService::withdraw($supplier, 200, $treasury->fresh(), 'اختبار: استرداد من مورد');
    $check('رصيد المحفظة بعد استرداد 200', 300.0, SupplierWalletService::balance($supplier));
    $assertBalanced($s3->journal_entry_id);
    $check('الخزينة زادت 200', $treasuryBefore2 - 1800, (float) $treasury->fresh()->balance);

    // ── ردّ التسوية عند الحذف ──
    $s4 = SupplierWalletService::refundInvoice($pInvoice, null, 'اختبار: ردّ عند الحذف');
    $check('الرصيد بعد ردّ 1,500', 1800.0, SupplierWalletService::balance($supplier));
    $assertBalanced($s4->journal_entry_id);
    $pInvoice->refresh();
    $check('مستحقّ الفاتورة عاد 1,500', 1500.0, (float) $pInvoice->due_value);
    $check('المسدَّد من المحفظة عاد صفراً', 0.0, (float) $pInvoice->wallet_paid_amount);
    $check('ذمّة المورد عادت كما كانت', $supplierAccountBefore, $ledgerBalance($supplierAccountIds));

    // ── منع الرصيد السالب على المورد ──
    try {
        SupplierWalletService::withdraw($supplier, 99999, $treasury->fresh(), 'اختبار');
        $check('السحب فوق الرصيد مرفوض (مورد)', 'رُفض', 'مرّ!');
    } catch (\Throwable $e) {
        $check('السحب فوق الرصيد مرفوض (مورد)', 'رُفض', 'رُفض');
    }

    $sLedgerSum = (float) DB::table('supplier_wallet_transactions')
        ->where('supplier_id', $supplier->id)
        ->selectRaw("ROUND(SUM(CASE WHEN type IN ('deposit','refund') THEN amount ELSE -amount END), 2) s")
        ->value('s');
    $check('الرصيد = مجموع الحركات (مورد)', $sLedgerSum, SupplierWalletService::balance($supplier));

    echo "\n══════ الفاتورة أكبر من الرصيد (البند ثالثاً) ══════\n";

    /* رصيد المحفظة ٢٠٠ (ما بقي من سيناريو العميل)، وفاتورةٌ بـ٥٠٠:
       يُخصَم المتاح وحده ويبقى الباقي مستحقّاً — ولا يُسحَب على المكشوف. */
    $bigInvoice = Invoice::create([
        'client_id' => $client->id,
        'code' => 'TEST-W2-'.uniqid(),
        'type' => 'normal',
        'invoice_date' => now()->toDateString(),
        'issue_date' => now()->toDateString(),
        'grand_total' => 500,
        'subtotal' => 500,
        'due_value' => 500,
        'payment_status' => 2,
        'is_paid' => false,
        'created_by' => $user->id,
    ]);

    // نفس حساب المبلغ الذي تُجريه كتلة `auto_pay_from_wallet` في `store()`
    $available = ClientWalletService::balance($client);
    $toApply = round(min($available, (float) $bigInvoice->due_value), 2);
    $check('المبلغ المطبَّق = الرصيد المتاح لا قيمة الفاتورة', 200.0, $toApply);

    $t4 = ClientWalletService::payInvoice($bigInvoice, $toApply, 'اختبار: تغطية جزئية');
    $assertBalanced($t4->journal_entry_id);

    $bigInvoice->refresh();
    $check('المحفظة صفر بعد التغطية الجزئية', 0.0, ClientWalletService::balance($client));
    $check('المتبقّي على الفاتورة 300', 300.0, (float) $bigInvoice->due_value);
    $check('حالة الفاتورة = 2 (غير مكتملة)', 2, (int) $bigInvoice->payment_status);
    $check('الفاتورة ليست مدفوعة', 0, (int) $bigInvoice->is_paid);

    // ومحاولةُ تجاوز الرصيد بعده مرفوضة
    try {
        ClientWalletService::payInvoice($bigInvoice, 300, 'اختبار: فوق الرصيد');
        $check('السداد فوق رصيد المحفظة مرفوض', 'رُفض', 'مرّ!');
    } catch (\Throwable $e) {
        $check('السداد فوق رصيد المحفظة مرفوض', 'رُفض', 'رُفض');
    }
    $check('المحفظة لم تصر سالبة', 0.0, ClientWalletService::balance($client));

    echo "\n══════ مفاتيح الإعدادات ══════\n";

    $salesFlag = \App\Models\SalesInvoiceSetting::current();
    $check('المفتاح موجود في نموذج إعدادات البيع', true,
        in_array('auto_pay_from_wallet', \App\Models\SalesInvoiceSetting::FLAGS, true));
    $check('العمود موجود في القاعدة', true,
        \Illuminate\Support\Facades\Schema::hasColumn('sales_invoice_settings', 'auto_pay_from_wallet'));
    $check('مفتاح المشتريات معروف في السجلّ', true,
        array_key_exists('auto_payment', \Modules\Purchases\Services\PurchaseSettings::keys()));

    echo "\n══════ توازن كلّ قيود المحافظ في هذه الجلسة ══════\n";

    $ids = array_merge(
        DB::table('client_wallet_transactions')->pluck('journal_entry_id')->all(),
        DB::table('supplier_wallet_transactions')->pluck('journal_entry_id')->all(),
    );

    $unbalanced = DB::table('journal_entry_details')
        ->whereIn('journal_entry_id', array_filter($ids))
        ->groupBy('journal_entry_id')
        ->havingRaw('ROUND(SUM(debit),2) <> ROUND(SUM(credit),2)')
        ->pluck('journal_entry_id')
        ->all();

    $check('لا قيد محفظةٍ غير متوازن', 0, count($unbalanced));

    $orphans = DB::table('client_wallet_transactions')->whereNull('journal_entry_id')->count()
        + DB::table('supplier_wallet_transactions')->whereNull('journal_entry_id')->count();
    $check('لا حركة محفظةٍ بلا قيد', 0, $orphans);

    echo "\n────────────────────────────\n";
    echo "نجح: {$pass}   أخفق: {$fail}\n";
} catch (\Throwable $e) {
    echo "\n!! استثناء: ".$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
    echo "(تراجعت المعاملة — لم يبقَ في القاعدة شيء)\n";
}
