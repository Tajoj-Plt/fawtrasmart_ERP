/**
 * نموذج طلب الشراء — بناء البنود وحساب الإجماليات والتحقّق قبل الحفظ.
 *
 * كل النصوص وإعدادات العملة تُحقن من الخادم في `window.purchaseOrderForm`
 * (انظر partials/form.blade.php)، فلا نصّ عربي ولا رمز عملة مكتوب هنا:
 * تبديل اللغة أو العملة من الإعدادات يسري على هذه الشاشة بلا تعديل.
 */
(function () {
    'use strict';

    const config = window.purchaseOrderForm;

    if (!config) {
        return;
    }

    const currency = config.currency;
    const i18n = config.i18n;

    const form = document.getElementById('purchaseOrderForm');
    const tableBody = document.getElementById('tableBody');
    const rowTemplate = document.getElementById('rowTemplate');

    if (!form || !tableBody || !rowTemplate) {
        return;
    }

    const hasSwal = typeof window.Swal !== 'undefined';
    let allowSubmit = false;

    // ==================== أدوات ====================

    /** تنسيق مبلغ بنفس قواعد العملة المضبوطة في الإعدادات */
    function money(value) {
        const amount = Number.isFinite(value) ? value : 0;
        const fixed = Math.abs(amount).toFixed(currency.decimals);
        const parts = fixed.split('.');

        const integer = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousands);
        const decimals = parts[1] ? currency.decimal + parts[1] : '';

        let out = integer + decimals;
        out = currency.suffix ? out + ' ' + currency.symbol : currency.symbol + ' ' + out;

        return amount < 0 ? '-' + out : out;
    }

    /** المبلغُ بقواعد العملة نفسِها بلا رمزها — لخلايا الجدول */
    function amount(value) {
        const amountValue = Number.isFinite(value) ? value : 0;
        const fixed = Math.abs(amountValue).toFixed(currency.decimals);
        const parts = fixed.split('.');
        const integer = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousands);
        const out = integer + (parts[1] ? currency.decimal + parts[1] : '');

        return amountValue < 0 ? '-' + out : out;
    }

    function num(input) {
        const value = parseFloat(input && input.value);

        return Number.isFinite(value) ? value : 0;
    }

    function field(row, name) {
        return row.querySelector('[data-field="' + name + '"]');
    }

    /**
     * وحدات صنف: تُملأ قائمة الوحدات وتظهر، أو تُخفى إن لم تكن للصنف وحدات.
     *
     * الخريطة تأتي من الخادم (`ProductUnits::map`) وتحمل المنتجات ذات
     * الوحدات وحدها، فغياب الصنف عنها يعني «لا وحدات» لا «لم تُحمَّل بعد».
     *
     * `saved` بندٌ محفوظ أو منقول من مستندٍ سابق — تُعاد وحدتُه لا الافتراضية.
     * يُطابَق بمعرّفه أوّلاً، ثمّ باسم الوحدة ومعاملها: وحدةُ الصنف بلا صفٍّ
     * في `product_units` تُحفظ بمعرّفٍ فارغ (`ProductUnits::fieldsFor`)، فلو
     * اكتُفي بالمعرّف لعاد كلّ بندٍ «عبوة» عند التحويل أو التعديل «حبّة».
     * `force` يمرَّر إلى `applyUnit` — يُستعمَل عند تبديل الصنف لا عند
     * إعادة بناء بندٍ محفوظ، فسعرُ البند المحفوظ لا يُمسّ.
     */
    function fillUnits(row, productId, saved, force) {
        const select = row.querySelector('.po-unit');
        const factor = row.querySelector('.po-unit-factor');
        const unitName = row.querySelector('.po-unit-name');

        if (!select) {
            return;
        }

        const units = (config.units || {})[productId] || [];

        select.innerHTML = '';

        if (!units.length) {
            select.style.display = 'none';
            select.disabled = true;
            if (factor) { factor.value = 1; }
            if (unitName) { unitName.value = ''; }
            // عمودُ الوحدة له عنوان، وخليّةٌ فارغة تحته تُقرأ «لم يُحمَّل
            // بعد» لا «لا وحدة لهذا الصنف» — فتحلّ الشرطة محلّ القائمة
            toggleUnitPlaceholder(row, true);
            updateBaseQuantity(row);
            return;
        }

        units.forEach(function (unit) {
            const option = document.createElement('option');
            // القيمة معرّف `product_units` إن وُجد، وإلا معرّف وحدة الصنف
            // مسبوقاً بعلامة تميّزه — الخادم يقرأ الاسم والمعامل معه
            option.value = unit.id === null ? 'cu:' + unit.category_unit_id : unit.id;
            option.textContent = unit.conversion_factor && unit.conversion_factor !== 1
                ? unit.name + ' × ' + unit.conversion_factor
                : unit.name;
            option.dataset.factor = unit.conversion_factor || 1;
            option.dataset.name = unit.name;
            option.dataset.price = unit.purchase_price || 0;
            select.appendChild(option);
        });

        select.style.display = '';
        select.disabled = false;
        toggleUnitPlaceholder(row, false);

        const valueOf = function (unit) {
            return unit.id === null ? 'cu:' + unit.category_unit_id : String(unit.id);
        };
        let preselect = -1;

        if (saved) {
            if (saved.product_unit_id) {
                preselect = units.findIndex(function (unit) {
                    return valueOf(unit) === String(saved.product_unit_id);
                });
            }

            if (preselect < 0 && saved.unit_name) {
                const savedFactor = parseFloat(saved.conversion_factor) || 1;
                const byName = units.filter(function (unit) { return unit.name === saved.unit_name; });
                const exact = byName.find(function (unit) {
                    return Math.abs((parseFloat(unit.conversion_factor) || 1) - savedFactor) < 0.0001;
                });
                const match = exact || byName[0];
                preselect = match ? units.indexOf(match) : -1;
            }
        }

        if (preselect < 0) {
            preselect = units.findIndex(function (unit) { return unit.is_default; });
        }

        select.selectedIndex = preselect >= 0 ? preselect : 0;
        applyUnit(row, force);
    }

    /**
     * نقل معامل التحويل واسم الوحدة من الخيار المختار إلى حقلَي الإرسال.
     *
     * `force` يعني أنّ المستخدم بدّل الوحدة بنفسه، فسعرُ الوحدة يحلّ محلّ
     * ما في الحقل. كان الشرط `num(price) === 0` وحده يحكم، ومعه لا يتغيّر
     * السعر أبداً بعد أوّل تعبئة: يختار المستخدم الصنف فيُملأ سعر الحبّة،
     * ثمّ يبدّل إلى «كرتون» فيبقى سعر الحبّة في الحقل — يُشترى الكرتون
     * بأربعة بدل ثمانيةٍ وأربعين. أمّا التعبئة التلقائية (بلا `force`)
     * فتبقى محكومةً بالشرط نفسه كي لا تمحو سعراً كتبه المستخدم.
     */
    function applyUnit(row, force) {
        const select = row.querySelector('.po-unit');
        const factor = row.querySelector('.po-unit-factor');
        const unitName = row.querySelector('.po-unit-name');
        const option = select && select.options[select.selectedIndex];

        if (!option) {
            return;
        }

        if (factor) { factor.value = option.dataset.factor || 1; }
        if (unitName) { unitName.value = option.dataset.name || ''; }

        // سعر شراء الوحدة إن سُجِّل لها
        const price = field(row, 'unit_price');
        const unitPrice = parseFloat(option.dataset.price || 0);

        if (price && unitPrice > 0 && (force || num(price) === 0)) {
            price.value = unitPrice;
        }

        updateBaseQuantity(row);
    }

    /** الشرطة مكان قائمة الوحدة حين لا وحدات للصنف */
    function toggleUnitPlaceholder(row, show) {
        const dash = row.querySelector('.po-unit-none');

        if (dash) {
            dash.style.display = show ? '' : 'none';
        }
    }

    /** اسم الوحدة الأساسية للصنف — معاملُها واحد */
    function baseUnitName(productId) {
        const units = (config.units || {})[productId] || [];

        for (let i = 0; i < units.length; i++) {
            if (Number(units[i].conversion_factor || 1) === 1) {
                return units[i].name;
            }
        }

        return i18n.baseUnit || '';
    }

    /** رقمٌ بلا أصفارٍ زائدة: 2400.0000 ⇒ 2400، و1.5000 ⇒ 1.5 */
    function trimNumber(value) {
        return String(Math.round(value * 10000) / 10000);
    }

    /**
     * «= ٢٤٠٠ حبة» تحت قائمة الوحدة — ما تُساويه الكميةُ المدخلة بالوحدة
     * الأساسية. يظهر حين يكون للوحدة معاملُ تحويلٍ غير الواحد وحده: من
     * يشتري بالحبّة لا يحتاج سطراً يخبره أنّ الحبّة حبّة.
     */
    function updateBaseQuantity(row) {
        const display = row.querySelector('.po-base-qty');

        if (!display) {
            return;
        }

        const factor = parseFloat((row.querySelector('.po-unit-factor') || {}).value) || 1;
        const quantity = num(field(row, 'quantity'));
        const product = field(row, 'product_id');

        if (factor === 1 || quantity <= 0) {
            display.style.display = 'none';
            display.textContent = '';
            return;
        }

        const text = '= ' + trimNumber(quantity * factor) + ' ' + baseUnitName(product && product.value);

        display.style.display = '';
        display.textContent = text;
        display.title = text;
    }

    function visibleRows() {
        return Array.prototype.slice.call(tableBody.querySelectorAll('tr.po-row'));
    }

    function alertBox(title, text, icon) {
        if (hasSwal) {
            window.Swal.fire({
                title: title,
                text: text,
                icon: icon || 'error',
                confirmButtonText: i18n.ok,
            });
        } else {
            window.alert(text);
        }
    }

    function confirmBox(title, text, onConfirm) {
        if (!hasSwal) {
            if (window.confirm(text)) {
                onConfirm();
            }
            return;
        }

        window.Swal.fire({
            title: title,
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: i18n.yes,
            cancelButtonText: i18n.no,
        }).then(function (result) {
            if (result.isConfirmed) {
                onConfirm();
            }
        });
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
            row.querySelector('.po-row__index').textContent = index + 1;

            row.querySelectorAll('.po-input').forEach(function (input) {
                input.name = 'items[' + index + '][' + input.dataset.field + ']';
            });
        });
    }

    /**
     * الضريبة المختارة في الصف: نسبتها ونوعها.
     *
     * النوع لا يُعرف من النسبة، فيأتي من سمات الخيار المحقونة من
     * «إعدادات الضريبة». وتُنسخ النسبة إلى الحقل المرافق ليصل العمودان
     * إلى الخادم: المعرّف والنسبة.
     */
    function rowTax(row) {
        const select = field(row, config.taxIdField || 'tax_1_id');

        if (!select || !select.options) {
            // شاشة لم تُحدَّث بعد: النسبة وحدها، وتُعامَل معاملة المضافة
            return { rate: num(field(row, config.taxField || 'tax_rate')), included: false };
        }

        const option = select.options[select.selectedIndex];
        const rate = parseFloat(option && option.dataset.rate) || 0;
        const included = !!(option && option.dataset.included === '1');

        const mirror = row.querySelector('.po-tax-rate');
        if (mirror) {
            mirror.value = rate;
        }

        return { rate: rate, included: included };
    }

    /**
     * الضريبة الثانية — فاتورة الشراء وحدها تحملها.
     *
     * تُحسب على الوعاء نفسه لا على الأول: ضريبتان بنسبة 15% و5% على
     * وعاء 1000 ⇒ 150 و50، لا 150 ثم 5% من 1150.
     */
    function rowSecondTax(row) {
        const select = row.querySelector('.po-tax2');

        if (!select || !select.options) {
            return { rate: 0, included: false };
        }

        const option = select.options[select.selectedIndex];
        const rate = parseFloat(option && option.dataset.rate) || 0;
        const included = !!(option && option.dataset.included === '1');

        const mirror = row.querySelector('.po-tax2-rate');
        if (mirror) {
            mirror.value = rate;
        }

        return { rate: rate, included: included };
    }

    /** مبلغ ضريبة على وعاء — بالنوع المضبوط لها */
    function taxAmount(base, tax) {
        if (tax.rate <= 0) {
            return 0;
        }

        return tax.included ? base - base / (1 + tax.rate / 100) : base * tax.rate / 100;
    }

    /**
     * إجمالي صف واحد.
     *
     * الضريبة المتضمَّنة تُستخرَج من المبلغ ولا تُضاف فوقه:
     * 2000 بنسبة 15% متضمَّنة ⇒ ضريبة 260.87 وإجمالي 2000 لا 2300.
     */
    function rowTotal(row) {
        const quantity = num(field(row, 'quantity'));
        const price = num(field(row, 'unit_price'));
        const rawDiscount = num(field(row, 'discount'));
        const discountType = field(row, 'discount_type');
        const isPercent = discountType && discountType.value === 'percentage';
        const discount = isPercent ? (quantity * price * rawDiscount / 100) : rawDiscount;
        const tax = rowTax(row);

        const tax2 = rowSecondTax(row);

        const base = Math.max(0, quantity * price - discount);
        const amount = taxAmount(base, tax);
        const amount2 = taxAmount(base, tax2);

        // المتضمَّن يُطرح من الوعاء، والمضاف يُضاف فوقه
        const includedAmount = (tax.included ? amount : 0) + (tax2.included ? amount2 : 0);
        const addedAmount = (tax.included ? 0 : amount) + (tax2.included ? 0 : amount2);

        return {
            // المجموع قبل الخصم بلا الضريبة المتضمَّنة، فيستقيم السطر:
            // المجموع − الخصم + الضريبة = الإجمالي
            gross: quantity * price - includedAmount,
            discount: discount,
            tax: amount + amount2,
            included: includedAmount > 0,
            net: base - includedAmount,
            total: base + addedAmount,
        };
    }

    /**
     * حصّةُ كلّ بندٍ من الخصم العامّ — نظيرُ
     * `DocumentTaxCalculator::allocateDocumentDiscount()` في الخادم.
     *
     * الخصمُ العامّ يخفض **وعاءَ الضريبة** لا الإجمالي وحده. وكانت الشاشة
     * تحسب ضريبةَ كلّ بندٍ على وعائه قبل الخصم العامّ، فتخالف ما يحفظه
     * الخادم: بنودٌ بألفين وخصمٌ عامٌّ بعشرين بنسبة ١٥٪ تعرض ٣٠٠ ويحفظ
     * الخادم ٢٩٧.
     *
     * والكسرُ المتبقّي على آخر بندٍ لا يضيع — كما في الخادم تماماً.
     */
    function allocateDocumentDiscount(nets, documentDiscount) {
        const shares = nets.map(function () { return 0; });

        if (documentDiscount <= 0 || !nets.length) {
            return shares;
        }

        const base = nets.reduce(function (sum, net) { return sum + Math.max(0, net); }, 0);

        if (base <= 0) {
            return shares;
        }

        const total = Math.min(documentDiscount, base);
        let remaining = total;

        for (let i = 0; i < nets.length; i++) {
            const net = Math.max(0, nets[i]);
            const share = (i === nets.length - 1)
                ? remaining
                : Math.round(total * net / base * 100) / 100;

            shares[i] = Math.max(0, Math.min(share, net));
            remaining = Math.round((remaining - shares[i]) * 100) / 100;
        }

        return shares;
    }

    /**
     * ضريبةُ الشحن المعروضة — نظيرُ `DocumentTaxCalculator::shippingTax()`.
     *
     * النسبةُ من منتقي تبويبة الشحن (`#tax_id`) إن اختِيرت، وإلّا من أوّل
     * ضريبةِ بندٍ لها نسبة — وهو معنى ترك «اختر الضريبة» فارغاً في هذه
     * الشاشة، كما يقرؤه الخادم عبر `shippingTaxIds()`.
     *
     * وهي **مضافةٌ دائماً** فوق تكلفة الشحن لا متضمَّنة فيها: الخادم يمرّر
     * `included = false` صراحةً.
     */
    function shippingTaxAmount(shippingCost) {
        if (shippingCost <= 0) {
            return 0;
        }

        const selector = document.getElementById('tax_id');
        let rate = 0;

        if (selector && selector.value) {
            const option = selector.options[selector.selectedIndex];
            rate = parseFloat(option && option.dataset.rate) || 0;
        } else {
            const rows = visibleRows();

            for (let i = 0; i < rows.length && rate <= 0; i++) {
                rate = rowTax(rows[i]).rate || rowSecondTax(rows[i]).rate || 0;
            }
        }

        if (rate <= 0) {
            return 0;
        }

        return Math.round(shippingCost * rate / 100 * 100) / 100;
    }

    function recalculate() {
        let subtotal = 0;
        let discount = 0;

        const rows = visibleRows();
        const lines = [];

        rows.forEach(function (row) {
            const line = rowTotal(row);
            lines.push(line);

            // نسخة الخصم المرافقة التي يقرأها الخادم تبقى مطابقة للمعروض
            const mirror = row.querySelector('.po-discount-mirror');
            if (mirror) {
                mirror.value = line.discount;
            }

            subtotal += line.gross;
            discount += line.discount;

            // الكمية بالوحدة الأساسية تتبع الكمية المدخلة، و`recalculate`
            // تُستدعى بعد كل تعديل عليها
            updateBaseQuantity(row);

            // الأقسام بلا تسعير لا تعرض عمود الإجمالي، فالخانة قد لا توجد
            const totalCell = row.querySelector('.po-row__total');
            if (totalCell) {
                // رقمٌ بلا رمز العملة — كخليّة «الإجمالي» في فاتورة المبيعات؛
                // الرمزُ في صندوق الإجماليات أسفلَ الجدول
                totalCell.textContent = amount(line.total);
            }
        });

        // الشحن والخصم العامّ والتسوية تدخل الإجمالي النهائي، فما يراه
        // المستخدم على الشاشة يطابق ما يحسبه الخادم عند الحفظ
        const settle = settlement();
        discount += settle.discount;

        /* ضريبةُ البنود تُحسب بعد توزيع الخصم العامّ على أوعيتها — بترتيب
           `PurchaseDocumentTotals::compute()` نفسِه. */
        const shares = allocateDocumentDiscount(
            lines.map(function (line) { return Math.max(0, line.net); }),
            settle.discount
        );

        let tax = 0;

        rows.forEach(function (row, index) {
            const taxableBase = Math.max(0, lines[index].net - shares[index]);

            tax += taxAmount(taxableBase, rowTax(row)) + taxAmount(taxableBase, rowSecondTax(row));
        });

        const shippingTax = shippingTaxAmount(settle.shipping);

        const grand = subtotal - discount + tax + shippingTax + settle.shipping + settle.adjustment;
        const deposit = advancePayment(grand);
        const refund = refundAmount();

        const totals = {
            subtotal: subtotal,
            discount: discount,
            shipping: settle.shipping,
            tax: tax,
            shipping_tax: shippingTax,
            grand: grand,
            deposit: deposit,
            refund: refund,
            remaining: Math.max(0, grand - deposit),
        };

        Object.keys(totals).forEach(function (key) {
            const target = document.querySelector('[data-total="' + key + '"]');

            if (target) {
                target.textContent = money(totals[key]);
            }

            // الأسطر المشروطة: تظهر متى صار للقيمة وجود وتُخفى متى عادت
            // صفراً. المجموع والإجمالي النهائي بلا `data-total-row` فيبقيان
            const row = document.querySelector('[data-total-row="' + key + '"]');

            if (row) {
                row.classList.toggle('d-none', Math.abs(totals[key]) < 0.005);
            }
        });

        /* «المتبقي» يشترط شرطاً ثانياً فوق القاعدة: ما دام لم يُدفع شيء
           فهو الإجمالي نفسه مكرَّراً في سطرين. فلا يظهر إلا بعد أن يُدفع
           أو يُسترد شيء — والحلقة أعلاه أخفته أصلاً إن لم يبقَ شيء. */
        const remainingRow = document.querySelector('[data-total-row="remaining"]');

        if (remainingRow && deposit + refund < 0.005) {
            remainingRow.classList.add('d-none');
        }
    }

    /**
     * الدفعة المقدَّمة (الإيداع) كما يحسبها الخادم: «الدفعة الكاملة» تعني
     * الإجمالي كلّه، وغيرها مبلغٌ أو نسبةٌ منه، ولا تتجاوزه في الحالين.
     */
    function advancePayment(grand) {
        if (!config.payment) {
            return 0;
        }

        const full = document.getElementById('is_full_advance_payment');

        if (full && full.checked) {
            return Math.max(0, grand);
        }

        const raw = num(document.querySelector('[name="advance_payment"]'));

        if (raw <= 0) {
            return 0;
        }

        const type = document.querySelector('[name="advance_payment_type"]');
        const amount = type && type.value === 'percentage' ? grand * raw / 100 : raw;

        return Math.min(Math.max(0, amount), Math.max(0, grand));
    }

    /** المبلغ المسترد نقداً في مستندات الإرجاع — الحقل غائب في غيرها */
    function refundAmount() {
        if (!config.refund) {
            return 0;
        }

        return num(document.getElementById('refund_amount'));
    }

    /**
     * قيم قسم «التوصيل والخصم والتسوية».
     *
     * الخصم العامّ قد يكون مبلغاً أو نسبة من مجموع البنود، والتسوية تُخصم
     * أو تُضاف حسب نوعها. القسم غير موجود في كل الشاشات، فالغائب صفر.
     */
    function settlement() {
        if (!config.settlement) {
            return { shipping: 0, discount: 0, adjustment: 0 };
        }

        const byName = function (name) {
            return document.querySelector('[name="' + name + '"]');
        };

        let gross = 0;
        visibleRows().forEach(function (row) { gross += rowTotal(row).gross; });

        const rawDiscount = num(byName('discount_amount'));
        const isPercent = (byName('discount_type') || {}).value === 'percentage';
        const discount = isPercent ? gross * rawDiscount / 100 : rawDiscount;

        const adjustValue = num(byName('adjustment_value'));
        const adjustSign = (byName('adjustment_type') || {}).value === 'addition' ? 1 : -1;

        return {
            shipping: num(byName('shipping_cost')),
            discount: discount,
            adjustment: adjustSign * adjustValue,
        };
    }

    /**
     * حقول البند التي يملكها المستند المصدر لا هذا المستند.
     *
     * ما طُلب شراؤه — الصنف ووحدته وكميته ووصفه — يُنقل بالتحويل ولا
     * يُعاد فتحه هنا. أمّا السعر والخصم والضريبة فمِلكُ هذا المستند:
     * «عرض السعر» غايتُه تسعير ما طُلب، فلو أُقفل السعر لبطلت الشاشة.
     */
    const LOCKED_FIELDS = ['product_id', 'product_unit_id', 'description', 'quantity'];

    /**
     * قفل قائمة اختيار.
     *
     * `disabled` لا يصلح هنا: المتصفّح لا يرسل الحقول المعطَّلة، فيصل
     * البند إلى الخادم بلا صنف ولا وحدة فيُرفض. البديل نزعُ ما عدا
     * الخيار المحدَّد — تبقى القيمة تُرسَل ولا يبقى ما يُختار غيرها.
     *
     * قائمةٌ بلا قيمة (صنفٌ حُذف من الأصناف مثلاً) تُترك مفتوحة عمداً،
     * وإلّا صار البند غير قابل للحفظ ولا للإصلاح.
     */
    function lockSelect(select) {
        if (!select.value) {
            return false;
        }

        Array.prototype.slice.call(select.options).forEach(function (option) {
            if (option.value !== select.value) {
                option.remove();
            }
        });

        select.setAttribute('tabindex', '-1');

        return true;
    }

    /**
     * قفل بندٍ جاء بالتحويل من مستند سابق — لا يُعدَّل ولا يُحذف.
     */
    function lockRow(row) {
        row.dataset.locked = '1';
        row.classList.add('po-row--locked');

        LOCKED_FIELDS.forEach(function (name) {
            const input = field(row, name);

            if (!input) {
                return;
            }

            input.setAttribute('aria-readonly', 'true');

            if (input.tagName === 'SELECT') {
                if (lockSelect(input)) {
                    input.classList.add('po-locked');
                }

                return;
            }

            input.readOnly = true;
            input.classList.add('po-locked');
        });

        const removeBtn = row.querySelector('.po-row__remove');

        if (removeBtn) {
            removeBtn.disabled = true;
            removeBtn.title = i18n.lockedRow || '';
        }

        // شارةُ قفلٍ بجانب رقم الصف: الحقلُ المقفل بلا علامة يُقرأ «معطوب»
        const indexCell = row.querySelector('.po-row__index');

        if (indexCell && !row.querySelector('.po-row__lock')) {
            const badge = document.createElement('i');
            badge.className = 'fas fa-lock po-row__lock';
            badge.title = i18n.lockedRow || '';
            indexCell.parentNode.appendChild(badge);
        }
    }

    function removeRow(row) {
        // بندٌ محوَّل: زرّ حذفه معطَّل أصلاً، وهذا الحارس لمن يبلغ الدالة
        // من طريق آخر
        if (row.dataset.locked) {
            alertBox(i18n.removeTitle, i18n.lockedRow, 'info');
            return;
        }

        if (visibleRows().length <= 1) {
            alertBox(i18n.removeTitle, i18n.lastRow, 'warning');
            return;
        }

        confirmBox(i18n.removeTitle, i18n.removeText, function () {
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

        row.querySelector('.po-row__remove').addEventListener('click', function () {
            removeRow(row);
        });

        /* اختيار صنفٍ يملأ سعره.
         *
         * كان الشرط `num(price) === 0` يمنع ذلك بعد أوّل بند: من بدّل الصنف
         * في سطرٍ مملوء بقي فيه سعرُ الصنف السابق — يُشترى صنفٌ بسعر آخر.
         * فالسعر يُصفَّر أوّلاً، ثمّ يملؤه سعرُ الوحدة الافتراضية إن كان
         * للصنف وحدات، وإلا سعرُ شرائه المسجَّل عليه. */
        const productSelect = field(row, 'product_id');
        productSelect.addEventListener('change', function () {
            const option = productSelect.options[productSelect.selectedIndex];
            const price = field(row, 'unit_price');

            if (price) { price.value = 0; }

            // وحدات الصنف الجديد — بلا وحدة محفوظة، فتُختار الافتراضية
            fillUnits(row, productSelect.value, null, true);

            // صنفٌ بلا وحدات، أو وحدةٌ بلا سعرٍ مسجَّل
            if (price && num(price) === 0 && option && option.dataset.price) {
                price.value = option.dataset.price;
            }

            recalculate();
        });

        const unitSelect = row.querySelector('.po-unit');

        if (unitSelect) {
            unitSelect.addEventListener('change', function () {
                // تبديلٌ صريح للوحدة: سعرها هو المعتمَد
                applyUnit(row, true);
                recalculate();
            });
        }

        // بند محفوظ: وحداته تُملأ ووحدته المحفوظة تُعاد
        if (productSelect.value) {
            fillUnits(row, productSelect.value, values || null);
        }

        row.querySelectorAll('.po-calc').forEach(function (input) {
            input.addEventListener('input', recalculate);
            input.addEventListener('change', recalculate);
        });

        // القفل بعد ملء الوحدات: `lockSelect` ينزع ما عدا المحدَّد، فلو
        // سبق الملءَ لَبقيت قائمة الوحدات فارغة ثم امتلأت من جديد
        if (values && values.locked) {
            lockRow(row);
        }

        tableBody.appendChild(row);

        /* قائمةُ الصنف قائمةٌ أصلية لا select2 — كما في «إنشاء فاتورة
         * مبيعات» بالضبط: كان الصندوقُ هنا بمظهر select2 (سهمٌ وخانةُ بحث
         * وارتفاعٌ آخر) فبدا جدولُ البنود مختلفاً عن نظيره في المبيعات
         * (طلب المستخدم 2026-09-21). المتصفّحُ يبحث في القائمة الأصلية
         * بالكتابة فوقها. */

        reindex();
        recalculate();

        return row;
    }

    // ==================== التحقّق ====================

    function validate() {
        const rows = visibleRows();

        if (rows.length === 0) {
            alertBox(i18n.removeTitle, i18n.itemsRequired, 'error');
            return false;
        }

        // بعض الأقسام بلا حقل «المسمى»، واسم حقل التاريخ يختلف بينها،
        // فيأتيان من إعدادات النموذج لا مكتوبَين هنا
        const title = document.getElementById('title');
        if (config.requireTitle !== false && title && !title.value.trim()) {
            title.focus();
            alertBox(i18n.saveTitle, i18n.titleRequired, 'error');
            return false;
        }

        const orderDate = document.getElementById(config.dateFieldId || 'order_date');
        if (orderDate && !orderDate.value) {
            orderDate.focus();
            alertBox(i18n.saveTitle, i18n.orderDateRequired, 'error');
            return false;
        }

        for (let index = 0; index < rows.length; index++) {
            const product = field(rows[index], 'product_id');
            const quantity = field(rows[index], 'quantity');

            if (!product.value) {
                product.focus();
                alertBox(i18n.saveTitle, i18n.itemRequired.replace('__ROW__', index + 1), 'error');
                return false;
            }

            if (!quantity.value || num(quantity) <= 0) {
                quantity.focus();
                alertBox(i18n.saveTitle, i18n.quantityRequired.replace('__ROW__', index + 1), 'error');
                return false;
            }
        }

        return true;
    }

    // ==================== الربط ====================

    document.querySelectorAll('.po-settle').forEach(function (input) {
        input.addEventListener('input', recalculate);
        input.addEventListener('change', recalculate);
    });

    // تبويبة الإيداع: المبلغ/النوع/الطريقة/المرجع لا تلزم إلا للدفع الجزئي
    // — الخادم يتجاهلها ويستبدل المبلغ بإجمالي الفاتورة عند «الدفعة الكاملة»
    const fullAdvancePayment = document.getElementById('is_full_advance_payment');
    const paymentDetailFields = document.getElementById('payment-detail-fields');

    if (fullAdvancePayment && paymentDetailFields) {
        const toggleAdvancePaymentFields = function () {
            paymentDetailFields.classList.toggle('d-none', fullAdvancePayment.checked);
            // «الدفعة الكاملة» تغيّر الإيداع والمتبقي في صندوق الإجماليات
            recalculate();
        };

        fullAdvancePayment.addEventListener('change', toggleAdvancePaymentFields);
        toggleAdvancePaymentFields();
    }

    // select2 حين يكون محمَّلاً في القالب — يُظهر المختار كوسم واضح
    if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
        window.jQuery('.po-select2').select2({ width: '100%' });
    }

    document.getElementById('addRowBtn').addEventListener('click', function () {
        addRow();
    });

    form.addEventListener('submit', function (event) {
        if (allowSubmit) {
            return;
        }

        event.preventDefault();

        if (!validate()) {
            return;
        }

        confirmBox(i18n.saveTitle, i18n.saveText, function () {
            if (hasSwal) {
                window.Swal.fire({
                    title: i18n.saving,
                    text: i18n.pleaseWait,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () {
                        window.Swal.showLoading();
                    },
                });
            }

            allowSubmit = true;
            form.submit();
        });
    });

    document.getElementById('cancelBtn').addEventListener('click', function () {
        confirmBox(i18n.cancelTitle, i18n.cancelText, function () {
            form.reset();
            tableBody.innerHTML = '';
            addRow();

            const selected = document.getElementById('selectedFile');
            if (selected) {
                selected.classList.add('d-none');
            }
        });
    });

    // ==================== المرفقات ====================

    const fileInput = document.getElementById('attachments');
    const uploadArea = document.getElementById('uploadArea');
    const selectedFile = document.getElementById('selectedFile');

    if (fileInput && uploadArea) {
        uploadArea.addEventListener('click', function () {
            fileInput.click();
        });

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

    // ==================== التهيئة ====================

    if (Array.isArray(config.rows) && config.rows.length > 0) {
        config.rows.forEach(addRow);
    } else {
        addRow();
    }
})();
