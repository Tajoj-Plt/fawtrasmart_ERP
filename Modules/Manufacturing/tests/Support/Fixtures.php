<?php

namespace Modules\Manufacturing\Tests\Support;

use App\Enums\ProductionPlanStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ManufacturOrders;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductionMaterials;
use App\Models\ProductionMaterialsItem;
use App\Models\ProductionPath;
use App\Models\ProductionPlan;
use App\Models\ProductionPlanItem;
use App\Models\ProductionStage;
use App\Models\StoreHouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/*
    بانيةُ بياناتٍ لاختبارات التصنيع.

    اخترنا بانيةً صريحةً بدل Factories لسببين:

      ١) الجداول هنا مليئةٌ بأعمدة NOT NULL بلا قيمةٍ افتراضية
         (`manufactur_orders` وحده يطلب اثني عشر عموداً)، ومعظمها
         مفاتيحُ لجداولَ أخرى. مصنعٌ لكل جدولٍ يعني سلسلةً من
         المصانع المتشابكة يصعب تتبّعها حين تفشل.

      ٢) Factories محلُّها `database/factories` في الجذر — خارج
         المديول. البانية تبقي كل شيء داخله.

    كلُّ ما يُنشأ هنا يمرّ عبر `Model::unguarded()` لأن معظم النماذج
    تحمي `id` و`created_by`، ونحن نحتاج قيماً محدَّدةً لا مُخمَّنة.
*/
class Fixtures
{
    /*
        عدّادٌ ساكن — لا لكل نسخة.

        `code` في قوائم المواد وأوامر التصنيع فريدٌ على مستوى الجدول،
        وكل اختبارٍ يحصل على بانيةٍ جديدة. فلو بدأ العدّاد من الصفر
        في كل اختبار لتكرّرت الأكواد بين الاختبارات — وهو ما يظهر
        حين يُعاد استخدام القاعدة بدل هجرتها من جديد.
    */
    private static int $sequence = 0;

