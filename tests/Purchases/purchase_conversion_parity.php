<?php

/**
 * تحويلُ استلامِ مشترياتٍ إلى فاتورة — يجب أن يعطي **نفسَ** قيد الفاتورة
 * المُنشأة مباشرةً بالأرقام ذاتها.
 *
 * تُنفَّذ على القاعدة الحيّة داخل معاملة تُلغى في آخرها. ليست اختبار
 * PHPUnit — انظر رأس `purchase_journal_entries.php`.
 *
 *   php artisan tinker --execute="app()->setLocale('ar'); require base_path('tests/Purchases/purchase_conversion_parity.php');"
 *
 * العطلُ الذي تغطّيه: أمرُ شراء POR-00001 بإجمالي 4104.25 (شحن 100، ضريبة
 * شحن 15، خصم مستند 30) كان يخرج فاتورةً بـ4019.25 وقيداً من ثلاثة سطور،
 * لأنّ `convertToInvoice()` كان يكتب `'shipping_cost' => 0` ولا يقرأ خصم
 * المستند، ولأنّ `postSupplierLiabilityEntry()` كان يبني القيد بنفسه.
 */

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReceiving;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Http\Controllers\InvoicesPurchaseController;
use Modules\Purchases\Http\Controllers\PurchaseReceivingController;

$pass = 0;
$fail = 0;

$check = function (string $label, $expected, $actual) use (&$pass, &$fail) {
    $ok = is_float($expected) || is_int($expected)
        ? abs((float) $expected - (float) $actual) < 0.005
        : $expected === $actual;

    $pass += $ok ? 1 : 0;
    $fail += $ok ? 0 : 1;
    echo $ok ? "  [ok]   {$label}: {$actual}\n" : "  [FAIL] {$label}: expected {$expected}, got {$actual}\n";
};

/** سطور قيدٍ مجمَّعةً باسم الحساب */
$linesOf = function (int $entryId): array {
    $out = [];

    foreach (JournalEntryDetail::where('journal_entry_id', $entryId)->get() as $d) {
        $name = Account::find($d->account_id)->name ?? ('#'.$d->account_id);
        $out[$name]['dr'] = round(($out[$name]['dr'] ?? 0) + (float) $d->debit, 2);
        $out[$name]['cr'] = round(($out[$name]['cr'] ?? 0) + (float) $d->credit, 2);
    }

    ksort($out);

    return $out;
};

$show = function (array $lines) {
    foreach ($lines as $name => $a) {
        printf("      %-26s مدين %-11s دائن %s\n", $name, number_format($a['dr'], 2), number_format($a['cr'], 2));
    }
    printf("      %-26s %-11s %s\n", '— المجموع —',
        number_format(array_sum(array_column($lines, 'dr')), 2),
        number_format(array_sum(array_column($lines, 'cr')), 2));
};

$invoke = function (object $o, string $m, array $args) {
    $r = new ReflectionMethod($o, $m);
    $r->setAccessible(true);

    return $r->invokeArgs($o, $args);
};

Auth::login(User::find(1));
DB::beginTransaction();

