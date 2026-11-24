<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNotification extends Model
{
    /*
        ═════════ الفرعُ والموظّفُ والفترة ═════════

        الإشعارُ الدائن يعكس فاتورةً، فيُختَم بما تُختَم به: فرعُه يُجمَّد
        معه فلا يتبع تبديلَ منشئه فرعَه، وموظّفُه يقول لمن يُنسب، وفترتُه
        تقول أين يقع في الدفتر.

        وكانت الفترةُ وحدها غائبة: تقريرُ فترةٍ يجمع مبيعاتِها من
        `invoices.accounting_period_id` ويجمع إشعاراتِها الدائنة بمقارنةِ
        تواريخ — فإن حُرّكت حدودُ الفترة اختلف الطرفان، وخرج صافي المبيعات
        لا يوافق ميزانَ المراجعة للفترة نفسِها.

        والتاريخُ الحاكم `credit_date` لا `created_at`: الإشعارُ يُنسَب إلى
        يوم إصداره لا إلى يوم إدخاله.
    */
    use \App\Models\Concerns\BelongsToAccountingPeriod;
    use \App\Models\Concerns\StampsOwnerContext;
    use \App\Traits\BelongsToBranch;
    use HasFactory;

    // تحديد اسم الجدول المرتبط بهذا المودل
    protected $table = 'credit_notifications';

    public function accountingDateColumn(): string
    {
        return 'credit_date';
    }

    // الحقول القابلة للتعبئة
    /**
     * الحقول القابلة للتعبئة.
     *
     * كانت القائمة تُسقط `shipping_tax` و`tax_rate` و`invoice_id`
     * و`currency` وغيرها، بينما `store()` يمرّرها إلى `create()` — فتُرمى
     * قبل أن تصل إلى الاستعلام بلا خطأ ولا تحذير. والأسوأ أنّ الضريبة
     * كانت تُمرَّر باسم `tax_total` لا `total_tax`، فلا يُكتب لها شيء
     * أبداً: ضريبةُ كل إشعار دائن في القاعدة `null`.
     */
    protected $fillable = [
        'client_id',
        'branch_id',
        'accounting_period_id',
        'invoice_id',
        'created_by',
        'employee_id',
        'store_house_id',
        'credit_date',
        'release_date',
        'credit_number',
        'currency',
        'subtotal',
        'due_value',
        'status',
        'total_discount',
        'total_tax',
        'tax_rate',
        'shipping_cost',
        'shipping_tax',
        'next_payment',
        'grand_total',
        'payment_method',
        'treasury_id',
        'notes',
        'attachments',
        'discount_type',
        'discount_amount',
        'tax_type',
    ];

    protected $casts = [
        'credit_date' => 'date',
        'release_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    /**
     * العلاقة مع جدول العملاء (Clients)
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * العلاقة مع جدول المستخدمين (Users) للمسؤول الذي أنشأ الاشعار
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة مع جدول الموظفين (Employees)
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * دالة للحصول على حالة الاشعار كنص
     * @return string
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            1 => 'Draft',
            2 => 'Pending',
            3 => 'Approved',
            4 => 'Converted to Invoice',
            5 => 'Cancelled',
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'credit_note_id');
    }

    /**
     * الفاتورة التي صدر عنها الإشعار.
     *
     * الإشعار الدائن يصدر عن فاتورة كما يصدر «الإشعار المدين» في
     * المشتريات عن فاتورة شراء. ولم يكن ثمّة عمود يربطهما، فلا يُعرف
     * أصلُ الإشعار إلّا من نصّ الملاحظات.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /** المستودع الذي رُدّت إليه البضاعة */
    public function storeHouse(): BelongsTo
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    /** خزينة ردّ المبلغ */
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'treasury_id');
    }

    /** طريقة الدفع المضبوطة في «طرق الدفع» */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method');
    }
}
