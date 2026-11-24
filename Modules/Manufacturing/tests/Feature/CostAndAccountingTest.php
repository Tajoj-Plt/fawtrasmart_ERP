<?php

namespace Modules\Manufacturing\Tests\Feature;

use App\Constants\SystemAccountIds;
use App\Enums\BomLineType;
use App\Enums\ManufacturOrderStatus;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\ManufacturOrders;
use App\Models\ManufacturOrdersItem;
use App\Models\Product;
use App\Models\StoreHouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\ResolvesExpenseAccounts;
use Modules\Manufacturing\Http\Controllers\ManufacturingOrdersController;
use Modules\Manufacturing\Services\InventoryService;
use Modules\Manufacturing\Tests\ManufacturingTestCase;

/*
    المرحلة ٤ — مصدر التكلفة والمخزون والمحاسبة.

    القاعدة الحاكمة: القيمة الخارجة من المخزون = القيمة في القيد.
*/
class CostAndAccountingTest extends ManufacturingTestCase
{
    /* ══════════ أ · الحسابات لا تُرحَّل على آباء ══════════ */

    /*
        كان «المنتج التام» مربوطاً بالحساب ١٦ «المخزون» وله ثمانية
        أبناء، بينما إخوته الثلاثة مربوطون بأبناء بلا نسل.
    */
    public function test_manufacturing_accounts_never_point_at_a_parent(): void
    {
        $keys = [
            ManufacturingSources::ACCOUNT_RAW_MATERIALS,
            ManufacturingSources::ACCOUNT_WIP,
            ManufacturingSources::ACCOUNT_FINISHED_GOODS,
            ManufacturingSources::ACCOUNT_WASTE,
            ManufacturingSources::ACCOUNT_OVERHEAD,
            ManufacturingSources::ACCOUNT_PRODUCTION_VARIANCE,
        ];

        $offenders = [];

        foreach ($keys as $key) {
            $accountId = DB::table('accounting_settings')->where('key', $key)->value('account_id');

            $this->assertNotNull($accountId, "المفتاح {$key} غير مربوط");

            $children = DB::table('accounts')->where('parent_id', $accountId)->count();

            if ($children > 0) {
                $offenders[] = "{$key} ⟵ {$accountId} ({$children} أبناء)";
            }
        }

        $this->assertSame([], $offenders, 'مفاتيح تُرحَّل على آباء: '.implode(' · ', $offenders));
    }

    public function test_finished_goods_points_at_the_inventory_child(): void
    {
        $this->assertSame(82, SystemAccountIds::FINISHED_GOODS_INVENTORY);

        $account = Account::find(SystemAccountIds::FINISHED_GOODS_INVENTORY);

        $this->assertNotNull($account);
        $this->assertSame('assets', $account->account_type);
        $this->assertSame(16, (int) $account->parent_id, 'يجب أن يبقى ابناً للمخزون');
    }

    public function test_overhead_points_at_the_expenses_child(): void
    {
        $this->assertSame(2965, SystemAccountIds::MANUFACTURING_OVERHEAD);

        $account = Account::find(SystemAccountIds::MANUFACTURING_OVERHEAD);

        $this->assertNotNull($account);
        $this->assertSame('expenses', $account->account_type);
        $this->assertSame(
            SystemAccountIds::MANUFACTURING_EXPENSES_PARENT,
            (int) $account->parent_id
        );
    }

    /* ══════════ ب · الصرف بتكلفة المخزون ══════════ */

    /*
        العطل الجذري: كان الصرف يستعمل سعر البند التقديري.
        مادةٌ تكلفتها ٨ وسعرُ بندها ١٢ كانت تُخرج ٨٠ من المخزون
        وتُقيّد ١٢٠ في الدفتر.
    */
    public function test_issuing_uses_inventory_cost_not_the_estimated_line_price(): void
    {
        [$order, $flour, $warehouse] = $this->orderReadyToIssue(
            onHand: 100, inventoryCost: 8.0, estimatedPrice: 12.0, required: 10
        );

        $this->issueMaterials($order, $warehouse);

        $line = DB::table('warehouse_permits_products')
            ->join('warehouse_permits', 'warehouse_permits.id', '=', 'warehouse_permits_products.warehouse_permits_id')
            ->where('warehouse_permits.reference_id', $order->id)
            ->where('warehouse_permits.permission_source_id', ManufacturingSources::PERMIT_ISSUE_MATERIALS)
            ->where('warehouse_permits_products.product_id', $flour->id)
            ->first();

        $this->assertNotNull($line, 'لم يُنشأ إذن صرف');
        $this->assertSame(8.0, (float) $line->unit_price, 'صُرف بالسعر التقديري لا بتكلفة المخزون');
        $this->assertSame(80.0, (float) $line->total);
    }

