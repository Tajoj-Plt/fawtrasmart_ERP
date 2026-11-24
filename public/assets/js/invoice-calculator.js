/**
 * نظام حساب الفواتير المحسن - مُصحح ومحدث
 * إصلاح مشاكل كارد المورد والإيداع
 * @version 2.2 - إصلاح الأخطاء الجديدة
 */

class InvoiceCalculator {
    constructor(options = {}) {
        this.options = {
            currency: 'ر.س',
            locale: 'ar',
            calculateDelay: 200,
            autoSave: true,
            autoSaveInterval: 300000, // 5 دقائق
            ...options
        };

        this.calculateTimer = null;
        this.hasUnsavedChanges = false;
        this.isInitialized = false;

        // ربط السياق
        this.init = this.init.bind(this);
        this.calculateTotals = this.calculateTotals.bind(this);
        this.debouncedCalculate = this.debouncedCalculate.bind(this);
    }

    /**
     * تهيئة النظام
     */
    init() {
        if (this.isInitialized) return;

        try {
            /* الربط أوّلاً: هو المالك الوحيد لنقرات «إضافة بند» و«حذف بند»،
               فلو رمى ما قبله خطأً لبقي الزرّان بلا أثر */
            this.bindEvents();
            this.setupNumberInputs();
            this.loadUserPreferences();

            // التحقق من وجود مورد محدد مسبقاً وإظهار كارد الرصيد
            this.checkPreselectedSupplier();

            this.calculateTotals();

            if (this.options.autoSave) {
                this.setupAutoSave();
            }

            this.isInitialized = true;
            console.log('✅ نظام حساب الفواتير جاهز');
            this.showNotification('نظام الفواتير جاهز للاستخدام', 'success', 2000);
        } catch (error) {
            console.error('خطأ في تهيئة النظام:', error);
            this.showNotification('حدث خطأ في تهيئة النظام', 'error');
        }
    }

    /**
     * التحقق من المورد المختار مسبقاً - مُصحح
     */
/**
 * التحقق من المورد المختار مسبقاً - مُصحح
 */
checkPreselectedSupplier() {
    const supplierSelect = document.getElementById('clientSelect');
    const balanceCard = document.getElementById('supplierBalanceCard');

    // إخفاء الكارد بشكل افتراضي دائماً عند فتح الصفحة
    if (balanceCard) {
        balanceCard.style.display = 'none';
    }

    // لا نعرض كارد الرصيد عند فتح الصفحة حتى لو كان هناك مورد محدد
    // سيتم إظهاره فقط عند اختيار المورد من القائمة
}

    /**
     * إعداد حقول الأرقام لتحويل الأرقام العربية
     */
    setupNumberInputs() {
        const numberInputs = document.querySelectorAll('input[type="number"]');

        numberInputs.forEach(input => {
            // تطبيق التنسيق
            input.style.direction = 'ltr';
            input.style.textAlign = 'left';
            input.style.fontFamily = 'Arial, sans-serif';

            // إزالة المستمعين السابقين لتجنب التكرار
            input.removeEventListener('input', this.handleNumberInput);
            input.removeEventListener('keypress', this.validateNumberInput);

            // إضافة المستمعين الجدد
            input.addEventListener('input', (e) => this.handleNumberInput(e));
            input.addEventListener('keypress', (e) => this.validateNumberInput(e));

            // تحويل القيم الموجودة
            if (input.value) {
                this.convertArabicToEnglish(input);
            }
        });
    }

    /**
     * معالجة إدخال الأرقام
     */
    handleNumberInput(e) {
        this.convertArabicToEnglish(e.target);
    }

    /**
     * تحويل الأرقام العربية إلى إنجليزية
     */
    convertArabicToEnglish(input) {
        /* ══════════ الحقولُ النصّية وحدها — لا القوائم ══════════
         *
         * هذه الدالّة تُطبّع الأرقام العربية ثمّ **تحذف كلّ ما ليس رقماً**
         * (`replace(/[^\d.-]/g, '')`). ومستمعُ `input` يناديها على كلّ ما
         * تعدّه `isCalculationField()` حقلَ حساب — وفيها `discount_type`
         * و`shipping_tax_id`، وكلاهما **`<select>`**.
         *
         * فاختيارُ «نسبة ٪» في خصم الفاتورة كان يمرّ هكذا:
         *
         *     select.value = 'percentage'
         *       ⇒ حدث input ⇒ حذفُ الحروف ⇒ select.value = ''
         *       ⇒ selectedIndex = -1 (القائمة بلا خيارٍ محدَّد)
         *       ⇒ القارئ `?.value || 'amount'` يقرأ '' فيسقط إلى «مبلغ»
         *
         * فلا تُطبَّق النسبةُ أبداً، ويُحسب العشرة عشرةَ ريالات لا عشرةً
         * بالمئة — وهو ما شكا منه المستخدم ثلاث مرّات: «أختار نوع الخصم
         * فما يضبط، ما يطبّق إلا على المبلغ». و«مبلغ» بدا سليماً لأنّ
         * القيمة تُمحى أيضاً فيسقط القارئ إلى «مبلغ» صدفةً.
         *
         * و`shipping_tax_id` نجا بالمصادفة: قيمتُه معرّفٌ رقميّ يبقى بعد
         * الحذف. ولو صار يوماً نصّياً لسقط معه.
         *
         * فالحراسةُ بالوسم لا بالاسم: ما ليس `input` ولا `textarea` لا
         * يُطبَّع — القوائمُ تُختار ولا تُكتب.
         */
        if (! input || ! /^(INPUT|TEXTAREA)$/.test(input.tagName) || ! input.value) return;

        const arabicNumbers = '٠١٢٣٤٥٦٧٨٩';
        const englishNumbers = '0123456789';

        let value = input.value.toString();

        // تحويل الأرقام العربية
        for (let i = 0; i < arabicNumbers.length; i++) {
            const regex = new RegExp(arabicNumbers[i], 'g');
            value = value.replace(regex, englishNumbers[i]);
        }

        /* الفاصلة العشرية العربية «٫» نقطةٌ، وفاصلُ الآلاف «٬» يُحذف.
           كانت «٫» تُحذف مع ما ليس رقماً، فيصير ‎١٢٠٫١٠‎ ‎12010‎ — مئةَ ضعف.
           ولم يكن يُرى في حقول `number`: المتصفّح يرفضها قبل أن تصل هنا.
           وصار الإيداعُ حقلاً نصّياً، فتصل. */
        value = value.replace(/٫/g, '.').replace(/٬/g, '');

        // تنظيف القيمة
        value = value.replace(/[^\d.-]/g, '');

        // التأكد من نقطة عشرية واحدة فقط
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }

        // التأكد من إشارة سالب واحدة في البداية فقط
        if (value.indexOf('-') > 0) {
            value = value.replace(/-/g, '');
        }

