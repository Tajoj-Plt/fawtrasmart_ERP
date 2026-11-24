/**
 * نموذج مستند المبيعات — بناء البنود وحساب الإجماليات والتحقّق قبل الحفظ.
 *
 * كل النصوص وإعدادات العملة تُحقن من الخادم في `window.salesDocumentForm`
 * (انظر قالب إنشاء/تعديل عرض السعر)، فلا نصّ عربي ولا رمز عملة مكتوب هنا:
 * تبديل اللغة أو العملة من الإعدادات يسري على هذه الشاشة بلا تعديل.
 *
 * أرقام الشاشة مرآة لِما يحسبه `QuoteController::store` حرفياً، فالمستخدم
 * لا يرى إجمالياً على الشاشة يخالف ما يُحفظ في قاعدة البيانات.
 */
(function () {
    'use strict';

    const config = window.salesDocumentForm;

    if (!config) {
        return;
    }

    const currency = config.currency || {};
    const i18n = config.i18n || {};
    const taxRates = config.taxRates || {};
    const taxTypes = config.taxTypes || {};
    const products = config.products || {};

    const form = document.getElementById(config.formId || 'salesDocumentForm');
    const tableBody = document.getElementById('tableBody');
    const rowTemplate = document.getElementById('rowTemplate');

    if (!form || !tableBody || !rowTemplate) {
        return;
    }

    const hasSwal = typeof window.Swal !== 'undefined';
    const hasSelect2 = !!(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function');

    // يمنع اعتراض الإرسال مرّتين: بعد تأكيد المستخدم نُرسل النموذج برمجياً
    // فيمرّ من هنا مرّة ثانية، ولولا هذه الراية لأعاد فتح نافذة التأكيد
    let allowSubmit = false;

    // ==================== أدوات ====================

    /** تنسيق مبلغ بنفس قواعد العملة المضبوطة في الإعدادات */
    function money(value) {
        const amount = Number.isFinite(value) ? value : 0;
        const decimals = Number.isFinite(currency.decimals) ? currency.decimals : 2;
        const fixed = Math.abs(amount).toFixed(decimals);
        const parts = fixed.split('.');

        const integer = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousands || '');
        const fraction = parts[1] ? (currency.decimal || '.') + parts[1] : '';

        let out = integer + fraction;
        out = currency.symbolFirst ? currency.symbol + ' ' + out : out + ' ' + currency.symbol;

        return amount < 0 ? '-' + out : out;
    }

    function num(input) {
        const value = parseFloat(input && input.value);

        return Number.isFinite(value) ? value : 0;
    }

    function field(row, name) {
        return row.querySelector('[data-field="' + name + '"]');
    }

    function byName(name) {
        return document.querySelector('[name="' + name + '"]');
    }

    function visibleRows() {
        return Array.prototype.slice.call(tableBody.querySelectorAll('tr.sl-row'));
    }

    /**
     * الرسائل تُعرض بـ SweetAlert2 حين يكون محمَّلاً، وبنوافذ المتصفّح حين
     * لا يكون: الشاشة تبقى صالحة للعمل ولو غاب أي اعتماد اختياري.
     *
     * لا يوجد مفتاح عنوان مستقلّ للتحذيرات، فـ `incomplete` هو العنوان
     * العامّ لكل ما يوقف المستخدم قبل الحفظ.
     */
    function alertBox(text, icon) {
        if (hasSwal) {
            window.Swal.fire({
                title: i18n.incomplete,
                text: text,
                icon: icon || 'error',
                confirmButtonText: i18n.confirm_yes,
            });
        } else {
            window.alert(text);
        }
    }

    function confirmBox(title, text, onConfirm) {
        if (!hasSwal) {
            if (window.confirm(text || title)) {
                onConfirm();
            }
            return;
        }

        window.Swal.fire({
            title: title,
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: i18n.confirm_yes,
            cancelButtonText: i18n.confirm_cancel,
        }).then(function (result) {
            if (result.isConfirmed) {
                onConfirm();
            }
        });
    }

    /**
     * رسائل «صنف مفقود» و«كمّية مفقودة» تحمل رقم الصف من الخادم بعلامة
     * `:row` — علامة لارافيل نفسها التي تحملها رسالة الترجمة؛ الاستبدال
     * بلا ضرر إن خلت الرسالة منها.
     */
    function withRow(message, index) {
        return String(message || '').replace(':row', index + 1);
    }

    // ==================== الصفوف ====================

    /**
     * إعادة الترقيم وأسماء الحقول.
     *
     * الأسماء تُبنى من موضع الصف الحالي لا من عدّاد متزايد، فحذف صف من
     * الوسط لا يترك فجوة في `items[]` تُربك التحقّق في الخادم.
     */
    function reindex() {
        visibleRows().forEach(function (row, index) {
            const label = row.querySelector('.sl-row__index');
            if (label) {
                label.textContent = index + 1;
            }

            row.querySelectorAll('.sl-input').forEach(function (input) {
                input.name = 'items[' + index + '][' + input.dataset.field + ']';
            });
        });
    }

    /** نسبة الضريبة المختارة في الصف — الخريطة تأتي من «إعدادات الضريبة» */
    function taxRate(row, name) {
        const select = field(row, name);
        const id = select && select.value;

        if (!id) {
            return 0;
        }

        const rate = parseFloat(taxRates[id]);

        return Number.isFinite(rate) ? rate : 0;
    }

    /** معرّف الضريبة المختارة في الصف، أو سلسلةً فارغة */
    function taxId(row, name) {
        const select = field(row, name);

        return (select && select.value) || '';
    }

    /**
     * مبلغ ضريبة على وعاء — بنوعها المضبوط في «إعدادات الضريبة».
     *
     * «متضمَّنة» تعني أن السعر يحتويها، فتُستخرَج منه ولا تُضاف فوقه:
     * ٢٠٠٠ بنسبة ١٥٪ متضمَّنة ⇒ ضريبة ٢٦٠٫٨٧ وإجمالي ٢٠٠٠ لا ٢٣٠٠. وهي
     * معادلة `DocumentTaxCalculator::amount()` في الخادم حرفياً، وكانت
     * الشاشة تضيف الضريبة دائماً فيخالف المعروضُ المحفوظ.
     */
    function taxAmount(base, rate, included) {
        if (base <= 0 || rate <= 0) {
            return 0;
        }

        return included ? base - base / (1 + rate / 100) : base * rate / 100;
    }

    /**
     * حسمُ «عروض الحسم» على صفٍّ واحد.
     *
     * نظيرُ `App\Services\Sales\OfferDiscountService::lineDiscount()`:
     * الأكبرُ حسماً يفوز، والمبلغ الثابت لكلّ وحدة في «حسم على البند»
     * ومرّةً في «اشترِ كميةً»، والحسمُ لا يتجاوز قيمة البند.
     *
     * العروضُ تصل من `sales::partials.discount-offers`، وتصفيةُ العميل هنا
     * لأنّه يتبدّل في الشاشة بعد تحميلها.
     */
    function offerDiscount(row, quantity, base) {
        const offers = window.DISCOUNT_OFFERS;

        if (!Array.isArray(offers) || offers.length === 0 || base <= 0 || quantity <= 0) {
            return 0;
        }

        const productField = field(row, 'product_id');
        const productId = parseInt((productField && productField.value) || 0, 10);

        if (!productId) {
            return 0;
        }

        const clientField = byName('client_id');
        const clientId = parseInt((clientField && clientField.value) || 0, 10);

        let best = 0;

        offers.forEach(function (offer) {
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
            const type = parseInt(offer.type, 10);

            if (type === 2 && quantity < threshold) {
                return;
            }

            let amount;

            if (parseInt(offer.discount_type, 10) === 2) {
                // النسبة لا تُضاعَف: ٣٠٪ تبقى ٣٠٪ مهما بلغت الكمّية
                amount = base * value / 100;
            } else if (type === 1) {
                amount = value * quantity;                              // لكلّ وحدة
            } else {
                amount = value * Math.floor(quantity / threshold);      // لكلّ مضاعف
            }

            amount = Math.min(amount, base);

            if (amount > best) {
                best = amount;
            }
        });

        return Math.round(best * 100) / 100;
    }

    /**
     * حسابات صفّ واحد بترتيب الخادم نفسه.
     *
     * الضريبة على الوعاء **بعد** خصم البند — وهو ما يفعله
     * `QuoteController::calculateTotals()` وما يفعله التحويل إلى فاتورة.
     * وكان الخادم يحسبها قبل الخصم في `store()` وبعده في التحويل، فيخرج
     * العرض بضريبةٍ وفاتورتُه بأخرى.
     *
     * والخصم مقصورٌ على قيمة البند: خصمٌ يفوقها يُظهر إجمالياً سالباً في
     * الشاشة بينما يقصّه الخادم — فيختلف المعروض عن المحفوظ.
     */
    function rowTotals(row) {
        const quantity = num(field(row, 'quantity'));
        const price = num(field(row, 'unit_price'));
        const rawDiscount = num(field(row, 'discount'));
        const discountField = field(row, 'discount_type');
        const isPercent = discountField && discountField.value === 'percentage';

        const base = quantity * price;
        let discount = Math.min(isPercent ? base * rawDiscount / 100 : rawDiscount, base);

        /* ما كتبه المستخدم يتقدّم، وإلّا فحسمُ «عروض الحسم» المطابق —
           `QuoteController::calculateTotals()` يفعل هذا عند الحفظ، فلو
           جهلته الشاشةُ لعرضت إجمالياً أكبر ممّا يُحفَظ. */
        if (discount <= 0) {
            discount = Math.min(offerDiscount(row, quantity, base), base);
        }

        const taxBase = base - discount;

        let tax = 0;
        let includedTax = 0;

        ['tax_1_id', 'tax_2_id'].forEach(function (slot) {
            const id = taxId(row, slot);

            if (!id) {
                return;
            }

            const included = taxTypes[id] === 'included';
            const amount = taxAmount(taxBase, taxRate(row, slot), included);

            tax += amount;

            if (included) {
                includedTax += amount;
            }
        });

        return {
            base: base,
            discount: discount,
            tax: tax,
            // الجزء المتضمَّن داخل السعر — يُطرح من المجموع فلا يُحسب مرّتين
            includedTax: includedTax,
            total: base - discount,
        };
    }

    /**
     * قيم قسم «الشحن والخصم والضريبة» على مستوى المستند.
     *
     * الحقول تُقرأ بالاسم لا بالمعرّف لأن القالب يعيد استخدامها في شاشات
     * أخرى من الوحدة بمعرّفات مختلفة، والغائب منها يُعامَل صفراً.
     */
    /**
     * نسبة الضريبة التي يُحمَّل بها الشحن: المختارة في «تبويب الشحن»
     * أوّلاً، فإن لم تُختَر فأوّل ضريبةٍ على بنود المستند.
     *
     * وهو ترتيب `DocumentTaxCalculator::shippingTax()` في الخادم حرفياً.
     * وضريبة الشحن **مضافةٌ** فوق تكلفته دائماً ولو كان نوع الضريبة
     * «متضمَّنة»: التكلفة تُكتب بلا ضريبة.
     */
    function shippingTaxRate() {
        const select = byName('shipping_tax_id');

        if (select && select.value) {
            const rate = parseFloat(taxRates[select.value]);

            return Number.isFinite(rate) ? rate : 0;
        }

        /* المُنتقي موجودٌ وتُرك على «بلا ضريبة» ⇒ لا ضريبة على الشحن —
           انظر `DocumentTaxCalculator::shippingTaxIds()`. الوراثةُ من
           ضريبة البنود تبقى للشاشات التي لا مُنتقيَ فيها. */
        if (select) {
            return 0;
        }

        const rows = visibleRows();

        for (const row of rows) {
            for (const slot of ['tax_1_id', 'tax_2_id']) {
                if (taxId(row, slot)) {
                    return taxRate(row, slot);
                }
            }
        }

        return 0;
    }

    function settlement(subtotal, lineDiscount) {
        const rawDiscount = num(byName('discount_amount'));
        const typeField = byName('discount_type');
        const isPercent = !!(typeField && typeField.value === 'percentage');

        /* صافي الأصناف بعد خصومات البنود — كان مُستعمَلاً أدناه بلا تعريف،
           فيرمي `recalculate()` عند أوّل صفّ ويقف السكربت كلُّه: لا إجماليات،
           ولا زرّ إضافة، ويبقى select2 مفتوحاً لأنّ حدثَ اختياره يرمي. */
        const netItems = Math.max(0, subtotal - lineDiscount);

        // النسبة على صافي الأصناف كما يحسبها `QuoteController` عند الحفظ
        const discount = isPercent ? netItems * rawDiscount / 100 : rawDiscount;

        /* كوبونٌ نوعُه «شحن مجانيّ» يُصفّر الشحن **وضريبته معاً** — وهو ما
           يفعله الخادم قبل حساب ضريبة الشحن لا بعده. ولو طُرح خصماً بقيمة
           الشحن لبقيت ضريبةُ شحنٍ أُعفي منه في الإجمالي وفي القيد. */
        const coupon = window.SALES_COUPON || {};
        const shipping = coupon.free_shipping ? 0 : num(byName('shipping_cost'));

        return {
            shipping: shipping,
            // صافي الأصناف — عليه يُوزَّع الخصم العامّ في وعاء الضريبة
            netItems: netItems,
            // خصمُ الكوبون كما ردّه الخادم — لا يُحسب في المتصفّح
            couponDiscount: Math.min(
                Math.max(0, parseFloat(coupon.discount) || 0),
                Math.max(0, netItems - Math.min(discount, netItems))
            ),
            // لا يتجاوز ما بقي بعد خصوم البنود — كما يقصّه الخادم
            discount: Math.min(discount, netItems),
            /* ضريبة الشحن يحسبها الخادم من الضريبة المختارة، فتُحسب هنا
               بالمعادلة نفسها. كانت تُقرأ مبلغاً يكتبه المستخدم في حقلٍ
               حرّ — رقمٌ لا يقابل نسبةً مضبوطة، ويخالف ما تحسبه الفاتورة
               بعد تحويل العرض. */
            shippingTax: shipping > 0 ? shipping * shippingTaxRate() / 100 : 0,
            // 1 = ضريبة مضافة، 2 = صفرية، 3 = معفاة
            type: parseInt((byName('tax_type') || {}).value, 10) || 1,
        };
    }

    function recalculate() {
        let subtotal = 0;
        let lineDiscount = 0;
        let lineTax = 0;
        let includedTax = 0;

        visibleRows().forEach(function (row) {
            const line = rowTotals(row);

            // المجموع الفرعي مجموع الأوعية قبل خصومات البنود — هكذا يحفظه
            // الخادم في `subtotal`، والخصومات تظهر مجمَّعة في سطرها الخاصّ
            subtotal += line.base;
            lineDiscount += line.discount;
            lineTax += line.tax;
            includedTax += line.includedTax;

            const totalCell = row.querySelector('.sl-line-total');
            if (totalCell) {
                totalCell.textContent = money(line.total);
            }
        });

        /* الضريبة المتضمَّنة داخل الأسعار تُطرح من المجموع قبل كل ما بعده
           — الخصم العامّ والإجمالي — وإلّا حُسبت مرّتين: مرّةً داخل السعر
           ومرّةً حين تُضاف `tax` فوقه. وهو ترتيب `calculateTotals()`. */
        subtotal -= includedTax;

        const settle = settlement(subtotal, lineDiscount);

        /* خصمُ الكوبون يدخل سطرَ الخصم نفسَه — كما يجمعه الخادم في
           `total_discount` ويقيّده في «الخصم المسموح به». */
        const discount = lineDiscount + settle.discount + settle.couponDiscount;

        /* الصفرية والمعفاة لا ضريبة عليهما — لا على البنود ولا على الشحن:
           عمود الضريبة في الصفوف يبقى معروضاً كما اختاره المستخدم، ولكنّ
           الإجمالي يتجاهله. وهو ما يفعله `calculateTotals()`. */
        /* الخصمُ العامّ (وخصمُ الكوبون) يُنقص وعاء الضريبة — يُوزَّع على
           البنود بنسبة صافي كلٍّ منها كما يفعل الخادم، فلا تعرض الشاشةُ
           ضريبةً غير التي تُحفَظ. */
        const generalDiscount = settle.discount + settle.couponDiscount;
        const taxRatio = settle.netItems > 0
            ? Math.max(0, settle.netItems - generalDiscount) / settle.netItems
            : 0;

        const tax = settle.type === 1 ? (lineTax * taxRatio) + settle.shippingTax : 0;

        const totals = {
            subtotal: subtotal,
            discount: discount,
            shipping: settle.shipping,
            tax: tax,
            grand: subtotal - discount + settle.shipping + tax,
        };

        Object.keys(totals).forEach(function (key) {
            const target = document.querySelector('[data-total="' + key + '"]');

            if (target) {
                target.textContent = money(totals[key]);
            }

            // الأسطر المشروطة: `data-total-row` على السطر يعني «لا تعرضني
            // إلا إذا كان لقيمتي وجود». المجموع والإجمالي بلا الوسم فيثبتان.
            // والحدّ 0.005 لأن العملة تُقرَّب لخانتين
            const row = document.querySelector('[data-total-row="' + key + '"]');

            if (row) {
                row.classList.toggle('d-none', Math.abs(totals[key]) < 0.005);
            }
        });
    }

    /** select2 حين يكون محمَّلاً في القالب — يُظهر المختار كوسم واضح */
    function initSelect2(scope) {
        if (!hasSelect2) {
            return;
        }

        window.jQuery(scope).find('.sl-select2').select2({ width: '100%' });
    }

    /**
     * select2 يُطلق حدث jQuery وحده، ولا تلتقطه المستمعات الأصلية
     * المربوطة بـ `addEventListener`، فنربط نسخة jQuery إلى جانبها.
     * التكرار غير ضارّ: الحساب لا يغيّر حالة، وملء السعر لا يكتب فوق قيمة.
     */
    function bridgeSelect2(element, handler) {
        if (!hasSelect2 || !element) {
            return;
        }

        window.jQuery(element).on('change', handler);
    }

    /**
     * وحداتُ الصنف في صفّه.
     *
     * تُبنى خياراتُ المُنتقي من `products[id].units` — وهي الخريطةُ نفسُها
     * التي تقرأها شاشتا الفاتورة والإشعار الدائن (`ProductUnits::map()`)،
     * فلا يختلف عرضُ السعر عن الفاتورة التي يتحوّل إليها.
     *
     * وصنفٌ بلا وحدات لا مُنتقيَ له: يبقى مخفيّاً ومعاملُه واحداً، فلا
     * يُسأل المستخدم عن اختيارٍ لا خيار فيه.
     *
     * @param {HTMLElement} row
     * @param {string|null} preferred وحدةٌ محفوظة تُستعاد عند فتح التعديل
     */
    function syncUnitOptions(row, preferred) {
        const unitSelect = field(row, 'product_unit_id');

        if (!unitSelect) {
            return;
        }

        const productSelect = field(row, 'product_id');
        const factorInput = field(row, 'conversion_factor');
        const nameInput = field(row, 'unit_name');
        const priceInput = field(row, 'unit_price');

        const baseLabel = i18n.base_unit || '—';

        unitSelect.innerHTML = '';
        unitSelect.appendChild(new Option(baseLabel, ''));

        const product = productSelect ? products[productSelect.value] : null;

        if (!product || !product.has_units || !product.units || !product.units.length) {
            unitSelect.style.display = 'none';
            unitSelect.disabled = true;

            if (factorInput) factorInput.value = '1';
            if (nameInput) nameInput.value = '';

            return;
        }

        let selected = null;

        product.units.forEach(function (unit) {
            const option = new Option(
                unit.name
                    + (unit.is_base_unit ? ' (' + baseLabel + ')' : '')
                    + (Number(unit.conversion_factor) !== 1 ? ' × ' + unit.conversion_factor : ''),
                unit.id || ('category_' + unit.category_unit_id)
            );

            option.setAttribute('data-price', unit.sale_price);
            option.setAttribute('data-factor', unit.conversion_factor);
            option.setAttribute('data-name', unit.name);

            unitSelect.appendChild(option);

            /* الوحدةُ المحفوظة تغلب الافتراضية: فتحُ عرضٍ للتعديل يجب أن
               يُظهر ما حُفظ فيه لا ما يفضّله الصنف اليوم */
            if (preferred && String(preferred) === option.value) {
                selected = option;
            } else if (!preferred && !selected && unit.is_default) {
                selected = option;
            }
        });

        unitSelect.style.display = '';
        unitSelect.disabled = false;

        if (selected) {
            selected.selected = true;
            applyUnit(row, selected, false);
        } else if (factorInput) {
            factorInput.value = '1';
        }
    }

    /**
     * أثرُ الوحدة على صفّها: معاملُها واسمُها، وسعرُها.
     *
     * @param {boolean} overwritePrice أيُدهَس سعرٌ مكتوب؟ نعم حين يختار
     *        المستخدمُ الوحدةَ بنفسه — الكرتون ليس بسعر الحبّة — ولا حين
     *        تُملأ الوحدةُ آلياً، فسعرٌ كتبه المستخدم أو حُفظ في العرض يبقى.
     */
    function applyUnit(row, option, overwritePrice) {
        const factorInput = field(row, 'conversion_factor');
        const nameInput = field(row, 'unit_name');
        const priceInput = field(row, 'unit_price');

        const factor = option ? parseFloat(option.getAttribute('data-factor')) : NaN;
        const price = option ? parseFloat(option.getAttribute('data-price')) : NaN;

        if (factorInput) {
            factorInput.value = Number.isFinite(factor) && factor > 0 ? factor : 1;
        }

        if (nameInput) {
            nameInput.value = option ? (option.getAttribute('data-name') || '') : '';
        }

        if (priceInput && Number.isFinite(price) && price > 0) {
            if (overwritePrice || !priceInput.value || num(priceInput) === 0) {
                priceInput.value = price.toFixed(2);
            }
        }
    }

    function removeRow(row) {
        if (visibleRows().length <= 1) {
            alertBox(i18n.last_row, 'warning');
            return;
        }

        confirmBox(i18n.confirm_discard, i18n.remove_confirm, function () {
            row.remove();
            reindex();
            recalculate();
        });
    }

    function addRow(values) {
        const row = rowTemplate.content.firstElementChild.cloneNode(true);

        if (values) {
            Object.keys(values).forEach(function (key) {
                const input = field(row, key);

                if (input && values[key] !== null && values[key] !== undefined) {
                    input.value = values[key];
                }
            });
        }

        /* الوحدةُ بعد القيم لا معها: خياراتُ المُنتقي تُبنى من الصنف،
           والصنفُ لا يُعرَف قبل أن تُطبَّق القيم. فإسنادُ `product_unit_id`
           في الحلقة أعلاه يقع على مُنتقٍ لا خيار فيه إلّا الفراغ ويسقط
           صامتاً — ولذلك تُعاد المحفوظةُ هنا. */
        syncUnitOptions(row, values ? values.product_unit_id : null);

        const remove = row.querySelector('.sl-row__remove');
        if (remove) {
            remove.addEventListener('click', function () {
                removeRow(row);
            });
        }

        const productSelect = field(row, 'product_id');

        /**
         * اختيار صنف يملأ سعره ووصفه المسجَّلَين له، ولا يكتب فوق ما كتبه
         * المستخدم: سعر مكتوب أو وصف مكتوب يبقى كما هو حتى لو غُيِّر الصنف.
         */
        const onProductChange = function () {
            /* الوحدة أوّلاً ثمّ سعرُ الصنف: الوحدةُ الافتراضية لها سعرُها
               (`product_units.sale_price`)، وهو أولى بالصفّ من سعر الصنف
               الأساسي — وهو ما تفعله شاشة الفاتورة. ولو أتى بعده لبقي
               السعرُ سعرَ الحبّة والوحدةُ كرتوناً.

               وكِلا الملءَين مشروطٌ بحقلٍ فارغٍ أو صفر، فما كتبه المستخدم
               لا يُدهَس بتغيير الصنف. */
            syncUnitOptions(row, null);

            const product = products[productSelect.value];

            if (product) {
                const price = field(row, 'unit_price');
                const description = field(row, 'description');

                if (price && num(price) === 0) {
                    price.value = product.price;
                }

                if (description && !description.value.trim() && product.description) {
                    description.value = product.description;
                }
            }

            recalculate();
        };

        if (productSelect) {
            productSelect.addEventListener('change', onProductChange);
        }

        const unitSelect = field(row, 'product_unit_id');

        if (unitSelect) {
            unitSelect.addEventListener('change', function () {
                // اختيارٌ صريح للوحدة يغلب السعرَ المكتوب: الكرتون ليس
                // بسعر الحبّة، وتركُ السعر على حاله يُخرج بنداً بسعرٍ
                // لا يخصّ وحدته
                applyUnit(row, unitSelect.options[unitSelect.selectedIndex], true);
                recalculate();
            });
        }

        row.querySelectorAll('.sl-calc').forEach(function (input) {
            input.addEventListener('input', recalculate);
            input.addEventListener('change', recalculate);
        });

        tableBody.appendChild(row);

        // select2 يُهيَّأ بعد الإلحاق بالمستند لا قبله، فهو يقيس عرض العنصر
        initSelect2(row);
        bridgeSelect2(productSelect, onProductChange);
        row.querySelectorAll('.sl-calc.sl-select2').forEach(function (input) {
            bridgeSelect2(input, recalculate);
        });

        reindex();
        recalculate();

        return row;
    }

    // ==================== التحقّق ====================

    function validate() {
        // العميل والتاريخ حقلان في رأس المستند لا في الجدول، ومعرّفاهما
        // يأتيان من الإعدادات لأن شاشات الوحدة تسمّيهما بأسماء مختلفة
        const client = document.getElementById(config.clientFieldId || 'client_id');
        if (client && !client.value) {
            client.focus();
            alertBox(i18n.need_client, 'error');
            return false;
        }

        const date = document.getElementById(config.dateFieldId || 'quote_date');
        if (date && !date.value) {
            date.focus();
            alertBox(i18n.need_date, 'error');
            return false;
        }

        const rows = visibleRows();

        if (rows.length === 0) {
            alertBox(i18n.need_items, 'error');
            return false;
        }

        for (let index = 0; index < rows.length; index++) {
            const product = field(rows[index], 'product_id');
            const quantity = field(rows[index], 'quantity');

            if (product && !product.value) {
                product.focus();
                alertBox(withRow(i18n.need_product, index), 'error');
                return false;
            }

            if (quantity && (!quantity.value || num(quantity) <= 0)) {
                quantity.focus();
                alertBox(withRow(i18n.need_quantity, index), 'error');
                return false;
            }
        }

        return true;
    }

    // ==================== الربط ====================

    document.querySelectorAll('.sl-settle').forEach(function (input) {
        input.addEventListener('input', recalculate);
        input.addEventListener('change', recalculate);

        if (input.classList.contains('sl-select2')) {
            bridgeSelect2(input, recalculate);
        }
    });

    const addRowBtn = document.getElementById('addRowBtn');

    if (addRowBtn) {
        addRowBtn.addEventListener('click', function () {
            const row = addRow();
            const product = field(row, 'product_id');

            // التركيز على الصنف: هو أول ما يُملأ، وملؤه يجلب السعر والوصف
            if (product) {
                product.focus();
            }
        });
    }

    form.addEventListener('submit', function (event) {
        if (allowSubmit) {
            return;
        }

        event.preventDefault();

        if (!validate()) {
            return;
        }

        // بلا SweetAlert2 لا نافذة تأكيد ولا حالة حفظ، فيُرسل النموذج مباشرة
        if (!hasSwal) {
            allowSubmit = true;
            form.submit();
            return;
        }

        confirmBox(i18n.confirm_save, i18n.confirm_save_text, function () {
            window.Swal.fire({
                title: i18n.saving,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function () {
                    window.Swal.showLoading();
                },
            });

            // تعطيل زرّ الحفظ يمنع إرسال المستند مرّتين بنقرة متعجّلة
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            allowSubmit = true;
            form.submit();
        });
    });

    const cancelBtn = document.getElementById('cancelBtn');

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            confirmBox(i18n.confirm_discard, i18n.confirm_discard_text, function () {
                form.reset();

                // `reset` لا يحذف الصفوف المُضافة برمجياً، فنبدأ من جدول
                // فارغ ثم صفّ واحد كما تبدأ شاشة الإنشاء
                tableBody.innerHTML = '';
                addRow();

                const selected = document.getElementById('selectedFile');
                if (selected) {
                    selected.classList.add('d-none');
                }
            });
        });
    }

    // ==================== المرفقات ====================

    const fileInput = document.getElementById('attachments');
    const uploadArea = document.getElementById('uploadArea');
    const selectedFile = document.getElementById('selectedFile');

    if (fileInput && uploadArea) {
        uploadArea.addEventListener('click', function () {
            fileInput.click();
        });

        // المنطقة ليست زرّاً أصلياً، فنمنحها سلوك الزرّ بالمفاتيح
        uploadArea.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });

        fileInput.addEventListener('change', function () {
            if (!selectedFile) {
                return;
            }

            if (fileInput.files.length === 0) {
                selectedFile.classList.add('d-none');
                return;
            }

            const file = fileInput.files[0];
            selectedFile.textContent = file.name + ' (' + (file.size / 1048576).toFixed(2) + ' MB)';
            selectedFile.classList.remove('d-none');
        });
    }

    /* ==================== الكوبون ====================

       الخصمُ يحسبه الخادم وحده (`SalesCouponService`) — لا يُعاد بناء
       شروطه هنا: حدُّ الطلب وسقفُ الخصم وحدودُ الاستعمال والمنتجات
       المسموحة، كلُّها في مكانٍ واحد. وهذه الشاشةُ تعرض ما ردّه، فلا
       يختلف المعروضُ عن المحفوظ — وهو الدرسُ المستفاد من «عروض الحسم».
       ================================================================ */
    (function () {
        const button = document.getElementById('coupon-check');
        const input = document.getElementById('coupon_code');
        const feedback = document.getElementById('coupon-feedback');

        if (!button || !input || !config.couponCheckUrl) {
            return;
        }

        function report(message, ok) {
            if (!feedback) {
                return;
            }

            feedback.style.display = '';
            feedback.textContent = message || '';
            feedback.classList.toggle('text-danger', !ok);
            feedback.classList.toggle('text-success', !!ok);
        }

        button.addEventListener('click', function () {
            let base = 0;
            let lineDiscount = 0;

            visibleRows().forEach(function (row) {
                const line = rowTotals(row);

                base += line.base - line.includedTax;
                lineDiscount += line.discount;
            });

            const settle = settlement(base, lineDiscount);
            const amount = Math.max(0, base - lineDiscount - settle.discount);

            const productIds = visibleRows()
                .map(function (row) {
                    const select = row.querySelector('.product-select, [name$="[product_id]"]');

                    return select ? select.value : null;
                })
                .filter(Boolean);

            button.disabled = true;

            fetch(config.couponCheckUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                },
                body: JSON.stringify({
                    code: input.value,
                    client_id: (byName('client_id') || {}).value || null,
                    amount: amount,
                    product_ids: productIds,
                    shipping_cost: num(byName('shipping_cost')),
                }),
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    window.SALES_COUPON = data.ok
                        ? { discount: data.discount, free_shipping: data.free_shipping }
                        : null;

                    report(data.message, data.ok);
                    recalculate();
                })
                .catch(function () {
                    window.SALES_COUPON = null;
                    report('', false);
                    recalculate();
                })
                .finally(function () {
                    button.disabled = false;
                });
        });

        // رمزٌ عُدِّل بعد فحصه لم يعد مفحوصاً — ولا يبقى خصمُه معروضاً
        input.addEventListener('input', function () {
            if (window.SALES_COUPON) {
                window.SALES_COUPON = null;
                report('', false);
                recalculate();
            }
        });
    })();

    // ==================== التهيئة ====================

    if (Array.isArray(config.rows) && config.rows.length > 0) {
        config.rows.forEach(function (values) {
            addRow(values);
        });
    } else {
        addRow();
    }
})();
