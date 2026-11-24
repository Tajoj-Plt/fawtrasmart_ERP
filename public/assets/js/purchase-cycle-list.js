/**
 * شاشات القوائم في دورة المشتريات — بحث بلا إعادة تحميل، تحديد جماعي، حذف.
 *
 * مشترك بين «طلبات عروض الأسعار» و«عروض أسعار المشتريات» و«أوامر الشراء»:
 * كل قسم يمرّر رابطه ونصوصه في `window.purchaseListConfig`، فلا نصّ عربي
 * ولا مسار مكتوب هنا وتتبع الشاشة لغةَ الواجهة.
 */
(function () {
    'use strict';

    const config = window.purchaseListConfig;

    if (!config) {
        return;
    }

    const i18n = config.i18n || {};
    const form = document.getElementById('search-form');
    const tableContainer = document.getElementById('table-container');
    const paginationContainer = document.getElementById('pagination-container');

    if (!form || !tableContainer) {
        return;
    }

    const spinner = document.getElementById('loading-spinner');
    const resultsCount = document.getElementById('results-count');
    const singleForm = document.getElementById('single-delete-form');
    const hasSwal = typeof window.Swal !== 'undefined';

    let requestToken = 0;

    // ==================== البحث ====================

    function collectFilters() {
        const params = new URLSearchParams();

        form.querySelectorAll('.search-input').forEach(function (input) {
            if (input.value) {
                params.append(input.name, input.value);
            }
        });

        return params;
    }

    function load(url) {
        const token = ++requestToken;

        if (spinner) {
            spinner.classList.remove('d-none');
        }

        const target = url || config.indexUrl;
        const separator = target.indexOf('?') === -1 ? '?' : '&';

        fetch(target + separator + collectFilters().toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                // الاستجابات المتأخّرة لا تدهس نتيجة بحث أحدث منها
                if (token !== requestToken || !payload || !payload.success) {
                    return;
                }

                tableContainer.innerHTML = payload.data;

                if (paginationContainer && payload.pagination !== undefined) {
                    paginationContainer.innerHTML = payload.pagination;
                    bindPagination();
                }

                if (resultsCount && i18n.results !== undefined) {
                    resultsCount.textContent = i18n.results.replace('__COUNT__', payload.total);
                }

                bindRowControls();
            })
            .catch(function () {})
            .finally(function () {
                if (token === requestToken && spinner) {
                    spinner.classList.add('d-none');
                }
            });
    }

    function bindPagination() {
        if (!paginationContainer) {
            return;
        }

        paginationContainer.querySelectorAll('a.page-link').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                load(link.getAttribute('href'));
            });
        });
    }

    // ==================== التحديد والحذف ====================

    function selectedBoxes() {
        return Array.prototype.slice.call(tableContainer.querySelectorAll('.order-checkbox:checked'));
    }

    function refreshBulkState() {
        const boxes = tableContainer.querySelectorAll('.order-checkbox');
        const count = selectedBoxes().length;
        const selectAll = tableContainer.querySelector('#select-all');
        const bulkBtn = document.getElementById('bulk-delete-btn');
        const bulkCount = document.getElementById('bulk-count');

        if (selectAll) {
            selectAll.checked = boxes.length > 0 && count === boxes.length;
            // حالة وسطى حين يكون بعض الصفوف محدَّداً لا كلّها
            selectAll.indeterminate = count > 0 && count < boxes.length;
        }

        if (bulkBtn) {
            bulkBtn.disabled = count === 0;
        }

        if (bulkCount) {
            bulkCount.textContent = count ? ' (' + count + ')' : '';
        }
    }

    function confirmDelete(text, onConfirm) {
        if (!hasSwal) {
            if (window.confirm(text)) {
                onConfirm();
            }
            return;
        }

        window.Swal.fire({
            title: i18n.deleteTitle,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: i18n.yesDelete,
            cancelButtonText: i18n.no,
        }).then(function (result) {
            if (result.isConfirmed) {
                onConfirm();
            }
        });
    }

    function bindRowControls() {
        const selectAll = tableContainer.querySelector('#select-all');

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                tableContainer.querySelectorAll('.order-checkbox').forEach(function (box) {
                    box.checked = selectAll.checked;
                });
                refreshBulkState();
            });
        }

        tableContainer.querySelectorAll('.order-checkbox').forEach(function (box) {
            box.addEventListener('change', refreshBulkState);
        });

        tableContainer.querySelectorAll('.po-delete-one').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();

                if (!singleForm) {
                    return;
                }

                const text = (i18n.deleteText || '').replace('__CODE__', link.dataset.code || '');

                confirmDelete(text, function () {
                    singleForm.action = link.dataset.action;
                    singleForm.submit();
                });
            });
        });

        refreshBulkState();
    }

    // ==================== الربط ====================

    const searchBtn = document.getElementById('search-btn');
    if (searchBtn) {
        searchBtn.addEventListener('click', function () { load(); });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        load();
    });

    form.querySelectorAll('.search-input').forEach(function (input) {
        input.addEventListener('change', function () { load(); });
    });

    /* قوائمُ المورّدين والموظفين طويلة — `.js-list-search` تأخذ خانةَ بحث.
     * select2 يطلق `change` عبر jQuery فلا يصل مستمعَ `addEventListener`
     * أعلاه، فيُعاد إطلاقه حدثاً أصلياً. */
    const $ = window.jQuery;
    const hasSelect2 = $ && typeof $.fn.select2 === 'function';

    if (hasSelect2) {
        $(form).find('select.js-list-search').each(function () {
            const el = this;
            $(el).select2({ width: '100%', allowClear: true, placeholder: el.options[0] ? el.options[0].text : '' });
            $(el).on('select2:select select2:clear', function () {
                el.dispatchEvent(new Event('change'));
            });
        });
    }

    function clearFilters() {
        form.reset();
        // `reset()` يعيد القيمة الأصلية ولا يُحدّث ما يعرضه select2
        if (hasSelect2) {
            $(form).find('select.js-list-search').trigger('change.select2');
        }
        load();
    }

    ['clear-filters', 'resetSearch'].forEach(function (id) {
        const button = document.getElementById(id);
        if (button) {
            button.addEventListener('click', clearFilters);
        }
    });

    const bulkForm = document.getElementById('bulk-form');
    const bulkBtn = document.getElementById('bulk-delete-btn');

    if (bulkForm && bulkBtn) {
        bulkBtn.addEventListener('click', function () {
            const count = selectedBoxes().length;

            if (count === 0) {
                return;
            }

            confirmDelete((i18n.bulkText || '').replace('__COUNT__', count), function () {
                bulkForm.submit();
            });
        });
    }

    bindPagination();
    bindRowControls();
})();
