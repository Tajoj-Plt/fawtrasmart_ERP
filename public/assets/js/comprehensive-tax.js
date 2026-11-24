/**
 * الضريبة الشاملة على المستند — خانةٌ واحدةٌ أعلى المستند تُطبَّق على كلّ الأصناف.
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * حين تُعلَّم ضريبةٌ «شاملةً للمستندات» في «إعدادات الضريبة»، تظهر في نماذج
 * البيع والشراء خانةٌ واحدةٌ (`document_tax_id`) بدل اختيار ضريبةٍ لكلّ سطر.
 * ما يُختار فيها يُفرَض على كلّ بند، و«بدون ضريبة» يمسحها عن الكلّ — والاختيارُ
 * اختياريٌّ على المستند. الخادمُ هو المصدرُ الموثوق
 * (`DocumentTaxCalculator::applyComprehensive`)، وهذا الملفّ يجعل الشاشةَ توافقه:
 * يُخفي عمودَ ضريبة الأسطر، ويطبّق ما في الخانة على كلّ صفٍّ عند التحميل وعند
 * تغيّر الخانة وعند إضافة صفٍّ جديد.
 *
 * ثلاثُ عائلاتٍ من نماذج البنود يخدمها منطقٌ واحدٌ بالكشف عن بنيتها:
 *   • A (invoice-calculator.js): `select.tax-select` قيمتُها النسبة، المعرّفُ في
 *     `data-id`، ومعها حقلٌ خفيّ `items[N][tax_1_id]`.
 *   • B (sales-document-form.js): `select[data-field="tax_1_id"]` قيمتُها المعرّف.
 *   • C (purchase-orders-form.js): `select[data-field="tax_1_id"]` قيمتُها المعرّف،
 *     ومعها حقلٌ خفيّ للنسبة `[data-field="tax_1"]`.
 *
 * التكوينُ من الشاشة قبل تحميل هذا الملف:
 *   window.comprehensiveTax = {
 *     enabled: true,
 *     fieldName: 'document_tax_id',
 *     taxes: { '2': { rate: 15, type: 'excluded' }, ... }   // معرّف ⇒ نسبة ونوع
 *   };
 */
