/**
 * حقول الهاتف والجوال: رقمٌ سعودي يبدأ بصفر وطولُه عشرُ خانات.
 *
 * نظيرُ `App\Rules\SaudiPhone` في المتصفّح — القاعدةُ هناك هي الحَكَم، وهذا
 * يُري الخطأ قبل الإرسال لا بعده. يُحمَّل في شاشات العميل وموظف العميل
 * والموظف والمورد.
 *
 * الحقلُ يُعرَّف بـ`data-saudi-phone` أو باسمه (PHONE_NAMES)، فالصفوفُ التي
 * تُبنى بـJS («إضافة جهة اتصال») تدخل بلا تهيئة: المستمعاتُ على المستند.
 *
 * الرقمُ القديمُ المخالف لا يحبس النموذج: ما دام الحقلُ على قيمته التي
 * حُمّل بها (`defaultValue`) لا يُفحص — كما يفعل الخادم.
 */
(function () {
    'use strict';

    if (window.SaudiPhone) {
        return;
    }

    var LENGTH = 10;
    var MESSAGE = 'الرقم يجب أن يبدأ بـ 0 ويتكوّن من 10 أرقام — مثال: 0512345678';
    var PHONE_NAMES = /^(phone|mobile|mobile_number|phone_number|emergency_contact_phone|employee_phone)$|^contacts\[\d+\]\[(phone|mobile)\]$/;
    var EASTERN = '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹';

    function isPhoneInput(el) {
        return !!el && el.tagName === 'INPUT' && el.type !== 'hidden'
            && (el.hasAttribute('data-saudi-phone') || PHONE_NAMES.test(el.name || ''));
    }

    function normalize(value) {
        var text = String(value || '').trim().replace(/[٠-٩۰-۹]/g, function (d) {
            return String(EASTERN.indexOf(d) % 10);
        });
        var international = text.charAt(0) === '+';
        var digits = text.replace(/\D+/g, '');

        if (digits.indexOf('00966') === 0) {
            digits = digits.slice(2);
            international = true;
        }
        if (digits.indexOf('966') === 0 && (international || digits.length === 12)) {
            digits = '0' + digits.slice(3).replace(/^0+/, '');
        }

        return digits;
    }

    function isValid(value) {
        return /^0[0-9]{9}$/.test(normalize(value));
    }

    function untouchedLegacy(input) {
        return input.defaultValue !== '' && input.value === input.defaultValue;
    }

    function feedbackOf(input, create) {
        var anchor = input.closest('.input-group') || input;
        var next = anchor.nextElementSibling;

        if (next && next.classList.contains('saudi-phone-feedback')) {
            return next;
        }
        if (!create) {
            return null;
        }

        var box = document.createElement('div');
        box.className = 'saudi-phone-feedback';
        box.setAttribute('role', 'alert');
        anchor.insertAdjacentElement('afterend', box);

        return box;
    }

    function prepare(input) {
        if (input.dataset.saudiPhoneReady) {
            return;
        }
        input.dataset.saudiPhoneReady = '1';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'tel');
        input.setAttribute('dir', 'ltr');
        input.setAttribute('maxlength', '16'); // يتّسع للّصق بمفتاح الدولة قبل ردّه صفراً
        if (!input.getAttribute('placeholder') || /الهاتف|جوال/.test(input.getAttribute('placeholder'))) {
            input.setAttribute('placeholder', '05xxxxxxxx');
        }
        input.removeAttribute('pattern');
        input.classList.add('saudi-phone-input');
    }

    /** يُظهر الخطأ أو يمحوه؛ يعيد `true` إن كان الحقلُ مقبولاً */
    function check(input, showError) {
        prepare(input);

        var value = input.value.trim();
        var ok = value === '' || untouchedLegacy(input) || isValid(value);

        input.setCustomValidity(ok ? '' : MESSAGE);

        if (ok) {
            input.classList.remove('is-invalid', 'saudi-phone-invalid');
            var old = feedbackOf(input, false);
            if (old) {
                old.textContent = '';
                old.style.display = 'none';
            }
            input.classList.toggle('saudi-phone-valid', value !== '' && isValid(value));
        } else if (showError) {
            input.classList.remove('saudi-phone-valid');
            input.classList.add('is-invalid', 'saudi-phone-invalid');
            var box = feedbackOf(input, true);
            var missing = LENGTH - normalize(value).length;
            box.textContent = MESSAGE + (normalize(value).charAt(0) === '0' && missing > 0
                ? ' (باقي ' + missing + ')' : '');
            box.style.display = 'block';
        }

        return ok;
    }

    document.addEventListener('focusin', function (event) {
        if (isPhoneInput(event.target)) {
            prepare(event.target);
        }
    });

    document.addEventListener('input', function (event) {
        var input = event.target;
        if (!isPhoneInput(input)) {
            return;
        }
        prepare(input);

        var clean = normalize(input.value).slice(0, LENGTH);
        if (clean !== input.value) {
            input.value = clean;
        }
        // أثناء الكتابة يُمحى الخطأ متى صحّ الرقم، ولا يُرفع قبل أن يفرغ الكاتب
        check(input, input.classList.contains('saudi-phone-invalid') || clean.length === LENGTH);
    });

    document.addEventListener('focusout', function (event) {
        if (isPhoneInput(event.target)) {
            check(event.target, true);
        }
    });

    // طورُ الالتقاط: يسبق مستمعات النموذج نفسه، فالنماذجُ المُرسَلة بـAJAX
    // تتوقّف هنا أيضاً لا عند ردّ الخادم
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.querySelectorAll) {
            return;
        }

        var invalid = Array.prototype.filter.call(form.querySelectorAll('input'), function (input) {
            return isPhoneInput(input) && !input.disabled && !check(input, true);
        });

        if (invalid.length) {
            event.preventDefault();
            event.stopImmediatePropagation();

            var pane = invalid[0].closest('.tab-pane');
            if (pane && pane.id && !pane.classList.contains('active')) {
                var tab = document.querySelector('[href="#' + pane.id + '"], [data-bs-target="#' + pane.id + '"], [data-target="#' + pane.id + '"]');
                if (tab) {
                    tab.click();
                }
            }
            invalid[0].focus();
            invalid[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }, true);

    var style = document.createElement('style');
    style.textContent = '.saudi-phone-input{text-align:left;letter-spacing:.5px}'
        + '.saudi-phone-input::placeholder{letter-spacing:0;opacity:.55}'
        + '.saudi-phone-input.saudi-phone-invalid{border-color:#dc3545!important}'
        + '.saudi-phone-input.saudi-phone-valid{border-color:#198754!important}'
        + '.saudi-phone-feedback{display:none;width:100%;margin-top:4px;font-size:12px;line-height:1.5;color:#dc3545;text-align:start}';
    document.head.appendChild(style);

    function prepareAll() {
        Array.prototype.forEach.call(document.querySelectorAll('input'), function (input) {
            if (isPhoneInput(input)) {
                prepare(input);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepareAll);
    } else {
        prepareAll();
    }

    window.SaudiPhone = { normalize: normalize, isValid: isValid, check: check, MESSAGE: MESSAGE };
})();
