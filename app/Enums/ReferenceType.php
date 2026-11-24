<?php

namespace App\Enums;

enum ReferenceType: string
{
    case OPENING_BALANCE = 'OPB';
    case JOURNAL_VOUCHER = 'JV';
    case PAYMENT_VOUCHER = 'PV';
    case RECEIPT_VOUCHER = 'RV';
    case PURCHASE_INVOICE = 'PI';
    case PURCHASE_RETURN = 'PR';
    case SALES_INVOICE = 'SI';
    case SALES_RETURN = 'SR';
    case DEBIT_NOTE = 'DN';
    case CREDIT_NOTE = 'CN';
    case APPROVED_WAREHOUSE = 'AW';

    case EXPENSES = 'EXP';
    case ADVANCE = 'ADV';
    case STOCK_ADJUSTMENT = 'STK';
    case TRANSFER = 'TRF';
    case SUPPLIER_PAYMENT = 'PAY';
    case CUSTOMER_COLLECTION = 'REC';
    case PAYROLL = 'SAL';
    case DEPRECIATION = 'DEP';
    case ACCRUAL_ADJUSTMENT = 'ACC';
    case PAYMENT_CANCELLATION = 'PCAN';
    case ASSET_PURCHASE = 'ASP';       // شراء أصول ثابتة
    case ASSET_SALE = 'ASS';           // بيع أصول ثابتة

    // أنواع الشيكات
    case RECEIVED_CHEQUE = 'RCHQ';      // شيك مستلم
    case RECEIVED_CHEQUE_COLLECT = 'RCHQC'; // تحصيل شيك مستلم
    case RECEIVED_CHEQUE_REJECT = 'RCHQR';  // رفض شيك مستلم
    case RECEIVED_CHEQUE_ENDORSE = 'RCHQE'; // تظهير شيك مستلم
    case PAYABLE_CHEQUE = 'PCHQ';       // شيك صادر
    case PAYABLE_CHEQUE_CASH = 'PCHQC'; // صرف شيك صادر
    case PAYABLE_CHEQUE_REJECT = 'PCHQR'; // رفض شيك صادر

    // تبديل البضاعة
    case PRODUCT_EXCHANGE = 'PEX';      // تبديل بضاعة

    // أوامر التصنيع والإذونات المخزنية
    case WAREHOUSE_PERMIT = 'WP';       // إذن مخزني
    case MANUFACTURING_ORDER = 'MO';    // أمر تصنيع

    // استبدال المكافآت
    case REWARD_REDEMPTION = 'RRD';     // استبدال مكافأة

    // إدارة العقارات — تحصيل دفعة عقد (إيجار/تأمين/ضريبة في قيدٍ واحد)
    case REAL_ESTATE_RENT = 'RER';      // تحصيل إيجار عقاري
    case REAL_ESTATE_OWNER_TRANSFER = 'RET'; // تحويل مستحقات مالك عقار
    case REAL_ESTATE_OWNER_CHARGE = 'REM';   // تحميل مصروف على مالك عقار

    /*
        الحجوزات — ثلاثة أحداثٍ ماليّة لا رابعَ لها:

          • عربونٌ يُقبَض قبل أداء الخدمة، فيُقيَّد التزاماً لا إيراداً.
          • رسمُ إلغاءٍ يُقتطع من العربون عند الإلغاء المتأخّر.
          • ردُّ ما تبقّى للعميل.

        وفاتورةُ الحجز ليست منها: تُقيَّد `SALES_INVOICE` كأيّ فاتورةِ بيع،
        فهي فاتورةُ بيعٍ فعلاً ولا تُبنى لها شجرةُ تقاريرَ ثانية.
    */
    case RESERVATION_DEPOSIT = 'RSVD';          // عربون حجز
    case RESERVATION_CANCELLATION_FEE = 'RSVC'; // رسم إلغاء حجز
    case RESERVATION_REFUND = 'RSVR';           // ردّ عربون حجز

    /*
        غرامة تأخير قسط — ذمّةٌ تُحمَّل على العميل مقابل تأخّره لا مقابل
        بيع. نوعٌ مستقلٌّ لا `JOURNAL_VOUCHER`: بلا تمييزها لا سبيل إلى
        قياس ما يُحصَّل من التعثّر، وهو رقمٌ يُقاس ليُعالَج.

        والقيد: من ح/ العميل، إلى ح/ غرامات تأخير الأقساط.
    */
    case INSTALLMENT_LATE_FEE = 'ILF';          // غرامة تأخير قسط

    /*
        تمويل التقسيط — ربحُ المرابحة والرسومُ الإدارية وضريبتُهما تُحمَّل
        على العميل عند حفظ الاتفاقية. والربحُ مؤجَّلٌ يُعترَف به مع التحصيل
        بقيد `INSTALLMENT_PROFIT`. انظر `InstallmentFinancing`.
    */
    case INSTALLMENT_FINANCING = 'IFN';         // تمويل تقسيط
    case INSTALLMENT_PROFIT = 'IPR';            // تحقّق ربح مرابحة

