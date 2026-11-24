<?php

/**
 * قيودُ مستندات الشراء — تُنفَّذ على القاعدة الحيّة داخل معاملة تُلغى في
 * آخرها، فلا يبقى منها صفٌّ واحد.
 *
 * **ليست اختبار PHPUnit** ولا تُدرَج في `phpunit.xml`: القيدُ يقوم على
 * شجرة حسابات مزروعة ومفاتيحَ مربوطة في «الحسابات المحاسبية»، وقاعدةُ
 * الاختبارات `sqlite :memory:` تبدأ فارغة — على نفس ما في
 * `tests/Wallets/` و`tests/SalesReports/` وسببِه.
 *
 *   php artisan tinker --execute="app()->setLocale('ar'); require 'tests/Purchases/purchase_journal_entries.php';"
 *
 * تغطّي ثلاثة أشياء أُصلحت معاً:
 *   ١) فصلَ ضريبة الشحن عن ضريبة البنود في سطرين.
 *   ٢) ترحيلَ الخصم إلى «خصم مكتسب» لا إلى «خصم مسموح به» (حساب المبيعات).
 *   ٣) بقاءَ القيد متوازناً **بعد التعديل** — وكان يختلّ بفارق
 *      (الخصم − الشحن) لأنّ التعديل يرقّع ثلاثة سطور من خمسة.
 */

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Support\AccountingSettings;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Http\Controllers\InvoicesPurchaseController;

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

