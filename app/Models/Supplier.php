<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'number_suply', 'trade_name', 'first_name', 'last_name', 'phone', 'mobile', 'email',
        'street1', 'street2', 'city', 'region', 'postal_code', 'country', 'tax_number',
        'commercial_registration', 'opening_balance', 'opening_balance_date', 'currency',
        'notes', 'attachments', 'created_by', 'updated_by', 'branch_id', 'account_id',
        // الموظّفُ المسؤول عن المورّد — العمودُ كان في الجدول منذ إنشائه
        // بلا استعمال: لا في `fillable` ولا علاقةً ولا حقلاً في الشاشة،
        // فلم يُضبَط له قيمةٌ قط (طلب المستخدم 2026-09-21)
        'employee_id',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * الاسم المعروض للمورّد.
     *
     * جدول `suppliers` لا يحمل عمود `name` — الاسم فيه `trade_name` وقد
     * يكون فارغاً فيُبنى من الاسم الأول والأخير. و32 موضعاً في الشيفرة
     * تكتب `$supplier->name` فتحصل على null، فتظهر «غير محدَّد» في الجداول
     * و«العميل» فارغاً في المطبوعات.
     */
    public function getNameAttribute(): string
    {
        if (filled($this->trade_name)) {
            return $this->trade_name;
        }

        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $full !== '' ? $full : (string) $this->number_suply;
    }

    // العلاقة مع فواتير الشراء
    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** الموظّف المسؤول عن المورّد — نظير `Client::employee()` في العملاء */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // العلاقة مع المدفوعات
    public function payments()
    {
        return $this->hasMany(PaymentsProcess::class, 'supplier_id');
    }

    public function account()
    {
        // العلاقة أصبحت hasOne لأن account_id موجود في جدول الحسابات
        return $this->hasOne(Account::class, 'supplier_id'); // تأكد من اسم العمود الصحيح
    }

    // حساب إجمالي المشتريات (الفواتير فقط)
    public function getTotalPurchasesAttribute()
    {
        return $this->purchaseInvoices()
            ->where('type', 'invoice')
            ->sum('grand_total');
    }

    // حساب إجمالي المرتجعات
    public function getTotalReturnsAttribute()
    {
        return $this->purchaseInvoices()
            ->where('type', 'return')
            ->sum('grand_total');
    }

    // حساب صافي المشتريات
    public function getNetPurchasesAttribute()
    {
        return $this->total_purchases - $this->total_returns;
    }

    // حساب إجمالي المدفوعات
    public function getTotalPaymentsAttribute()
    {
        return $this->payments->sum('amount');
    }

    // حساب التسويات (إذا كانت موجودة)
    public function getAdjustmentsAttribute()
    {
        // إذا كان لديك نموذج للتسويات، يمكنك استخدامه هنا
        return 0; // افتراضيًا، يمكنك تعديل هذا الجزء
    }

    // حساب الرصيد الحالي
    // الصيغة: الرصيد الافتتاحي + المشتريات - المرتجعات - المدفوعات
    //
    // العمود `opening_balance` يحمل **المقدار بلا إشارة**، واتجاهه محفوظٌ في
    // `accounts.opening_balance_type` لا هنا (انظر
    // SupplierManagementController::updateSupplierOpeningBalance). فرصيدٌ
    // افتتاحيّ اتجاهه `debit` — أي «لنا عليه»، كدفعةٍ مقدَّمة سُلِّمت له —
    // يُطرح ولا يُجمع، وإلا قلبَ الصيغةُ اتجاهَ المديونية وأظهرت ضِعفَ المبلغ.
    public function getCurrentBalanceAttribute()
    {
        $opening = (float) $this->opening_balance;

        if (optional($this->account)->opening_balance_type === \App\Models\Account::DEBIT) {
            $opening = -$opening;
        }

        return $opening + $this->total_purchases - $this->total_returns - $this->total_payments;
    }

    // للتوافقية مع الكود القديم
    public function getBalanceAttribute()
    {
        return $this->current_balance;
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullAddressAttribute()
    {
        $address = [];
        if ($this->street1) {
            $address[] = $this->street1;
        }
        if ($this->street2) {
            $address[] = $this->street2;
        }
        if ($this->city) {
            $address[] = $this->city;
        }
        if ($this->region) {
            $address[] = $this->region;
        }
        if ($this->postal_code) {
            $address[] = $this->postal_code;
        }
        if ($this->country) {
            $address[] = $this->country;
        }

        return implode(', ', $address);
    }
}
