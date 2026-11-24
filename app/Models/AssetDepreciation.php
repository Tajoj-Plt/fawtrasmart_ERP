<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetDepreciation extends Model
{
    /**
     * فرعُ المستند: يُختَم عند الإنشاء (`StampsOwnerContext`) ويُصفّى عند
     * القراءة (`BelongsToBranch::scopeForBranch`).
     *
     * كان الجدول بلا عمود فرعٍ إطلاقاً، فتبديلُ الفرع في الشريط العلوي لا
     * يجد ما يُصفّي به. والصفوفُ التي سبقت العمود تبقى ظاهرةً في كل الفروع
     * (`orWhereNull`) فلا يختفي تاريخٌ عند التشغيل.
     */
    use BelongsToBranch, StampsOwnerContext;

    use HasDocumentCurrency, HasFactory;

    /**
     * الحقول القابلة للتعبئة
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'status',
        'name',
        'date_price',
        'date_service',
        'account_id',
        'place',
        'region_age',
        'quantity',
        'description',
        'purchase_value',
        'currency',
        'cash_account',
        'tax1',
        'tax2',
        'employee_id',
        'client_id',
        'attachments',
    ];

    /**
     * الحقول التي تعامل كتواريخ
     *
     * @var array
     */
    protected $dates = [
        'date_price',      // تاريخ الشراء
        'date_service',    // تاريخ بدء الخدمة
        'created_at',      // تاريخ الإنشاء
        'updated_at',      // تاريخ التحديث
    ];

    /**
     * علاقة مع جدول الحسابات (حساب الأصل)
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * علاقة مع حساب النقدية
     */
    public function cashAccountRelation()
    {
        return $this->belongsTo(Account::class, 'cash_account');
    }

    /**
     * علاقة مع جدول الموظفين
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * علاقة مع جدول العملاء
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * علاقة مع جدول الإهلاك
     */
    public function depreciation()
    {
        return $this->hasOne(AssetDep::class, 'asset_id');
    }

    /**
     * دالة للحصول على النص العربي لطريقة الإهلاك
     */
    public function getDepreciationMethodTextAttribute()
    {
        $methods = [
            1 => 'طريقة القسط الثابت',
            2 => 'طريقة القسط المتناقص',
            3 => 'وحدات الانتاج',
            4 => 'بدون الاهلاك',
        ];

        return $methods[$this->depreciation_method] ?? '';
    }

    /**
     * دالة للحصول على النص العربي للضريبة
     */
    public function getTaxTextAttribute($value)
    {
        $types = [
            1 => 'القيمة المضافة',
            2 => 'صفرية',
            3 => 'قيمة مضافة',
        ];

        return $types[$value] ?? '';
    }

    /**
     * دالة للحصول على نوع العملة
     */
    public function getCurrencyTextAttribute()
    {
        $currencies = [
            1 => 'ريال',
            2 => 'دولار',
        ];

        return $currencies[$this->currency] ?? '';
    }
}
