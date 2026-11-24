<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'type_id',
        'description',
        'created_by',
        'type_log',
        'old_value',
        'icon',
        'ip_address',
        'browser',
        'os',
        'device',
        'route',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * أنواعُ السجلّ التي لا تُحذف: أثرُ التدقيق على حركةٍ مالية.
     *
     * شاشةُ سجل النشاطات تُتيح الحذفَ والحذفَ الجملي و«تنظيف ما مضى عليه
     * ثلاثون يوماً». والأخيرةُ تمسح كلَّ ما قبل شهر بلا استثناء — فيذهب
     * معها سردُ من صرف راتباً ومتى ومن أيِّ خزينة، وهو في كثيرٍ من الحالات
     * الأثرُ الوحيد: الصرفُ التلقائي لا يكتب في مكانٍ آخر.
     *
     * والجزءُ الصلب — `payroll_runs.disbursed_by` و`disbursed_at` — يبقى،
     * لكنّه لا يحمل السردَ ولا يشمل ما لا صفَّ له كإصدار السلف.
     *
     * فهذه الأنواعُ تُستثنى من الحذف بأنواعه الثلاثة. وما سواها يبقى
     * قابلاً للتنظيف كما كان — فالغرضُ حمايةُ الأثر المالي لا تعطيلُ الشاشة.
     *
     * @return array<int, string>
     */
    public static function protectedTypes(): array
    {
        return [
            'payroll_run_log',
            'payroll_batch_log',
            'payroll_log',
            'salary_slip_log',
            'salary_advance_request_log',
            'finance_log',

            /*
                تصحيحُ الحضور أثرٌ ماليٌّ كذلك: اعتمادُه يكتب على
                `attendance_days` — الحالةَ والأوقاتَ ودقائقَ التأخير —
                وعليها تُبنى خصميةُ الغياب والتأخير. فمن غيّر يومَ حضورٍ
                ولماذا ومن اعتمده لا يُكتب إلا هنا؛ والصفُّ المعدَّل نفسُه
                يحمل قيمتَه الجديدة ولا يحمل تاريخَها.

                وكانت «تنظيف ما مضى عليه ثلاثون يوماً» تمسحه مع غيره —
                فيبقى الخصمُ في الراتب ويذهب مسوّغُه.
            */
            'attendance_correction_request_log',

            /*
                العقود والدفعات العقارية أثرٌ ماليّ: من اعتمد عقداً أو أنهاه
                أو ألغى دفعةً مرحّلة ولماذا لا يُكتب إلا هنا — فلا يمسحه
                «تنظيف ما مضى عليه ثلاثون يوماً».
            */
            're_contract_log',
            're_payment_log',

            /*
                وسجلُّ المالك كذلك: تحويلُ مستحقاته وإلغاءُ التحويل وتعديلُ
                اتفاقية العمولة تُكتب تحته. وتسويةُ تأمين المستأجر (ردٌّ أو خصم)
                قيدٌ ماليّ يُسأل عنه بعد سنوات.
            */
            're_owner_log',
            're_deposit_log',
        ];
    }

    /** السجلّاتُ المحميّة — لا تُحذف. */
    public function scopeProtectedRecords($query)
    {
        return $query->whereIn('type', self::protectedTypes());
    }

    /** ما يجوز حذفه. */
    public function scopeDeletable($query)
    {
        return $query->whereNotIn('type', self::protectedTypes());
    }

    /** هل هذا السجلّ أثرٌ ماليّ محميّ؟ */
    public function isProtected(): bool
    {
        return in_array($this->type, self::protectedTypes(), true);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function Product()
    {
        return $this->belongsTo(Product::class, 'type_id');
    }

    public function payments()
    {
        return $this->belongsTo(PaymentsProcess::class, 'type_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'type_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'type_id');
    }

    public function quote()
    {
        return $this->belongsTo(quote::class, 'type_id');
    }

    public function OrdersPurchases()
    {
        return $this->belongsTo(PurchaseOrder::class, 'type_id');
    }

    public function purchase_quotation()
    {
        return $this->belongsTo(PurchaseQuotation::class, 'type_id');
    }

    public function quotation_view()
    {
        return $this->belongsTo(PurchaseQuotationView::class, 'type_id');
    }

    public function purchase_request()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'type_id');
    }

    public function sales()
    {
        return $this->belongsTo(Invoice::class, 'type_id');
    }

    public function CreditNotification()
    {
        return $this->belongsTo(CreditNotification::class, 'type_id');
    }

    public function purchase_invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'type_id');
    }

    public function purchase_return_log()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'type_id');
    }

    public function attendance_days_log()
    {
        return $this->belongsTo(AttendanceDays::class, 'type_id');
    }

    public function attendanceSheet()
    {
        return $this->belongsTo(AttendanceSheets::class, 'type_id');
    }

    public function holiday_list()
    {
        return $this->belongsTo(HolidayList::class, 'type_id');
    }

    public function attendance_rules_log()
    {
        return $this->belongsTo(AttendanceRule::class, 'type_id');
    }

    public function warehouse_log()
    {
        return $this->belongsTo(WarehousePermits::class, 'type_id');
    }

    public function pathMan()
    {
        return $this->belongsTo(ProductionPath::class, 'type_id');
    }

    public function work_station()
    {
        return $this->belongsTo(WorkStations::class, 'type_id');
    }

    public function production_material()
    {
        return $this->belongsTo(ProductionMaterials::class, 'type_id');
    }

    public function manufacturing_order()
    {
        return $this->belongsTo(ManufacturOrders::class, 'type_id');
    }

    public function indirect_cost()
    {
        return $this->belongsTo(IndirectCost::class, 'type_id');
    }

    public function LeavePermissions()
    {
        return $this->belongsTo(LeavePermissions::class, 'type_id');
    }

    public function salary_log()
    {
        return $this->belongsTo(SalaryItem::class, 'type_id');
    }
}