        /* لا يُعاد الإسناد إن لم يتغيّر شيء: في الحقل النصّي يقفز المؤشّر
           إلى آخر السطر مع كل إسناد، فيتعذّر تصحيحُ رقمٍ في وسطه. */
        if (input.value !== value) input.value = value;
    }

    /**
     * التحقق من صحة إدخال الأرقام
     */
    validateNumberInput(e) {
        const allowedKeys = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '-'];
        const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        const controlKeys = ['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];

        if (allowedKeys.includes(e.key) ||
            arabicNumbers.includes(e.key) ||
            controlKeys.includes(e.key) ||
            e.ctrlKey || e.altKey) {
            return true;
        }

        e.preventDefault();
        return false;
    }

    /**
     * ربط الأحداث - مُصحح
     */
    bindEvents() {
        // أحداث الحساب الفوري
        document.addEventListener('input', (e) => {
            if (this.isCalculationField(e.target)) {
                this.convertArabicToEnglish(e.target);
                this.debouncedCalculate();
                this.hasUnsavedChanges = true;
            }
        });

        // أحداث التغيير - مُصحح لتشمل المورد
        document.addEventListener('change', (e) => {
            if (this.isCalculationField(e.target) ||
                e.target.classList.contains('product-select') ||
                e.target.classList.contains('tax-select') ||
                e.target.classList.contains('discount-type') ||
                e.target.id === 'clientSelect') { // إضافة معالج المورد

                this.handleFieldChange(e.target);
                this.debouncedCalculate();
                this.hasUnsavedChanges = true;
            }
        });

        // أحداث النقر
        document.addEventListener('click', (e) => this.handleClick(e));

        // اختصارات لوحة المفاتيح
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // تحذير من المغادرة
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟';
                return e.returnValue;
            }
        });

        // إرسال النموذج
        const form = document.getElementById('invoice-form');
        if (form) {
            form.addEventListener('submit', () => {
                this.hasUnsavedChanges = false;
            });
        }

        // أحداث checkbox للدفع - مُصححة
        this.bindPaymentEvents();
    }

    /**
     * ربط أحداث الدفع - مُصحح للإيداع
     */
    bindPaymentEvents() {
        /* مربّعا «الفاتورة مدفوعة» و«دفعة مقدّمة» يديرهما
           `applyFullPaymentState()` وحدها — انظر تعليقها. كان هنا
           مستمعان ثانيان لهما يبدآن بـ`.full-payment-fields` و
           `.advance-payment-fields`، وهما صنفان لا وجود لهما في أيّ
           شاشة، فيسقط المستمع بـ`TypeError` قبل أن يبلغ عمله. */

        // مراقبة تغيير حقل الدفعة المقدمة - مُصحح
        const advancePaymentInput = document.querySelector("[name='advance_payment']");
        if (advancePaymentInput) {
            advancePaymentInput.addEventListener('input', () => {
                this.convertArabicToEnglish(advancePaymentInput);
                this.debouncedCalculate();
            });
        }
    }

    /**
     * التحقق من حقول الحساب - مُصحح
     */
    isCalculationField(element) {
        const calculationClasses = ['quantity', 'price', 'discount-amount', 'discount-percentage'];
        /* `discount_type` و`shipping_tax_id` مضافان: الأوّل يقلب الخصم
           العامّ بين مبلغٍ ونسبة، والثاني يحدّد ضريبة الشحن — وكلاهما
           يغيّر الإجمالي. وكانا خارج القائمة، فيبدّل المستخدم الحقل
           ولا يتحرّك الملخّص حتى يمسّ حقلاً آخر. */
        const calculationNames = ['discount_amount', 'discount_type', 'adjustment_value', 'paid_amount', 'shipping_cost', 'shipping_tax_id', 'advance_payment'];
        const calculationIds = ['paid-amount-input', 'advance-payment', 'debugAdvancePayment'];

        return calculationClasses.some(cls => element.classList.contains(cls)) ||
               calculationNames.some(name => element.name === name) ||
               calculationIds.some(id => element.id === id) ||
               element.classList.contains('tax-select');
    }

    /**
     * معالجة تغيير الحقول - مُصحح
     */
    handleFieldChange(element) {
        if (element.classList.contains('product-select')) {
            this.updateProductPrice(element);
        }

        // إصلاح معالج اختيار المورد
        if (element.id === 'clientSelect') {
            this.showSupplierBalance(element);
        }

        if (element.classList.contains('discount-type')) {
            this.toggleDiscountType(element);
        }

        if (element.classList.contains('tax-select')) {
            this.updateTaxHiddenField(element);
        }
    }

    /**
     * معالجة النقرات
     */
    handleClick(e) {
        // إضافة صف جديد
        if (e.target.closest('#add-row')) {
            e.preventDefault();
            this.addNewRow();
        }

        // حذف صف
        if (e.target.closest('.remove-row')) {
            e.preventDefault();
            this.removeRow(e.target.closest('.item-row'));
        }

        // التبويبات
        const tabButtons = ['tab-discount', 'tab-deposit', 'tab-shipping', 'tab-documents'];
        if (tabButtons.includes(e.target.id)) {
            e.preventDefault();
            this.switchTab(e.target.id);
        }

        // التبويبات الفرعية
        if (e.target.id === 'tab-new-document' || e.target.id === 'tab-uploaded-documents') {
            e.preventDefault();
            this.switchDocumentTab(e.target.id);
        }
    }

    /**
     * معالجة لوحة المفاتيح
     */
    handleKeyboard(e) {
        // Ctrl+S للحفظ
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('invoice-form')?.submit();
        }

        // F2 لإضافة صف جديد
        if (e.key === 'F2') {
            e.preventDefault();
            this.addNewRow();
        }

        // F3 للمعاينة السريعة
        if (e.key === 'F3') {
            e.preventDefault();
            this.showQuickPreview();
        }

        // Escape لإلغاء العمليات
        if (e.key === 'Escape') {
            if (typeof Swal !== 'undefined') {
                Swal.close();
            }
        }
    }

    /**
     * حساب مؤجل لتحسين الأداء
     */
    debouncedCalculate() {
        clearTimeout(this.calculateTimer);
        this.calculateTimer = setTimeout(() => {
            this.calculateTotals();
        }, this.options.calculateDelay);
    }

    /**
     * الحساب الرئيسي للإجماليات - مُصحح للإيداع
     */
    calculateTotals() {
        try {
            let subtotal = 0;
            let totalItemDiscount = 0;
            let totalItemTax = 0;
            // الجزء المتضمَّن داخل الأسعار — يُطرح من المجموع فلا يُحسب مرّتين
            let totalIncludedTax = 0;
            let taxDetails = {};

            // مسح الصفوف الديناميكية السابقة
            document.querySelectorAll('.dynamic-tax-row').forEach(row => row.remove());

            // حساب كل صف من العناصر
            document.querySelectorAll('.item-row').forEach(row => {
                const itemCalc = this.calculateItemRow(row);

                subtotal += itemCalc.subtotal;
                totalItemDiscount += itemCalc.discount;
                totalItemTax += itemCalc.tax;
                totalIncludedTax += itemCalc.includedTax || 0;

                // دمج تفاصيل الضرائب
                Object.keys(itemCalc.taxDetails).forEach(taxName => {
                    if (!taxDetails[taxName]) {
                        taxDetails[taxName] = 0;
                    }
                    taxDetails[taxName] += itemCalc.taxDetails[taxName];
                });

                // تحديث إجمالي الصف
                this.updateRowTotal(row, itemCalc.rowTotal);
            });

            /* صافي الأصناف = المجموع − خصوم البنود − الضريبة المتضمَّنة،
               وعليه يُحسب الخصم العامّ. */
            const netItems = subtotal - totalIncludedTax - totalItemDiscount;
            const additionalDiscount = this.calculateAdditionalDiscount(netItems);

            // حساب التسوية
            const adjustmentAmount = this.calculateAdjustment();

            // حساب الشحن
            const shipping = this.calculateShipping(taxDetails);

            /* الإجمالي — على قياس ما يحفظه الخادم بالضبط.
             *
             * الأسعار أُدخلت شاملةً الضريبة المتضمَّنة، فتُطرح من المجموع
             * قبل أن تُضاف `totalTax` إليه، وإلّا حُسبت مرّتين. وهذا ما
             * يفعله `InvoicesController::store()`:
             *
             *     $total_amount = $total_amount - $included_item_tax;
             *
             * ومن دونه كانت الشاشة تعرض إجمالياً أكبر ممّا يُحفَظ —
             * «التذييل يعطي المبلغ بعد الضريبة» وهي متضمَّنة فيه أصلاً.
             */
            const netSubtotal = subtotal - totalIncludedTax;
            const totalDiscount = totalItemDiscount + additionalDiscount;

            /* الخصمُ العامّ يُنقص وعاء الضريبة — يُوزَّع على البنود بنسبة
               صافي كلٍّ منها كما يفعل
               `DocumentTaxCalculator::allocateDocumentDiscount()` في الخادم،
               فلا يعرض الشاشةُ ضريبةً غير التي تُحفظ. */
            const taxRatio = netItems > 0 ? Math.max(0, netItems - additionalDiscount) / netItems : 0;
            const totalTax = (totalItemTax * taxRatio) + shipping.tax;
            const grandTotal = netSubtotal - totalDiscount + adjustmentAmount + shipping.cost + totalTax;

            // حساب الدفعة المقدمة - مُصحح
            const advancePayment = this.calculateAdvancePayment(grandTotal);

            // حساب المبلغ المدفوع
            const paidAmount = this.calculatePaidAmount();

            // حساب المبلغ المتبقي - مُصحح
            const remainingAmount = grandTotal - paidAmount - advancePayment;

            // تحديث العرض - مُصحح
            this.updateDisplay({
                // المعروض هو المحفوظ: `subtotal` في القاعدة صافٍ من المتضمَّنة
                subtotal: netSubtotal,
                totalDiscount: totalDiscount,
                shippingCost: shipping.cost,
                shippingTax: shipping.tax,
                advancePayment: advancePayment,
                paidAmount: paidAmount,
                grandTotal: grandTotal,
                remainingAmount: remainingAmount
            });

            // إضافة الصفوف الديناميكية
            this.addDynamicRows({
                additionalDiscount: additionalDiscount,
                adjustmentAmount: adjustmentAmount,
                taxDetails: taxDetails
            });

        } catch (error) {
            console.error('خطأ في الحسابات:', error);
            this.showNotification('حدث خطأ في الحسابات', 'error');
        }
    }

    /**
     * حساب صف عنصر واحد
     */
    calculateItemRow(row) {
        const quantity = this.getFloatValue(row.querySelector('.quantity')) || 0;
        const unitPrice = this.getFloatValue(row.querySelector('.price')) || 0;
        const subtotal = quantity * unitPrice;

        // حساب خصم العنصر
        const discount = this.calculateItemDiscount(row, subtotal);

        // حساب ضرائب العنصر
        const taxResult = this.calculateItemTaxes(row, subtotal - discount);

        /* المتضمَّنة داخل السعر لا تُضاف فوقه، والمضافة تُضاف.
           كان `subtotal - discount + totalTax` يجمع الاثنتين، فبند
           سعره ٢٠٠٠ بضريبةٍ متضمَّنة ١٥٪ يظهر إجماليه ٢٢٦٠٫٨٧ بينما
           يُحفَظ ٢٠٠٠ — والفارق يراه المستخدم في التذييل. */
        const addedTax = taxResult.totalTax - taxResult.includedTax;
        const rowTotal = subtotal - discount + addedTax;

        return {
            subtotal: subtotal,
            discount: discount,
            tax: taxResult.totalTax,
            includedTax: taxResult.includedTax,
            taxDetails: taxResult.taxDetails,
            rowTotal: rowTotal
        };
    }

    /**
     * حساب خصم العنصر
     */
    calculateItemDiscount(row, subtotal) {
        const discountType = row.querySelector('.discount-type')?.value || 'amount';
        let manual = 0;

        if (discountType === 'percentage') {
            const percentage = this.getFloatValue(row.querySelector('.discount-percentage')) || 0;
            manual = Math.min((subtotal * percentage) / 100, subtotal);
        } else {
            const amount = this.getFloatValue(row.querySelector('.discount-amount')) || 0;
            manual = Math.min(amount, subtotal);
        }

        /* ما كتبه المستخدم يتقدّم، وإلّا فحسمُ العرض المطابق — نفس قاعدة
           الخادم حرفياً، وإلّا عرضت الشاشةُ إجمالياً غيرَ الذي يُحفَظ. */
        if (manual > 0) {
            return manual;
        }

        return this.matchOfferDiscount(row, subtotal);
    }

    /**
     * حسمُ «عروض الحسم» على صفٍّ واحد.
     *
     * نظيرُ `App\Services\Sales\OfferDiscountService::lineDiscount()` —
     * والقواعد واحدة: العرضُ الأكبر حسماً يفوز، والمبلغ الثابت لكلّ وحدة في
     * «حسم على البند» ومرّةً في «اشترِ كميةً»، والحسم لا يتجاوز قيمة البند.
     *
     * العروضُ تصل من `sales::partials.discount-offers` في `window.DISCOUNT_OFFERS`،
     * وتصفيةُ العميل هنا لأنّ العميل يتبدّل في الشاشة بعد تحميلها.
     */
    matchOfferDiscount(row, subtotal) {
        const offers = window.DISCOUNT_OFFERS;

        if (!Array.isArray(offers) || offers.length === 0 || subtotal <= 0) {
            return 0;
        }

        const productId = parseInt(row.querySelector('.product-select')?.value || 0, 10);
        const quantity = this.getFloatValue(row.querySelector('.quantity')) || 0;

        if (!productId || quantity <= 0) {
            return 0;
        }

        const clientField = document.querySelector("[name='client_id']");
        const clientId = parseInt(clientField?.value || 0, 10);

        let best = 0;

        offers.forEach(offer => {
            // العملاء: قائمةٌ فارغة تعني الكلّ
            if (Array.isArray(offer.clients) && offer.clients.length > 0) {
                if (!clientId || offer.clients.indexOf(clientId) === -1) {
                    return;
                }
            }

            // المنتجات: `null` تعني كلَّ المنتجات، والفارغة تعني لا منتج
            if (offer.products !== null && offer.products !== undefined) {
                if (offer.products.indexOf(productId) === -1) {
                    return;
                }
            }

            const value = parseFloat(offer.discount_value) || 0;

            if (value <= 0) {
                return;
            }

            const threshold = Math.max(1, parseFloat(offer.quantity) || 1);

            if (parseInt(offer.type, 10) === 2 && quantity < threshold) {
                return;
            }

            let amount;

            if (parseInt(offer.discount_type, 10) === 2) {
                // النسبة لا تُضاعَف: ٣٠٪ تبقى ٣٠٪ مهما بلغت الكمّية
                amount = (subtotal * value) / 100;
            } else if (parseInt(offer.type, 10) === 1) {
                amount = value * quantity;                                  // لكلّ وحدة
            } else {
                amount = value * Math.floor(quantity / threshold);          // لكلّ مضاعف
            }

            amount = Math.min(amount, subtotal);

            if (amount > best) {
                best = amount;
            }
        });

        return Math.round(best * 100) / 100;
    }

    /**
     * حساب ضرائب العنصر
     */
    calculateItemTaxes(row, taxableAmount) {
        let totalTax = 0;
        /* الجزء **المتضمَّن** داخل السعر — يُتابَع على حدة.
           السعر المُدخَل يحتويه أصلاً، فإضافته إلى الإجمالي تحسبه مرّتين. */
        let includedTax = 0;
        let taxDetails = {};

        // الضريبة الأولى
        const tax1Result = this.calculateSingleTax(
            row.querySelector("[name^='items'][name$='[tax_1]']"),
            taxableAmount
        );

        // الضريبة الثانية
        const tax2Result = this.calculateSingleTax(
            row.querySelector("[name^='items'][name$='[tax_2]']"),
            taxableAmount
        );

        [tax1Result, tax2Result].forEach(function (result) {
            if (result.amount <= 0) {
                return;
            }

            totalTax += result.amount;

            if (result.type === 'included') {
                includedTax += result.amount;
            }

            taxDetails[result.name] = (taxDetails[result.name] || 0) + result.amount;
        });

        return { totalTax: totalTax, includedTax: includedTax, taxDetails: taxDetails };
    }

    /**
     * حساب ضريبة واحدة
     */
    calculateSingleTax(taxSelect, amount) {
        if (!taxSelect || !taxSelect.value) {
            return { amount: 0, name: '', type: '' };
        }

        const selectedOption = taxSelect.options[taxSelect.selectedIndex];
        const taxValue = parseFloat(selectedOption.value) || 0;
        const taxName = selectedOption.dataset.name || selectedOption.text;
        const taxType = selectedOption.dataset.type || 'excluded';

        let taxAmount = 0;

        if (taxValue > 0) {
            if (taxType === 'included') {
                // ضريبة متضمنة في السعر
                taxAmount = amount - (amount / (1 + (taxValue / 100)));
            } else {
                // ضريبة مضافة على السعر
                taxAmount = (amount * taxValue) / 100;
            }
        }

        return {
            amount: taxAmount,
            name: taxName,
            type: taxType
        };
    }

    /**
     * حساب الخصم الإضافي
     */
    calculateAdditionalDiscount(netItems) {
        /* الوعاء **صافي الأصناف** — بعد خصوم البنود.
         *
         * ترتيبُ الحساب: إجمالي الأصناف − خصومات الأصناف ⇒ صافي الأصناف
         * − الخصم العامّ ⇒ صافي التوريد + الشحن ⇒ الوعاء الضريبي.
         * وكان يُمرَّر المجموعُ **قبل** خصوم البنود، فنسبةُ ١٠٪ تُحسب على
         * مبلغٍ أكبر ممّا يقع عليه الخصم فعلاً، وتخالف ما يحفظه الخادم. */
        const discountAmount = this.getFloatValue(document.querySelector("[name='discount_amount']")) || 0;
        const discountType = document.querySelector("[name='discount_type']")?.value || 'amount';

        if (discountAmount <= 0) return 0;

        if (discountType === 'percentage') {
            return Math.min((netItems * discountAmount) / 100, netItems);
        }

        return Math.min(discountAmount, netItems);
    }

    /**
     * حساب التسوية
     */
    calculateAdjustment() {
        const adjustmentValue = this.getFloatValue(document.querySelector("[name='adjustment_value']")) || 0;
        const adjustmentType = document.querySelector("[name='adjustment_type']")?.value || 'discount';

        if (adjustmentValue <= 0) return 0;

        return adjustmentType === 'discount' ? -adjustmentValue : adjustmentValue;
    }

    /**
     * حساب الدفعة المقدمة - مُصحح للعرض
     */
    calculateAdvancePayment(grandTotal) {
        const advanceAmount = this.getFloatValue(document.querySelector("[name='advance_payment']")) ||
                              this.getFloatValue(document.getElementById('debugAdvancePayment')) || 0;
        const advanceType = document.querySelector("[name='advance_payment_type']")?.value || 'amount';
        const isAdvancePaid = document.querySelector('.advance-payment-toggle')?.checked ||
                              document.querySelector("[name='is_advance_paid']")?.checked || false;

        if (!isAdvancePaid || advanceAmount <= 0) return 0;

        let calculatedAdvance = 0;
        if (advanceType === 'percentage') {
            calculatedAdvance = Math.min((grandTotal * advanceAmount) / 100, grandTotal);
        } else {
            calculatedAdvance = Math.min(advanceAmount, grandTotal);
        }

        return calculatedAdvance;
    }

    /**
     * حساب المبلغ المدفوع
     */
    calculatePaidAmount() {
        const paidAmountInput = document.getElementById('paid-amount-input');
        return this.getFloatValue(paidAmountInput) || 0;
    }

    /**
     * حساب الشحن
     */
    calculateShipping(taxDetails) {
        const shippingCost = this.getFloatValue(document.querySelector("[name='shipping_cost']")) || 0;
        let shippingTax = 0;

        if (shippingCost > 0) {
            const shipping = this.resolveShippingTax();

            if (shipping.rate > 0) {
                // مضافةٌ فوق تكلفة الشحن دائماً — تكلفة الشحن تُكتب بلا
                // ضريبة، وهو ما ينصّ عليه `DocumentTaxCalculator::shippingTax()`
                shippingTax = (shippingCost * shipping.rate) / 100;

                const taxName = shipping.name + " (شحن)";

                // إضافة ضريبة الشحن لتفاصيل الضرائب
                if (!taxDetails[taxName]) {
                    taxDetails[taxName] = 0;
                }
                taxDetails[taxName] += shippingTax;
            }
        }

        return { cost: shippingCost, tax: shippingTax };
    }

    /**
     * الضريبة التي يُحمَّل بها الشحن: المختارة في «تبويب الشحن» أوّلاً، فإن
     * لم تُختَر فأوّل ضريبةٍ على بنود المستند.
     *
     * هذا ترتيب `DocumentTaxCalculator::shippingTax()` في الخادم حرفياً.
     * وكانت الشاشة تقف عند الحقل المختار وحده: فاتورةٌ بضريبةٍ على بنودها
     * وشحنٍ بلا ضريبةٍ مختارة تُعرَض بلا ضريبة شحن ثمّ تُحفَظ بها، فيخالف
     * الإجمالي المعروض الإجماليَ المحفوظ.
     */
    resolveShippingTax() {
        const select = document.querySelector("[name='shipping_tax_id']");

        if (select && select.value) {
            const option = select.options[select.selectedIndex];

            return {
                rate: parseFloat(option.dataset.rate) || 0,
                name: option.dataset.name || option.text,
            };
        }

        /* المُنتقي موجودٌ وتُرك على «بلا ضريبة» ⇒ لا ضريبة على الشحن.
           «بلا ضريبة» اختيارٌ لا سكوت، ونقضُه خطأ في المال: شحنٌ ١٠٠
           «بلا ضريبة» كان يخرج بضريبة ١٥ لأنّه يرث ضريبة البنود.
           والوراثة تبقى للشاشات التي لا مُنتقيَ فيها أصلاً — انظر
           `DocumentTaxCalculator::shippingTaxIds()`. */
        if (select) {
            return { rate: 0, name: '' };
        }

        const slots = ["[name^='items'][name$='[tax_1]']", "[name^='items'][name$='[tax_2]']"];
        const rows = Array.prototype.slice.call(document.querySelectorAll('.item-row'));

        for (const row of rows) {
            for (const slot of slots) {
                const taxSelect = row.querySelector(slot);

                if (!taxSelect || !taxSelect.value) {
                    continue;
                }

                const option = taxSelect.options[taxSelect.selectedIndex];

                // أوّل ضريبةٍ مضبوطة يقف عندها الخادم ولو كانت نسبتها صفراً
                return {
                    rate: parseFloat(option.value) || 0,
                    name: option.dataset.name || option.text,
                };
            }
        }

        return { rate: 0, name: '' };
    }

    /**
     * الحصول على قيمة رقمية من عنصر
     */
    getFloatValue(element) {
        if (!element) return 0;
        const value = element.textContent || element.value || '0';
        return parseFloat(value.replace(/[^\d.-]/g, '')) || 0;
    }

    /**
     * تحديث إجمالي الصف
     */
    updateRowTotal(row, total) {
        const totalElement = row.querySelector('.row-total');
        if (totalElement) {
            totalElement.textContent = total.toFixed(2);
        }
    }

    /**
     * تحديث العرض - مُصحح للإيداع
     */
    updateDisplay(data) {
        this.updateElement('subtotal', data.subtotal);
        this.updateElement('total-discount', data.totalDiscount);

        // حساب المجموع بعد الخصم
        const afterDiscount = data.subtotal - data.totalDiscount;
        this.updateElement('after-discount', afterDiscount);

        // حساب مجموع الضريبة
        const taxTotal = data.grandTotal - afterDiscount - (data.shippingCost || 0);
        this.updateElement('tax-total', Math.max(0, taxTotal));

        /* سطرا «ضريبة البنود» و«ضريبة الشحن» منفصلين — في الشاشات التي
           تعرضهما (الفاتورة الدورية). `updateElement` لا يفعل شيئاً حيث
           لا عنصر، فبقيّة الشاشات على سطرها الواحد. */
        this.updateElement('shipping-tax', data.shippingTax || 0);
        this.updateElement('items-tax-total', Math.max(0, taxTotal - (data.shippingTax || 0)));

        this.updateElement('shipping-cost', data.shippingCost);
        this.updateElement('advance-payment', data.advancePayment);
        this.updateElement('grand-total', data.grandTotal);
        this.updateElement('remaining-amount', data.remainingAmount);

        // تحديث المبلغ المدفوع في الجدول
        if (data.paidAmount > 0) {
            this.updateElement('paid-amount-display', data.paidAmount);
        } else {
            this.updateElement('paid-amount-display', 0);
        }

        /* «المتبقي» يشترط شرطاً ثانياً فوق «أظهرني إن كنت غير صفر»: ما دام
           لم يُدفع شيء فهو الإجمالي نفسه مكرَّراً في سطرين. فيظهر متى دُفع
           شيء — إيداعاً كان أو سداداً — وبقي بعده شيء. */
        const settled = (data.advancePayment || 0) + (data.paidAmount || 0);
        const remainingRow = document.querySelector('[data-total-row="remaining-amount"]');

        if (remainingRow) {
            const hide = settled < 0.005 || Math.abs(data.remainingAmount || 0) < 0.005;
            remainingRow.classList.toggle('d-none', hide);
        }
    }

    /**
     * تحديث عنصر في DOM.
     *
     * وإن كان للعنصر سطرٌ في صندوق الإجماليات موسومٌ بـ
     * `data-total-row="<id>"` فهو سطرٌ مشروط: يظهر متى صار للقيمة وجود
     * ويُخفى متى عادت صفراً. الأسطر الثابتة (المجموع والإجمالي النهائي)
     * بلا هذا الوسم فلا يمسّها شيء. والحدّ 0.005 لأن المعروض مقرَّبٌ
     * لخانتين، فما دونه يُطبع «0.00» ويبدو سطراً فارغاً بلا معنى.
     */
    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value.toFixed(2);
        }

        const row = document.querySelector('[data-total-row="' + id + '"]');
        if (row) {
            row.classList.toggle('d-none', Math.abs(value) < 0.005);
        }
    }

    /**
     * إضافة الصفوف الديناميكية
     */
    addDynamicRows(data) {
        const taxRowsContainer = document.getElementById('tax-rows');
        if (!taxRowsContainer) return;

        const insertPoint = taxRowsContainer.querySelector('tr:last-child');

        // إضافة صف الخصم الإضافي
        if (data.additionalDiscount > 0) {
            this.insertTaxRow(insertPoint, 'خصم إضافي', data.additionalDiscount, 'text-danger', '-');
        }

        // إضافة صف التسوية
        if (data.adjustmentAmount !== 0) {
            const adjustmentLabel = data.adjustmentAmount > 0 ? 'إضافة' : 'خصم';
            const cssClass = data.adjustmentAmount > 0 ? 'text-success' : 'text-danger';
            const sign = data.adjustmentAmount > 0 ? '+' : '-';
            this.insertTaxRow(insertPoint, adjustmentLabel, Math.abs(data.adjustmentAmount), cssClass, sign);
        }

        // تحديث تفاصيل الضرائب في العنصر tax-details
        const taxDetailsElement = document.getElementById('tax-details');
        if (taxDetailsElement && Object.keys(data.taxDetails).length > 0) {
            let taxDetailsText = 'تفاصيل الضرائب: ';
            const taxDetailsArray = Object.entries(data.taxDetails).map(([taxName, taxAmount]) => {
                return `${taxName}: ${taxAmount.toFixed(2)} ر.س`;
            });
            taxDetailsText += taxDetailsArray.join(' | ');
            taxDetailsElement.textContent = taxDetailsText;
        } else if (taxDetailsElement) {
            taxDetailsElement.textContent = '';
        }

        // إضافة صفوف الضرائب (إذا لزم الأمر)
        // Object.entries(data.taxDetails).forEach(([taxName, taxAmount]) => {
        //     if (taxAmount > 0) {
        //         this.insertTaxRow(insertPoint, taxName, taxAmount, 'text-info', '+');
        //     }
        // });
    }

    /**
     * إدراج صف ضريبة
     */
    insertTaxRow(insertPoint, label, amount, cssClass, sign) {
        /* عدد الأعمدة يُقرأ من رأس الجدول لا يُكتب رقماً.
           كان `colspan="7"` ثابتاً — أي تسعة أعمدة — والملف مشتركٌ بين
           أربع شاشات: أوّل عمودٍ يُضاف في إحداها (عمود «الوحدة» في
           الفاتورة والإشعار الدائن) يزيح صفَّي «خصم إضافي» و«التسوية»
           عن عمود المبلغ. الرأسُ هو مصدر العدد الوحيد الصادق. */
        const table = insertPoint.closest('table');
        const columns = table?.querySelectorAll('thead th').length || 9;
        const row = document.createElement('tr');
        row.classList.add('dynamic-tax-row');
        row.innerHTML = `
            <td colspan="${Math.max(1, columns - 2)}" class="text-right">${label}</td>
            <td><span class="${cssClass}">${sign}${amount.toFixed(2)}</span> ${this.options.currency}</td>
            <td></td>
        `;
        insertPoint.parentNode.insertBefore(row, insertPoint);
    }

    /**
     * إضافة صف جديد.
     *
     * كان الجسم كلُّه معلَّقاً بينما `handleClick()` و`handleKeyboard()`
     * ما زالتا تناديانه: كلّ نقرةٍ على «إضافة بند» وكلّ ضغطة F2 ترمي
     * `TypeError` وتوقف بقيّة معالج النقر. النقرة تنجو لأنّ لـ`#add-row`
     * مستمعاً ثانياً يسقط إلى `addNewItemRow()`، أمّا F2 فلا تعمل أصلاً.
     *
     * التنفيذ في `addNewItemRow()` — وهو الأصحّ: يحسب الفهرس التالي من
     * أكبر فهرسٍ مستعمَل لا من عدد الصفوف.
     */
    addNewRow() {
        addNewItemRow();
    }

    // النسخة القديمة، تُترك للمقارنة:
    // addNewRow_legacy() {
    //     const table = document.querySelector('#items-table tbody');
    //     const rowCount = table.children.length;
    //     const firstRow = table.children[0];

    //     if (!firstRow) return;

    //     const newRow = firstRow.cloneNode(true);

    //     // تحديث أسماء الحقول
    //     newRow.querySelectorAll('input, select').forEach(input => {
    //         if (input.name) {
    //             input.name = input.name.replace(/\[\d+\]/, `[${rowCount}]`);

    //             // إعادة تعيين القيم
    //             if (input.type !== 'hidden') {
    //                 if (input.classList.contains('quantity')) {
    //                     input.value = '1';
    //                 } else if (input.type === 'number') {
    //                     input.value = '0';
    //                 } else {
    //                     input.value = '';
    //                 }
    //             }
    //         }
    //     });

    //     // إعادة تهيئة Select2 إذا كان متوفراً
    //     const select2Elements = newRow.querySelectorAll('.select2');
    //     select2Elements.forEach(element => {
    //         if (typeof $ !== 'undefined' && $.fn.select2) {
    //             $(element).removeClass('select2-hidden-accessible').next().remove().end().select2();
    //         }
    //     });

    //     table.appendChild(newRow);
    //     this.setupNumberInputs(); // إعادة تهيئة حقول الأرقام للصف الجديد
    //     this.calculateTotals();

    //     this.showNotification('تم إضافة صف جديد', 'success');
    // }

    /**
     * حذف صف
     */
    removeRow(row) {
        const totalRows = document.querySelectorAll('.item-row').length;

        if (totalRows <= 1) {
            this.showNotification('لا يمكن حذف جميع العناصر', 'warning');
            return;
        }

        row.remove();
        this.calculateTotals();
        this.showNotification('تم حذف الصف', 'info');
    }

    /**
     * تحديث سعر المنتج
     */
    updateProductPrice(selectElement) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const price = selectedOption.getAttribute('data-price') || 0;
        const row = selectElement.closest('.item-row');
        const priceInput = row.querySelector('.price');

        if (priceInput) {
            priceInput.value = price;
            this.convertArabicToEnglish(priceInput);
            this.calculateTotals();
        }
    }

    /**
     * عرض رصيد المورد - مُصحح
     */
    showSupplierBalance(selectElement) {
        const balanceCard = document.getElementById('supplierBalanceCard');

        // التحقق من أن المورد تم اختياره فعلياً وليس مجرد القيمة الافتراضية
        if (!selectElement ||
            !selectElement.value ||
            selectElement.value === '' ||
            selectElement.value === '0' ||
            selectElement.selectedIndex === 0) {
            if (balanceCard) {
                balanceCard.style.display = 'none';
            }
            return;
        }

        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const supplierName = selectedOption.text;
        const supplierBalance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;

        const nameElement = document.getElementById('supplierName');
        const balanceElement = document.getElementById('supplierBalance');
        const statusElement = document.getElementById('balanceStatus');

        if (nameElement) nameElement.textContent = supplierName;
        if (balanceElement) balanceElement.textContent = Math.abs(supplierBalance).toFixed(2);

        if (statusElement && balanceElement) {
            if (supplierBalance > 0) {
                this.setBalanceStatus(statusElement, balanceElement, 'دائن', '#4CAF50');
            } else if (supplierBalance < 0) {
                this.setBalanceStatus(statusElement, balanceElement, 'مدين', '#f44336');
            } else {
                this.setBalanceStatus(statusElement, balanceElement, 'متوازن', '#FFC107');
            }
        }

        // إظهار الكارد مع تأثير
        if (balanceCard) {
            balanceCard.style.display = 'block';
            balanceCard.style.opacity = '0';
            balanceCard.style.transform = 'translateY(-20px)';

            setTimeout(() => {
                balanceCard.style.transition = 'all 0.3s ease';
                balanceCard.style.opacity = '1';
                balanceCard.style.transform = 'translateY(0)';
            }, 10);
        }
    }

    /**
     * تعيين حالة الرصيد
     */
    setBalanceStatus(statusElement, balanceElement, text, color) {
        statusElement.textContent = text;
        statusElement.style.color = color;
        balanceElement.style.color = color;
    }

    /**
     * تبديل نوع الخصم
     */
    toggleDiscountType(selectElement) {
        const row = selectElement.closest('.item-row');
        const discountAmountInput = row.querySelector('.discount-amount');
        const discountPercentageInput = row.querySelector('.discount-percentage');
        const toPercentage = selectElement.value === 'percentage';

        /* الرقم المكتوب ينتقل مع الوحدة.
         *
         * خصمُ البند حقلان يتبادلان الظهور — `discount` و`discount_percentage`
         * — بينما خصمُ الفاتورة حقلٌ واحدٌ بمُنتقي وحدة. فمن كتب «10» ثمّ
         * بدّل الوحدة إلى «٪» كان يجد الحقلَ الظاهر **صفراً**، ويسقط خصمُه
         * من المجموع بلا رسالة: الرقم باقٍ في الحقل المخفيّ، والخادم يقرأ
         * الظاهر (`lineDiscountInput()` يقرأ `discount_percentage` متى كان
         * النوع نسبةً). فالمنقول هنا هو ما كتبه المستخدم لا أكثر — تحويلُ
         * «10 ريالات» إلى «10٪» تبديلُ وحدةٍ صريحٌ اختاره بيده، وهو ما
         * يفعله مُنتقي خصم الفاتورة بالضبط.
         */
        const from = toPercentage ? discountAmountInput : discountPercentageInput;
        const to = toPercentage ? discountPercentageInput : discountAmountInput;

        if (from && to) {
            const carried = parseFloat(from.value) || 0;

            if (carried > 0 && !(parseFloat(to.value) > 0)) {
                // النسبة لا تتجاوز مئة، والمبلغ بلا سقفٍ هنا
                to.value = toPercentage ? Math.min(carried, 100) : carried;
            }

            from.value = 0;
        }

        if (discountAmountInput) discountAmountInput.style.display = toPercentage ? 'none' : 'block';
        if (discountPercentageInput) discountPercentageInput.style.display = toPercentage ? 'block' : 'none';
    }

    /**
     * تحديث حقل الضريبة المخفي
     */
    updateTaxHiddenField(selectElement) {
        const row = selectElement.closest('.item-row');
        const taxType = selectElement.getAttribute('data-target');
        const hiddenInput = row.querySelector(`input[name^="items"][name$="[${taxType}_id]"]`);

        if (hiddenInput) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            hiddenInput.value = selectedOption.getAttribute('data-id') || '';
        }
    }

    /**
     * تبديل التبويبات
     */
    switchTab(tabId) {
        // إزالة الفئة النشطة من جميع التبويبات
        document.querySelectorAll('.nav-link').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-section').forEach(section => section.classList.add('d-none'));

        // إضافة الفئة النشطة للتبويب المحدد
        const targetTab = document.getElementById(tabId);
        if (targetTab) targetTab.classList.add('active');

        // إظهار القسم المقابل
        const sectionMap = {
            'tab-discount': 'section-discount',
            'tab-deposit': 'section-deposit',
            'tab-shipping': 'section-shipping',
            'tab-documents': 'section-documents'
        };

        const targetSection = sectionMap[tabId];
        if (targetSection) {
            const section = document.getElementById(targetSection);
            if (section) section.classList.remove('d-none');
        }
    }

    /**
     * تبديل تبويبات المستندات
     */
    switchDocumentTab(tabId) {
        document.querySelectorAll('#tab-new-document, #tab-uploaded-documents').forEach(tab =>
            tab.classList.remove('active'));
        document.querySelectorAll('#content-new-document, #content-uploaded-documents').forEach(content =>
            content.classList.add('d-none'));

        const targetTab = document.getElementById(tabId);
        if (targetTab) targetTab.classList.add('active');

        if (tabId === 'tab-new-document') {
            const content = document.getElementById('content-new-document');
            if (content) content.classList.remove('d-none');
        } else {
            const content = document.getElementById('content-uploaded-documents');
            if (content) content.classList.remove('d-none');
        }
    }

    /**
     * معاينة سريعة
     */
    showQuickPreview() {
        const supplierSelect = document.getElementById('clientSelect');
        const supplier = supplierSelect ? supplierSelect.options[supplierSelect.selectedIndex].text : 'غير محدد';
        const itemsCount = document.querySelectorAll('.item-row').length;
        const subtotal = this.getFloatValue(document.getElementById('subtotal'));
        const discount = this.getFloatValue(document.getElementById('total-discount'));
        const total = this.getFloatValue(document.getElementById('grand-total'));
        const advancePayment = this.getFloatValue(document.getElementById('advance-payment'));
        const paidAmount = this.getFloatValue(document.getElementById('paid-amount-display'));
        const remainingAmount = this.getFloatValue(document.getElementById('remaining-amount'));

        const message = `
            <div class="text-right" style="direction: rtl;">
                <p><strong>المورد:</strong> ${supplier}</p>
                <p><strong>عدد العناصر:</strong> ${itemsCount}</p>
                <p><strong>المجموع الفرعي:</strong> ${subtotal.toFixed(2)} ر.س</p>
                <p><strong>إجمالي الخصومات:</strong> ${discount.toFixed(2)} ر.س</p>
                <p><strong>الدفعة المقدمة:</strong> ${advancePayment.toFixed(2)} ر.س</p>
                <p><strong>المبلغ المدفوع:</strong> ${paidAmount.toFixed(2)} ر.س</p>
                <p><strong>المجموع الكلي:</strong> ${total.toFixed(2)} ر.س</p>
                <p><strong>المبلغ المتبقي:</strong> ${remainingAmount.toFixed(2)} ر.س</p>
            </div>
        `;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'معاينة سريعة',
                html: message,
                icon: 'info',
                confirmButtonText: 'إغلاق'
            });
        } else {
            alert('معاينة سريعة:\n' +
                  'المورد: ' + supplier + '\n' +
                  'عدد العناصر: ' + itemsCount + '\n' +
                  'المجموع الفرعي: ' + subtotal.toFixed(2) + ' ر.س\n' +
                  'إجمالي الخصومات: ' + discount.toFixed(2) + ' ر.س\n' +
                  'الدفعة المقدمة: ' + advancePayment.toFixed(2) + ' ر.س\n' +
                  'المبلغ المدفوع: ' + paidAmount.toFixed(2) + ' ر.س\n' +
                  'المجموع الكلي: ' + total.toFixed(2) + ' ر.س\n' +
                  'المبلغ المتبقي: ' + remainingAmount.toFixed(2) + ' ر.س');
        }
    }

    /**
     * عرض الإشعارات
     */
    showNotification(message, type = 'info', duration = 3000) {
        console.log(`${type.toUpperCase()}: ${message}`);

        // إذا كان SweetAlert متوفر
        if (typeof Swal !== 'undefined') {
            const iconMap = {
                'success': 'success',
                'error': 'error',
                'warning': 'warning',
                'info': 'info'
            };

            Swal.fire({
                text: message,
                icon: iconMap[type] || 'info',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duration,
                timerProgressBar: true
            });
        } else {
            // إنشاء إشعار مخصص
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} notification-toast`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 250px;
                max-width: 400px;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                padding: 15px;
                margin-bottom: 10px;
            `;

            const iconMap = {
                'success': 'check-circle',
                'error': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };

            notification.innerHTML = `
                <i class="fa fa-${iconMap[type] || 'info-circle'}" style="margin-left: 8px;"></i>
                ${message}
                <button type="button" class="close" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 18px; margin-right: 10px; cursor: pointer;">
                    <span>&times;</span>
                </button>
            `;

            document.body.appendChild(notification);

            // تأثير الظهور
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 100);

            // الإخفاء التلقائي
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, duration);
        }
    }

    /**
     * التحقق من صحة النموذج
     */
    validateForm() {
        const errors = [];

        // التحقق من العميل — الملف لا تحمّله إلا شاشات المبيعات
        const client = document.getElementById('clientSelect')?.value;
        if (!client) {
            errors.push('يجب اختيار العميل');
        }

        // التحقق من وجود عناصر صحيحة
        let hasValidItems = false;
        document.querySelectorAll('.item-row').forEach(row => {
            const product = row.querySelector('.product-select')?.value;
            const quantity = this.getFloatValue(row.querySelector('.quantity'));
            const price = this.getFloatValue(row.querySelector('.price'));

            if (product && quantity > 0 && price > 0) {
                hasValidItems = true;
            }
        });

        if (!hasValidItems) {
            errors.push('يجب إضافة عنصر واحد صحيح على الأقل');
        }

        if (errors.length > 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'خطأ في البيانات',
                    html: errors.join('<br>'),
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
            } else {
                alert('خطأ في البيانات:\n' + errors.join('\n'));
            }
            return false;
        }

        return true;
    }

    /**
     * حفظ كمسودة
     */
    saveAsDraft() {
        if (!this.validateForm()) return;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'جاري حفظ المسودة...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        const form = document.getElementById('invoice-form');
        const formData = new FormData(form);
        formData.append('is_draft', '1');

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('تم حفظ الفاتورة كمسودة بنجاح', 'success');
                this.hasUnsavedChanges = false;
            } else {
                this.showNotification('حدث خطأ أثناء حفظ المسودة', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showNotification('حدث خطأ أثناء حفظ المسودة', 'error');
        })
        .finally(() => {
            if (typeof Swal !== 'undefined') {
                Swal.close();
            }
        });
    }

    /**
     * مسح جميع العناصر
     */
    clearAllItems() {
        const confirmMessage = 'هل أنت متأكد؟ سيتم مسح جميع العناصر من الفاتورة';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'سيتم مسح جميع العناصر من الفاتورة',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، امسح الكل',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performClearAllItems();
                }
            });
        } else {
            if (confirm(confirmMessage)) {
                this.performClearAllItems();
            }
        }
    }

    /**
     * تنفيذ مسح جميع العناصر
     */
    performClearAllItems() {
        // الاحتفاظ بصف واحد فقط
        const allRows = document.querySelectorAll('.item-row');
        for (let i = 1; i < allRows.length; i++) {
            allRows[i].remove();
        }

        // مسح بيانات الصف الأول
        if (allRows[0]) {
            allRows[0].querySelectorAll('input, select').forEach(input => {
                if (input.type !== 'hidden') {
                    if (input.classList.contains('quantity')) {
                        input.value = '1';
                    } else if (input.type === 'number') {
                        input.value = '0';
                    } else {
                        input.value = '';
                    }
                }
            });
        }

        this.calculateTotals();
        this.showNotification('تم مسح جميع العناصر بنجاح', 'success');
    }

    /**
     * تحميل إعدادات المستخدم
     */
    loadUserPreferences() {
        try {
            const preferences = localStorage.getItem('invoicePreferences');
            if (preferences) {
                const parsed = JSON.parse(preferences);

                // العميل المحفوظ لا يُعاد تعبئته: القيمة كانت تُكتب على
                // `#clientSelect` مباشرةً بلا إخطار select2، فيبقى المنسدل
                // ظاهراً بلا اختيار بينما القيمة الفعلية عميلٌ آخر —
                // وقد تُحفظ الفاتورة بعميل غير الذي يراه المستخدم.

                /* ══════════ مهلة السداد لا تُستعاد ══════════
                 *
                 * كانت آخرُ مهلةٍ كُتبت («١٠ أيام» مثلاً) تُحفَظ في
                 * `localStorage` وتُعبَّأ في كل فاتورةٍ جديدة، فتُحفظ فواتيرُ
                 * بمهلةٍ لم يكتبها المستخدم فيها. المهلةُ تخصّ المستند:
                 * الحقلُ يبدأ فارغاً ولا يحمل إلا ما يُدخله المستخدم، أو ما
                 * ينسخه `fillInvoiceFromSource()` من مستندٍ مصدر.
                 * والمخزَّن القديم يُمسح هنا.
                 */
                if ('defaultTerms' in parsed) {
                    delete parsed.defaultTerms;
                }

                /* ══════════ تكلفة الشحن لا تُستعاد ══════════
                 *
                 * كانت تُحفَظ في `localStorage` وتُعاد تعبئتها في كل مستندٍ
                 * جديد: من كتب «٣٠» مرّةً واحدة وجدها في كل فاتورةٍ بعدها
                 * — وفي المرتجع والإشعار الدائن أيضاً، لأن الاستعادة
                 * بالاسم `shipping_cost` لا بالشاشة. ولأنّها تُكتب في
                 * الحقل قبل أن ينظر المستخدم إلى تبويب الشحن، تدخل
                 * الإجمالي وتُقيَّد إيرادَ شحنٍ لم يحصل.
                 *
                 * ومبلغُ شحنٍ يخصّ مستنداً بعينه ليس «تفضيلاً» يُذكر أصلاً.
                 * والمخزَّن القديم يُمسح هنا، فلا يحتاج المستخدم إلى
                 * تنظيف متصفّحه ليتخلّص من الرقم العالق.
                 */
                if ('defaultShippingCost' in parsed) {
                    delete parsed.defaultShippingCost;
                }

                // لم يبقَ تفضيلٌ يُحفظ — يُزال المفتاح كاملاً
                localStorage.removeItem('invoicePreferences');
            }
        } catch (error) {
            console.warn('تعذر تحميل الإعدادات المحفوظة:', error);
        }
    }

    /**
     * حفظ إعدادات المستخدم
     */
    saveUserPreferences() {
        // لا شيء يُحفظ: مهلة السداد وتكلفة الشحن كلتاهما تخصّان المستند
        // لا المستخدم، واستعادتهما كانت تملأ فواتير جديدة بقيمٍ لم تُكتب فيها.
    }

    /**
     * إعداد الحفظ التلقائي
     */
    setupAutoSave() {
        /* لا مؤقّت حفظٍ تلقائي.
         *
         * كان هنا `setInterval` كل ٥ دقائق يستدعي `validateForm()` متى كُتب
         * أيّ شيء — و`validateForm()` تفتح نافذة «خطأ في البيانات» — فتقفز
         * النافذة فجأةً في منتصف تعبئة الفاتورة/المرتجع/الإشعار الدائن بلا
         * أيّ ضغطة من المستخدم. والحفظ نفسه لم يعمل قطّ: `/invoices/auto-save`
         * لا مسار له، ولا بادئة لغة فيه. */
    }

    /**
     * الحفظ التلقائي
     */
    autoSave() {
        const form = document.getElementById('invoice-form');
        if (!form) return;

        const formData = new FormData(form);
        formData.append('auto_save', '1');

        fetch('/invoices/auto-save', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('تم الحفظ التلقائي', 'success', 2000);
                this.hasUnsavedChanges = false;
            }
        })
        .catch(error => {
            console.warn('فشل الحفظ التلقائي:', error);
        });
    }

    /**
     * تدمير المثيل وتنظيف الذاكرة
     */
    destroy() {
        // إزالة المستمعين
        document.removeEventListener('input', this.handleFieldChange);
        document.removeEventListener('change', this.handleFieldChange);
        document.removeEventListener('click', this.handleClick);
        document.removeEventListener('keydown', this.handleKeyboard);

        // مسح المؤقتات
        if (this.calculateTimer) {
            clearTimeout(this.calculateTimer);
        }

        this.isInitialized = false;
        console.log('تم تدمير مثيل حاسبة الفواتير');
    }
}