/** استدعاء تابعٍ خاصّ — القيدُ يُبنى في توابع `private` لا منفذَ لها غيرها */
$invoke = function (object $object, string $method, array $args) {
    $ref = new ReflectionMethod($object, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($object, $args);
};

/** سطور القيد مجمَّعةً باسم الحساب: ['اسم' => ['dr'=>…, 'cr'=>…]] */
$linesOf = function (int $entryId): array {
    $out = [];

    foreach (JournalEntryDetail::where('journal_entry_id', $entryId)->get() as $d) {
        $name = Account::find($d->account_id)->name ?? ('#'.$d->account_id);
        $out[$name]['dr'] = ($out[$name]['dr'] ?? 0) + (float) $d->debit;
        $out[$name]['cr'] = ($out[$name]['cr'] ?? 0) + (float) $d->credit;
    }

    return $out;
};

DB::beginTransaction();

try {
    $controller = new InvoicesPurchaseController;

    $supplier = Supplier::first();

    if (! $supplier) {
        throw new RuntimeException('لا مورد في القاعدة — لا يمكن بناء القيد.');
    }

    echo "\n═══ الحسابات المربوطة ═══\n";

    foreach (['purchases', 'vat_paid', 'shipping_vat_paid', 'purchase_shipping', 'purchase_discount', 'allowed_discount'] as $key) {
        $account = AccountingSettings::account($key);
        printf("  %-20s %s\n", $key, $account ? $account->name.' ('.$account->code.')' : '(غير مربوط)');
    }

    $discountAccount = AccountingSettings::account('purchase_discount');
    $allowedAccount = AccountingSettings::account('allowed_discount');

    $check('حساب خصم الشراء اسمه «خصم مكتسب»', 'خصم مكتسب', $discountAccount->name ?? '—');
    $check('وهو غير حساب خصم البيع', true, (int) ($discountAccount->id ?? 0) !== (int) ($allowedAccount->id ?? 0));

    /*
        أرقامُ PINV-00069 نفسِها — الفاتورة التي وُجدت مختلّةً في القاعدة:
        بندٌ ١٠×١٠٠، خصمُ سطر ١٠٠، خصمٌ عامّ ١٠٪، شحن ٥٠، ضريبة ١٥٪.
        منها ٧٫٥٠ ضريبةُ الشحن (٥٠ × ١٥٪) و١٢١٫٥٠ ضريبةُ البنود.
    */
    $invoice = PurchaseInvoice::create([
        'code' => 'TEST-JE-'.uniqid(),
        'type' => 'invoice',
        'supplier_id' => $supplier->id,
        'date' => now()->toDateString(),
        'status' => 1,
        'payment_status' => 'unpaid',
        'subtotal' => 1000.00,
        'total_discount' => 190.00,
        'shipping_cost' => 50.00,
        'shipping_tax' => 7.50,
        'total_tax' => 129.00,
        'grand_total' => 989.00,
        'due_value' => 989.00,
        'created_by' => 1,
    ]);

    echo "\n═══ ١) القيد عند الإنشاء ═══\n";

    $invoke($controller, 'createAccountingEntriesFixed', [$invoice, 989.00, 129.00, 0, null]);

    $entry = JournalEntry::where('purchase_invoice_id', $invoice->id)
        ->where('reference_number', $invoice->code)
        ->first();

    $check('أُنشئ قيدُ الفاتورة', true, $entry !== null);

    $lines = $linesOf($entry->id);

    foreach ($lines as $name => $amounts) {
        printf("    %-26s مدين %-10s دائن %s\n", $name, number_format($amounts['dr'], 2), number_format($amounts['cr'], 2));
    }

    $dr = array_sum(array_column($lines, 'dr'));
    $cr = array_sum(array_column($lines, 'cr'));

    $check('المدين = الدائن', round($cr, 2), round($dr, 2));
    $check('سطر المشتريات', 1000.00, $lines['المشتريات']['dr'] ?? 0);
    $check('ضريبة البنود وحدها', 121.50, $lines[AccountingSettings::account('vat_paid')->name]['dr'] ?? 0);
    $check('ضريبة الشحن في حسابها', 7.50, $lines['ضريبة شحن مشتريات']['dr'] ?? 0);
    $check('تكلفة الشحن', 50.00, $lines['شحن مشتريات']['dr'] ?? 0);
    $check('الخصم دائنٌ في «خصم مكتسب»', 190.00, $lines['خصم مكتسب']['cr'] ?? 0);
    $check('لا شيء في «خصم مسموح به»', 0.0, ($lines['خصم مسموح به']['cr'] ?? 0) + ($lines['خصم مسموح به']['dr'] ?? 0));

    echo "\n═══ ٢) القيد بعد التعديل ═══\n";

    /* تُغيَّر الأرقام كما يفعل المستخدم: شحنٌ أكبر وخصمٌ أكبر وضريبةٌ أعلى.
       وقبل الإصلاح كان يكفي **ألّا** يغيّر شيئاً ليختلّ القيد. */
    $invoice->update([
        'subtotal' => 1200.00,
        'total_discount' => 200.00,
        'shipping_cost' => 60.00,
        'shipping_tax' => 9.00,
        'total_tax' => 159.00,
        'grand_total' => 1219.00,
        'due_value' => 1219.00,
    ]);

    $invoke($controller, 'updateAccountingEntriesFixed', [
        $invoice->fresh(), 989.00, 0, 129.00, 1219.00, 0, 159.00, null,
    ]);

    $lines = $linesOf($entry->id);

    foreach ($lines as $name => $amounts) {
        printf("    %-26s مدين %-10s دائن %s\n", $name, number_format($amounts['dr'], 2), number_format($amounts['cr'], 2));
    }

    $dr = array_sum(array_column($lines, 'dr'));
    $cr = array_sum(array_column($lines, 'cr'));

    $check('المدين = الدائن بعد التعديل', round($cr, 2), round($dr, 2));
    // 1219 − 159 − 60 + 200
    $check('سطر المشتريات أُعيد حسابه', 1200.00, $lines['المشتريات']['dr'] ?? 0);
    $check('ضريبة البنود تبعت التعديل', 150.00, $lines[AccountingSettings::account('vat_paid')->name]['dr'] ?? 0);
    $check('ضريبة الشحن تبعت التعديل', 9.00, $lines['ضريبة شحن مشتريات']['dr'] ?? 0);
    $check('الشحن تبع التعديل', 60.00, $lines['شحن مشتريات']['dr'] ?? 0);
    $check('الخصم تبع التعديل', 200.00, $lines['خصم مكتسب']['cr'] ?? 0);
    $check('ذمم المورد = الإجمالي', 1219.00, $lines[Account::ledger()->where('supplier_id', $supplier->id)->first()->name]['cr'] ?? 0);

    echo "\n═══ ٣) فاتورة بلا شحن ولا خصم ═══\n";

    $plain = PurchaseInvoice::create([
        'code' => 'TEST-JE2-'.uniqid(),
        'type' => 'invoice',
        'supplier_id' => $supplier->id,
        'date' => now()->toDateString(),
        'status' => 1,
        'payment_status' => 'unpaid',
        'subtotal' => 500.00,
        'total_discount' => 0,
        'shipping_cost' => 0,
        'shipping_tax' => 0,
        'total_tax' => 75.00,
        'grand_total' => 575.00,
        'due_value' => 575.00,
        'created_by' => 1,
    ]);

    $invoke($controller, 'createAccountingEntriesFixed', [$plain, 575.00, 75.00, 0, null]);

    $plainEntry = JournalEntry::where('purchase_invoice_id', $plain->id)->first();
    $plainLines = $linesOf($plainEntry->id);

    $check('بلا شحن: لا سطر ضريبة شحن', false, isset($plainLines['ضريبة شحن مشتريات']));
    $check('بلا شحن: الضريبة كلُّها سطرٌ واحد', 75.00, $plainLines[AccountingSettings::account('vat_paid')->name]['dr'] ?? 0);
    $check(
        'متوازن',
        round(array_sum(array_column($plainLines, 'cr')), 2),
        round(array_sum(array_column($plainLines, 'dr')), 2)
    );

    echo "\n═══ ٤) مرتجع المشتريات — يعكس الفاتورة سطراً بسطر ═══\n";

    $returnController = new \Modules\Purchases\Http\Controllers\ReturnsInvoiceController;

    $return = PurchaseInvoice::create([
        'code' => 'TEST-RET-'.uniqid(),
        'type' => 'Return',
        'supplier_id' => $supplier->id,
        'date' => now()->toDateString(),
        'status' => 1,
        'payment_status' => 'unpaid',
        'subtotal' => 1000.00,
        'total_discount' => 190.00,
        'shipping_cost' => 50.00,
        'shipping_tax' => 7.50,
        'total_tax' => 129.00,
        'grand_total' => 989.00,
        'due_value' => 989.00,
        'created_by' => 1,
    ]);

    $invoke($returnController, 'createReturnAccountingEntriesFixed', [$return, $supplier, 0, null]);

    $returnEntry = JournalEntry::where('purchase_invoice_id', $return->id)
        ->where('reference_number', $return->code)
        ->first();

    $check('أُنشئ قيدُ المرتجع', true, $returnEntry !== null);

    $rl = $linesOf($returnEntry->id);

    foreach ($rl as $name => $amounts) {
        printf("    %-26s مدين %-10s دائن %s\n", $name, number_format($amounts['dr'], 2), number_format($amounts['cr'], 2));
    }

    $check(
        'المرتجع متوازن',
        round(array_sum(array_column($rl, 'cr')), 2),
        round(array_sum(array_column($rl, 'dr')), 2)
    );
    $check('المشتريات دائن بالصافي وحده', 1000.00, $rl['المشتريات']['cr'] ?? 0);
    $check('ضريبة البنود مستردّة', 121.50, $rl[AccountingSettings::account('vat_paid')->name]['cr'] ?? 0);
    $check('ضريبة الشحن مستردّة في حسابها', 7.50, $rl['ضريبة شحن مشتريات']['cr'] ?? 0);
    $check('الشحن عاد دائناً', 50.00, $rl['شحن مشتريات']['cr'] ?? 0);
    $check('الخصم المكتسب رُدَّ مديناً', 190.00, $rl['خصم مكتسب']['dr'] ?? 0);

    echo "\n═══ ٥) المرتجع بعد التعديل ═══\n";

    $return->update([
        'subtotal' => 600.00,
        'total_discount' => 100.00,
        'shipping_cost' => 20.00,
        'shipping_tax' => 3.00,
        'total_tax' => 78.00,
        'grand_total' => 598.00,
        'due_value' => 598.00,
    ]);

    $invoke($returnController, 'updateAccountingEntriesFixed', [
        $return->fresh(), 989.00, 0, 129.00, 598.00, 0, 78.00, null,
    ]);

    $rl = $linesOf($returnEntry->id);

    foreach ($rl as $name => $amounts) {
        printf("    %-26s مدين %-10s دائن %s\n", $name, number_format($amounts['dr'], 2), number_format($amounts['cr'], 2));
    }

    $check(
        'المرتجع ما زال متوازناً',
        round(array_sum(array_column($rl, 'cr')), 2),
        round(array_sum(array_column($rl, 'dr')), 2)
    );
    // 598 − 78 − 20 + 100
    $check('سطر المشتريات تبع التعديل', 600.00, $rl['المشتريات']['cr'] ?? 0);
    $check('الخصم تبع التعديل', 100.00, $rl['خصم مكتسب']['dr'] ?? 0);
    $check('ذمم المورد تبعت التعديل', 598.00, $rl[Account::ledger()->where('supplier_id', $supplier->id)->first()->name]['dr'] ?? 0);

    echo "\n═══ ٦) الإشعار المدين ═══\n";

    $noticeController = new \Modules\Purchases\Http\Controllers\CityNoticesController;

    $notice = PurchaseInvoice::create([
        'code' => 'TEST-DN-'.uniqid(),
        'type' => 'City Notice',
        'supplier_id' => $supplier->id,
        'date' => now()->toDateString(),
        'status' => 1,
        'payment_status' => 'unpaid',
        'subtotal' => 1000.00,
        'total_discount' => 190.00,
        'shipping_cost' => 50.00,
        'shipping_tax' => 7.50,
        'total_tax' => 129.00,
        'grand_total' => 989.00,
        'due_value' => 989.00,
        'created_by' => 1,
    ]);

    $invoke($noticeController, 'createCityNoticeAccountingEntries', [$notice, $supplier, 0, null]);

    $noticeEntry = JournalEntry::where('reference_number', $notice->code)->first();

    $check('أُنشئ قيدُ الإشعار', true, $noticeEntry !== null);

    $nl = $linesOf($noticeEntry->id);

    foreach ($nl as $name => $amounts) {
        printf("    %-26s مدين %-10s دائن %s\n", $name, number_format($amounts['dr'], 2), number_format($amounts['cr'], 2));
    }

    $check(
        'الإشعار متوازن',
        round(array_sum(array_column($nl, 'cr')), 2),
        round(array_sum(array_column($nl, 'dr')), 2)
    );
    $check('المشتريات دائن بالصافي وحده', 1000.00, $nl['المشتريات']['cr'] ?? 0);
    $check('ضريبة الشحن في حسابها', 7.50, $nl['ضريبة شحن مشتريات']['cr'] ?? 0);
    $check('الشحن عاد دائناً', 50.00, $nl['شحن مشتريات']['cr'] ?? 0);
    $check('الخصم المكتسب رُدَّ مديناً', 190.00, $nl['خصم مكتسب']['dr'] ?? 0);

    echo "\n───────────────────────────────\n";
    echo "  نجح {$pass}   أخفق {$fail}\n";
    echo "───────────────────────────────\n";
} catch (\Throwable $e) {
    echo "\n[EXCEPTION] ".$e->getMessage()."\n";
    echo '  '.$e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
    echo "\nتمّ التراجع — لم يبقَ في القاعدة أثر.\n";
}
