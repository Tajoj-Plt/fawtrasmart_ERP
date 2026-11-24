<?php

namespace App\Models;

use App\Enums\ManufacturOrderStatus;
use App\Enums\ProductionPlanStatus;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * خطّةُ إنتاج — رأسُ نيّةٍ تتحوّل بنودُها إلى أوامر تصنيع.
 *
 * الخطّةُ لا تمسّ مخزوناً ولا دفتراً بذاتها، ولا تحمل مبلغاً — فلا عملةَ
 * عليها ([\App\Models\Concerns\HasDocumentCurrency] لمستندٍ ذي مبالغ).
 * أثرُها كلُّه عبر الأوامر المولَّدة منها، وهي التي تُختم بالعملة — انظر
 * [\Modules\Manufacturing\Http\Controllers\Concerns\GeneratesOrdersFromPlan].
 *
 * @property ProductionPlanStatus $status
 */
class ProductionPlan extends Model
{
    use BelongsToBranch;

    protected $table = 'production_plans';

    protected $fillable = [
        'name', 'code', 'from_date', 'to_date', 'status',
        'employee_id', 'client_id', 'account_id', 'storehouse_id',
        'notes', 'created_by', 'updated_by',
        // الفرع — `null` تعني «كل الفروع»، انظر BelongsToBranch::scopeForBranch
        'branch_id',
    ];