    /** مستخدمٌ مسجَّلُ الدخول — كثيرٌ من المسارات تقرأ auth()->id(). */
    public function user(array $attributes = []): User
    {
        return $this->make(User::class, array_merge([
            'name' => 'مستخدم اختبار',
            'email' => $this->unique('user').'@test.local',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    public function category(array $attributes = []): Category
    {
        return $this->make(Category::class, array_merge([
            'name' => 'تصنيف اختبار '.$this->next(),
        ], $attributes));
    }

    public function warehouse(array $attributes = []): StoreHouse
    {
        return $this->make(StoreHouse::class, array_merge([
            'name' => 'مستودع اختبار '.$this->next(),
        ], $attributes));
    }

    public function client(array $attributes = []): Client
    {
        return $this->make(Client::class, array_merge([
            'trade_name' => 'عميل اختبار '.$this->next(),
        ], $attributes));
    }

    public function employee(array $attributes = []): Employee
    {
        /*
            رغم اسمه، `employees.Job_role_id` مقيَّدٌ بمفتاحٍ أجنبي إلى
            جدول `roles` (أدوار Spatie) لا إلى `job_roles`. تسميةٌ
            مضلِّلة، لكنّ القيد هو الحَكَم.
        */
        $roleId = DB::table('roles')->insertGetId([
            'name' => $this->unique('دور اختبار'),
            'guard_name' => 'web',
        ]);

        return $this->make(Employee::class, array_merge([
            'first_name' => 'موظّف اختبار',
            'email' => $this->unique('emp').'@test.local',
            'Job_role_id' => $roleId,
            'created_by' => 1,
        ], $attributes));
    }

    /*
        منتج. `category_id` و`created_by` مطلوبان في المخطّط،
        فيُنشأ التصنيف تلقائياً ما لم يُمرَّر.
    */
    public function product(array $attributes = []): Product
    {
        $attributes['category_id'] ??= $this->category()->id;

        return $this->make(Product::class, array_merge([
            'name' => 'منتج اختبار '.$this->next(),
            'created_by' => 1,
        ], $attributes));
    }

    /*
        رصيدٌ افتتاحي لمنتجٍ في مستودع.

        نكتب في `product_details` مباشرةً لأنه سجلُّ الحركات الذي
        يُشتقّ منه الرصيد — لا عمودَ رصيدٍ مخزَّن في `products`.
    */
    public function stock(Product $product, StoreHouse $warehouse, float $quantity, float $unitPrice = 10.0): ProductDetails
    {
        return $this->make(ProductDetails::class, [
            'product_id' => $product->id,
            'store_house_id' => $warehouse->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'date' => now()->toDateString(),
            'time' => now()->toTimeString(),
            'type_of_operation' => 1,
            'type' => 1,
            'status' => 1,
        ]);
    }

    /** مسارُ إنتاج ومعه مرحلةٌ واحدة على الأقل. */
    public function path(array $attributes = []): ProductionPath
    {
        $path = $this->make(ProductionPath::class, array_merge([
            'name' => 'مسار اختبار '.$this->next(),
            'code' => $this->unique('PATH'),
        ], $attributes));

        $this->make(ProductionStage::class, [
            'production_paths_id' => $path->id,
            'stage_name' => 'مرحلة أولى',
        ]);

        return $path->refresh();
    }

    /*
        قائمةُ مواد (BOM).

        `quantity` هنا هي «كمية الإنتاج» — الأساسُ الذي تُنسب إليه
        كميات المواد. تركُها ١ يجعل الحساب مباشراً في الاختبارات
        التي لا تعنى بها.
    */
    public function bom(Product $product, array $attributes = []): ProductionMaterials
    {
        $attributes['production_path_id'] ??= $this->path()->id;
        $attributes['account_id'] ??= $this->expenseAccount()->id;

        return $this->make(ProductionMaterials::class, array_merge([
            'name' => 'قائمة مواد '.$this->next(),
            'code' => $this->unique('BOM'),
            'product_id' => $product->id,
            'quantity' => 1,
            'last_total_cost' => 0,
        ], $attributes));
    }

    /*
        سطرُ مادةٍ خام داخل قائمة مواد.

        جدولُ البنود واحدٌ لأربعة أنواعٍ من السطور (خام، مصروف،
        عملية، هالك)، وكلُّ نوعٍ له أعمدتُه ببادئته. سطرُ الخام
        يملأ أعمدة `raw_*` ويترك البقية فارغة — وهذا ما يميّزه.
    */
    public function bomRawLine(ProductionMaterials $bom, Product $raw, float $quantity, float $unitPrice = 10.0): ProductionMaterialsItem
    {
        return $this->make(ProductionMaterialsItem::class, [
            'production_material_id' => $bom->id,
            'raw_product_id' => $raw->id,
            'raw_production_stage_id' => $this->firstStageOf($bom->production_path_id),
            'raw_quantity' => $quantity,
            'raw_unit_price' => $unitPrice,
            'raw_total' => $quantity * $unitPrice,
        ]);
    }

    /** أمرُ تصنيع جاهزٌ بكل مفاتيحه المطلوبة. */
    public function order(ProductionMaterials $bom, array $attributes = []): ManufacturOrders
    {
        /*
            `??=` لا يصلح هنا: تمريرُ `null` صراحةً يعني «أمرٌ بلا عميل»
            — وهي حالةٌ صارت مشروعة بعد المرحلة ٣ — بينما `??=` يراها
            غياباً فيملؤها. فنميّز «لم يُمرَّر» عن «مُرِّر فارغاً».
        */
        $attributes['client_id'] = array_key_exists('client_id', $attributes)
            ? $attributes['client_id']
            : $this->client()->id;

        $attributes['account_id'] = array_key_exists('account_id', $attributes)
            ? $attributes['account_id']
            : $this->expenseAccount()->id;

        $attributes['employee_id'] ??= $this->employee()->id;

        return $this->make(ManufacturOrders::class, array_merge([
            'name' => 'أمر تصنيع '.$this->next(),
            'code' => $this->unique('MO'),
            'from_date' => now()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'product_id' => $bom->product_id,
            'quantity' => 1,
            'production_material_id' => $bom->id,
            'production_path_id' => $bom->production_path_id,
            'last_total_cost' => 0,
        ], $attributes));
    }

    /*
        خطّةُ إنتاج — رأسٌ بلا بنود.

        `status` تُترك «معتمدة» لأنّ أكثر ما يُختبَر في الخطط هو التوليدُ
        وما بعده، وهو ممنوعٌ من «مسوّدة». ومن أراد اختبارَ المنع مرّر
        `['status' => ProductionPlanStatus::Draft]`.
    */
    public function plan(array $attributes = []): ProductionPlan
    {
        $attributes['employee_id'] ??= $this->employee()->id;

        return $this->make(ProductionPlan::class, array_merge([
            'name' => 'خطة إنتاج '.$this->next(),
            'code' => $this->unique('PP'),
            'from_date' => now()->toDateString(),
            'to_date' => now()->addWeek()->toDateString(),
            'status' => ProductionPlanStatus::Approved,
        ], $attributes));
    }

    /** بندٌ في خطّة — منتجُ الوصفة وقائمتُها ومسارُها وكميّةٌ مخطّطة. */
    public function planItem(ProductionPlan $plan, ProductionMaterials $bom, float $quantity, array $attributes = []): ProductionPlanItem
    {
        return $this->make(ProductionPlanItem::class, array_merge([
            'production_plan_id' => $plan->id,
            'product_id' => $bom->product_id,
            'production_material_id' => $bom->id,
            'production_path_id' => $bom->production_path_id,
            'planned_quantity' => $quantity,
        ], $attributes));
    }

    /** أيُّ حسابِ مصروفاتٍ فرعي من الشجرة المزروعة. */
    public function expenseAccount(): Account
    {
        $account = Account::where('type', 'sub')->first();

        return $account ?? $this->make(Account::class, [
            'name' => 'حساب اختبار',
            'type' => 'sub',
            'balance_type' => 'debit',
        ]);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function make(string $model, array $attributes): Model
    {
        return Model::unguarded(fn () => $model::create($attributes));
    }

    /** أولُ مرحلةٍ في مسارٍ ما — كل بندٍ ينتمي إلى مرحلة. */
    private function firstStageOf(?int $pathId): ?int
    {
        if ($pathId === null) {
            return null;
        }

        $stage = ProductionStage::where('production_paths_id', $pathId)->orderBy('id')->first();

        return $stage?->id;
    }

    private function next(): int
    {
        return ++self::$sequence;
    }

    /*
        رمزٌ فريد للأعمدة ذات القيد الفريد.

        العدّاد وحده لا يكفي: تشغيلٌ على قاعدةٍ مُعاد استخدامها قد
        يصادف صفوفاً باقيةً من تشغيلٍ سابق. اللاحقةُ العشوائية تحسم.
    */
    private function unique(string $prefix): string
    {
        return $prefix.'-'.$this->next().'-'.substr(md5(uniqid('', true)), 0, 8);
    }
}
