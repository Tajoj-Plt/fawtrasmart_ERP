<?php

/**
 * أمرُ الشراء يحفظ حصّةَ ضريبة الشحن وحدها — وإلّا وصلت الفاتورةُ المحوَّلة
 * بصفر، فاندمجت ضريبةُ الشحن في سطر ضريبة البنود وضاع الحسابُ المستقلّ.
 *
 * تُنفَّذ على القاعدة الحيّة داخل معاملة تُلغى في آخرها. ليست اختبار
 * PHPUnit — انظر رأس `purchase_journal_entries.php`.
 *
 *   php artisan tinker --execute="app()->setLocale('ar'); require base_path('tests/Purchases/purchase_order_shipping_tax.php');"
 *
 * ══════════════════ العطل ══════════════════
 *
 * `PurchaseOrdersController` كان يحسب `$shipping_tax` ويضمّه إلى `total_tax`
 * ثمّ **لا يكتبه في عموده** — لا في `store()` ولا في `update()`. فأمرُ
 * POR-00001 حُفظ بشحنٍ 100 وضريبةِ شحنٍ 0٫00، وسلسلةُ «أمر ← استلام ←
 * فاتورة» تنقل الصفر إلى آخرها: فيخرج قيدُ الفاتورة المحوَّلة بلا سطر
 * «ضريبة شحن مشتريات» بينما الفاتورةُ المُنشأة مباشرةً تحمله.
 *
 * ══════════════════ لماذا `store()` لا `update()` ══════════════════
 *
 * الأمرُ القائم لا يقبل التعديل لسببين مستقلّين، وكلاهما حارسٌ صحيح:
 * حالتُه «معتمَد» و`update()` يرفض تعديل المعتمَد، وله استلامٌ يشير إلى
 * بنوده فيمنع المفتاحُ الأجنبيّ استبدالها. فيُنشأ أمرٌ جديدٌ بنفس المدخلات.
 */

use App\Models\PurchaseInvoice;
use App\Models\TaxSitting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Http\Controllers\PurchaseOrdersController;

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

Auth::login(User::find(1));
DB::beginTransaction();

try {
    $order = PurchaseInvoice::where('type', 'Requested')->latest('id')->first();

    if (! $order) {
        throw new RuntimeException('لا أمرَ شراءٍ في القاعدة تُقتبس منه البنود.');
    }

    $vat = TaxSitting::where('tax', 15)->first();

    printf("\nالأمر القائم %s: شحن %s / ضريبة شحن %s / ضريبة %s\n",
        $order->code, $order->shipping_cost, $order->shipping_tax, $order->total_tax);

    $check('العمود صفرٌ على الأمر القديم (أثرُ العطل)', 0.0, (float) $order->shipping_tax);

    $items = [];

    foreach (DB::table('invoice_items')->where('purchase_invoice_id', $order->id)->get() as $row) {
        $items[] = [
            'product_id' => $row->product_id,
            'item' => $row->item,
            'description' => $row->description,
            'quantity' => $row->quantity,
            'unit_price' => $row->unit_price,
            'discount' => $row->discount,
            'discount_type' => $row->discount_type,
            'tax_1_id' => $row->tax_1_id,
            'tax_1' => $row->tax_1,
            'tax_2_id' => $row->tax_2_id,
            'tax_2' => $row->tax_2,
            'product_unit_id' => $row->product_unit_id,
            'unit_name' => $row->unit_name,
            'conversion_factor' => $row->conversion_factor,
        ];
    }

    $check('للأمر بنود تُقتبس', true, $items !== []);

    echo "\n═══ أمرٌ جديد عبر store(): شحن 100 بضريبة 15٪ ═══\n";

    $request = Request::create('/x', 'POST', [
        'supplier_id' => $order->supplier_id,
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => 'Under Review',
        'account_id' => $order->account_id,
        'shipping_cost' => 100,
        'tax_id' => $vat->id,
        'discount_value' => 30,
        'discount_type' => 'amount',
        'items' => $items,
    ]);

    (new PurchaseOrdersController)->store($request);

    // المتحكّم يبتلع الاستثناء ويردّ تحويلةً برسالة — تُطبع وإلّا بدا الإخفاق صامتاً
    if ($flash = session('error')) {
        echo "  [redirect error] {$flash}\n";
    }

    $created = PurchaseInvoice::where('type', 'Requested')->latest('id')->first();

    $check('أُنشئ أمرٌ جديد', true, $created && (int) $created->id !== (int) $order->id);

    printf("\n  %s: شحن %s / ضريبة شحن %s / ضريبة %s / إجمالي %s\n",
        $created->code, $created->shipping_cost, $created->shipping_tax,
        $created->total_tax, $created->grand_total);

    $check('ضريبة الشحن حُفظت في عمودها', 15.0, (float) $created->shipping_tax);
    $check('الشحن حُفظ', 100.0, (float) $created->shipping_cost);

    // `total_tax` يبقى المجموع: بنودٌ + شحن — فالمطبوعاتُ لا تتبدّل
    $check('total_tax يضمّ حصّة الشحن', true, (float) $created->total_tax > 15.0);

    echo "\n───────────────────────────────\n";
    echo "  نجح {$pass}   أخفق {$fail}\n";
    echo "───────────────────────────────\n";
} catch (\Throwable $e) {
    echo "\n[EXCEPTION] ".$e->getMessage()."\n  ".$e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
    echo "\nتمّ التراجع — لم يبقَ في القاعدة أثر.\n";
}