// إنشاء مثيل عام للاستخدام
let invoiceCalculator = null;

// تهيئة النظام عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    invoiceCalculator = new InvoiceCalculator({
        currency: 'ر.س',
        locale: 'ar',
        calculateDelay: 200,
        autoSave: true
    });

    invoiceCalculator.init();

    /* والمثيل نفسه على `window`.
     *
     * `let invoiceCalculator` في أعلى الملف يصنع رابطاً في نطاق السكربت
     * **لا خاصّيةً على `window`**. فكل شيفرةٍ تحرس نفسها بـ
     * `if (window.invoiceCalculator) { … }` — وهي حراسةٌ صحيحة لأن
     * السكربت قد لا يكون محمَّلاً في تلك الشاشة — كانت تقرأ `undefined`
     * وتتخطّى نفسها بصمت. وأثرُها المرئيّ: «نسخ الفاتورة» يملأ الحقول
     * ولا تُعاد الإجماليات، فيُرى خصمُ الفاتورة المنسوخة وشحنُها في
     * الحقول ولا يظهران في المجموع.
     */
    window.invoiceCalculator = invoiceCalculator;

    // جعل الوظائف متاحة عالمياً للاستخدام من HTML
    window.saveAsDraft = () => invoiceCalculator.saveAsDraft();
    window.clearAllItems = () => invoiceCalculator.clearAllItems();
    window.showQuickPreview = () => invoiceCalculator.showQuickPreview();
    window.showSupplierBalance = (element) => invoiceCalculator.showSupplierBalance(element);
    window.updateHiddenInput = (element) => invoiceCalculator.updateTaxHiddenField(element);

    // إضافة معالجات للخيارات المتقدمة - مُصحح
    setupAdvancedOptions();
});

