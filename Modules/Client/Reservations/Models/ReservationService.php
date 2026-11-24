<?php

namespace Modules\Client\Reservations\Models;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Product;
use App\Models\TaxSitting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * خدمةٌ قابلة للحجز — البُعد الزمني لمنتجٍ قائم.
 *
 * لا تكرّر كتالوج المنتجات: `product_id` يشير إلى `products` بنوع
 * `ProductType::SERVICES`، ومنه يُقرأ السعرُ والضريبةُ والحسابُ المحاسبي.
 * وما تحمله هذه الخدمةُ زيادةً هو ما لا يعرفه المنتج: المدّة، ووقتا
 * التحضير والتنظيف، والموظفون المؤهَّلون، والحدُّ اليومي.
 *
 * **`price` فارغاً يعني «اقرأه من المنتج»** لا «مجّاني». والصفرُ الصريح
 * وحده هو المجّاني — والفرقُ بينهما هو ما تحفظه `effectivePrice()`.
 */
class ReservationService extends Model
{
    use SoftDeletes;

    protected $table = 'reservation_services';

    protected $fillable = [
        'name', 'code', 'description', 'product_id', 'branch_id',
        'duration', 'buffer_before', 'buffer_after', 'max_daily',
        'price', 'tax_id', 'is_active', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'duration' => 'integer',
        'buffer_before' => 'integer',
        'buffer_after' => 'integer',
        'max_daily' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ════════════════════ العلاقات ════════════════════

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(TaxSitting::class, 'tax_id');
    }

    /**
     * الموظفون المؤهَّلون.
     *
     * **جدولٌ فارغٌ يعني «كلُّ الموظفين»** لا «لا أحد» — انظر
     * `ReservationAvailabilityService::eligibleEmployees()`.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'reservation_service_employee',
            'reservation_service_id',
            'employee_id'
        )->withTimestamps();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'service_id');
    }

    // ════════════════════ النطاقات ════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * خدماتُ فرعٍ بعينه — **ومعها الخدماتُ العامّة**.
     *
     * `branch_id` فارغاً يعني «متاحة في كل الفروع»، فحصرُها بالمطابقة
     * التامّة يُخفي الخدماتِ العامّة عن كل فرع فلا تظهر في أي شاشة.
     */
    public function scopeForBranchOrGlobal(Builder $query, $branchId): Builder
    {
        $branchId = (int) $branchId;

        if ($branchId <= 0) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($branchId) {
            $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
        });
    }

    // ════════════════════ الاسم ════════════════════

    /**
     * الاسمُ المعروض — **المنتجُ هو المصدر، والحقلُ المحلّي تجاوز**.
     *
     * الجدولُ يصف قابليّةَ الحجز (مدّة، بُفَر، حدّ يومي، موظفون مؤهَّلون)
     * لا كتالوجاً ثانياً للأصناف. فحين يُربَط بمنتج يأخذ اسمَه، وإلّا
     * انفصل الاسمان بعد أوّل تعديلٍ في كتالوج المخزون وقرأ المستخدمُ
     * اسمين لشيءٍ واحد.
     *
     * والتجاوزُ يبقى ممكناً: خدمةٌ تُحجَز باسمٍ يخالف اسمَ المنتج
     * («كشف أوّل» على منتج «كشف») تكتبه في `name` فيُقدَّم.
     */
    public function displayName(): string
    {
        // المنتجُ هو المصدر حين يُربط — لا تجاوزَ محلّياً يُنشئ اسمين لشيءٍ واحد
        return (string) ($this->product?->name ?: ($this->name ?: __('reservations.ledger.service_line')));
    }

    /** ليُقرأ في القوالب كخاصّية: `$service->display_name` */
    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
    // ════════════════════ المال والزمن ════════════════════

    /**
     * السعرُ الفعلي: **سعرُ بيع المنتج** — مصدرُ السعر في المبيعات والفواتير.
     *
     * عمودا `price` و`tax_id` في الملفّ بقيا لصفوفٍ قديمة بلا منتج وحدها؛ أمّا
     * الخدمةُ المربوطة بمنتج فلا تحمل سعراً ثانياً يفترق عن سعرها في الفاتورة.
     */
    public function effectivePrice(): float
    {
        if ($this->product) {
            return (float) ($this->product->sale_price ?? 0);
        }

        return (float) ($this->price ?? 0);
    }

    /** الضريبةُ الفعلية: ضريبةُ المنتج (`tax1`) — كما تقرؤها فاتورةُ المبيعات. */
    public function effectiveTaxId(): ?int
    {
        if ($this->product) {
            return $this->product->tax1 ? (int) $this->product->tax1 : null;
        }

        return $this->tax_id ? (int) $this->tax_id : null;
    }

    // ════════════════════ ملفُّ الحجز للمنتج ════════════════════

    public function scopeForProduct(Builder $query, $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * ملفُّ حجز منتجٍ خدمي — المحفوظُ إن وُجد، وإلّا ملفٌّ افتراضيّ **غيرُ محفوظ**.
     *
     * الافتراضيُّ يحمل المدّةَ من إعدادات الحجوزات وبلا تحضيرٍ ولا تنظيف ولا
     * قصرٍ على موظفين (قائمةُ المؤهَّلين الفارغة تعني «الجميع»). ولا يُكتب في
     * القاعدة: حجزُ خدمةٍ لا يُنشئ صفّاً في الإعدادات من ورائك.
     */
    public static function profileFor(Product $product): self
    {
        $profile = static::query()->forProduct($product->id)->first()
            ?? new static([
                'product_id' => $product->id,
                'duration' => (int) ReservationSetting::current()->default_duration,
                'buffer_before' => 0,
                'buffer_after' => 0,
                'is_active' => true,
            ]);

        $profile->setRelation('product', $product);

        return $profile;
    }

    /**
     * الوقتُ المحجوزُ فعلاً على تقويم الموظف — الخدمةُ وتحضيرُها وتنظيفُها.
     *
     * والفرقُ عن `duration` مقصود: العميلُ يدفع مقابل الخدمة وحدها، لكنّ
     * الموظّف مشغولٌ بالثلاثة. فخلطُهما إمّا يُفوتِر التنظيفَ على العميل
     * أو يسمح بحجزٍ يبدأ قبل أن يفرغ الموظف من سابقه.
     */
    public function blockedMinutes(): int
    {
        return (int) $this->duration + (int) $this->buffer_before + (int) $this->buffer_after;
    }
}
