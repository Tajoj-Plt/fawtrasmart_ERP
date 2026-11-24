<?php

namespace Modules\Client\Reservations\Enums;

/**
 * حالة الحجز — ومعها **جدولُ الانتقالات المسموحة**.
 *
 * ═════════════ لماذا تعدادٌ لا جدولُ حالات؟ ═════════════
 *
 * طلبُ العمل يقول: «لا تستخدم ENUM إذا كان النظام يعتمد جداول ديناميكية».
 * والنظام **لا يعتمدها**: `app/Enums/` فيه نحو أربعين تعداداً وهو اصطلاحه
 * المعتمد في كل حالةٍ لها معنى برمجي — `OrderStatus`، `PaymentStatus`،
 * `BatchStatus`، `ChequeStatus`، `VisitStatus`. وجدولُ حالاتٍ هنا وحده
 * يعني نمطاً يتيماً لا نظير له، ويخالف القاعدة 65 (بنيةُ النظام القائمة
 * تسبق الخطة المقترحة).
 *
 * لكنّ **عمود قاعدة البيانات `string` لا `ENUM`**: إضافةُ حالةٍ يجب ألّا
 * تعني `ALTER TABLE` على جدولٍ حيّ.
 *
 * ═════════════ ولماذا الانتقالاتُ هنا لا في الخدمة؟ ═════════════
 *
 * لأنّ السؤال «هل يجوز الانتقال من كذا إلى كذا؟» يُطرَح في ثلاثة مواضع
 * على الأقلّ: الخدمةُ تمنع، والمتحكّمُ يُظهر الأزرارَ الجائزة، والقالبُ
 * يُلوّن الشارة. ولو كُتب الجدول في الخدمة لَقرأه القالبُ بتخمينٍ يفترق
 * عنها بعد أوّل تعديل — فيظهر زرٌّ يرفضه الحفظ.
 *
 * ═════════════ المسار ═════════════
 *
 *   مسودة ← قيد الانتظار ← مؤكَّد ← حضر العميل ← قيد التنفيذ ← مكتمل
 *
 * وحالاتٌ جانبية تُغادر المسار: إلغاء، إعادة جدولة، عدم حضور، رفض.
 *
 * **«معاد جدولته» ليست نهايةً.** إعادةُ الجدولة تنقل الحجز إلى موعدٍ آخر
 * وتُعيده إلى `PENDING` — والحالةُ هنا لقطةٌ تُسجَّل في خطّ الحياة لا
 * مستقرٌّ يبقى فيه. ولذلك تعود منها إلى `PENDING` و`CONFIRMED` معاً.
 */