// تنظيف عند مغادرة الصفحة
window.addEventListener('beforeunload', function() {
    if (invoiceCalculator) {
        invoiceCalculator.destroy();
    }
});

/**
 * قفل «الفاتورة مدفوعة».
 *
 * ─────────────────────────────────────────────────────────────
 * لماذا دالّةٌ واحدة؟
 * ─────────────────────────────────────────────────────────────
 *
 * كان للمربّع نفسِه (`#full-payment-check`) مستمعان في هذا الملف:
 * واحدٌ في `bindPaymentEvents()` يملأ المبلغ ولا يقفله، وآخرُ في
 * `setupAdvancedOptions()` يقفله. وكلاهما يبدأ بـ
 * `document.querySelector('.full-payment-fields').style.display` —
 * وذلك الصنف غير موجودٍ في أيٍّ من الشاشات، فيرمي الأوّل `TypeError`
 * قبل أن يبلغ سطر القفل.
 *
 * فالنتيجة: المبلغ يُملأ ويبقى قابلاً للكتابة، فيغيّره المستخدم ويظنّ
 * فاتورته مدفوعةً جزئياً — بينما الخادم يتجاهل ما كتبه ويقيّدها مدفوعةً
 * بالكامل: `InvoicesController::store()` يُعيد حساب `advance_payment`
 * متى وصلته `is_paid`.
 *
 * وهنا حالةٌ واحدة تُطبَّق من موضعٍ واحد: عند التغيير، وعند تغيّر
 * الإجمالي، وعند فتح الشاشة — فإعداد «الفاتورة مدفوعة افتراضياً» يصل
 * مربّعُه مؤشَّراً بلا حدث `change` يتبعه.
 *
 * @param {boolean} [recalculate=true] إعادة حساب الإجماليات بعد التطبيق
 */