(function () {
    'use strict';

    var cfg = window.comprehensiveTax;

    if (!cfg || !cfg.enabled) {
        return;
    }

    var FIELD = cfg.fieldName || 'document_tax_id';
    var TAXES = cfg.taxes || {};

    var TAX_SELECTS = 'select.tax-select, select[data-field="tax_1_id"], select[data-field="tax_2_id"]';

    var forcing = false;

    /** خانةُ الضريبة العلوية. */
    function headerField() {
        return document.querySelector('[name="' + FIELD + '"]');
    }

    /** معرّفُ الضريبة المختارة حالياً — أو `null` لـ«بدون ضريبة». */
    function currentTaxId() {
        var h = headerField();
        if (!h) {
            return null;
        }
        var v = String(h.value || '');
        return v === '' ? null : v;
    }

    function setSelectValue(sel, value) {
        if (sel.value !== value) {
            sel.value = value;
        }
    }

    /** يضبط ضريبةَ صفٍّ واحدٍ على المعرّف المعطى — أو يمسحها حين `null`. */
    function applyToRow(row, taxId) {
        if (!row || !row.querySelectorAll) {
            return;
        }

        // ── العائلة A ──
        row.querySelectorAll('select.tax-select').forEach(function (sel) {
            if (sel.getAttribute('data-target') === 'tax_2') {
                setSelectValue(sel, ''); // لا ضريبةَ ثانية
            } else if (taxId === null) {
                setSelectValue(sel, ''); // الخيار الفارغ = بدون ضريبة
            } else {
                var opt = sel.querySelector('option[data-id="' + taxId + '"]');
                setSelectValue(sel, opt ? opt.value : '');
            }

            if (typeof window.updateHiddenInput === 'function') {
                window.updateHiddenInput(sel);
            }
        });

        // ── العائلتان B و C ──
        row.querySelectorAll('select[data-field="tax_1_id"]').forEach(function (sel) {
            setSelectValue(sel, taxId === null ? '' : String(taxId));

            var rate = row.querySelector('[data-field="tax_1"]');
            if (rate) {
                rate.value = (taxId !== null && TAXES[taxId]) ? TAXES[taxId].rate : 0;
            }
        });

        row.querySelectorAll('select[data-field="tax_2_id"]').forEach(function (sel) {
            setSelectValue(sel, '');
            var rate = row.querySelector('[data-field="tax_2"]');
            if (rate) {
                rate.value = 0;
            }
        });
    }

    /** يُعيد حسابَ صفٍّ عبر إطلاق `change` على منتقي ضريبته (بعلمٍ يمنع حلقتنا). */
    function recalcRow(row) {
        var sel = row.querySelector('select.tax-select, select[data-field="tax_1_id"]');
        if (!sel) {
            return;
        }
        forcing = true;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        sel.dispatchEvent(new Event('input', { bubbles: true }));
        forcing = false;
    }

    /** كلُّ صفوف البنود التي تحمل منتقيَ ضريبة. */
    function allRows() {
        var rows = [];
        document.querySelectorAll(TAX_SELECTS).forEach(function (sel) {
            var row = sel.closest('tr');
            if (row && rows.indexOf(row) === -1) {
                rows.push(row);
            }
        });
        return rows;
    }

    /** يطبّق ضريبةَ الخانة العلوية على كلّ الصفوف ثمّ يُعيد حسابها. */
    function applyAll() {
        var taxId = currentTaxId();
        allRows().forEach(function (row) {
            applyToRow(row, taxId);
            recalcRow(row);
        });
    }

    /** الخليّةُ (`td`/`th`) الابنُ المباشرُ لصفّها. */
    function closestCell(el, row) {
        var node = el;
        while (node && node.parentElement !== row) {
            node = node.parentElement;
        }
        return node;
    }

    /** خلايا الصفّ (`td`/`th`) وحدها — الصفُّ قد يحمل `<input>` خفيّةً بين
        خلاياه تزحزح الفهرسةَ لو عُدّت الأبناءُ كلُّهم. */
    function cellsOf(row) {
        return Array.prototype.filter.call(row.children, function (el) {
            return el.tagName === 'TD' || el.tagName === 'TH';
        });
    }

    /** يُخفي رأسَ عمود الضريبة في جدولٍ من موضع خليّة الضريبة بين الخلايا. */
    function hideTaxHeader(table) {
        if (!table || table.__ctxHeaderHidden) {
            return;
        }
        table.__ctxHeaderHidden = true;

        // ملاحظة: `querySelector('tbody tr, tr')` يلتقط صفّ الرأس أوّلاً؛
        // فيُفضَّل صفُّ الجسم صراحةً
        var bodyRow = table.querySelector('tbody tr') || table.querySelector('tr');
        if (!bodyRow) {
            return;
        }

        var cells = cellsOf(bodyRow);

        bodyRow.querySelectorAll(TAX_SELECTS).forEach(function (sel) {
            var cell = closestCell(sel, bodyRow);
            var idx = cell ? cells.indexOf(cell) : -1;
            if (idx < 0) {
                return;
            }
            table.querySelectorAll('thead tr').forEach(function (tr) {
                var headCells = cellsOf(tr);
                if (headCells[idx]) {
                    headCells[idx].style.display = 'none';
                }
            });
        });
    }

    /** يُخفي خليّةَ الضريبة في صفٍّ بعينه. */
    function hideRowTaxCells(row) {
        row.querySelectorAll(TAX_SELECTS).forEach(function (sel) {
            var cell = closestCell(sel, row);
            if (cell) {
                cell.style.display = 'none';
            }
        });
    }

    function init() {
        var selects = document.querySelectorAll(TAX_SELECTS);
        if (!selects.length) {
            return;
        }

        var tables = [];
        var rows = [];

        selects.forEach(function (sel) {
            var table = sel.closest('table');
            if (table && tables.indexOf(table) === -1) {
                tables.push(table);
            }
            var row = sel.closest('tr');
            if (row && rows.indexOf(row) === -1) {
                rows.push(row);
            }
        });

        tables.forEach(hideTaxHeader);
        rows.forEach(hideRowTaxCells);

        // تطبيقٌ أوّليٌّ لقيمة الخانة على كلّ الصفوف
        applyAll();

        // الربطُ مرّةً واحدةً لكلّ جدول
        tables.forEach(function (table) {
            if (table.__ctxWired) {
                return;
            }
            table.__ctxWired = true;

            var body = table.querySelector('tbody') || table;

            var observer = new MutationObserver(function (mutations) {
                var taxId = currentTaxId();
                mutations.forEach(function (m) {
                    Array.prototype.forEach.call(m.addedNodes, function (node) {
                        if (node.nodeType !== 1) {
                            return;
                        }
                        var added = node.matches && node.matches('tr') ? [node] : [];
                        if (node.querySelectorAll) {
                            Array.prototype.push.apply(
                                added,
                                Array.prototype.slice.call(node.querySelectorAll('tr'))
                            );
                        }
                        added.forEach(function (row) {
                            if (row.querySelector(TAX_SELECTS)) {
                                hideRowTaxCells(row);
                                applyToRow(row, taxId);
                                recalcRow(row);
                            }
                        });
                    });
                });
            });

            observer.observe(body, { childList: true, subtree: true });

            /* يُعيد الفرضَ متى حرّك تغييرُ المنتج منتقيَ ضريبةِ صفٍّ عن قيمة
               الخانة. مرفوعُ العلم يتخطّى الحدثَ الذي أطلقناه. */
            table.addEventListener(
                'change',
                function (e) {
                    if (forcing) {
                        return;
                    }
                    var t = e.target;
                    if (t && t.matches && t.matches(TAX_SELECTS)) {
                        var row = t.closest('tr');
                        if (row) {
                            applyToRow(row, currentTaxId());
                            recalcRow(row);
                        }
                    }
                },
                false
            );
        });

        // تغيّرُ الخانة العلوية يُطبَّق على كلّ الصفوف
        var h = headerField();
        if (h && !h.__ctxWired) {
            h.__ctxWired = true;
            h.addEventListener('change', applyAll);
        }
    }

    function boot() {
        init();
        window.setTimeout(init, 0);
        window.addEventListener('load', function () {
            window.setTimeout(init, 0);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