try {
    /* الاستلام موجودٌ ومُعتمَد وقد حُوِّل مرّةً من قبل، فتُعاد حالتُه إلى
       ما قبل التحويل داخل المعاملة: تُمحى الفاتورةُ السابقة وقيدُها
       ويُصفَّر `invoiced_quantity` — ثمّ يُحوَّل من جديد بالشيفرة الجديدة. */
    $receiving = PurchaseReceiving::with('purchaseOrder')->firstWhere('status', 'approved');

    if (! $receiving) {
        throw new RuntimeException('لا استلام معتمَد في القاعدة.');
    }

    $order = $receiving->purchaseOrder;

    /* الأمرُ القائم في القاعدة أُنشئ **قبل** عمود `shipping_tax`، فحصّةُ
       ضريبة الشحن فيه مدموجةٌ في `total_tax` ولا سبيل إلى استخراجها —
       فيبقى 15 منها خارج الفاتورة المحوَّلة. وهذا نقصُ بياناتٍ تاريخيّ لا
       عطبُ شيفرة: المستنداتُ الجديدة تحفظ الحصّة وحدها.

       فتُملأ هنا — داخل المعاملة المُلغاة — بما يمثّل أمراً يُنشأ اليوم،
       ليُختبَر ما سيجري فعلاً على المستندات الجديدة. */
    if ((float) $order->shipping_tax == 0.0 && (float) $order->shipping_cost > 0) {
        $order->shipping_tax = round((float) $order->total_tax - ((float) $order->subtotal * 0.15), 2);
        $order->save();
        echo "\n(مُلئت ضريبة شحن الأمر بـ".$order->shipping_tax." — الأمر أقدم من العمود)\n";
    }

    printf("\nأمر الشراء %s: شحن %s / ضريبة شحن %s / خصم مستند %s / ضريبة %s / إجمالي %s\n",
        $order->code, $order->shipping_cost, $order->shipping_tax, $order->discount_amount,
        $order->total_tax, $order->grand_total);

    foreach (PurchaseInvoice::where('purchase_receiving_id', $receiving->id)->get() as $old) {
        foreach (JournalEntry::where('purchase_invoice_id', $old->id)->pluck('id') as $jid) {
            JournalEntryDetail::where('journal_entry_id', $jid)->delete();
            JournalEntry::where('id', $jid)->delete();
        }
        DB::table('invoice_items')->where('purchase_invoice_id', $old->id)->delete();
        $old->delete();
    }
    DB::table('purchase_receiving_items')->where('purchase_receiving_id', $receiving->id)->update(['invoiced_quantity' => 0]);

    echo "\n═══ ١) التحويل إلى فاتورة ═══\n";

    $controller = new PurchaseReceivingController;
    $controller->convertToInvoice(Request::create('/x', 'POST', ['payment_status' => 'deferred']), $receiving->id);

    $converted = PurchaseInvoice::where('purchase_receiving_id', $receiving->id)->latest('id')->first();

    $check('نشأت الفاتورة المحوَّلة', true, $converted !== null);

    printf("    %s: شحن %s / ضريبة شحن %s / خصم %s / ضريبة %s / إجمالي %s\n",
        $converted->code, $converted->shipping_cost, $converted->shipping_tax,
        $converted->total_discount, $converted->total_tax, $converted->grand_total);

    $check('الشحن انتقل من الأمر', (float) $order->shipping_cost, (float) $converted->shipping_cost);
    $check('ضريبة الشحن انتقلت', (float) $order->shipping_tax, (float) $converted->shipping_tax);
    $check('خصم المستند انتقل', (float) $order->discount_amount, (float) $converted->discount_amount);
    $check('الإجمالي = إجمالي الأمر', (float) $order->grand_total, (float) $converted->grand_total);

    $convEntry = JournalEntry::where('purchase_invoice_id', $converted->id)
        ->where('reference_number', $converted->code)->first();
    $convLines = $linesOf($convEntry->id);
    $show($convLines);

    $check(
        'قيد التحويل متوازن',
        round(array_sum(array_column($convLines, 'cr')), 2),
        round(array_sum(array_column($convLines, 'dr')), 2)
    );

    echo "\n═══ ٢) فاتورة أُنشئت مباشرةً بنفس الأرقام ═══\n";

    $direct = PurchaseInvoice::create([
        'code' => 'TEST-DIRECT-'.uniqid(),
        'type' => 'invoice',
        'supplier_id' => $converted->supplier_id,
        'date' => $converted->date,
        'status' => 1,
        'payment_status' => 'unpaid',
        'subtotal' => $converted->subtotal,
        'total_discount' => $converted->total_discount,
        'discount_amount' => $converted->discount_amount,
        'shipping_cost' => $converted->shipping_cost,
        'shipping_tax' => $converted->shipping_tax,
        'total_tax' => $converted->total_tax,
        'grand_total' => $converted->grand_total,
        'due_value' => $converted->grand_total,
        'created_by' => 1,
    ]);

    $invoke(new InvoicesPurchaseController, 'createAccountingEntriesFixed',
        [$direct, (float) $direct->grand_total, (float) $direct->total_tax, 0, null]);

    $directEntry = JournalEntry::where('purchase_invoice_id', $direct->id)
        ->where('reference_number', $direct->code)->first();
    $directLines = $linesOf($directEntry->id);
    $show($directLines);

    echo "\n═══ ٣) المطابقة سطراً بسطر ═══\n";

    $check('عدد السطور متطابق', count($directLines), count($convLines));

    foreach ($directLines as $name => $amounts) {
        $check('«'.$name.'» مدين', $amounts['dr'], $convLines[$name]['dr'] ?? -1);
        $check('«'.$name.'» دائن', $amounts['cr'], $convLines[$name]['cr'] ?? -1);
    }

    echo "\n───────────────────────────────\n";
    echo "  نجح {$pass}   أخفق {$fail}\n";
    echo "───────────────────────────────\n";
} catch (\Throwable $e) {
    echo "\n[EXCEPTION] ".$e->getMessage()."\n  ".$e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
    echo "\nتمّ التراجع — لم يبقَ في القاعدة أثر.\n";
}
