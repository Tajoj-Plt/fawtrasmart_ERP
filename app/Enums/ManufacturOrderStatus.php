<?php

namespace App\Enums;

/**
 * حالات أمر التصنيع وآلة الانتقال بينها.
 *
 * ── لماذا هذا الملف ──────────────────────────────────────────────────
 *
 * قبله كانت الحالة تُكتب بطريقتين في نفس المتحكّم:
 *
 *     $order->update(['status' => 'materials_received']);              // نصّ خام
 *     $order->update(['status' => ManufacturOrders::STATUS_COMPLETED]); // ثابت
 *
 * وتُقرأ بثلاث طرق متعارضة: القوائم تقرأها نصّاً، واللوحة تقرأها
 * عدداً `(int) $order->status` فتحوّل 'pending' إلى 0 وتعرض كل أمرٍ
 * «مسوّدة»، والتقارير تقرأها عدداً أيضاً فتُرجع نتائج خاطئة.
 *
 * ولم يكن ثمّة جدولُ انتقالات: كل دالة تفحص ما يحلو لها
 * (canBeCompleted، canBeCancelled) وبعضها لا يفحص شيئاً — فأمكن
 * تعديل أمرٍ مُرحَّل محاسبياً، وأمكن استلام المواد مرتين بعد إرجاعٍ جزئي.
 *
 * هنا: مصدر واحد للحقيقة، وجدول انتقالات صريح يرفض ما ليس فيه.
 *
 * ── التركيب ──────────────────────────────────────────────────────────
 *
 * في App\Models\ManufacturOrders:
 *
 *     protected $casts = [
 *         'status' => \App\Enums\ManufacturOrderStatus::class,
 *     ];
 *
 * ⚠️ بعد التركيب يصير `$order->status` كائناً لا نصّاً. ادمج النصّ
 *    في الرسائل بـ `$order->status->label()` لا `$order->status`.
 */
enum ManufacturOrderStatus: string
{
    /** أُنشئ الأمر ولم تُصرف مواده بعد. الحالة الوحيدة القابلة للتعديل. */
    case Pending = 'pending';

    /** صُرفت المواد الخام من المستودع وخُصمت من الرصيد. */
    case MaterialsReceived = 'materials_received';

    /** العمل جارٍ على المحطات. */
    case InProgress = 'in_progress';

    /** أُنتج المنتج ودخل المخزون، ورُحِّلت القيود الستة. */
    case Completed = 'completed';

    /** أُقفل الأمر نهائياً — لا تعديل ولا تراجع إلا بإعادة فتحه. */
    case Closed = 'closed';

    /** أُلغي، وأُرجعت مواده إن كانت مصروفة. */
    case Cancelled = 'cancelled';