function applyFullPaymentState(recalculate) {
    const fullPaymentCheck = document.getElementById('full-payment-check');

    if (!fullPaymentCheck) {
        return;
    }

    const paid = fullPaymentCheck.checked;
    const paidAmountInput = document.getElementById('paid-amount-input');
    const advanceCheck = document.querySelector('.advance-payment-toggle');

    /* مُنتقي «مبلغ/نسبة» بجانب الحقل يُقفل معه: تركُه مفتوحاً يسمح بقلب
       المبلغ المقفول إلى «نسبة»، فيصير الإجمالي كلُّه نسبةً مئوية. */
    const paidAmountType = paidAmountInput
        ? paidAmountInput.closest('.input-group') &&
          paidAmountInput.closest('.input-group').querySelector('select')
        : null;

    if (paidAmountInput) {
        if (paid) {
            const grandTotalText = document.getElementById('grand-total')
                ? document.getElementById('grand-total').textContent
                : '0';

            const grandTotal = parseFloat(String(grandTotalText).replace(/[^\d.-]/g, '')) || 0;

            paidAmountInput.value = grandTotal.toFixed(2);
        } else if (paidAmountInput.readOnly) {
            // لا يُصفَّر إلّا ما كتبه القفلُ نفسه: مبلغٌ كتبه المستخدم قبل
            // أن يؤشّر «مدفوعة» يعود كما تركه
            paidAmountInput.value = '0';
        }

        paidAmountInput.readOnly = paid;
        paidAmountInput.classList.toggle('is-locked', paid);
    }

    if (paidAmountType) {
        if (paid) {
            paidAmountType.value = 'amount';
        }

        /* `disabled` يُسقط الحقل من الإرسال فلا يعرف الخادم نوع المبلغ.
           القفل بلا إسقاط: منع المؤشّر ومفتاح التنقّل. */
        paidAmountType.style.pointerEvents = paid ? 'none' : '';
        paidAmountType.tabIndex = paid ? -1 : 0;
        paidAmountType.classList.toggle('is-locked', paid);
    }

    /* الدفعة المقدّمة والدفع الكامل نقيضان: دفعةٌ مقدّمة على فاتورةٍ
       مدفوعةٍ بالكامل لا معنى لها */
    if (advanceCheck) {
        if (paid) {
            advanceCheck.checked = false;

            const advanceInput = document.getElementById('paid-amount-input-extra')
                || document.querySelector("[name='paid_amount']");

            if (advanceInput) {
                advanceInput.value = '0';
            }
        }

        advanceCheck.disabled = paid;
    }

    const extraCard = document.getElementById('section-deposit-extra');
    if (extraCard && paid) {
        extraCard.style.display = 'none';
    }

    // التلميح الذي يشرح سببَ القفل — يظهر مع القفل ويختفي معه
    const lockHint = document.getElementById('full-payment-lock-hint');
    if (lockHint) {
        lockHint.classList.toggle('d-none', !paid);
    }

    if (recalculate !== false && typeof invoiceCalculator !== 'undefined'
        && invoiceCalculator && invoiceCalculator.calculateTotals) {
        invoiceCalculator.calculateTotals();
    }
}

