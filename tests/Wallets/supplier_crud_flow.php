<?php

/**
 * الشاشات الأربع لمحفظة المورد عبر المتحكّم — داخل معاملة تُلغى.
 */

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierWalletTransaction;
use App\Services\Wallets\SupplierWalletService;
use App\Support\TreasuryAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Modules\Purchases\Http\Controllers\SupplierWalletController;

$pass = 0;
$fail = 0;

$check = function (string $label, $expected, $actual) use (&$pass, &$fail) {
    $ok = is_float($expected) || is_int($expected)
        ? abs((float) $expected - (float) $actual) < 0.005
        : $expected === $actual;

    $ok ? $pass++ : $fail++;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ')."{$label}: {$actual}".($ok ? '' : "  (المتوقَّع {$expected})")."\n";
};

$balanced = function (int $entryId) use ($check) {
    $r = DB::table('journal_entry_details')->where('journal_entry_id', $entryId)
        ->selectRaw('ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) c, COUNT(*) n')->first();
    $check("قيد #{$entryId} متوازن (مدين {$r->d} = دائن {$r->c}، أسطر {$r->n})", (float) $r->d, (float) $r->c);
};

Auth::loginUsingId(1);
View::share('errors', new \Illuminate\Support\ViewErrorBag);
app()->setLocale('ar');

DB::beginTransaction();

try {
    $supplier = Supplier::first();
    $treasury = TreasuryAccounts::cash()->first();
    $controller = app(SupplierWalletController::class);

    $before = SupplierWalletService::balance($supplier);
    echo "المورد #{$supplier->id} — الرصيد قبل: {$before}\n\n";

    // ── الشاشات تُعرض ──
    foreach (['index' => fn () => $controller->index(Request::create('/')),
        'show' => fn () => $controller->show($supplier->id),
        'create' => fn () => $controller->create(Request::create('/'), $supplier->id)] as $name => $call) {
        try {
            $html = $call()->render();
            $li = preg_match_all('/<li\b/', $html);
            $div = preg_match_all('/<div\b/', $html);
            $ok = $li === substr_count($html, '</li>') && $div === substr_count($html, '</div>');
            $check("شاشة {$name} تُعرض ووسمُها متوازن", true, $ok);
        } catch (\Throwable $e) {
            $check("شاشة {$name} تُعرض", 'بلا خطأ', $e->getMessage());
        }
    }

    // ── store: دفعة مقدَّمة 2,000 ──
    $controller->store(Request::create('/', 'POST', [
        'type' => 'deposit', 'amount' => 2000, 'treasury_account_id' => $treasury->id,
        'note' => 'اختبار: دفعة مقدَّمة',
    ]), $supplier->id);

    $check('الرصيد بعد الدفعة', $before + 2000, SupplierWalletService::balance($supplier));

    $tx = SupplierWalletTransaction::where('supplier_id', $supplier->id)->orderByDesc('id')->first();
    $check('نوع الحركة', 'deposit', $tx->type);
    $balanced($tx->journal_entry_id);

    // ── edit + update: 2000 ← 1,200 ──
    $html = $controller->edit($supplier->id, $tx->id)->render();
    $check('شاشة التعديل تُعرض', true, str_contains($html, 'تعديل حركة #'.$tx->id));

    $controller->update(Request::create('/', 'PUT', [
        'type' => 'deposit', 'amount' => 1200, 'treasury_account_id' => $treasury->id,
        'note' => 'اختبار: بعد التعديل',
    ]), $supplier->id, $tx->id);

    $tx->refresh();
    $check('المبلغ بعد التعديل', 1200.0, (float) $tx->amount);
    $check('الرصيد بعد التعديل', $before + 1200, SupplierWalletService::balance($supplier));
    $check('الرصيد بعدها أُعيد ترتيبه', $before + 1200, (float) $tx->balance_after);
    $balanced($tx->journal_entry_id);

    // ── سداد فاتورة شراء ثم تعديله ──
    $invoice = PurchaseInvoice::create([
        'supplier_id' => $supplier->id, 'code' => 'TEST-SCRUD-'.uniqid(), 'type' => 'invoice',
        'date' => now()->toDateString(), 'grand_total' => 900, 'subtotal' => 900, 'due_value' => 900,
        'payment_status' => 'unpaid', 'is_paid' => false, 'created_by' => 1,
    ]);

    $controller->store(Request::create('/', 'POST', [
        'type' => 'invoice_paid', 'amount' => 900, 'purchase_invoice_id' => $invoice->id,
    ]), $supplier->id);

    $invoice->refresh();
    $check('الفاتورة سُوِّيت من المحفظة', 900.0, (float) $invoice->wallet_paid_amount);
    $check('مستحقّ الفاتورة صفر', 0.0, (float) $invoice->due_value);
    $check('حالة الفاتورة = paid', 'paid', $invoice->payment_status);
    $check('الرصيد بعد التسوية', $before + 300, SupplierWalletService::balance($supplier));

    $payTx = SupplierWalletTransaction::where('supplier_id', $supplier->id)->orderByDesc('id')->first();

    $controller->update(Request::create('/', 'PUT', [
        'type' => 'invoice_paid', 'amount' => 400, 'purchase_invoice_id' => $invoice->id,
    ]), $supplier->id, $payTx->id);

    $invoice->refresh();
    $check('المسدَّد بعد التعديل', 400.0, (float) $invoice->wallet_paid_amount);
    $check('المستحقّ عاد إلى 500', 500.0, (float) $invoice->due_value);
    $check('حالة الفاتورة = partially_paid', 'partially_paid', $invoice->payment_status);
    $check('الرصيد بعد تعديل التسوية', $before + 800, SupplierWalletService::balance($supplier));
    $balanced($payTx->fresh()->journal_entry_id);

    // ── الرصيد = مجموع الحركات، وآخر سطرٍ يطابقه ──
    $sum = (float) DB::table('supplier_wallet_transactions')->where('supplier_id', $supplier->id)
        ->selectRaw("ROUND(SUM(CASE WHEN type IN ('deposit','refund') THEN amount ELSE -amount END),2) s")->value('s');
    $check('الرصيد = مجموع الحركات', $sum, SupplierWalletService::balance($supplier));

    $last = SupplierWalletTransaction::where('supplier_id', $supplier->id)->orderByDesc('id')->first();
    $check('آخر «الرصيد بعدها» = رصيد المحفظة', SupplierWalletService::balance($supplier), (float) $last->balance_after);

    // ── لا قيد غير متوازن ──
    $ids = DB::table('supplier_wallet_transactions')->pluck('journal_entry_id')->filter()->all();
    $bad = DB::table('journal_entry_details')->whereIn('journal_entry_id', $ids)
        ->groupBy('journal_entry_id')->havingRaw('ROUND(SUM(debit),2) <> ROUND(SUM(credit),2)')
        ->pluck('journal_entry_id')->all();
    $check('لا قيد محفظةٍ غير متوازن', 0, count($bad));

    echo "\n────────────────────────────\nنجح: {$pass}   أخفق: {$fail}\n";
} catch (\Throwable $e) {
    echo "\n!! استثناء: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
    echo "(تراجعت المعاملة)\n";
}
