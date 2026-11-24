<?php

namespace App\Console\Commands;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Support\AccountingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * توليد حركةٍ تجارية كاملة: عملاء ومورّدون وفواتير ومرتجعات وإشعارات
 * ومدفوعات — **كلٌّ بقيده المحاسبي المتوازن**.
 *
 * **لماذا القيد جزءٌ من التوليد لا زينةً عليه؟**
 *
 * بياناتٌ تُدرَج في `invoices` وحدها تملأ شاشة الفواتير وتترك النظام أخرس:
 * ميزان المراجعة فارغ، وكشف حساب العميل فارغ، وأعمار الديون فارغة، ورصيد
 * الخزينة صفر. فما فائدة ستّين فاتورةً لا يعرف بها دفتر اليومية؟ لذلك كل
 * مستندٍ هنا يُولَّد ومعه قيدُه على **الحسابات نفسها** التي تستعملها شاشات
 * النظام — تُقرأ من «الحسابات المحاسبية» وتسقط إلى `SystemAccountIds`
 * احتياطاً، تماماً كما يفعل `InvoicesController`.
 *
 * **والمخزون كذلك**: الشراء يزيد الكميات والبيع ينقصها، فلا يُباع ما ليس
 * في المستودع ولا تظهر تقارير المخزون بأرقامٍ لا مستند وراءها.
 *
 * **الترقيم** من `next_document_number()` لا من عدّادٍ محليّ — فالمولَّد
 * يمرّ بنفس «تسلسل أرقام المستندات» الذي تمرّ به الفواتير الحقيقية،
 * ويُختبر معه.
 *
 * كل ما يولَّد يحمل الوسم `DEMO` في `notes`، فيُميَّز في أي شاشة.
 *
 * **الاستعمال**
 *
 *     php artisan data:seed-demo --check     ← فحص المتطلّبات بلا كتابة
 *     php artisan data:seed-demo
 *     php artisan data:seed-demo --clients=50 --suppliers=50 --sales=200 --purchases=120 --months=12
 *
 * @see \App\Console\Commands\PurgeTransactionalData النظير الذي يمسح
 */
class SeedDemoTransactions extends Command
{
    protected $signature = 'data:seed-demo
                            {--clients=20 : عدد العملاء}
                            {--suppliers=20 : عدد المورّدين}
                            {--sales=60 : عدد فواتير المبيعات}
                            {--purchases=40 : عدد فواتير الشراء}
                            {--months=6 : توزيع التواريخ على هذا العدد من الأشهر الماضية}
                            {--check : فحص المتطلّبات وعرضها بلا كتابة}';

    protected $description = 'توليد عملاء ومورّدين وفواتير مبيعات ومشتريات ومرتجعات وإشعارات دائنة ومدفوعات، مع قيودها المحاسبية';

    /** مفاتيح الحسابات ⇐ معرّفاتها بعد الحلّ */
    private array $acc = [];

    private int $userId = 1;

    private int $storehouseId = 0;

    /** @var array<int, object> */
    private array $products = [];

    /** إحصاء ما وُلِّد */
    private array $made = [];

