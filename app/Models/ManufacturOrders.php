<?php

namespace App\Models;

use App\Enums\ManufacturOrderStatus;
use App\Models\Concerns\HasDocumentCurrency;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class ManufacturOrders extends Model
{
    use BelongsToBranch, HasDocumentCurrency;

    protected $table = 'manufactur_orders';

    protected $fillable = [
        'name', 'code', 'from_date', 'to_date', 'account_id',
        'employee_id', 'client_id', 'product_id', 'quantity',
        'production_material_id', 'production_path_id', 'last_total_cost',
        'status', 'storehouse_id', 'materials_received_at', 'completed_at',
        'finished_at', 'main_warehouse_id', 'waste_warehouse_id',
        'actual_quantity', 'finish_notes', 'created_by', 'updated_by',
        'currency',
        // الفرع — `null` تعني «كل الفروع»، انظر BelongsToBranch::scopeForBranch
        'branch_id',
        // إذن إضافة المنتج التام — كان يُكتب في completeOrder ويُهمَل
        // صامتاً بحماية الإسناد الجماعي، فيعجز undoCompletion عن إيجاده
        'warehouse_permit_id',
    ];

    protected $dates = ['finished_at', 'materials_received_at', 'completed_at'];

    /**
     * الحالة كائنُ Enum لا نصّاً.
     *
     * كانت تُكتب مرة ثابتاً ومرة نصّاً خاماً، وتُقرأ في اللوحة والتقارير
     * عدداً `(int) $order->status` — و`(int) 'pending'` صفر، فكل أمر
     * يُعرض «مسوّدة». وآلة الانتقالات صارت في الـEnum بدل شروط متناثرة.
     */
    protected $casts = [
        'status' => ManufacturOrderStatus::class,

        /*
            الكميات عشرية.

            كان `quantity` من نوع `int(11)`، فأمرٌ بـ٢٫٥ كجم يُحفظ ٢
            صامتاً. وُسِّع إلى `DECIMAL(18,4)` في هجرة 2026_09_02_000001.
            والتحويل هنا لازم: بلا `$casts` يعيد Laravel العمودَ العشري
            **نصّاً** ('2.5000')، فتُقارَن الكميات كسلاسل وتُجمَع بضمٍّ
            ضمني — ويمرّ ذلك صامتاً حتى تظهر نتيجةٌ خاطئة في تقرير.
        */
        'quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
    ];

    public function manufacturOrdersItem()
    {
        return $this->hasMany(ManufacturOrdersItem::class, 'manufactur_order_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productionMaterial()
    {
        return $this->belongsTo(ProductionMaterials::class);
    }

    public function productionPath()
    {
        return $this->belongsTo(ProductionPath::class);
    }

    public function manufacturOrders()
    {
        return $this->hasMany(ManufacturOrdersItem::class);
    }

    public function mainWarehouse()
    {
        return $this->belongsTo(StoreHouse::class, 'main_warehouse_id');
    }

    public function wasteWarehouse()
    {
        return $this->belongsTo(StoreHouse::class, 'waste_warehouse_id');
    }

    public function storehouse()
    {
        return $this->belongsTo(StoreHouse::class, 'storehouse_id');
    }

    /*
     * ثوابت الحالة ودوالها المساعدة انتقلت إلى App\Enums\ManufacturOrderStatus.
     *
     *   ManufacturOrders::STATUS_PENDING   →  ManufacturOrderStatus::Pending
     *   $order->isCompleted()              →  $order->status === ManufacturOrderStatus::Completed
     *   $order->canBeCompleted()           →  $order->status->canComplete()
     *   $order->canReceiveMaterials()      →  $order->status->canReceiveMaterials()
     *   $order->canBeCancelled()           →  $order->status->canCancel()
     *
     * وأضاف الـEnum ما لم يكن موجوداً: جدول انتقالات صريح يرفض ما ليس
     * فيه، وحالتَي `in_progress` و`closed` اللتين كان المتحكّم يكتبهما
     * وهما خارج ENUM العمود.
     */
}
