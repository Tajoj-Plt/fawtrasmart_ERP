<?php

namespace Modules\Manufacturing\Tests\Feature;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Tests\ManufacturingTestCase;

/*
    اختبارُ التحقّق من أن أدوات المرحلة ٢ تعمل.

    ليس اختباراً وظيفياً — الاختبارات الوظيفية تُكتب مع كل مرحلةٍ
    بوصفها معيارَ قبولها. هذا يثبت أن البنية التحتية قائمة: القاعدة
    تُهاجَر، الحسابات تُزرع، البانية تُنتج بيانات، والقفلُ الذي
    أُضيف في المرحلة ١-ب مدعومٌ فعلاً من المحرّك.
*/
class HarnessSmokeTest extends ManufacturingTestCase
{
    public function test_it_runs_against_a_test_database_not_the_development_one(): void
    {
        $database = DB::connection()->getDatabaseName();

        $this->assertStringContainsString('test', strtolower($database));
        $this->assertNotSame('fawtra1', $database, 'الاختبارات تشير إلى قاعدة التطوير');
    }

    public function test_manufacturing_migrations_ran(): void
    {
        foreach ([
            'manufactur_orders',
            'manufactur_orders_items',
            'production_materials',
            'production_materials_items',
            'production_paths',
            'production_stages',
            'warehouse_permits',
            'product_details',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "الجدول {$table} مفقود");
        }
    }

    /*
        حسابُ انحراف تكاليف الإنتاج أُنشئ في المرحلة ٠ وربُط بمفتاح
        الإعدادات. المراحل اللاحقة (تسوية WIP) تعتمد عليه، فنتأكّد
        هنا أن السَّيدر يزرعه ويربطه فعلاً لا في قاعدة التطوير وحدها.
    */
    public function test_production_variance_account_is_seeded_and_linked(): void
    {
        $account = Account::find(SystemAccountIds::PRODUCTION_VARIANCE);

        $this->assertNotNull($account, 'حساب انحراف تكاليف الإنتاج غير مزروع');
        $this->assertSame('expenses', $account->account_type);

        $this->assertDatabaseHas('accounting_settings', [
            'key' => 'production_variance',
            'account_id' => SystemAccountIds::PRODUCTION_VARIANCE,
        ]);
    }

    public function test_fixtures_build_a_complete_manufacturing_order(): void
    {
        $warehouse = $this->fixtures->warehouse();
        $flour = $this->fixtures->product(['name' => 'دقيق']);
        $bread = $this->fixtures->product(['name' => 'خبز']);

        $this->fixtures->stock($flour, $warehouse, 100);

        $bom = $this->fixtures->bom($bread, ['quantity' => 10]);
        $this->fixtures->bomRawLine($bom, $flour, 5, 2.5);

        $order = $this->fixtures->order($bom, ['quantity' => 20]);

        $this->assertSame(100.0, $this->stockOf($flour->id, $warehouse->id));
        $this->assertSame($bread->id, $order->product_id);
        $this->assertSame($bom->id, $order->production_material_id);
        $this->assertNotNull($order->production_path_id);
        $this->assertSame(0, $this->permitCount($order->id));
    }

    /*
        القفلُ المتشائم هو ما يمنع الإرسال المزدوج في المرحلة ١-ب.
        على SQLite لا أثر له إطلاقاً — لهذا تعمل هذه المجموعة على
        MySQL. نتأكّد أن الاستعلام يُبنى ويُنفَّذ لا أنه يُتجاهل.
    */
    public function test_pessimistic_locking_is_supported_by_the_driver(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());

        $warehouse = $this->fixtures->warehouse();
        $product = $this->fixtures->product();
        $bom = $this->fixtures->bom($product);
        $order = $this->fixtures->order($bom);

        $sql = DB::table('manufactur_orders')->whereKey($order->id)->lockForUpdate()->toSql();
        $this->assertStringContainsString('for update', strtolower($sql));

        DB::transaction(function () use ($order, $warehouse) {
            $locked = DB::table('manufactur_orders')
                ->where('id', $order->id)
                ->lockForUpdate()
                ->first();

            $this->assertNotNull($locked);
            $this->assertSame($order->id, (int) $locked->id);
            $this->assertNotNull($warehouse->id);
        });
    }
}
