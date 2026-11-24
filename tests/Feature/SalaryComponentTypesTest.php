<?php

namespace Tests\Feature;

use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Salaries\SalaryComponentTypeController;
use Modules\HR\Http\Controllers\Salaries\SalaryItemsController;
use Tests\TestCase;

/**
 * كتالوج البدلات والاستقطاعات (§15 TODO 4.1).
 *
 * الأنواع المعيارية تُزرع برموز ثابتة، والبند الحرّ يرتبط اختيارياً.
 * لا صلاحية جديدة ولا مفتاح باقة جديد — حراسة بنود الراتب.
 */
class SalaryComponentTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('salary_component_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->unsignedTinyInteger('kind');
            $t->boolean('is_insurable')->default(false);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_system')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedTinyInteger('status')->default(1);
            $t->timestamps();
        });

        Schema::create('salary_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->tinyInteger('type')->nullable()->default(1);
            $t->unsignedBigInteger('component_type_id')->nullable();
            $t->tinyInteger('status')->nullable()->default(1);
            $t->text('description')->nullable();
            $t->tinyInteger('salary_item_value')->nullable()->default(1);
            $t->decimal('amount', 10)->nullable();
            $t->string('calculation_formula')->nullable();
            $t->text('condition')->nullable();
            $t->unsignedBigInteger('account_id')->nullable();
            $t->timestamps();
        });

        Schema::create('logs', function (Blueprint $t) {
            $t->id();
            $t->string('type')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->string('type_log')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->timestamps();
        });

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    public function test_seeded_rows_are_the_three_standard_allowances(): void
    {
        $codes = array_column(SalaryComponentType::seededRows(), 'code');

        $this->assertSame([
            SalaryComponentType::CODE_HOUSING,
            SalaryComponentType::CODE_TRANSPORT,
            SalaryComponentType::CODE_NATURE_OF_WORK,
        ], $codes);

        $housing = collect(SalaryComponentType::seededRows())
            ->firstWhere('code', SalaryComponentType::CODE_HOUSING);

        $this->assertTrue($housing['is_insurable']);
        $this->assertTrue($housing['is_default']);
        $this->assertTrue($housing['is_system']);
        $this->assertSame(SalaryItem::TYPE_ADDITION, $housing['kind']);
    }

    public function test_catalog_routes_use_salary_items_permission_and_plan(): void
    {
        $this->assertTrue(Route::has('SalaryComponentTypes.index'));
        $this->assertTrue(Route::has('SalaryComponentTypes.store'));

        $index = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'SalaryComponentTypes.index');
        $store = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'SalaryComponentTypes.store');

        $this->assertContains('permission.role:hr_salary_items_view', $index->gatherMiddleware());
        $this->assertContains('plan:hr_salary_items', $index->gatherMiddleware());
        $this->assertContains('permission.role:hr_salary_items_manage', $store->gatherMiddleware());
        $this->assertContains('plan:hr_salary_items', $store->gatherMiddleware());
    }

    public function test_system_type_cannot_be_deleted(): void
    {
        $type = $this->systemHousing();

        $response = $this->controller()->destroy($type->id);

        $this->assertTrue(SalaryComponentType::query()->whereKey($type->id)->exists());
        $this->assertSame('لا يُحذف النوع المعياري المزروع', $response->getSession()->get('error'));
    }

    public function test_custom_type_can_be_deleted_and_unlinks_items(): void
    {
        $type = SalaryComponentType::create([
            'code' => 'custom_phone',
            'name' => 'بدل هاتف',
            'kind' => SalaryItem::TYPE_ADDITION,
            'is_system' => false,
            'status' => SalaryComponentType::STATUS_ACTIVE,
        ]);
        $item = SalaryItem::create([
            'name' => 'هاتف أحمد',
            'type' => SalaryItem::TYPE_ADDITION,
            'component_type_id' => $type->id,
        ]);

        $this->controller()->destroy($type->id);

        $this->assertFalse(SalaryComponentType::query()->whereKey($type->id)->exists());
        $this->assertNull($item->fresh()->component_type_id);
    }

    public function test_salary_item_links_to_catalog_and_inherits_kind(): void
    {
        $type = $this->systemHousing();

        $response = $this->itemsController()->store(Request::create('/x', 'POST', [
            'name' => 'سكن فرع الرياض',
            'type' => SalaryItem::TYPE_DEDUCTION,
            'status' => SalaryItem::STATUS_ACTIVE,
            'salary_item_value' => SalaryItem::VALUE_TYPE_AMOUNT,
            'amount' => 1500,
            'component_type_id' => $type->id,
        ]));

        $this->assertTrue($response->isRedirect());
        $item = SalaryItem::query()->first();
        $this->assertNotNull($item);
        $this->assertSame($type->id, $item->component_type_id);
        $this->assertSame(SalaryItem::TYPE_ADDITION, (int) $item->type);
        $this->assertTrue($item->componentType->is($type));
    }

    public function test_store_creates_a_custom_type_with_generated_code(): void
    {
        $response = $this->controller()->store(Request::create('/x', 'POST', [
            'name' => 'بدل اتصال',
            'kind' => SalaryItem::TYPE_ADDITION,
            'status' => SalaryComponentType::STATUS_ACTIVE,
            'is_insurable' => '1',
            'sort_order' => 40,
        ]));

        $this->assertTrue($response->isRedirect());
        $type = SalaryComponentType::query()->where('name', 'بدل اتصال')->first();
        $this->assertNotNull($type);
        $this->assertFalse($type->is_system);
        $this->assertTrue($type->is_insurable);
        $this->assertNotSame('', $type->code);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $type->code);
    }

    public function test_system_type_keeps_code_and_kind_on_update(): void
    {
        $type = $this->systemHousing();

        $this->controller()->update(Request::create('/x', 'POST', [
            'name' => 'بدل سكن محدث',
            'code' => 'hacked',
            'kind' => SalaryItem::TYPE_DEDUCTION,
            'status' => SalaryComponentType::STATUS_ACTIVE,
            'is_default' => '1',
            'is_insurable' => '0',
            'sort_order' => 5,
        ]), $type->id);

        $type->refresh();
        $this->assertSame(SalaryComponentType::CODE_HOUSING, $type->code);
        $this->assertSame(SalaryItem::TYPE_ADDITION, (int) $type->kind);
        $this->assertSame('بدل سكن محدث', $type->name);
        $this->assertFalse($type->is_insurable);
        $this->assertTrue($type->is_default);
    }

    public function test_unique_code_is_latin_and_falls_back_when_slug_is_empty(): void
    {
        $fromArabic = SalaryComponentType::uniqueCode('بدل خاص');
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $fromArabic);

        $fallback = SalaryComponentType::uniqueCode('!!!');
        $this->assertStringStartsWith('comp_', $fallback);
    }

    private function systemHousing(): SalaryComponentType
    {
        $row = collect(SalaryComponentType::seededRows())
            ->firstWhere('code', SalaryComponentType::CODE_HOUSING);

        return SalaryComponentType::query()->firstOrCreate(
            ['code' => $row['code']],
            $row
        );
    }

    private function controller(): SalaryComponentTypeController
    {
        return new SalaryComponentTypeController;
    }

    private function itemsController(): SalaryItemsController
    {
        return new SalaryItemsController;
    }
}