    public function handle(): int
    {
        if (! $this->resolvePrerequisites()) {
            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info('كل المتطلّبات متوفّرة. للتنفيذ: php artisan data:seed-demo');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('══════ التوليد ══════');

        try {
            DB::transaction(function () {
                $clients = $this->makeClients((int) $this->option('clients'));
                $suppliers = $this->makeSuppliers((int) $this->option('suppliers'));

                $this->openStock();
                $this->makePurchases($suppliers, (int) $this->option('purchases'));
                $this->makeSales($clients, (int) $this->option('sales'));
            });
        } catch (Throwable $e) {
            $this->error('أخفق التوليد ولم يتغيّر شيء: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('إعادة احتساب أرصدة الحسابات…');
        $this->recalculateBalances();

        $this->renderSummary();

        return self::SUCCESS;
    }

    // ══════════════════════════════════════════════════════════════════
    //  المتطلّبات
    // ══════════════════════════════════════════════════════════════════

    /**
     * الأصنافُ والمستودعُ والحساباتُ التي يقوم عليها التوليد.
     *
     * تُفحص كلُّها قبل أوّل كتابة لا أثناءها: حسابٌ مفقودٌ يُكتشف عند
     * الفاتورة الأربعين يترك القاعدةَ نصفَ ممتلئة — والفحصُ المسبق يردّ
     * بسببٍ مسمّى قبل أن يُكتب شيء.
     */
    private function resolvePrerequisites(): bool
    {
        $ok = true;

        $this->products = DB::table('products')->select('id', 'name', 'purchase_price', 'sale_price')->get()->all();

        if ($this->products === []) {
            $this->error('✗ لا أصناف في النظام — أضف صنفاً واحداً على الأقل.');
            $ok = false;
        } else {
            $this->line('<info>✓</info> أصناف متاحة: '.count($this->products));
        }

        $house = DB::table('store_houses')->orderByDesc('major')->first();

        if (! $house) {
            $this->error('✗ لا مستودعات — أضف مستودعاً واحداً على الأقل.');
            $ok = false;
        } else {
            $this->storehouseId = (int) $house->id;
            $this->line('<info>✓</info> المستودع: '.$house->name);
        }

        $user = DB::table('users')->whereIn('role', ['manager', 'main'])->first() ?? DB::table('users')->first();

        if (! $user) {
            $this->error('✗ لا مستخدمين.');
            $ok = false;
        } else {
            $this->userId = (int) $user->id;
        }

        // المفتاح ⇐ البديل الثابت حين لا يكون مضبوطاً في شاشة الحسابات
        $needed = [
            'sales' => SystemAccountIds::SALES,
            'sales_returns' => SystemAccountIds::SALES_RETURNS,
            'purchases' => SystemAccountIds::PURCHASES,
            'suppliers' => SystemAccountIds::SUPPLIERS,
            'clients_account' => 18,
            'main_treasury' => SystemAccountIds::MAIN_TREASURY,
            'allowed_discount' => SystemAccountIds::ALLOWED_DISCOUNT,
        ];

        foreach ($needed as $key => $fallback) {
            $id = AccountingSettings::id($key, $fallback);
            $account = $id ? DB::table('accounts')->find($id) : null;

            if (! $account) {
                $this->error("✗ حساب «{$key}» غير مضبوط ولا موجود (المحاولة: {$id}).");
                $ok = false;

                continue;
            }

            $this->acc[$key] = (int) $account->id;
            $this->line(sprintf('<info>✓</info> %-18s → %s %s', $key, $account->code, $account->name));
        }

        // ضريبة القيمة المضافة: بالمفتاح إن وُجد، وإلّا بالكود المحاسبي
        foreach (['vat_payable' => '2141', 'vat_paid' => '2142'] as $key => $code) {
            $id = AccountingSettings::id($key) ?: DB::table('accounts')->where('code', $code)->value('id');

            if (! $id) {
                $this->error("✗ حساب «{$key}» (كود {$code}) غير موجود.");
                $ok = false;

                continue;
            }

            $this->acc[$key] = (int) $id;
            $this->line(sprintf('<info>✓</info> %-18s → %s', $key, DB::table('accounts')->find($id)->name));
        }

        return $ok;
    }

    // ══════════════════════════════════════════════════════════════════
    //  حسابات الجهات
    // ══════════════════════════════════════════════════════════════════

    /** معرّف الجهة ⇐ معرّف حسابها في الشجرة */
    private array $clientAccounts = [];

    private array $supplierAccounts = [];

    /**
     * حسابُ العميل الفرعي — لا الحساب الأب.
     *
     * **لماذا الفرعي؟** لأنّ `InvoicesController` يُرحّل إليه:
     * `Account::ledger()->where('client_id', …)->first()`. فترحيلُ البيانات المولَّدة
     * إلى الأب `1241 العملاء` يجعل ميزان المراجعة صحيحاً وكشفَ حساب كل
     * عميلٍ فارغاً — والأخيرُ هو ما يُفتح يومياً، لا الأوّل.
     *
     * ويسقط إلى الأب حين لا يكون للعميل حساب، فلا يُفقد القيد: شغّل
     * `clients:backfill-accounts` قبل التوليد ليصيب كلَّ عميل.
     */
    private function clientAccount(int $clientId): int
    {
        if (! array_key_exists($clientId, $this->clientAccounts)) {
            $this->clientAccounts[$clientId] = (int) (DB::table('accounts')
                ->where('client_id', $clientId)->value('id') ?: $this->acc['clients_account']);
        }

        return $this->clientAccounts[$clientId];
    }

    /** حسابُ المورّد الفرعي — نظير `clientAccount()` */
    private function supplierAccount(int $supplierId): int
    {
        if (! array_key_exists($supplierId, $this->supplierAccounts)) {
            $this->supplierAccounts[$supplierId] = (int) (DB::table('accounts')
                ->where('supplier_id', $supplierId)->value('id') ?: $this->acc['suppliers']);
        }

        return $this->supplierAccounts[$supplierId];
    }

    // ══════════════════════════════════════════════════════════════════
    //  الجهات
    // ══════════════════════════════════════════════════════════════════

    /**
     * إنشاء عملاء — أو إعادةُ القائمين حين يكون العدد صفراً.
     *
     * الصفرُ ليس «لا تفعل شيئاً» بل «استعمل من في القاعدة»: فإعادةُ توليد
     * المستندات بعد مسحها لا ينبغي أن تضاعف الجهات في كل مرّة.
     *
     * @return array<int, int>
     */
    private function makeClients(int $count): array
    {
        if ($count <= 0) {
            $ids = DB::table('clients')->pluck('id')->all();
            $this->line('  عملاء (قائمون): '.count($ids));

            return $ids;
        }

        $ids = [];
        $now = now();
        $nextCode = (int) DB::table('clients')->max('code') + 1;

        foreach ($this->names($count, 'عميل') as $i => $name) {
            $ids[] = (int) DB::table('clients')->insertGetId([
                'trade_name' => $name,
                'first_name' => $this->pick(self::FIRST_NAMES),
                'last_name' => $this->pick(self::LAST_NAMES),
                // `clients.code` عمودٌ رقمي لا نصّي — و`status` قائمةٌ
                // نصّية (`active`…) لا رقم. الخلطُ بينهما يردّه المحرّك
                'code' => $nextCode + $i,
                'phone' => '05'.random_int(10000000, 99999999),
                'city' => $this->pick(self::CITIES),
                'country' => 'SA',
                'currency' => 'SAR',
                'status' => 'active',
                'notes' => 'DEMO',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->made['عملاء'] = count($ids);
        $this->line('  عملاء: '.count($ids));

        return $ids;
    }

    /**
     * إنشاء مورّدين — أو إعادةُ القائمين حين يكون العدد صفراً.
     *
     * @return array<int, int>
     */
    private function makeSuppliers(int $count): array
    {
        if ($count <= 0) {
            $ids = DB::table('suppliers')->pluck('id')->all();
            $this->line('  موردون (قائمون): '.count($ids));

            return $ids;
        }

        $ids = [];
        $now = now();

        foreach ($this->names($count, 'مورّد') as $i => $name) {
            $ids[] = (int) DB::table('suppliers')->insertGetId([
                // `number_suply` إلزامي بلا قيمة افتراضية
                'number_suply' => 'DEMO-S'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'trade_name' => $name,
                'first_name' => $this->pick(self::FIRST_NAMES),
                'last_name' => $this->pick(self::LAST_NAMES),
                'phone' => '05'.random_int(10000000, 99999999),
                'city' => $this->pick(self::CITIES),
                'country' => 'SA',
                'currency' => 'SAR',
                'status' => 1,
                'notes' => 'DEMO',
                'created_by' => $this->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->made['موردون'] = count($ids);
        $this->line('  موردون: '.count($ids));

        return $ids;
    }

    // ══════════════════════════════════════════════════════════════════
    //  المخزون
    // ══════════════════════════════════════════════════════════════════

    /**
     * رصيدٌ افتتاحي للمخزون.
     *
     * بلا كميّةٍ في المستودع لا تُباع فاتورةٌ واحدة — والشراء وحده لا يكفي
     * لأنّ التواريخ متداخلة: بيعُ شهرٍ أوّلَ قد يسبق شراءَه في الترتيب.
     * فيُفتح لكل صنفٍ رصيدٌ يكفي، ثمّ يتحرّك بالمستندات.
     */
    private function openStock(): void
    {
        foreach ($this->products as $product) {
            $row = DB::table('product_details')
                ->where('product_id', $product->id)
                ->where('store_house_id', $this->storehouseId)
                ->first();

            $opening = random_int(400, 900);

            if ($row) {
                DB::table('product_details')->where('id', $row->id)->update(['quantity' => $opening]);
            } else {
                DB::table('product_details')->insert([
                    'product_id' => $product->id,
                    'store_house_id' => $this->storehouseId,
                    'quantity' => $opening,
                    'unit_price' => $this->cost($product),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->line('  رصيد مخزون افتتاحي لـ'.count($this->products).' صنفاً');
    }

    // ══════════════════════════════════════════════════════════════════
    //  المشتريات
    // ══════════════════════════════════════════════════════════════════

    /** @param  array<int, int>  $suppliers */
    private function makePurchases(array $suppliers, int $count): void
    {
        if ($suppliers === []) {
            return;
        }

        $invoices = 0;
        $returns = 0;
        $payments = 0;

        for ($i = 0; $i < $count; $i++) {
            $supplierId = $this->pick($suppliers);
            $date = $this->randomDate();
            $lines = $this->buildLines(fn ($p) => $this->cost($p), random_int(1, 4));

            $id = $this->writePurchase($supplierId, $date, $lines, 'invoice');
            $invoices++;

            // الشراء يُدخل البضاعة
            $this->moveStock($lines, +1);

            // ثلثُ الفواتير يُسدَّد، وعُشرُها يُرتجع جزئياً
            if (random_int(1, 3) === 1) {
                $this->writeSupplierPayment($supplierId, $date, $lines['grand_total']);
                $payments++;
            }

            if (random_int(1, 10) === 1) {
                $returnLines = $this->buildLines(fn ($p) => $this->cost($p), 1);
                $this->writePurchase($supplierId, $this->shift($date), $returnLines, 'Return');
                $this->moveStock($returnLines, -1);
                $returns++;
            }
        }

        $this->made['فواتير شراء'] = $invoices;
        $this->made['مرتجع مشتريات'] = $returns;
        $this->made['سندات صرف'] = $payments;
        $this->line("  مشتريات: {$invoices} فاتورة، {$returns} مرتجعاً، {$payments} سند صرف");
    }

    /**
     * فاتورة شراء أو مرتجعها، بقيدها.
     *
     *   شراء:  من حـ/ المشتريات + حـ/ ض.ق.م المدفوعة   إلى حـ/ الموردون
     *   مرتجع: من حـ/ الموردون   إلى حـ/ المشتريات + حـ/ ض.ق.م المدفوعة
     */
    private function writePurchase(int $supplierId, Carbon $date, array $lines, string $type): int
    {
        $isReturn = $type === 'Return';
        $code = next_document_number($isReturn ? 'purchase-return' : 'purchase-invoice');

        $id = (int) DB::table('purchase_invoices')->insertGetId([
            'code' => $code,
            'type' => $type,
            'supplier_id' => $supplierId,
            'account_id' => $this->supplierAccount($supplierId),
            'date' => $date->toDateString(),
            'payment_status' => 'unpaid',
            'subtotal' => $lines['subtotal'],
            'total_discount' => $lines['discount'],
            'total_tax' => $lines['tax'],
            'grand_total' => $lines['grand_total'],
            'due_value' => $lines['grand_total'],
            // `status` قائمةٌ محصورة: disagree|approval|Under Review|convert invoice
            'status' => 'convert invoice',
            'is_received' => 1,
            'notes' => 'DEMO',
            'created_by' => $this->userId,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        foreach ($lines['items'] as $line) {
            DB::table('invoice_items')->insert([
                'purchase_invoice_id' => $id,
                // `purchase_invoice_id_type` عمودٌ رقمي رغم اسمه — يُترك
                // فارغاً بدل حشو نوعِ المستند نصّاً فيه
                'product_id' => $line['product_id'],
                'store_house_id' => $this->storehouseId,
                'item' => $line['name'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'total' => $line['total'],
                'type' => 'product',
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $supplierAccount = $this->supplierAccount($supplierId);

        $debits = $isReturn
            ? [[$supplierAccount, $lines['grand_total']]]
            : [[$this->acc['purchases'], $lines['net']], [$this->acc['vat_paid'], $lines['tax']]];

        $credits = $isReturn
            ? [[$this->acc['purchases'], $lines['net']], [$this->acc['vat_paid'], $lines['tax']]]
            : [[$supplierAccount, $lines['grand_total']]];

        $this->writeJournal(
            $code,
            $isReturn ? 'PR' : 'PI',
            $date,
            ($isReturn ? 'مرتجع مشتريات' : 'فاتورة شراء').' رقم '.$code,
            $debits,
            $credits,
            ['purchase_invoice_id' => $id]
        );

        return $id;
    }

    /** سند صرفٍ لمورّد: من حـ/ الموردون إلى حـ/ الخزينة */
    private function writeSupplierPayment(int $supplierId, Carbon $date, float $amount): void
    {
        $date = $this->shift($date);

        DB::table('payments_process')->insert([
            'supplier_id' => $supplierId,
            'treasury_id' => $this->acc['main_treasury'],
            'account_id' => $this->acc['suppliers'],
            'payment_date' => $date->toDateString(),
            'amount' => $amount,
            'type' => 'purchases',
            // العمودان رقميّان: ١ مسدَّد، و١ = «كاش» في `payment_methods`
            'payment_status' => 1,
            'Payment_method' => 1,
            'notes' => 'DEMO',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $code = next_document_number('payment-voucher');

        $this->writeJournal(
            $code,
            'PAY',
            $date,
            'سداد مورّد رقم '.$code,
            [[$this->supplierAccount($supplierId), $amount]],
            [[$this->acc['main_treasury'], $amount]]
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  المبيعات
    // ══════════════════════════════════════════════════════════════════

    /** @param  array<int, int>  $clients */
    private function makeSales(array $clients, int $count): void
    {
        if ($clients === []) {
            return;
        }

        $invoices = 0;
        $returns = 0;
        $credits = 0;
        $receipts = 0;

        for ($i = 0; $i < $count; $i++) {
            $clientId = $this->pick($clients);
            $date = $this->randomDate();
            $lines = $this->buildLines(fn ($p) => $this->price($p), random_int(1, 5));

            $this->writeSale($clientId, $date, $lines, 'normal');
            $invoices++;

            $this->moveStock($lines, -1);

            // نصفُ الفواتير يُحصَّل
            if (random_int(1, 2) === 1) {
                $this->writeClientReceipt($clientId, $date, $lines['grand_total']);
                $receipts++;
            }

            // عُشرٌ يُرتجع، وعُشرٌ يُصدر له إشعار دائن
            if (random_int(1, 10) === 1) {
                $returnLines = $this->buildLines(fn ($p) => $this->price($p), 1);
                $this->writeSale($clientId, $this->shift($date), $returnLines, 'returned');
                $this->moveStock($returnLines, +1);
                $returns++;
            }

            if (random_int(1, 10) === 1) {
                $this->writeCreditNote($clientId, $this->shift($date));
                $credits++;
            }
        }

        $this->made['فواتير مبيعات'] = $invoices;
        $this->made['مرتجع مبيعات'] = $returns;
        $this->made['إشعارات دائنة'] = $credits;
        $this->made['سندات قبض'] = $receipts;
        $this->line("  مبيعات: {$invoices} فاتورة، {$returns} مرتجعاً، {$credits} إشعاراً، {$receipts} سند قبض");
    }

    /**
     * فاتورة مبيعات أو مرتجعها، بقيدها.
     *
     *   بيع:   من حـ/ العملاء   إلى حـ/ المبيعات + حـ/ ض.ق.م المحصلة
     *   مرتجع: من حـ/ مردودات المبيعات + حـ/ ض.ق.م   إلى حـ/ العملاء
     */
    private function writeSale(int $clientId, Carbon $date, array $lines, string $type): int
    {
        $isReturn = $type === 'returned';
        $code = next_document_number($isReturn ? 'return-invoice' : 'invoice');

        $id = (int) DB::table('invoices')->insertGetId([
            'code' => $code,
            'type' => $type,
            'client_id' => $clientId,
            // `invoices.treasury_id` مفتاحٌ أجنبي إلى جدول `treasuries` لا
            // إلى `accounts` — وهو فارغ في هذه القاعدة. يُترك فارغاً بدل
            // حشو معرّف حسابٍ يردّه القيد المرجعي.
            // (ملاحظة: العمود نفسه يشير إلى `accounts` في جداول أخرى —
            //  `salary_slips` و`sales_target_achievements` — وهو تعارضٌ
            //  في المخطّط لا يخصّ هذا الأمر.)
            'invoice_date' => $date->toDateString(),
            'issue_date' => $date->toDateString(),
            'currency' => 'SAR',
            // رقمٌ لا نصّ — كما يكتبها `InvoicesController`:
            // ١ مكتملة، ٢ جزئية، ٣ غير مسدّدة
            'payment_status' => 3,
            'subtotal' => $lines['subtotal'],
            'discount_amount' => $lines['discount'],
            'tax_total' => $lines['tax'],
            'total' => $lines['net'],
            'grand_total' => $lines['grand_total'],
            'due_value' => $lines['grand_total'],
            'remaining_amount' => $lines['grand_total'],
            'is_paid' => 0,
            'notes' => 'DEMO',
            'created_by' => $this->userId,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        foreach ($lines['items'] as $line) {
            DB::table('invoice_items')->insert([
                'invoice_id' => $id,
                'product_id' => $line['product_id'],
                'store_house_id' => $this->storehouseId,
                'item' => $line['name'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'total' => $line['total'],
                'type' => 'product',
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        if ($lines['tax'] > 0) {
            DB::table('taxs_invoice')->insert([
                'name' => 'ضريبة القيمة المضافة',
                'invoice_id' => $id,
                'type_invoice' => $isReturn ? 'return' : 'invoice',
                'rate' => self::VAT_RATE * 100,
                'value' => $lines['tax'],
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $clientAccount = $this->clientAccount($clientId);

        $debits = $isReturn
            ? [[$this->acc['sales_returns'], $lines['net']], [$this->acc['vat_payable'], $lines['tax']]]
            : [[$clientAccount, $lines['grand_total']]];

        $credits = $isReturn
            ? [[$clientAccount, $lines['grand_total']]]
            : [[$this->acc['sales'], $lines['net']], [$this->acc['vat_payable'], $lines['tax']]];

        $this->writeJournal(
            $code,
            $isReturn ? 'SR' : 'SI',
            $date,
            ($isReturn ? 'مرتجع مبيعات' : 'فاتورة مبيعات').' رقم '.$code,
            $debits,
            $credits,
            ['invoice_id' => $id, 'client_id' => $clientId]
        );

        return $id;
    }

    /** إشعار دائن: من حـ/ مردودات المبيعات + الضريبة إلى حـ/ العملاء */
    private function writeCreditNote(int $clientId, Carbon $date): void
    {
        $lines = $this->buildLines(fn ($p) => $this->price($p), 1);
        $code = next_document_number('credit-note');

        $id = (int) DB::table('credit_notifications')->insertGetId([
            'client_id' => $clientId,
            'credit_number' => $code,
            'credit_date' => $date->toDateString(),
            'currency' => 'SAR',
            'store_house_id' => $this->storehouseId,
            'subtotal' => $lines['subtotal'],
            'total_discount' => $lines['discount'],
            'total_tax' => $lines['tax'],
            'grand_total' => $lines['grand_total'],
            'due_value' => $lines['grand_total'],
            'status' => 1,
            'notes' => 'DEMO',
            'created_by' => $this->userId,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        foreach ($lines['items'] as $line) {
            DB::table('invoice_items')->insert([
                'credit_note_id' => $id,
                'product_id' => $line['product_id'],
                'store_house_id' => $this->storehouseId,
                'item' => $line['name'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'total' => $line['total'],
                'type' => 'product',
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $this->writeJournal(
            $code,
            'CN',
            $date,
            'إشعار دائن رقم '.$code,
            [[$this->acc['sales_returns'], $lines['net']], [$this->acc['vat_payable'], $lines['tax']]],
            [[$this->clientAccount($clientId), $lines['grand_total']]],
            ['client_id' => $clientId]
        );
    }

    /** سند قبضٍ من عميل: من حـ/ الخزينة إلى حـ/ العملاء */
    private function writeClientReceipt(int $clientId, Carbon $date, float $amount): void
    {
        $date = $this->shift($date);

        DB::table('payments_process')->insert([
            'client_id' => $clientId,
            'treasury_id' => $this->acc['main_treasury'],
            'account_id' => $this->clientAccount($clientId),
            'payment_date' => $date->toDateString(),
            'amount' => $amount,
            'type' => 'sales',
            'payment_status' => 1,
            'Payment_method' => 1,
            'notes' => 'DEMO',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $code = next_document_number('receipt-voucher');

        $this->writeJournal(
            $code,
            'REC',
            $date,
            'سند قبض رقم '.$code,
            [[$this->acc['main_treasury'], $amount]],
            [[$this->clientAccount($clientId), $amount]],
            ['client_id' => $clientId]
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  القيد
    // ══════════════════════════════════════════════════════════════════

    /**
     * كتابة قيدٍ بطرفيه.
     *
     * الأطرافُ صفرية القيمة تُسقَط — فاتورةٌ بلا ضريبة لا يُكتب لها سطرُ
     * ضريبةٍ بصفر، فيبقى القيد مقروءاً. والتوازن يُفحص قبل الكتابة: قيدٌ
     * غير متوازن يُفسد ميزان المراجعة كلَّه، وكشفُه هنا أهون من تتبّعه
     * لاحقاً في تقريرٍ لا يوازن بريالٍ واحد.
     *
     * @param  array<int, array{0:int, 1:float}>  $debits
     * @param  array<int, array{0:int, 1:float}>  $credits
     */
    private function writeJournal(
        string $reference,
        string $type,
        Carbon $date,
        string $description,
        array $debits,
        array $credits,
        array $extra = []
    ): void {
        $debits = array_values(array_filter($debits, fn ($r) => round((float) $r[1], 2) > 0));
        $credits = array_values(array_filter($credits, fn ($r) => round((float) $r[1], 2) > 0));

        $totalDebit = round(array_sum(array_column($debits, 1)), 2);
        $totalCredit = round(array_sum(array_column($credits, 1)), 2);

        if ($debits === [] || $credits === []) {
            return;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \RuntimeException(
                "قيد غير متوازن ({$reference}): مدين {$totalDebit} ≠ دائن {$totalCredit}"
            );
        }

        $entryId = (int) DB::table('journal_entries')->insertGetId([
            'reference_number' => $reference,
            'reference_type' => $type,
            'date' => $date->toDateString(),
            'description' => $description,
            'status' => 1,
            'is_posted' => 1,
            'is_opening_balance' => 0,
            'currency' => 'SAR',
            'created_by_employee' => $this->userId,
            'created_at' => $date,
            'updated_at' => $date,
        ] + $extra);

        $rows = [];

        foreach ($debits as [$accountId, $amount]) {
            $rows[] = [
                'journal_entry_id' => $entryId,
                'account_id' => $accountId,
                'description' => $description,
                'debit' => round((float) $amount, 2),
                'credit' => 0,
                'is_debit' => 1,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        foreach ($credits as [$accountId, $amount]) {
            $rows[] = [
                'journal_entry_id' => $entryId,
                'account_id' => $accountId,
                'description' => $description,
                'debit' => 0,
                'credit' => round((float) $amount, 2),
                'is_debit' => 0,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        DB::table('journal_entry_details')->insert($rows);
    }

    // ══════════════════════════════════════════════════════════════════
    //  أدوات
    // ══════════════════════════════════════════════════════════════════

    private const VAT_RATE = 0.15;

    private const FIRST_NAMES = ['محمد', 'أحمد', 'عبدالله', 'سالم', 'خالد', 'فهد', 'ناصر', 'يوسف', 'عمر', 'طارق'];

    private const LAST_NAMES = ['الحربي', 'القحطاني', 'العتيبي', 'الشمري', 'الدوسري', 'الزهراني', 'الغامدي', 'المطيري'];

    private const CITIES = ['الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'المدينة المنورة', 'أبها', 'تبوك', 'القصيم'];

    private const TRADE_WORDS = ['النور', 'الفجر', 'السلام', 'الأمانة', 'الرواد', 'المستقبل', 'الخليج', 'الوفاء', 'النخبة', 'الشرق', 'الواحة', 'البيان', 'اليمامة', 'الصفوة', 'المنارة'];

    private const TRADE_KINDS = ['للتجارة', 'التجارية', 'للمقاولات', 'للتوريدات', 'الحديثة', 'للاستيراد', 'العالمية'];

    /**
     * أسماءُ جهاتٍ غير مكرَّرة.
     *
     * التركيبُ من قائمتين يعطي أكثر من مئة اسمٍ متمايز؛ وما زاد يُذيَّل
     * برقمٍ فلا يتكرّر اسمان مهما بلغ العدد المطلوب.
     *
     * @return array<int, string>
     */
    private function names(int $count, string $label): array
    {
        $out = [];
        $seen = [];

        for ($i = 0; $i < $count; $i++) {
            $base = $this->pick(self::TRADE_WORDS).' '.$this->pick(self::TRADE_KINDS);
            $name = isset($seen[$base]) ? $base.' '.($seen[$base] + 1) : $base;
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $out[] = $name;
        }

        return $out;
    }

    /**
     * بنودُ مستند: أصنافٌ بكميّاتٍ وأسعار، ثمّ الخصمُ والضريبةُ والإجمالي.
     *
     * @param  callable(object): float  $priceOf
     * @return array{items: array, subtotal: float, discount: float, net: float, tax: float, grand_total: float}
     */
    private function buildLines(callable $priceOf, int $lineCount): array
    {
        $items = [];
        $subtotal = 0.0;

        $picked = (array) array_rand($this->products, min($lineCount, count($this->products)));

        foreach ($picked as $index) {
            $product = $this->products[$index];
            $quantity = random_int(1, 12);
            $unitPrice = round($priceOf($product), 2);
            $total = round($unitPrice * $quantity, 2);
            $subtotal += $total;

            $items[] = [
                'product_id' => (int) $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $total,
            ];
        }

        $subtotal = round($subtotal, 2);

        // خصمٌ على بعض المستندات لا كلِّها، فتظهر حالتا الوجود والغياب
        $discount = random_int(1, 4) === 1 ? round($subtotal * (random_int(2, 10) / 100), 2) : 0.0;
        $net = round($subtotal - $discount, 2);
        $tax = round($net * self::VAT_RATE, 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'net' => $net,
            'tax' => $tax,
            'grand_total' => round($net + $tax, 2),
        ];
    }

    /**
     * حركةُ المخزون: `+1` للوارد و`-1` للصادر.
     *
     * لا تنزل الكميّة تحت الصفر — مخزونٌ سالب يُفسد كل تقرير يقرؤه.
     */
    private function moveStock(array $lines, int $direction): void
    {
        foreach ($lines['items'] as $line) {
            $row = DB::table('product_details')
                ->where('product_id', $line['product_id'])
                ->where('store_house_id', $this->storehouseId)
                ->first();

            if (! $row) {
                continue;
            }

            $next = max(0, (int) $row->quantity + ($direction * (int) $line['quantity']));
            DB::table('product_details')->where('id', $row->id)->update(['quantity' => $next]);
        }
    }

    /** تكلفة الصنف — وسعرٌ معقول حين لا تكون مضبوطة */
    private function cost(object $product): float
    {
        $cost = (float) ($product->purchase_price ?? 0);

        return $cost > 0 ? $cost : (float) random_int(40, 400);
    }

    /** سعر بيع الصنف — وإلّا التكلفة مضافاً إليها هامش */
    private function price(object $product): float
    {
        $price = (float) ($product->sale_price ?? 0);

        return $price > 0 ? $price : round($this->cost($product) * 1.35, 2);
    }

    /** تاريخٌ عشوائي داخل نافذة الأشهر المطلوبة */
    private function randomDate(): Carbon
    {
        $months = max(1, (int) $this->option('months'));
        $start = now()->copy()->subMonths($months)->startOfMonth();
        $days = max(1, $start->diffInDays(now()));

        return $start->copy()->addDays(random_int(0, $days));
    }

    /** تاريخٌ بعد المستند بأيّامٍ قليلة — للسداد والمرتجع */
    private function shift(Carbon $date): Carbon
    {
        $shifted = $date->copy()->addDays(random_int(1, 20));

        return $shifted->isFuture() ? now()->copy() : $shifted;
    }

    /** @param  array<int, mixed>  $options */
    private function pick(array $options): mixed
    {
        return $options[array_rand($options)];
    }

    private function recalculateBalances(): void
    {
        $ids = DB::table('accounts')->pluck('id');
        $bar = $this->output->createProgressBar($ids->count());

        foreach ($ids as $id) {
            try {
                Account::updateBalancesHierarchically($id);
            } catch (Throwable $e) {
                $this->newLine();
                $this->warn("تعذّر احتساب رصيد الحساب #{$id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function renderSummary(): void
    {
        $this->newLine();
        $this->info('══════ ما وُلِّد ══════');

        $rows = [];
        foreach ($this->made as $label => $count) {
            $rows[] = [$label, number_format($count)];
        }

        $rows[] = ['—', '—'];
        $rows[] = ['قيود اليومية', number_format(DB::table('journal_entries')->where('is_opening_balance', 0)->count())];
        $rows[] = ['أطراف القيود', number_format(DB::table('journal_entry_details')->count())];

        $this->table(['البند', 'العدد'], $rows);
    }
}