    /**
     * الحالةُ كائنُ Enum لا نصّاً — نفسُ ما فُعل بأوامر التصنيع بعد أن
     * قُرئت حالتُها عدداً في اللوحة فعُرض كلُّ أمرٍ «مسوّدة».
     */
    protected $casts = [
        'status' => ProductionPlanStatus::class,
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    // ─────────────────────────────────────────────────────────────────
    // العلاقات
    // ─────────────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(ProductionPlanItem::class, 'production_plan_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function storeHouse(): BelongsTo
    {
        return $this->belongsTo(StoreHouse::class, 'storehouse_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─────────────────────────────────────────────────────────────────
    // أسئلة عن حال الخطّة
    // ─────────────────────────────────────────────────────────────────

    /**
     * هل وُلِّد عن الخطّة أمرُ تصنيعٍ واحد على الأقل؟
     *
     * هذا هو الحارسُ الحقيقيّ للحذف — لا الحالةُ وحدها. فالحذفُ يقطع
     * الأوامرَ عن سببها، فلا يبقى ما يفسّر لماذا أُنتج ما أُنتج.
     */
    public function hasGeneratedOrders(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->whereNotNull('manufactur_order_id')->isNotEmpty();
        }

        return $this->items()->whereNotNull('manufactur_order_id')->exists();
    }

    /**
     * البنودُ القابلة للتوليد — مادّةُ زرّ «توليد الأوامر».
     *
     * بندٌ لم يُولَّد أمرُه قطّ، أو أُلغي أمرُه: الأمرُ الملغى لن يُنتج
     * شيئاً، فبندُه عاد بلا أمرٍ حيّ ويُولَّد له أمرٌ جديد.
     */
    public function pendingItems(): HasMany
    {
        return $this->items()->where(function ($query) {
            $query->whereNull('manufactur_order_id')
                ->orWhereHas('manufacturOrder', function ($order) {
                    $order->where('status', ManufacturOrderStatus::Cancelled->value);
                });
        });
    }

    /**
     * أين وصلت أوامرُ الخطّة — العدُّ الذي تُشتقّ منه الحالةُ والمؤشّرات.
     *
     *  · `pending`   بنودٌ لم يُولَّد أمرُها قطّ (أو حُذف أمرُها).
     *  · `cancelled` بنودٌ أمرُها ملغى — قابلةٌ للتوليد من جديد.
     *  · `live`      بنودٌ لها أمرٌ قائم (غير ملغى).
     *  · `finished`  من القائمة: أمرُها «مكتمل» أو «مغلق».
     *
     * `$fresh` تُعيد القراءة من القاعدة: الأوامرُ تتحرّك من شاشتها، فما
     * حُمِّل في أوّل الطلب قد لا يصف ما في القاعدة الآن.
     *
     * @return array{total:int, pending:int, cancelled:int, live:int, finished:int}
     */
    public function orderProgress(bool $fresh = false): array
    {
        if ($fresh || ! $this->relationLoaded('items')) {
            $items = $this->items()->with('manufacturOrder')->get();
        } else {
            $items = $this->items;
            $items->loadMissing('manufacturOrder');
        }

        $progress = ['total' => $items->count(), 'pending' => 0, 'cancelled' => 0, 'live' => 0, 'finished' => 0];

        foreach ($items as $item) {
            $order = $item->manufacturOrder;

            if (! $order) {
                $progress['pending']++;
            } elseif ($order->status === ManufacturOrderStatus::Cancelled) {
                $progress['cancelled']++;
            } else {
                $progress['live']++;

                if (in_array($order->status, ManufacturOrderStatus::finished(), true)) {
                    $progress['finished']++;
                }
            }
        }

        return $progress;
    }

    /**
     * نسبةُ الإنجاز: كم بنداً له أمرٌ قائم من جملة البنود، وكم منها اكتمل.
     *
     * تُحسب من البنود المحمَّلة إن كانت — كي لا تُطلق القائمةُ استعلاماً
     * لكل صفّ (`with('items.manufacturOrder')` كافٍ).
     *
     * @return array{generated:int, total:int, finished:int, generable:int}
     */
    public function generatedRatio(): array
    {
        $progress = $this->orderProgress();

        return [
            'generated' => $progress['live'],
            'total' => $progress['total'],
            'finished' => $progress['finished'],
            // ما يُولَّد الآن: ما لم يُولَّد قطّ وما أُلغي أمرُه
            'generable' => $progress['pending'] + $progress['cancelled'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // الحالةُ مشتقّةٌ من الأوامر
    // ─────────────────────────────────────────────────────────────────

    /**
     * تُعيد ضبطَ حالة الخطّة على ما تقوله أوامرُها فعلاً، وتُرجع الحالة.
     *
     * ── القاعدة ──────────────────────────────────────────────────────
     *
     *  · «مسوّدة» و«ملغاة» لا تُمَسّان: الاعتمادُ والإلغاءُ قرارُ إنسان.
     *  · لا أمرَ قائماً (لم يُولَّد شيء، أو حُذفت الأوامر، أو أُلغيت
     *    كلُّها)                                        ⟵ «معتمدة».
     *  · كلُّ أمرٍ قائم «مكتمل» أو «مغلق»، ولا بندَ لم يُولَّد أمرُه قطّ
     *                                                   ⟵ «مكتملة».
     *  · غيرُ ذلك — وُلِّد بعضُها أو كلُّها والعملُ جارٍ   ⟵ «قيد التنفيذ».
     *
     * فتوليدُ الأوامر كلِّها **لا يُكمل** الخطّة: الأوامرُ ما زالت
     * «جديدة»، والخطّةُ تكتمل حين يُنتَج ما خُطِّط لا حين يُكتب أمرُه.
     *
     * والأمرُ الملغى **يُتجاهَل** ما دام للخطّة أمرٌ قائم غيرُه: لا
     * يمنع اكتمالَها ولا يُحسب إنجازاً، وبندُه يعود قابلاً للتوليد ما
     * دامت الخطّة «قيد التنفيذ» أو «معتمدة».
     *
     * ── متى تُستدعى ──────────────────────────────────────────────────
     *
     * بعد التوليد، وعند عرض الخطّة وسردها (احتياطاً)، ومن دورة حياة
     * الأمر عند إتمامه والتراجع عنه وإغلاقه وإلغائه وحذفه — عبر
     * [self::syncStatusForOrder].
     */
    public function syncStatusFromOrders(): ProductionPlanStatus
    {
        if (in_array($this->status, [ProductionPlanStatus::Cancelled, ProductionPlanStatus::Draft], true)) {
            return $this->status;
        }

        $progress = $this->orderProgress(fresh: true);

        $target = match (true) {
            $progress['live'] === 0 => ProductionPlanStatus::Approved,
            $progress['pending'] === 0 && $progress['finished'] === $progress['live'] => ProductionPlanStatus::Completed,
            default => ProductionPlanStatus::InProgress,
        };

        if ($this->status !== $target && $this->status->canTransitionTo($target)) {
            $this->update(['status' => $target]);
        }

        return $this->status;
    }

    /**
     * الخططُ التي وُلِّد عنها هذا الأمر — تُقرأ **قبل حذف الأمر**:
     * الحذفُ يُفرِّغ ربطَ البند (`nullOnDelete`) فلا يبقى ما يدلّ عليها.
     *
     * @return array<int, int>
     */
    public static function planIdsForOrder(int $orderId): array
    {
        return ProductionPlanItem::where('manufactur_order_id', $orderId)
            ->pluck('production_plan_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * تُعيد اشتقاقَ حالة الخطّة التي وُلِّد عنها الأمر — تُستدعى من دورة
     * حياة الأمر بعد كلّ تغييرٍ في حالته. أمرٌ لم يُولَّد من خطّة لا يفعل
     * شيئاً.
     */
    public static function syncStatusForOrder(ManufacturOrders|int $order): void
    {
        static::syncStatuses(static::planIdsForOrder($order instanceof ManufacturOrders ? (int) $order->id : $order));
    }

    /**
     * تُعيد اشتقاقَ حالة خططٍ بمعرّفاتها — لما بعد حذف الأمر، بمعرّفاتٍ
     * قُرئت قبله بـ[self::planIdsForOrder].
     *
     * @param  iterable<int>  $planIds
     */
    public static function syncStatuses(iterable $planIds): void
    {
        foreach (static::whereKey(collect($planIds)->all())->get() as $plan) {
            $plan->syncStatusFromOrders();
        }
    }

    /** إجماليّ الكميّات المخطّطة — للبطاقات والمؤشّرات. */
    public function plannedQuantity(): float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (float) $items->sum('planned_quantity');
    }
}