    // ─────────────────────────────────────────────────────────────────
    // العرض
    // ─────────────────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'جديد',
            self::MaterialsReceived => 'تم استلام المواد',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'مكتمل',
            self::Closed => 'مغلق',
            self::Cancelled => 'ملغي',
        };
    }

    /** لون الشارة في الواجهة (Bootstrap contextual). */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::MaterialsReceived => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Closed => 'dark',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'file-text',
            self::MaterialsReceived => 'package',
            self::InProgress => 'settings',
            self::Completed => 'check-circle',
            self::Closed => 'lock',
            self::Cancelled => 'x-circle',
        };
    }

    /**
     * للقوائم المنسدلة ومرشِّحات البحث.
     *
     * كانت getFilterOptions تعرض 'active' و'in_progress' — وأولاهما
     * حالة لا يكتبها المتحكّم إطلاقاً، بينما 'materials_received'
     * و'closed' غائبتان عن القائمة. أي خياران بلا نتائج، وحالتان
     * حقيقيتان لا يمكن الفلترة عليهما.
     */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'tone' => $c->tone(),
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
     *   pending ──────────────► materials_received ──► in_progress
     *      │                          │  ▲                  │
     *      │                          │  └── (إرجاع كامل) ──┘
     *      │                          │         من pending
     *      │                          ▼
     *      │                     completed ◄──────────────────┘
     *      │                       │   ▲
     *      │              (تراجع)  │   │ (إعادة فتح)
     *      │                       ▼   │
     *      │              materials_received   closed
     *      │
     *      └──► cancelled   (من pending أو materials_received أو in_progress)
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [
                self::MaterialsReceived,
                self::Cancelled,
            ],
            self::MaterialsReceived => [
                self::InProgress,
                self::Completed,
                self::Pending,      // إرجاع كل المواد
                self::Cancelled,
            ],
            self::InProgress => [
                self::Completed,
                self::Cancelled,
            ],
            self::Completed => [
                self::Closed,
                self::MaterialsReceived,   // التراجع عن الإنهاء
            ],
            self::Closed => [
                self::Completed,           // إعادة الفتح
            ],
            self::Cancelled => [],         // نهائية
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
                implode('، ', array_map(fn (self $c) => $c->label(), $this->allowedNext())) ?: 'لا شيء (حالة نهائية)'
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // أسئلة الصلاحية — تحلّ محلّ الشروط المتناثرة
    // ─────────────────────────────────────────────────────────────────

    /** التعديل مسموح في «جديد» وحدها: بعدها للأمر أثرٌ مخزني ومحاسبي. */
    public function isEditable(): bool
    {
        return $this === self::Pending;
    }

    /** الحذف مسموح في «جديد» و«ملغى» فقط — وبشرط ألا يكون له إذن مخزني. */
    public function isDeletable(): bool
    {
        return in_array($this, [self::Pending, self::Cancelled], true);
    }

    public function canReceiveMaterials(): bool
    {
        return $this === self::Pending;
    }

    /*
        الإرجاع ممنوعٌ بعد الإتمام.

        كانت `Completed` في القائمة. وبعد الإتمام تكون المواد قد
        استُهلكت في المنتج التام، وأُقفل «مخزون تحت التشغيل» بقيد
        الانحراف. فإرجاعُ موادَّ حينئذٍ يزيد المخزون ويُرحّل قيداً
        دائناً على WIP وقد صار رصيدُه صفراً — فينفتح الحساب بسالبٍ
        لا مصدر له، ويصير المنتج التام محمَّلاً بتكلفة موادَّ عادت
        إلى المستودع.

        والطريقُ الصحيح موجود: `undoCompletion` يعيد الأمر إلى
        «تم استلام المواد» ويعكس قيد الإتمام، ثم يُرجَع ما لم يُستهلك.
    */
    public function canReturnMaterials(): bool
    {
        return in_array($this, [self::MaterialsReceived, self::InProgress], true);
    }

    public function canComplete(): bool
    {
        return in_array($this, [self::MaterialsReceived, self::InProgress], true);
    }

    public function canUndoCompletion(): bool
    {
        return $this === self::Completed;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Pending, self::MaterialsReceived, self::InProgress], true);
    }

    public function canClose(): bool
    {
        return $this === self::Completed;
    }

    public function canReopen(): bool
    {
        return $this === self::Closed;
    }

    /** هل رُحِّلت قيود هذا الأمر؟ يمنع الحذف والتعديل بأثر رجعي. */
    public function isPosted(): bool
    {
        return in_array($this, [self::Completed, self::Closed], true);
    }

    /** حالة نهائية لا انتقال منها. */
    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** الحالات التي للأمر فيها مواد مصروفة من المخزون. */
    public function holdsMaterials(): bool
    {
        return in_array($this, [self::MaterialsReceived, self::InProgress], true);
    }

    // ─────────────────────────────────────────────────────────────────
    // مساعدات
    // ─────────────────────────────────────────────────────────────────

    /**
     * تحويل متسامح — للبيانات القديمة التي قد تحمل أرقاماً.
     *
     * كانت اللوحة والتقارير تعامل الحالة كعدد (0..3)، فقد توجد صفوف
     * قديمة بأرقام. هذه الدالة تستوعبهما معاً أثناء الترحيل.
     */
    public static function fromLegacy(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($parsed = self::tryFrom((string) $value)) {
            return $parsed;
        }

        // خريطة الأرقام القديمة كما كانت تُقرأ في dashboard.blade.php
        return match ((string) $value) {
            '0' => self::Pending,
            '1' => self::InProgress,
            '2' => self::Completed,
            '3' => self::Cancelled,
            default => null,
        };
    }

    /** الحالات التي تُعدّ «نشطة» في اللوحة والمؤشرات. */
    public static function active(): array
    {
        return [self::Pending, self::MaterialsReceived, self::InProgress];
    }

    /** الحالات المنتهية. */
    public static function finished(): array
    {
        return [self::Completed, self::Closed];
    }
}