    /*
        قيدُ الإقفال السنوي — يُصفّر حساباتِ الإيرادات والمصروفات في آخر يومٍ من
        السنة المالية ويُرحِّل صافيها إلى «الأرباح والخسائر المرحّلة». يُنشئه
        `YearEndClosingService` عند إقفال السنة ويحذفه عند إعادة فتحها،
        و`reference_id` معرّفُ السنة المالية.
    */
    case YEAR_CLOSING = 'YCL';                  // إقفال سنوي

    /**
     * Get the Arabic name for the reference type
     */
    public function getName(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'قيد افتتاحي',
            self::JOURNAL_VOUCHER => 'قيد يومية عام',
            self::PAYMENT_VOUCHER => 'سند صرف',
            self::RECEIPT_VOUCHER => 'سند قبض',
            self::PURCHASE_INVOICE => 'فاتورة مشتريات',
            self::PURCHASE_RETURN => 'مردودات مشتريات',
            self::SALES_INVOICE => 'فاتورة مبيعات',
            self::SALES_RETURN => 'مردودات مبيعات',
            self::DEBIT_NOTE => 'إشعار مدين',
            self::CREDIT_NOTE => 'إشعار دائن',
            self::EXPENSES => 'مصروفات',
            self::ADVANCE => 'سلفة/عهدة',
            self::STOCK_ADJUSTMENT => 'تسوية مخزون',
            self::TRANSFER => 'تحويل داخلي',
            self::SUPPLIER_PAYMENT => 'مدفوعات مورّدين',
            self::CUSTOMER_COLLECTION => 'تحصيلات عملاء',
            self::PAYROLL => 'مسير رواتب',
            self::DEPRECIATION => 'إهلاك أصول',
            self::ASSET_PURCHASE => 'شراء أصل ثابت',
            self::ASSET_SALE => 'بيع أصل ثابت',

            self::PAYMENT_CANCELLATION => 'إلغاء دفعة',

            self::APPROVED_WAREHOUSE => 'مخزن معتمد',

            // أنواع الشيكات
            self::RECEIVED_CHEQUE => 'شيك مستلم',
            self::RECEIVED_CHEQUE_COLLECT => 'تحصيل شيك',
            self::RECEIVED_CHEQUE_REJECT => 'رفض شيك مستلم',
            self::RECEIVED_CHEQUE_ENDORSE => 'تظهير شيك',
            self::PAYABLE_CHEQUE => 'شيك صادر',
            self::PAYABLE_CHEQUE_CASH => 'صرف شيك',
            self::PAYABLE_CHEQUE_REJECT => 'رفض شيك صادر',

            // تبديل البضاعة
            self::PRODUCT_EXCHANGE => 'تبديل بضاعة',

            // أوامر التصنيع والإذونات المخزنية
            self::WAREHOUSE_PERMIT => 'إذن مخزني',
            self::MANUFACTURING_ORDER => 'أمر تصنيع',

            // استبدال المكافآت
            self::REWARD_REDEMPTION => 'استبدال مكافأة',

            self::ACCRUAL_ADJUSTMENT => 'تسوية قيود محاسبية',

            // الحجوزات
            self::RESERVATION_DEPOSIT => 'عربون حجز',
            self::RESERVATION_CANCELLATION_FEE => 'رسم إلغاء حجز',
            self::RESERVATION_REFUND => 'ردّ عربون حجز',

            self::INSTALLMENT_LATE_FEE => 'غرامة تأخير قسط',
            self::INSTALLMENT_FINANCING => 'تمويل تقسيط',
            self::INSTALLMENT_PROFIT => 'تحقّق ربح مرابحة',
            self::YEAR_CLOSING => 'قيد إقفال سنوي',
            self::REAL_ESTATE_RENT => 'تحصيل إيجار عقاري',
            self::REAL_ESTATE_OWNER_TRANSFER => 'تحويل مستحقات مالك عقار',
            self::REAL_ESTATE_OWNER_CHARGE => 'تحميل مصروف على مالك عقار',
        };
    }

    /**
     * Get the description for the reference type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'لإثبات أرصدة بداية السنة المالية',
            self::JOURNAL_VOUCHER => 'لإثبات القيود المحاسبية المتنوعة',
            self::PAYMENT_VOUCHER => 'لإثبات دفعات نقدية أو بنكية',
            self::RECEIPT_VOUCHER => 'لإثبات القبض النقدي أو البنكي',
            self::PURCHASE_INVOICE => 'لإثبات شراء بضاعة أو خدمات من المورد',
            self::PURCHASE_RETURN => 'لإثبات إرجاع بضاعة للمورد',
            self::SALES_INVOICE => 'لإثبات بيع بضاعة أو خدمات للعميل',
            self::SALES_RETURN => 'لإثبات إرجاع بضاعة من العميل',
            self::DEBIT_NOTE => 'لزيادة المديونية على العميل أو المورد',
            self::CREDIT_NOTE => 'لتخفيض المديونية على العميل أو المورد',
            self::EXPENSES => 'لتسجيل المصروفات التشغيلية والإدارية',
            self::ADVANCE => 'لتسجيل السلف أو العهد للموظفين',
            self::STOCK_ADJUSTMENT => 'لتسجيل فروقات الجرد أو التسويات المخزنية',
            self::TRANSFER => 'تحويل بين حسابات/مخازن/فروع',
            self::SUPPLIER_PAYMENT => 'تسديد فواتير الموردين',
            self::CUSTOMER_COLLECTION => 'تحصيل مستحقات فواتير العملاء',
            self::PAYROLL => 'تسجيل قيود الرواتب والبدلات والاستقطاعات',
            self::DEPRECIATION => 'لإثبات إهلاك الأصول الثابتة',
            self::ASSET_PURCHASE => 'لإثبات شراء أصل ثابت جديد',
            self::ASSET_SALE => 'لإثبات بيع أصل ثابت والتخلص منه',

            self::PAYMENT_CANCELLATION => 'لإثبات إلغاء دفعة وعكس قيدها المحاسبي',
            self::ACCRUAL_ADJUSTMENT => 'قيود التسوية بنهاية الفترة المالية',

            self::APPROVED_WAREHOUSE => 'لإثبات حركات المخزن من أوامر وتحويلات معتمدة',

            // أنواع الشيكات
            self::RECEIVED_CHEQUE => 'لإثبات استلام شيك من العميل',
            self::RECEIVED_CHEQUE_COLLECT => 'لإثبات تحصيل الشيك من البنك',
            self::RECEIVED_CHEQUE_REJECT => 'لإثبات رفض الشيك وإرجاعه للعميل',
            self::RECEIVED_CHEQUE_ENDORSE => 'لإثبات تظهير الشيك لطرف ثالث',
            self::PAYABLE_CHEQUE => 'لإثبات إصدار شيك للمورد',
            self::PAYABLE_CHEQUE_CASH => 'لإثبات صرف الشيك من البنك',
            self::PAYABLE_CHEQUE_REJECT => 'لإثبات رفض الشيك الصادر',

            // تبديل البضاعة
            self::PRODUCT_EXCHANGE => 'لإثبات تبديل بضاعة تالفة ببضاعة سليمة',

            // أوامر التصنيع والإذونات المخزنية
            self::WAREHOUSE_PERMIT => 'لإثبات إذن مخزني صادر أو وارد',
            self::MANUFACTURING_ORDER => 'لإثبات أمر تصنيع منتج',

            // استبدال المكافآت
            self::REWARD_REDEMPTION => 'لإثبات استبدال نقاط بمكافأة (نقدية، خصم، قسيمة، هدية)',

            // الحجوزات
            self::RESERVATION_DEPOSIT => 'لإثبات قبض عربون حجز قبل أداء الخدمة — التزامٌ على المنشأة لا إيراد',
            self::RESERVATION_CANCELLATION_FEE => 'لإثبات رسم الإلغاء المقتطع من عربون حجزٍ أُلغي',
            self::RESERVATION_REFUND => 'لإثبات ردّ ما تبقّى من عربون الحجز للعميل',

            self::INSTALLMENT_LATE_FEE => 'لإثبات غرامة تأخّر سداد قسطٍ على العميل — إيرادٌ عارض لا مبيعات',
            self::INSTALLMENT_FINANCING => 'لإثبات ربح المرابحة والرسوم الإدارية وضريبتهما على العميل عند حفظ اتفاقية التقسيط — الربح مؤجَّل',
            self::INSTALLMENT_PROFIT => 'لنقل نصيب ما حُصِّل من الأقساط من أرباح المرابحة المؤجلة إلى الإيراد',
            self::YEAR_CLOSING => 'لتصفير حسابات الإيرادات والمصروفات في آخر السنة المالية وترحيل صافي ربحها أو خسارتها إلى الأرباح والخسائر المرحّلة',
            self::REAL_ESTATE_RENT => 'لإثبات تحصيل دفعة عقد عقاري: إيراد الإيجار وضريبته وتأمين المستأجر',
            self::REAL_ESTATE_OWNER_TRANSFER => 'لإثبات تحويل صافي مستحقات المالك من الخزينة أو البنك إلى المالك',
            self::REAL_ESTATE_OWNER_CHARGE => 'لإثبات تحميل مالك العقار بمصروف دفعه المكتب عنه (صيانة وغيرها)',
        };
    }

    /**
     * Get all reference types with their details
     */
    public static function getAllWithDetails(): array
    {
        $types = [];
        foreach (self::cases() as $case) {
            $types[$case->value] = [
                'code' => $case->value,
                'name' => $case->getName(),
                'description' => $case->getDescription(),
            ];
        }

        return $types;
    }

    /**
     * Get reference type by its string value
     */
    public static function fromValue(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return null;
    }
}