/**
 * إعداد الخيارات المتقدمة - مُصحح
 */
function setupAdvancedOptions() {
    const fullPaymentCheck = document.getElementById('full-payment-check');
    const advancePaymentCheck = document.querySelector('.advance-payment-toggle');

    if (fullPaymentCheck) {
        fullPaymentCheck.addEventListener('change', function () {
            applyFullPaymentState();
        });
    }

    if (advancePaymentCheck) {
        advancePaymentCheck.addEventListener('change', function () {
            // الدفع الكامل يُقفل ما دامت الدفعة المقدّمة مؤشَّرة
            if (fullPaymentCheck) {
                if (this.checked) {
                    fullPaymentCheck.checked = false;
                }

                fullPaymentCheck.disabled = this.checked;
            }

            if (!this.checked) {
                const advanceInput = document.getElementById('paid-amount-input-extra')
                    || document.querySelector("[name='paid_amount']");

                if (advanceInput) {
                    advanceInput.value = '0';
                }
            }

            applyFullPaymentState();
        });
    }

    /* الإجمالي يتغيّر مع كلّ بندٍ يُضاف، والمبلغ المقفول يتبعه. بدونها
       يبقى المدفوع على إجمالي أوّل بند. */
    const grandTotalElement = document.getElementById('grand-total');

    if (grandTotalElement) {
        new MutationObserver(function () {
            if (fullPaymentCheck && fullPaymentCheck.checked) {
                // بلا إعادة حساب: الحسابُ هو من غيّر الإجمالي، والنداءُ
                // منه يدور بلا نهاية
                applyFullPaymentState(false);
            }
        }).observe(grandTotalElement, { childList: true, characterData: true, subtree: true });
    }

    // الحالة الأولى — «الفاتورة مدفوعة افتراضياً» يصل مربّعُه مؤشَّراً
    // بلا حدث `change` يتبعه
    applyFullPaymentState(false);
}