    /* القاعدة الحاكمة، مقيسةً بالأرقام. */
    public function test_the_value_leaving_inventory_equals_the_value_in_the_journal(): void
    {
        [$order, $flour, $warehouse] = $this->orderReadyToIssue(
            onHand: 100, inventoryCost: 8.0, estimatedPrice: 12.0, required: 10
        );

        $inv = app(InventoryService::class);
        $valueBefore = $this->stockOf($flour->id, $warehouse->id) * $inv->unitCost($flour->id, $warehouse->id);

        $this->issueMaterials($order, $warehouse);

        $valueAfter = $this->stockOf($flour->id, $warehouse->id) * $inv->unitCost($flour->id, $warehouse->id);
        $leftInventory = round($valueBefore - $valueAfter, 2);

        $posted = round((float) DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->where('journal_entries.reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('journal_entries.reference_id', $order->id)
            ->where('journal_entry_details.account_id', SystemAccountIds::WORK_IN_PROGRESS_INVENTORY)
            ->sum('journal_entry_details.debit'), 2);

        $this->assertSame($leftInventory, $posted, 'قيمة المخزون المنصرفة تخالف قيمة القيد');
    }

    /* التقديري يبقى إلى جانب الفعلي — الفرق هو انحراف السعر. */
    public function test_estimated_and_actual_costs_are_both_kept(): void
    {
        [$order, , $warehouse] = $this->orderReadyToIssue(
            onHand: 100, inventoryCost: 8.0, estimatedPrice: 12.0, required: 10
        );

        $this->issueMaterials($order, $warehouse);

        $item = $order->manufacturOrdersItem()->first();

        $this->assertSame(12.0, (float) $item->raw_unit_price, 'التقديري ضاع');
        $this->assertSame(8.0, (float) $item->raw_actual_unit_price, 'الفعلي لم يُثبَّت');
        $this->assertSame(80.0, (float) $item->raw_actual_total);
    }

    /* ══════════ ج · كل قيد متوازن ══════════ */

    public function test_every_manufacturing_entry_is_balanced(): void
    {
        [$order, , $warehouse] = $this->orderReadyToIssue(
            onHand: 100, inventoryCost: 8.0, estimatedPrice: 12.0, required: 10
        );

        $this->issueMaterials($order, $warehouse);

        $unbalanced = DB::table('journal_entries')
            ->join('journal_entry_details', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->where('journal_entries.reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('journal_entries.reference_id', $order->id)
            ->groupBy('journal_entries.id')
            ->havingRaw('ABS(SUM(journal_entry_details.debit) - SUM(journal_entry_details.credit)) > 0.01')
            ->pluck('journal_entries.id');

        $this->assertCount(0, $unbalanced, 'قيود غير متوازنة: '.$unbalanced->implode(', '));
    }

    /* ══════════ د · حراس الحالة ══════════ */

    /*
        بعد الإتمام تكون المواد قد استُهلكت وأُقفل WIP. فإرجاعها
        يزيد المخزون ويفتح WIP بسالبٍ لا مصدر له.
    */
    public function test_materials_cannot_be_returned_after_completion(): void
    {
        $this->assertFalse(
            ManufacturOrderStatus::Completed->canReturnMaterials(),
            'الإرجاع ما زال مسموحاً بعد الإتمام'
        );

        $this->assertTrue(ManufacturOrderStatus::MaterialsReceived->canReturnMaterials());
        $this->assertTrue(ManufacturOrderStatus::InProgress->canReturnMaterials());
    }

    /* ══════════ هـ · حصر حساب المصروف ══════════ */

    public function test_expense_accounts_are_limited_to_the_manufacturing_tree(): void
    {
        $controller = new class
        {
            use ResolvesExpenseAccounts;

            public function ids(): array
            {
                return $this->allowedExpenseAccountIds();
            }
        };

        $allowed = $controller->ids();

        $this->assertNotEmpty($allowed, 'قائمة حسابات المصروف فارغة');

        foreach ($allowed as $id) {
            $account = Account::find($id);

            $this->assertSame('expenses', $account->account_type, "الحساب {$id} ليس حساب مصروفات");
            $this->assertSame(
                0,
                Account::where('parent_id', $id)->count(),
                "الحساب {$id} له أبناء — لا يصلح للترحيل"
            );
        }

        // حسابٌ من خارج الشجرة — الخزينة مثلاً — يجب ألّا يكون فيها
        $cash = Account::where('account_type', 'assets')->first();

        if ($cash !== null) {
            $this->assertNotContains($cash->id, $allowed, 'حساب أصول ضمن حسابات المصروف');
        }
    }

    /*
        الحارسُ الخادمي — لا القائمةُ المعروضة.

        تقييدُ الواجهة يمنع الخطأ لا العبث: طلبٌ مصنوعٌ باليد يمرّر أيَّ
        معرّف. فالفحصُ المعتدّ به هو الذي يرفض على الخادم.
    */
    public function test_an_expense_account_outside_the_tree_is_rejected(): void
    {
        $outsider = Account::query()
            ->where('account_type', 'assets')
            ->whereDoesntHave('children')
            ->first();

        $this->assertNotNull($outsider, 'لا حساب أصول للاختبار');

        $guard = new class
        {
            use ResolvesExpenseAccounts;

            public function check(Request $request): void
            {
                $this->assertExpenseAccountsAllowed($request);
            }
        };

        $this->expectException(ValidationException::class);

        $guard->check(Request::create('/', 'POST', [
            'expenses_account_id' => [$outsider->id],
        ]));
    }

    /* والحسابُ المشروع يمرّ — كي لا يكون الحارس مانعاً للجميع. */
    public function test_an_expense_account_inside_the_tree_is_accepted(): void
    {
        $allowed = Account::query()
            ->where('parent_id', SystemAccountIds::MANUFACTURING_EXPENSES_PARENT)
            ->whereDoesntHave('children')
            // «غير المباشرة» خارج قائمة بنود المصروف — تدخل الأمرَ من شاشة
            // التكاليف غير المباشرة لا من هنا
            ->where('name', 'not like', '%غير مباشر%')
            ->first();

        $this->assertNotNull($allowed, 'شجرة مصروفات التصنيع فارغة');

        $guard = new class
        {
            use ResolvesExpenseAccounts;

            public function check(Request $request): void
            {
                $this->assertExpenseAccountsAllowed($request);
            }
        };

        $guard->check(Request::create('/', 'POST', [
            'expenses_account_id' => [$allowed->id],
        ]));

        $this->assertTrue(true, 'مرّ بلا استثناء');
    }

    // ═════════════════════════════════════════════════════════════════

    /*
        صرفُ المواد بمناداة المتحكّم مباشرةً.

        لا عبر HTTP: المسار محميٌّ بوسيطِ صلاحيات، وتجهيزُ الصلاحيات
        يختبر ضبطَ الوصول لا منطقَ التكلفة. والسياسةُ على الأمر غير
        مُعرَّفة بعد، فـ`authorizeOrder` تتخطّاها بصمت.
    */
    private function issueMaterials($order, $warehouse): void
    {
        $this->actingAs($this->fixtures->user());

        $controller = app(ManufacturingOrdersController::class);

        $response = $controller->receiveMaterials(
            Request::create('/', 'POST', ['storehouse_id' => $warehouse->id]),
            $order->id
        );

        $error = $response->getSession()?->get('error');

        $this->assertNull($error, 'تعذّر صرف المواد: '.$error);
    }

    /**
     * أمرٌ جاهزٌ للصرف: مخزونٌ بتكلفةٍ معلومة، وبندٌ بسعرٍ تقديريٍّ مختلف.
     *
     * @return array{0:ManufacturOrders, 1:Product, 2:StoreHouse}
     */
    private function orderReadyToIssue(
        float $onHand,
        float $inventoryCost,
        float $estimatedPrice,
        float $required
    ): array {
        $warehouse = $this->fixtures->warehouse();
        $flour = $this->fixtures->product(['name' => 'دقيق']);
        $bread = $this->fixtures->product(['name' => 'خبز']);

        $this->fixtures->stock($flour, $warehouse, $onHand, $inventoryCost);

        $bom = $this->fixtures->bom($bread);
        $order = $this->fixtures->order($bom, [
            'quantity' => 1,
            'status' => ManufacturOrderStatus::Pending,
            'storehouse_id' => $warehouse->id,
        ]);

        ManufacturOrdersItem::create([
            'manufactur_order_id' => $order->id,
            'line_type' => BomLineType::Raw,
            'raw_product_id' => $flour->id,
            'raw_quantity' => $required,
            'raw_unit_price' => $estimatedPrice,
            'raw_total' => $required * $estimatedPrice,
        ]);

        return [$order->fresh('manufacturOrdersItem'), $flour, $warehouse];
    }
}
