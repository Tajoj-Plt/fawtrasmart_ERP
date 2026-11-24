<?php

namespace App\Enums;

/**
 * نوع المنتج — وقدراتُه.
 *
 * **لماذا قدرات لا أسماء فقط**
 *
 * كان النوع نصّاً حرّاً يُقارَن بـ`switch` في كل شاشة على حدة، ولا موضع
 * واحد يقول «هل لهذا النوع مخزون؟». فنشأ عن ذلك عطلان:
 *
 *   • شاشتا الإضافة والتعديل تعرضان الحقول نفسها لكل نوع — فيُسأل عن طول
 *     الخدمة وعرضها وارتفاعها وحدّ إعادة طلبها.
 *   • ولا شيء يمنع إصدار إذنٍ مخزني لخدمة أو إدخال كمّية لها، فتظهر
 *     الخدمة في أرصدة المستودعات وتقارير الجرد وقيمة المخزون.
 *
 * `tracksStock()` هنا هي الإجابة الوحيدة التي تعتمد عليها النماذج وحارس
 * الأذون معاً — فلا يفترق سلوك الشاشة عن سلوك الحفظ.
 */
enum ProductType: string
{
    /** سلعة مادّية لها رصيد في المستودعات. */
    case PRODUCTS = 'products';

    /** خدمة تُؤدّى ولا تُخزَّن — ساعة عمل، تركيب، صيانة. */
    case SERVICES = 'services';

    /** منتج يُركَّب من مكوّنات، وله رصيد كالسلعة. */
    case COMPILED = 'compiled';

    /** منتج رقمي يُسلَّم تنزيلاً — لا رصيد له ولا شحن. */
    case DIGITAL = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::PRODUCTS => 'منتج مخزني',
            self::SERVICES => 'منتج خدمي',
            self::COMPILED => 'منتج تجميعي',
            self::DIGITAL => 'منتج رقمي',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PRODUCTS => 'سلعة لها رصيد في المستودعات وتُصرَف بأذون',
            self::SERVICES => 'عمل يُؤدّى — لا رصيد له ولا مستودع',
            self::COMPILED => 'يُركَّب من مكوّنات، وله رصيد كالسلعة',
            self::DIGITAL => 'يُسلَّم تنزيلاً — بلا رصيد ولا شحن',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PRODUCTS => 'package',
            self::SERVICES => 'briefcase',
            self::COMPILED => 'layers',
            self::DIGITAL => 'download-cloud',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PRODUCTS => 'bg-primary',
            self::SERVICES => 'bg-info',
            self::COMPILED => 'bg-warning',
            self::DIGITAL => 'bg-secondary',
        };
    }

    /**
     * هل لهذا النوع رصيدٌ في المستودعات؟
     *
     * الجواب الوحيد الذي تعتمد عليه النماذج وحارس الأذون معاً.
     */
    public function tracksStock(): bool
    {
        return match ($this) {
            self::PRODUCTS, self::COMPILED => true,
            self::SERVICES, self::DIGITAL => false,
        };
    }

    /** هل يُشحن مادّياً — فيُسأل عن وزنه وأبعاده؟ */
    public function isShippable(): bool
    {
        return $this->tracksStock();
    }

    /** هل يُركَّب من مكوّنات؟ */
    public function hasComponents(): bool
    {
        return $this === self::COMPILED;
    }

    /** هل تنطبق عليه صلاحية الإرجاع والضمان؟ */
    public function isReturnable(): bool
    {
        return $this !== self::SERVICES;
    }

    /**
     * النوع من قيمةٍ خام — والمجهول يُعامَل «مخزنيّاً».
     *
     * الافتراض متعمَّد: منتجٌ بنوعٍ تالف أولى أن يُحرَس كسلعة من أن يُعفى
     * من كل حراسة.
     */
    public static function from_value($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value) ?? self::PRODUCTS;
    }

    /** الأنواع التي لها رصيد — لشروط `whereIn` في الاستعلامات. */
    public static function stockedValues(): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), fn (self $t) => $t->tracksStock())
        ));
    }

    /** الأنواع التي لا رصيد لها. */
    public static function stocklessValues(): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), fn (self $t) => ! $t->tracksStock())
        ));
    }

    public static function toArray(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