// دوال مساعدة إضافية

/**
 * دالة مساعدة لإظهار رصيد المورد - محدثة
 */
function showSupplierBalance(selectElement) {
    if (invoiceCalculator && invoiceCalculator.showSupplierBalance) {
        invoiceCalculator.showSupplierBalance(selectElement);
    } else {
        // نسخة احتياطية
        const balanceCard = document.getElementById('supplierBalanceCard');

        if (!selectElement || !selectElement.value || selectElement.value === '') {
            if (balanceCard) {
                balanceCard.style.display = 'none';
            }
            return;
        }

        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const supplierName = selectedOption.text;
        const supplierBalance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;

        const nameElement = document.getElementById('supplierName');
        const balanceElement = document.getElementById('supplierBalance');
        const statusElement = document.getElementById('balanceStatus');

        if (nameElement) nameElement.textContent = supplierName;
        if (balanceElement) balanceElement.textContent = Math.abs(supplierBalance).toFixed(2);

        if (statusElement && balanceElement) {
            if (supplierBalance > 0) {
                statusElement.textContent = 'دائن';
                statusElement.style.color = '#4CAF50';
                balanceElement.style.color = '#4CAF50';
            } else if (supplierBalance < 0) {
                statusElement.textContent = 'مدين';
                statusElement.style.color = '#f44336';
                balanceElement.style.color = '#f44336';
            } else {
                statusElement.textContent = 'متوازن';
                statusElement.style.color = '#FFC107';
                balanceElement.style.color = '#FFC107';
            }
        }

        if (balanceCard) {
            balanceCard.style.display = 'block';
        }
    }
}

/**
 * دالة مسح جميع البنود - محدثة
 */
function clearAllItems() {
    if (invoiceCalculator && invoiceCalculator.clearAllItems) {
        invoiceCalculator.clearAllItems();
    } else {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'تأكيد المسح',
                text: 'هل أنت متأكد من مسح جميع البنود؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، امسح الكل',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    performClearAllItems();
                }
            });
        } else {
            if (confirm('هل أنت متأكد من مسح جميع البنود؟')) {
                performClearAllItems();
            }
        }
    }
}

function performClearAllItems() {
    const itemRows = document.querySelectorAll('.item-row');
    itemRows.forEach((row, index) => {
        if (index === 0) {
            // مسح بيانات الصف الأول فقط
            row.querySelectorAll('input, select').forEach(input => {
                if (input.type !== 'hidden') {
                    if (input.classList.contains('quantity')) {
                        input.value = '1';
                    } else if (input.type === 'number') {
                        input.value = '0';
                    } else {
                        input.value = '';
                    }
                }
            });
        } else {
            // حذف باقي الصفوف
            row.remove();
        }
    });

    // إعادة حساب الإجماليات
    if (invoiceCalculator && invoiceCalculator.calculateTotals) {
        invoiceCalculator.calculateTotals();
    }

    // إظهار رسالة نجاح
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'تم المسح!',
            text: 'تم مسح جميع البنود بنجاح',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    }
}

/**
 * دالة معاينة سريعة - محدثة
 */
