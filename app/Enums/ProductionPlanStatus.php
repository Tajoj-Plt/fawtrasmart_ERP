<?php

namespace App\Enums;

/**
 * حالاتُ خطّة الإنتاج وآلةُ الانتقال بينها.
 *
 * ── ما الخطّة، ولماذا لها حالات ───────────────────────────────────────
 *
 * الخطّةُ نيّةٌ لا حركة: «ننتج ٥٠٠ كرتونة في أسبوعٍ قادم». ولا تمسّ
 * مخزوناً ولا دفتراً حتى تتحوّل بنودُها إلى **أوامر تصنيع** — وعندئذٍ
 * وحدَه يبدأ الأثر: حجزُ المواد، ثم صرفُها، ثم القيود.
 *
 * فالحالة هنا تحرس أمرين لا ثالث لهما:
 *
 *  · **متى يجوز التعديل.** بعد التوليد صار لبنود الخطّة أوامرُ تقابلها،
 *    فتعديلُ كميّةِ بندٍ وُلِّد أمرُه يجعل الخطّة تصف شيئاً والأمرُ ينفّذ
 *    غيرَه — والفرقُ لا يظهر في شاشةٍ واحدة.
 *
 *  · **متى يجوز التوليد.** المسوّدةُ عملٌ جارٍ لم يُراجَع بعد، فالتوليد
 *    منها يُنشئ أوامرَ عن أرقامٍ لم يقرّها أحد. ولذلك يلزم «معتمدة»
 *    أولاً — وهي الخطوةُ التي تفصل من يخطّط عمّن يقرّ.
 *
 * ── ولماذا Enum لا نصّ حرّ ────────────────────────────────────────────
 *
 * نفسُ الدرس المكتوب في [ManufacturOrderStatus]: الحالةُ هناك كُتبت
 * مرّةً نصّاً ومرّةً ثابتاً وقُرئت عدداً، فعُرض كلُّ أمرٍ «مسوّدة» في
 * اللوحة. الخطّةُ تبدأ من حيث انتهى ذلك — مصدرٌ واحد، وجدولُ انتقالاتٍ
 * صريح يرفض ما ليس فيه.
 *
 * ── التركيب ──────────────────────────────────────────────────────────
 *
 *     protected $casts = ['status' => \App\Enums\ProductionPlanStatus::class];
 *
 * ⚠️ بعده يصير `$plan->status` كائناً لا نصّاً. ادمج النصّ في الرسائل
 *    بـ`$plan->status->label()` لا `$plan->status`.
 */
enum ProductionPlanStatus: string
{
    /** تُحرَّر بعد — الحالةُ الوحيدة التي لم يُقرَّ فيها شيء. */
    case Draft = 'draft';

    /** أُقِرّت أرقامُها، فصار توليدُ الأوامر منها مسموحاً. */
    case Approved = 'approved';

    /** وُلِّد بعضُ أوامرها أو كلُّها، والأوامرُ لم تكتمل كلُّها بعد. */
    case InProgress = 'in_progress';

    /**
     * وُلِّد أمرُ كلِّ بند، وكلُّ أمرٍ قائمٍ منها «مكتمل» أو «مغلق».
     *
     * التوليدُ وحدَه لا يُكملها: الخطّةُ تكتمل حين يُنتَج ما خُطِّط لا
     * حين يُكتب أمرُه. والأمرُ الملغى يُتجاهَل ما دام لها أمرٌ قائم
     * غيرُه — انظر `ProductionPlan::syncStatusFromOrders`.
     */
    case Completed = 'completed';

    /** أُلغيت — نهائية، ولا تُحذف ما دامت لها أوامر: تبقى ويُسأل عنها. */
    case Cancelled = 'cancelled';