enum ReservationStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';
    case RESCHEDULED = 'rescheduled';
    case REJECTED = 'rejected';

    /** التسميةُ من ملفّ الترجمة `reservations.status.*` — بلغة الواجهة الحالية. */
    public function label(): string
    {
        return __('reservations.status.'.$this->value);
    }

    /** صنف شارة Bootstrap — القوالب لا تُقرّر اللون بنفسها. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-secondary',
            self::PENDING => 'bg-warning',
            self::CONFIRMED => 'bg-info',
            self::CHECKED_IN => 'bg-primary',
            self::IN_PROGRESS => 'bg-primary',
            self::COMPLETED => 'bg-success',
            self::CANCELLED => 'bg-danger',
            self::NO_SHOW => 'bg-dark',
            self::RESCHEDULED => 'bg-warning',
            self::REJECTED => 'bg-danger',
        };
    }

    /** لون التقويم — يوافق شارةَ القائمة فلا يفترق التمثيلان. */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => '#6c757d',
            self::PENDING => '#f39c12',
            self::CONFIRMED => '#22a06b',
            self::CHECKED_IN => '#2980b9',
            self::IN_PROGRESS => '#8e44ad',
            self::COMPLETED => '#2f6fed',
            self::CANCELLED => '#e74c3c',
            self::NO_SHOW => '#2c3e50',
            self::RESCHEDULED => '#d35400',
            self::REJECTED => '#c0392b',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'edit-3',
            self::PENDING => 'clock',
            self::CONFIRMED => 'check-circle',
            self::CHECKED_IN => 'log-in',
            self::IN_PROGRESS => 'play-circle',
            self::COMPLETED => 'check-square',
            self::CANCELLED => 'x-circle',
            self::NO_SHOW => 'user-x',
            self::RESCHEDULED => 'calendar',
            self::REJECTED => 'slash',
        };
    }

    /**
     * الحالاتُ التي يجوز الانتقالُ إليها من هذه.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING, self::CONFIRMED, self::CANCELLED, self::REJECTED],
            self::PENDING => [self::CONFIRMED, self::REJECTED, self::CANCELLED, self::RESCHEDULED],
            self::CONFIRMED => [self::CHECKED_IN, self::IN_PROGRESS, self::CANCELLED, self::NO_SHOW, self::RESCHEDULED],
            self::CHECKED_IN => [self::IN_PROGRESS, self::COMPLETED, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            // انظر ترويسة الملف: «معاد جدولته» لقطةٌ لا مستقرّ
            self::RESCHEDULED => [self::PENDING, self::CONFIRMED, self::CANCELLED],
            // الحالات النهائية: لا مخرج منها
            self::COMPLETED, self::CANCELLED, self::NO_SHOW, self::REJECTED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل استقرّ الحجز نهائياً فلا يُحرَّك بعدها؟ */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * هل يشغل هذا الحجزُ الموعدَ فعلاً — أي يُحتسب في كشف التعارض؟
     *
     * الملغى والمرفوض ومن لم يحضر **لا يشغلون شيئاً**: فترتُهم تعود متاحةً
     * للحجز، وإلّا ظلّ الموعدُ محجوزاً بحجزٍ ميّت إلى الأبد.
     */
    public function occupiesSlot(): bool
    {
        return match ($this) {
            self::CANCELLED, self::NO_SHOW, self::REJECTED, self::RESCHEDULED => false,
            default => true,
        };
    }

    /** هل بلغ الحجزُ مرحلةً تستحقّ فيها الخدمةُ إيرادها؟ */
    public function isRevenueEarned(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * هل يجوز «تحويل إلى فاتورة» من هذه الحالة؟
     *
     * المؤكَّدُ وما بعده وحدَه: مسودةٌ أو حجزٌ ينتظر التأكيد لم يلتزم به
     * أحدٌ بعد، والملغى والمرفوض ومن لم يحضر لا خدمةَ تُفوتَر لهم. والقاعدةُ
     * هنا مرّةً واحدة — يقرؤها زرُّ الشاشة وحارسُ المسار وشاشةُ الفاتورة.
     */
    public function isInvoiceable(): bool
    {
        return match ($this) {
            self::CONFIRMED, self::CHECKED_IN, self::IN_PROGRESS, self::COMPLETED => true,
            default => false,
        };
    }

    /**
     * الحالاتُ التي يُنشأ الحجزُ عليها مباشرةً من النموذج.
     *
     * مسودةٌ أو انتظار، والتأكيدُ لمن يملك صلاحيتَه. أمّا «مكتمل» أو «حضر»
     * فلا تُكتب عند الإنشاء: كلُّ ما بعد التأكيد انتقالٌ له زرُّه وصلاحيتُه
     * وصفُّه في خطّ الحياة.
     *
     * @return array<int, self>
     */
    public static function storableAtCreate(bool $canConfirm): array
    {
        return $canConfirm
            ? [self::DRAFT, self::PENDING, self::CONFIRMED]
            : [self::DRAFT, self::PENDING];
    }

    /** الحالاتُ الشاغلة للمواعيد — لشرط `whereIn` في كشف التعارض. */
    public static function occupyingValues(): array
    {
        return array_values(array_map(
            fn (self $c) => $c->value,
            array_filter(self::cases(), fn (self $c) => $c->occupiesSlot())
        ));
    }

    /**
     * الحالة من قيمةٍ خام — والمجهول يُعامَل «قيد الانتظار».
     *
     * الافتراضُ متعمَّد: حجزٌ بحالةٍ تالفة أولى أن يُعرَض للمراجعة من أن
     * يُحسَب مؤكَّداً أو مكتملاً فيدخل التقارير المالية.
     */
    public static function from_value($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value)
            ?? self::fromLegacy((string) $value)
            ?? self::PENDING;
    }

    /**
     * ترجمةُ حالات الجدول القديم (`confirm|review|bill|cancel|done`).
     *
     * الجدول كان فارغاً يوم التحويل فلا صفَّ يُترجَم فعلاً — لكنّ نسخةً
     * احتياطيةً قديمة قد تُستعاد، وحينها تُقرأ حالاتُها بلا تلف.
     */
    public static function fromLegacy(string $value): ?self
    {
        return match ($value) {
            'confirm' => self::CONFIRMED,
            'review' => self::PENDING,
            'bill' => self::CHECKED_IN,
            'cancel' => self::CANCELLED,
            'done' => self::COMPLETED,
            default => null,
        };
    }

    /** @return array<string, string> القيمة => التسمية، لقوائم الاختيار */
    public static function toArray(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