function showQuickPreview() {
    if (invoiceCalculator && invoiceCalculator.showQuickPreview) {
        invoiceCalculator.showQuickPreview();
    } else {
        const supplierSelect = document.getElementById('clientSelect');
        const supplierName = supplierSelect ? supplierSelect.options[supplierSelect.selectedIndex].text : 'غير محدد';

        const grandTotal = document.getElementById('grand-total');
        const totalAmount = grandTotal ? grandTotal.textContent : '0.00';

        const itemRows = document.querySelectorAll('.item-row');
        let itemsCount = 0;
        itemRows.forEach(row => {
            const productSelect = row.querySelector('.product-select');
            if (productSelect && productSelect.value) {
                itemsCount++;
            }
        });

        const advancePayment = document.getElementById('advance-payment');
        const advanceValue = advancePayment ? advancePayment.textContent : '0.00';

        const paidAmount = document.getElementById('paid-amount-display');
        const paidValue = paidAmount ? paidAmount.textContent : '0.00';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '📋 معاينة سريعة للفاتورة',
                html: `
                    <div class="text-right" style="direction: rtl;">
                        <p><strong>المورد:</strong> ${supplierName}</p>
                        <p><strong>عدد البنود:</strong> ${itemsCount}</p>
                        <p><strong>المبلغ الإجمالي:</strong> ${totalAmount} ر.س</p>
                        <p><strong>الدفعة المقدمة:</strong> ${advanceValue} ر.س</p>
                        <p><strong>المبلغ المدفوع:</strong> ${paidValue} ر.س</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'حسناً',
                width: '500px'
            });
        } else {
            alert(`معاينة الفاتورة:\nالمورد: ${supplierName}\nعدد البنود: ${itemsCount}\nالمبلغ الإجمالي: ${totalAmount} ر.س\nالدفعة المقدمة: ${advanceValue} ر.س\nالمبلغ المدفوع: ${paidValue} ر.س`);
        }
    }
}

/**
 * دالة حفظ كمسودة - محدثة
 */
function saveAsDraft() {
    if (invoiceCalculator && invoiceCalculator.saveAsDraft) {
        invoiceCalculator.saveAsDraft();
    } else {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'حفظ كمسودة',
                text: 'هل تريد حفظ الفاتورة كمسودة؟',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'نعم، احفظ',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    const draftInput = document.createElement('input');
                    draftInput.type = 'hidden';
                    draftInput.name = 'is_draft';
                    draftInput.value = '1';
                    document.getElementById('invoice-form').appendChild(draftInput);
                    document.getElementById('invoice-form').submit();
                }
            });
        } else {
            if (confirm('هل تريد حفظ الفاتورة كمسودة؟')) {
                const draftInput = document.createElement('input');
                draftInput.type = 'hidden';
                draftInput.name = 'is_draft';
                draftInput.value = '1';
                document.getElementById('invoice-form').appendChild(draftInput);
                document.getElementById('invoice-form').submit();
            }
        }
    }
}

/**
 * دالة تحديث الحقول المخفية للضرائب
 */
function updateHiddenInput(selectElement) {
    if (invoiceCalculator && invoiceCalculator.updateTaxHiddenField) {
        invoiceCalculator.updateTaxHiddenField(selectElement);
    } else {
        const row = selectElement.closest('.item-row');
        const taxType = selectElement.getAttribute('data-target');
        const hiddenInput = row.querySelector(`input[name^="items"][name$="[${taxType}_id]"]`);

        if (hiddenInput) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            hiddenInput.value = selectedOption.getAttribute('data-id') || '';
        }
    }
}

// إضافة معالجات إضافية للتحقق من صحة النموذج
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('invoice-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // التحقق من المورد
            const supplierSelect = document.getElementById('clientSelect');
            if (!supplierSelect || !supplierSelect.value) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'خطأ!',
                        text: 'يرجى اختيار المورد',
                        icon: 'error',
                        confirmButtonText: 'حسناً'
                    });
                } else {
                    alert('يرجى اختيار المورد');
                }
                return false;
            }

            // التحقق من وجود منتجات
            const itemRows = document.querySelectorAll('.item-row');
            if (itemRows.length === 0) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'خطأ!',
                        text: 'يرجى إضافة منتج واحد على الأقل',
                        icon: 'error',
                        confirmButtonText: 'حسناً'
                    });
                } else {
                    alert('يرجى إضافة منتج واحد على الأقل');
                }
                return false;
            }

            // التحقق من وجود منتجات محددة
            let hasValidItems = false;
            itemRows.forEach(function(row) {
                const productSelect = row.querySelector('.product-select');
                const quantity = row.querySelector('.quantity');
                const price = row.querySelector('.price');

                // البند الحرّ أُسقط: كلّ بندٍ يقابله منتجٌ مختار
                const hasItem = !!(productSelect && productSelect.value);

                if (hasItem &&
                    quantity && parseFloat(quantity.value) > 0 &&
                    price && parseFloat(price.value) > 0) {
                    hasValidItems = true;
                }
            });

            if (!hasValidItems) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'خطأ!',
                        text: 'يرجى تحديد منتج واحد صحيح على الأقل مع كمية وسعر صحيحين',
                        icon: 'error',
                        confirmButtonText: 'حسناً'
                    });
                } else {
                    alert('يرجى تحديد منتج واحد صحيح على الأقل مع كمية وسعر صحيحين');
                }
                return false;
            }
        });
    }

    /*
     * «إضافة بند» و«حذف بند» لهما مالكٌ واحد: `handleClick()` في الصنف.
     *
     * كان هنا مستمعان يكرّران ما تفعله `handleClick()` حرفياً — أحدهما
     * على `#add-row` والآخر مفوَّضٌ على `.remove-row` — فكلّ نقرةٍ على
     * «إضافة بند» تنفّذ `addNewItemRow()` مرّتين فيظهر بندان، وكلّ نقرةٍ
     * على «حذف» بصفٍّ وحيد تفتح تنبيهين.
     *
     * كانا مبرَّرين يوم كان جسم `addNewRow()` معلَّقاً فترمي استدعاءَه
     * `handleClick()` خطأً؛ صار الجسم يعمل فبقيا تكراراً محضاً.
     */
});

/**
 * دالة إضافة صف جديد احتياطية
 */
function addNewItemRow() {
    const tableBody = document.querySelector('#items-table tbody');
    if (!tableBody) return;

    const existingRows = tableBody.querySelectorAll('.item-row');
    const firstRow = existingRows[0];

    if (!firstRow) return;

    /*
     * الفهرس التالي = أكبر فهرسٍ مستعمَل + 1، لا عددُ الصفوف.
     *
     * كان `existingRows.length`: احذف صفّاً من الوسط ثم أضف صفّاً، فيعود
     * العدّاد إلى فهرسٍ ما زال مستعمَلاً — فيصل إلى الخادم `items[2]`
     * مرّتين، ويحتفظ PHP بالأخير وحده. فيختفي بندٌ من الفاتورة بلا خطأ
     * ولا تحذير.
     */
    let nextIndex = 0;

    existingRows.forEach(row => {
        row.querySelectorAll('[name]').forEach(field => {
            const match = field.name.match(/\[(\d+)\]/);
            if (match) nextIndex = Math.max(nextIndex, parseInt(match[1], 10) + 1);
        });
    });

    const rowCount = nextIndex;

    const newRow = firstRow.cloneNode(true);

    // تحديث أسماء الحقول
    newRow.querySelectorAll('input, select').forEach(input => {
        if (input.name) {
            input.name = input.name.replace(/\[\d+\]/, `[${rowCount}]`);

            /* خصمُ البند حقولٌ مخفيّة الآن (لا عمودَ له في الشاشات)، والمخفيُّ
               لا يُصفَّر أدناه — فالصفُّ المُستنسَخ في المرتجع والإشعار الدائن
               كان سيرث خصمَ البند الأوّل المنقول من الفاتورة. البندُ الجديد
               بلا خصم. */
            if (input.classList.contains('discount-amount') || input.classList.contains('discount-percentage')) {
                input.value = '0';
            } else if (input.classList.contains('discount-type')) {
                input.value = 'amount';
            }

            // إعادة تعيين القيم
            if (input.type !== 'hidden') {
                if (input.classList.contains('quantity')) {
                    input.value = '1';
                } else if (input.type === 'number') {
                    input.value = '0';
                } else {
                    input.value = '';
                }
            }
        }
    });

    /* الوحدة تعود إلى حالتها الأولى في الصفّ الجديد.
       الاستنساخ ينسخ خياراتِ وحدات الصنف المختار في الصفّ الأوّل وحالةَ
       ظهورها، والصنفُ نفسُه يُفرَّغ — فيبقى في الصفّ الجديد مُنتقي وحداتٍ
       لصنفٍ لم يُختَر. وصار العمودُ ظاهراً بذاته، فالخلل يُرى. */
    const newUnitSelect = newRow.querySelector('.unit-select');
    if (newUnitSelect) {
        newUnitSelect.querySelectorAll('option').forEach((option, index) => {
            if (index > 0) option.remove();
        });
        newUnitSelect.style.display = 'none';
        newUnitSelect.disabled = true;
    }

    const newUnitInfo = newRow.querySelector('.unit-info');
    if (newUnitInfo) {
        newUnitInfo.textContent = '';
        newUnitInfo.style.display = 'none';
    }

    const newBaseUnitQuantity = newRow.querySelector('.base-unit-quantity');
    if (newBaseUnitQuantity) {
        newBaseUnitQuantity.textContent = '';
        newBaseUnitQuantity.style.display = 'none';
    }

    const newConversionFactor = newRow.querySelector('.conversion-factor');
    if (newConversionFactor) newConversionFactor.value = '1';

    // إعادة تعيين إجمالي الصف
    const rowTotal = newRow.querySelector('.row-total');
    if (rowTotal) {
        rowTotal.textContent = '0.00';
    }

    tableBody.appendChild(newRow);

    // إعادة حساب الإجماليات
    if (invoiceCalculator && invoiceCalculator.calculateTotals) {
        invoiceCalculator.calculateTotals();
    }
}