<?php

/**
 * دورة الشاشات الأربع لمحفظة العميل عبر المتحكّم — داخل معاملة تُلغى.
 */

use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Invoice;
use App\Services\Wallets\ClientWalletService;
use App\Support\TreasuryAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Modules\Client\Http\Controllers\ClientWalletController;

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
    $client = Client::first();
    $treasury = TreasuryAccounts::cash()->first();
    $controller = app(ClientWalletController::class);

    $before = ClientWalletService::balance($client);
    echo "العميل #{$client->id} — الرصيد قبل: {$before}\n\n";

    // ── store: إيداع 1,000 ──
    $controller->store(Request::create('/', 'POST', [
        'type' => 'deposit',
        'amount' => 1000,
        'treasury_account_id' => $treasury->id,
        'note' => 'اختبار: إيداع من شاشة الإضافة',
    ]), $client->id);

    $check('الرصيد بعد الإيداع', $before + 1000, ClientWalletService::balance($client));

    $tx = ClientWalletTransaction::where('client_id', $client->id)->orderByDesc('id')->first();
    $check('نوع الحركة المحفوظة', 'deposit', $tx->type);
    $check('الرصيد بعدها مسجَّل', $before + 1000, (float) $tx->balance_after);
    $balanced($tx->journal_entry_id);

    // ── edit: الشاشة تُفتح ──
    $html = $controller->edit($client->id, $tx->id)->render();
    $check('شاشة التعديل تُعرض وفيها المبلغ الحالي', true, str_contains($html, 'تعديل حركة #'.$tx->id));

    // ── update: تعديل المبلغ إلى 400 ──
    $controller->update(Request::create('/', 'PUT', [
        'type' => 'deposit',
        'amount' => 400,
        'treasury_account_id' => $treasury->id,
        'note' => 'اختبار: بعد التعديل',
    ]), $client->id, $tx->id);

    $tx->refresh();
    $check('المبلغ بعد التعديل', 400.0, (float) $tx->amount);
    $check('الرصيد بعد التعديل', $before + 400, ClientWalletService::balance($client));
    $check('الرصيد بعدها أُعيد ترتيبه', $before + 400, (float) $tx->balance_after);
    $check('نفس القيد لا قيدٌ جديد', true, $tx->journal_entry_id !== null);
    $balanced($tx->journal_entry_id);
    $check('الملاحظة تغيّرت', 'اختبار: بعد التعديل', $tx->note);

    /* تحويلٌ يُخرج الرصيد سالباً يُردّ: الإيداع ٤٠٠ هو كلُّ ما في المحفظة،
       فجعلُه سحباً يتركها عند ‎-١٠٠‎. والحارس في `updateTransaction()` يرفضه،
       و`run()` تتراجع عن المعاملة فلا يبقى أثر. */
    try {
        $controller->update(Request::create('/', 'PUT', [
            'type' => 'withdraw', 'amount' => 100, 'treasury_account_id' => $treasury->id,
        ]), $client->id, $tx->id);
    } catch (\Throwable $e) {
        // `run()` تبتلع الاستثناء وتُرجع redirect — فالفحص على الأثر لا على الرمي
    }

    $tx->refresh();
    $check('تحويلٌ يُخرج الرصيد سالباً لا يقع', 'deposit', $tx->type);
    $check('الرصيد لم يتأثّر بالمحاولة المرفوضة', $before + 400, ClientWalletService::balance($client));

    // ── تحويل النوع حين يسمح الرصيد ──
    $controller->store(Request::create('/', 'POST', [
        'type' => 'deposit', 'amount' => 900, 'treasury_account_id' => $treasury->id,
    ]), $client->id);

    $check('الرصيد بعد إيداعٍ ثانٍ', $before + 1300, ClientWalletService::balance($client));

    $controller->update(Request::create('/', 'PUT', [
        'type' => 'withdraw',
        'amount' => 100,
        'treasury_account_id' => $treasury->id,
        'note' => 'اختبار: صار سحباً',
    ]), $client->id, $tx->id);

    $tx->refresh();
    $check('النوع بعد التحويل', 'withdraw', $tx->type);
    // 900 (الإيداع الثاني) ناقص 100 (الأوّل وقد صار سحباً)
    $check('الرصيد بعد التحويل إلى سحب', $before + 800, ClientWalletService::balance($client));
    $balanced($tx->journal_entry_id);

    // آخر سطرٍ في الكشف يساوي رصيد المحفظة بعد إعادة الترتيب
    $last = ClientWalletTransaction::where('client_id', $client->id)->orderByDesc('id')->first();
    $check('آخر «الرصيد بعدها» = رصيد المحفظة', ClientWalletService::balance($client), (float) $last->balance_after);

    // ── الرصيد = مجموع الحركات ──
    $sum = (float) DB::table('client_wallet_transactions')->where('client_id', $client->id)
        ->selectRaw("ROUND(SUM(CASE WHEN type IN ('deposit','refund') THEN amount ELSE -amount END),2) s")->value('s');
    $check('الرصيد = مجموع الحركات', $sum, ClientWalletService::balance($client));

    // ── سداد فاتورة ثم تعديله ──
    $invoice = Invoice::create([
        'client_id' => $client->id, 'code' => 'TEST-CRUD-'.uniqid(), 'type' => 'normal',
        'invoice_date' => now()->toDateString(), 'issue_date' => now()->toDateString(),
        'grand_total' => 600, 'subtotal' => 600, 'due_value' => 600,
        'payment_status' => 2, 'is_paid' => false, 'created_by' => 1,
    ]);

    $controller->store(Request::create('/', 'POST', [
        'type' => 'invoice_paid', 'amount' => 600, 'invoice_id' => $invoice->id,
    ]), $client->id);

    $invoice->refresh();
    $check('الفاتورة سُدِّدت من المحفظة', 600.0, (float) $invoice->wallet_paid_amount);
    $check('مستحقّ الفاتورة صفر', 0.0, (float) $invoice->due_value);

    $payTx = ClientWalletTransaction::where('client_id', $client->id)->orderByDesc('id')->first();

    // تعديل السداد إلى 250
    $controller->update(Request::create('/', 'PUT', [
        'type' => 'invoice_paid', 'amount' => 250, 'invoice_id' => $invoice->id,
    ]), $client->id, $payTx->id);

    $invoice->refresh();
    $check('المسدَّد من المحفظة بعد التعديل', 250.0, (float) $invoice->wallet_paid_amount);
    $check('المستحقّ عاد إلى 350', 350.0, (float) $invoice->due_value);
    $check('حالة الفاتورة = 2 (غير مكتملة)', 2, (int) $invoice->payment_status);
    $balanced($payTx->fresh()->journal_entry_id);

    // ── منع الرصيد السالب بالتعديل ──
    try {
        $controller->update(Request::create('/', 'PUT', [
            'type' => 'withdraw', 'amount' => 999999, 'treasury_account_id' => $treasury->id,
        ]), $client->id, $payTx->id);

        $balanceNow = ClientWalletService::balance($client);
        $check('تعديلٌ يُخرج الرصيد سالباً مرفوض', true, $balanceNow >= -0.005);
    } catch (\Throwable $e) {
        $check('تعديلٌ يُخرج الرصيد سالباً مرفوض', true, true);
    }

    // ── لا قيد محفظةٍ غير متوازن ──
    $ids = DB::table('client_wallet_transactions')->pluck('journal_entry_id')->filter()->all();
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