    // ─────────────────────────────────────────────────────────────────
    // العرض
    // ─────────────────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسوّدة',
            self::Approved => 'معتمدة',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }

    /** لون الشارة في الواجهة (Bootstrap contextual) — كما في أوامر التصنيع. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Approved => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'file-text',
            self::Approved => 'check-square',
            self::InProgress => 'settings',
            self::Completed => 'check-circle',
            self::Cancelled => 'x-circle',
        };
    }

    /** للقوائم المنسدلة ومرشِّحات البحث. */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'tone' => $case->tone(),
            ],
            self::cases()
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // آلة الحالات
    // ─────────────────────────────────────────────────────────────────

    /**
     * الانتقالات المسموحة من هذه الحالة. ما ليس هنا يُرفض.
     *
     *   draft ──(اعتماد)──► approved ──(توليد)──► in_progress
     *     │                   │  ▲                     │
     *     │       (فكّ اعتماد) │  └─(لا أمرَ قائماً)────┤
     *     │                   ▼                        │ (اكتمل كلُّ أمرٍ قائم)
     *     │                 draft                      ▼
     *     │                   ▲──(لا أمرَ قائماً)─── completed
     *     │                                            │
     *     │        in_progress ◄──(تراجعٌ عن إتمام، ───┘
     *     │                        أو حُذف أمرٌ)
     *     │
     *     └──► cancelled   (من draft أو approved أو in_progress)
     *
     * «لا أمرَ قائماً»: حُذفت الأوامرُ المولَّدة أو أُلغيت **كلُّها**،
     * فلا يبقى في الخطّة ما يُنفَّذ، وحالتُها الصادقة «معتمدة» لا «قيد
     * التنفيذ» ولا «مكتملة». الحالةُ مشتقّةٌ من حالات الأوامر
     * (`ProductionPlan::syncStatusFromOrders`)، وبلا هذه الانتقالات تبقى
     * خطّةٌ «مكتملة» ولا أمرَ لها.
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [
                self::Approved,
                self::Cancelled,
            ],
            self::Approved => [
                self::InProgress,
                self::Completed,   // أُعيد اشتقاقُها وقد اكتملت أوامرُها كلُّها
                self::Draft,       // فكُّ الاعتماد ما دام لم يُولَّد شيء
                self::Cancelled,
            ],
            self::InProgress => [
                self::Completed,   // اكتمل أو أُغلق كلُّ أمرٍ قائم
                self::Approved,    // حُذفت الأوامرُ المولَّدة أو أُلغيت كلُّها
                self::Cancelled,
            ],
            self::Completed => [
                self::InProgress,  // تراجعٌ عن إتمام أمر، أو حُذف أمرٌ فعاد بندُه قابلاً للتوليد
                self::Approved,    // وحُذفت كلُّها — فلم يبقَ أمرٌ قائم أصلاً
            ],
            self::Cancelled => [], // نهائية
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }

    /**
     * @throws \DomainException إذا كان الانتقال غير مسموح
     */
    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw new \DomainException(sprintf(
                'انتقال غير مسموح: من «%s» إلى «%s». المسموح: %s',
                $this->label(),
                $target->label(),
                implode('، ', array_map(fn (self $case) => $case->label(), $this->allowedNext())) ?: 'لا شيء (حالة نهائية)'
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // أسئلة الصلاحية
    // ─────────────────────────────────────────────────────────────────

    /**
     * التعديلُ الكامل — إضافةُ بندٍ وحذفُه وتغييرُ كميّته.
     *
     * مقصورٌ على «مسوّدة» و«معتمدة»: بعدهما وُلِّد أمرٌ عن بندٍ على
     * الأقل، فتعديلُ البنود يجعل الخطّةَ تصف غيرَ ما تنفّذه أوامرُها.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Approved], true);
    }

    /**
     * الحذف — في «مسوّدة» و«ملغاة» وحدهما.
     *
     * ويظلّ الشرطُ الأهمّ خارج الـEnum: ألّا يكون للخطّة أمرٌ مولَّد.
     * انظر `ProductionPlan::hasGeneratedOrders()`.
     */
    public function isDeletable(): bool
    {
        return in_array($this, [self::Draft, self::Cancelled], true);
    }

    /** توليدُ أوامر التصنيع — بعد الاعتماد لا قبله. */
    public function canGenerateOrders(): bool
    {
        return in_array($this, [self::Approved, self::InProgress], true);
    }

    public function canApprove(): bool
    {
        return $this === self::Draft;
    }

    /** فكُّ الاعتماد — ما دام لم يُولَّد شيء بعد. */
    public function canRevertToDraft(): bool
    {
        return $this === self::Approved;
    }

    /**
     * الإلغاء — يقول «لن يُولَّد منها المزيد».
     *
     * ولا يُتاح على «مكتملة»: أوامرُها كلُّها أُنجزت، فلا يبقى ما يُمنَع
     * توليدُه. وإلغاءُ أمرٍ بعينه موضعُه شاشةُ الأوامر.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Approved, self::InProgress], true);
    }

    /** حالةٌ نهائية لا انتقال منها. */
    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    // ─────────────────────────────────────────────────────────────────
    // مساعدات
    // ─────────────────────────────────────────────────────────────────

    /**
     * تحويلٌ متسامح — للقيم الآتية من الطلبات.
     *
     * يُرجع `null` لما لا يُعرَف بدل أن يرمي، فيقرّر النداءُ ماذا يفعل —
     * ومرشِّحُ القائمة يُهمل القيمةَ المختلَقة بدل أن يُسقط الشاشة.
     */
    public static function parse(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom((string) $value);
    }

    /** الحالاتُ التي تُعدّ «نشطة» في اللوحة والمؤشرات. */
    public static function active(): array
    {
        return [self::Draft, self::Approved, self::InProgress];
    }
}
