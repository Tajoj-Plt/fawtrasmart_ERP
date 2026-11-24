<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosGeneralSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        // `null` = الإعدادُ العام الذي ترثه الفروع كلُّها. وصفٌّ بفرعٍ
        // محدَّد يتقدّم عليه لذلك الفرع وحده
        'branch_id',
        'max_discount_percent',

        // ضوابطُ التشغيل — كانت مفاتيحَ فرعيّةً بلا عمودٍ عامّ
        'require_shift_open',
        'enforce_shift_window',
        'allow_cash_refund',
        'max_refund_amount',
        'default_customer_id',

        /*
            مستودعُ نقطة البيع.

            كان المستودعُ يُشتقّ من عهدة الموظّف (`default_warehouses`) —
            «مخزون المندوب». وهو منطقُ مندوبٍ يحمل بضاعتَه، لا منطقُ كاشيرٍ
            يبيع من مخزن الفرع. فصار إعداداً يُختار، بالتوريث نفسِه:
            صفُّ الفرع ← الصفُّ العام. انظر الهجرة `2026_09_07_000001`.
        */
        'store_house_id',
        'invoice_template',

        /*
            قالبُ طباعة المرتجع — عمودٌ أُضيف بعد `invoice_template`.

            وغيابُه عن `$fillable` كان يُسقط القيمةَ **بصمت**: النموذجُ
            يُرسلها، والتحقّقُ يقبلها، و`update()` تتجاهلها. فيحفظ المستخدم
            ويرى الاختيارَ يعود إلى ما كان بلا رسالةِ خطأ.
        */
        'return_template',
        'active_payment_method_ids',
        'default_payment_method_id',
        'allowed_categories_type',
        'allowed_categories_ids',
        'enable_departments',
        'show_product_images',
        'show_print_window_after_confirm',
        'enable_auto_settlement',
        'enable_sales_settlement',
        'default_bank_account_id',

        /*
            حساباتُ نقاط البيع — تُحلّ قبل «الحسابات المحاسبية» المشتركة.

            ولا حسابَ مخزونٍ هنا: حسابُ المخزون يتبع **المستودعَ** المختار
            أعلاه (`accounts.storehouse_id`)، فاختيارُ المستودع يختار حسابَه
            معه ولا يُضبط مرّتين. والخزينةُ تبقى عهدةَ الموظّف من
            `treasury_employees` — انظر الهجرة `2026_08_29_300000`.
        */
        'sales_account_id',
        'sales_returns_account_id',
        'cost_of_sales_account_id',
        'discount_account_id',
        'vat_account_id',
        'cash_over_account_id',
        'cash_short_account_id',
    ];

    protected $casts = [
        // بولياناتٌ صريحة: القيمةُ تعود نصّاً من MySQL، و"0"
        // نصّاً صادقةٌ في PHP — فيُقرأ المطفأُ مشتعلاً
        'require_shift_open' => 'boolean',
        'enforce_shift_window' => 'boolean',
        'allow_cash_refund' => 'boolean',
        'max_refund_amount' => 'decimal:2',
        'allowed_categories_ids' => 'array',
        'active_payment_method_ids' => 'array',
        'enable_departments' => 'boolean',
        'apply_custom_fields_validation' => 'boolean',
        'show_product_images' => 'boolean',
        'show_print_window_after_confirm' => 'boolean',
        'enable_auto_settlement' => 'boolean',
        'enable_sales_settlement' => 'boolean',
    ];

    // العلاقات
    public function defaultCustomer()
    {
        return $this->belongsTo(Client::class, 'default_customer_id');
    }

    public function activePaymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'active_payment_method_ids');
    }

    public function defaultPaymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function defaultBankAccount()
    {
        return $this->belongsTo(Account::class, 'default_bank_account_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** مستودعُ نقطة البيع — يُقرأ منه المعروض ويُخصم منه المباع. */
    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    /**
     * إعداداتُ فرعٍ بعينه — وإلا الإعدادُ العام.
     *
     * ترتيبُ التفضيل: صفُّ الفرع ← الصفُّ العام (`branch_id = null`) ← أولُ
     * صفٍّ موجود ← صفٌّ فارغ.
     *
     * والأخيران احتياطٌ للانتقال: قواعدُ قائمة فيها صفٌّ واحد بلا
     * `branch_id` مضبوط، فلا يجوز أن تعود الشاشةُ فارغةً بعد الترحيل.
     */
    public static function forBranch(?int $branchId = null)
    {
        if ($branchId) {
            $scoped = self::where('branch_id', $branchId)->first();

            if ($scoped) {
                return $scoped;
            }
        }

        return self::whereNull('branch_id')->first()
            ?: self::first()
            ?: new self;
    }

    /**
     * الإعداداتُ المطبَّقة على المستخدم الحالي.
     *
     * كانت الشيفرة تكتب `PosGeneralSetting::first()` في تسعة مواضع
     * و`find(1)` في موضعٍ عاشر — والأخيرُ يفترض أن معرّف الصفّ هو ١ حرفياً،
     * وهو افتراضٌ ينكسر بأول حذفٍ وإعادةِ إنشاء.
     */
    public static function getSettings()
    {
        return self::forBranch(current_branch_id());
    }

    /**
     * طرقُ الدفع المفعَّلة.
     *
     * `status` و`is_active` عمودان موجودان معاً في `payment_methods`
     * (الأولُ نصٌّ افتراضُه `'active'`، والثاني بولياني) — فالاكتفاءُ بأحدهما
     * يُقصي صفوفاً صحيحة بحسب أيّهما مُلئ. وكان في الشيفرة موضعٌ يفحص
     * `->where('status', 0)` — نصٌّ يُقارَن بعدد، فلا يوافق شيئاً أبداً.
     */
    public function getActivePaymentMethods()
    {
        if (! $this->active_payment_method_ids) {
            return collect();
        }

        return PaymentMethod::whereIn('id', $this->active_payment_method_ids)
            ->where(function ($query) {
                $query->where('status', 'active')->orWhere('is_active', 1);
            })
            ->get();
    }

    public function getSelectedCategories()
    {
        if ($this->allowed_categories_type === 'all') {
            return Category::all();
        } elseif ($this->allowed_categories_type === 'only' && $this->allowed_categories_ids) {
            return Category::whereIn('id', $this->allowed_categories_ids)->get();
        } elseif ($this->allowed_categories_type === 'except' && $this->allowed_categories_ids) {
            return Category::whereNotIn('id', $this->allowed_categories_ids)->get();
        }

        return collect();
    }
}
