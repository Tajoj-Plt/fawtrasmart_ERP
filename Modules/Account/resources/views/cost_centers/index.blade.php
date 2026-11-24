@extends('account::master')

@section('title', 'مراكز التكلفة')

@section('content')
    <div class="content-body">

        {{--
            كانت هذه الشاشة صفحة HTML كاملة بذاتها — `<!DOCTYPE html>` ووسم
            `<head>` وترويسة وشريط جانبي وتذييل: ألف سطر تُكرّر القالب الرئيسي
            بدل أن ترثه. فنجت وحدها من كل ما طُبِّق على الوحدة: لا ثيم معتمد،
            ولا ترويسة موحّدة، ولا عرضَ لأخطاء التحقّق ولا لتنبيهات الجلسة.

            ثم كانت شجرتَين لبيانٍ واحد: `jstree` في عمودٍ جانبيّ تُحمَّل من
            CDN خارجيّ، وجدولٌ مسطَّح إلى جانبها يعرض طبقةً واحدة — الجذورَ،
            أو أبناءَ عقدةٍ تحلّ محلَّها. فالشجرةُ لا تُرى شجرةً في أيّ لحظة،
            ولا يُعرف أين ينتهي فرعٌ ويبدأ آخر. وصارت جدولاً شجريّاً واحداً
            يُطوى ويُبسَط، على نمط شجرة مجموعات الأصناف.
        --}}
        @section('page-actions')
            @if ($can['create'])
                <button type="button" id="addAccountModalButton" class="btn btn-primary">
                    <i class="feather icon-plus"></i> مركز تكلفة جديد
                </button>
            @endif
        @endsection

        @include('account::components.page-head', [
            'title' => 'مراكز التكلفة',
            'icon' => 'pie-chart',
            'sub' => 'شجرة مراكز التكلفة — يُوزَّع عليها المصروف في سطور القيد',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الحسابات' => null,
                'مراكز التكلفة' => null,
            ],
        ])

        {{--
            الأرقام قبل الشجرة: كانت الشاشة تفتح على شجرةٍ وجدولٍ فارغ، فلا
            يُعرف حجم التوزيع إلا بالتنقّل عقدةً عقدة.
        --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-pie-chart"></i></span>
                <span class="stk-metric__label">إجمالي المراكز</span>
                <span class="stk-metric__value">{{ number_format($stats['total'] ?? 0) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-folder"></i></span>
                <span class="stk-metric__label">مراكز رئيسية</span>
                <span class="stk-metric__value">{{ number_format($stats['main'] ?? 0) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-git-branch"></i></span>
                <span class="stk-metric__label">مراكز فرعية</span>
                <span class="stk-metric__value">{{ number_format($stats['sub'] ?? 0) }}</span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-git-merge"></i> الشجرة
                    <span class="stk-count">{{ number_format($stats['total'] ?? 0) }}</span>
                </h6>

                <div class="d-flex align-items-center" style="gap:.5rem; flex-wrap:wrap">
                    {{--
                        البحثُ كان حقلاً في الشاشة لا يُصغي إليه شيء: يُكتب فيه
                        فلا يقع شيء. وصار يُرشِّح الشجرةَ ويفتح آباءَ ما طابق،
                        فالمطابقُ يظهر في موضعه من الشجرة لا منتزعاً منها.
                    --}}
                    <input type="text" id="treeSearch" class="form-control form-control-sm"
                        style="min-width:220px" placeholder="ابحث باسم المركز أو كوده…"
                        aria-label="بحث في الشجرة">

                    <button type="button" class="btn btn-sm btn-outline-secondary" id="expand_all">
                        <i class="feather icon-maximize-2"></i> فتح الكل
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="collapse_all">
                        <i class="feather icon-minimize-2"></i> طيّ الكل
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 stk-tree" dir="rtl">
                    <thead>
                        <tr>
                            <th>المركز</th>
                            <th class="text-center" style="width:14%">الكود</th>
                            <th class="text-center" style="width:12%">النوع</th>
                            <th class="text-center" style="width:12%">الفروع</th>
                            <th class="text-center" style="width:20%">الخيارات</th>
                        </tr>
                    </thead>

                    <tbody id="table-body">
                        @include('account::cost_centers.partials.tree-body', [
                            'roots' => $roots,
                            'can' => $can,
                        ])
                    </tbody>
                </table>
            </div>

            <div id="searchEmpty" class="stk-empty" hidden>
                <i class="feather icon-search"></i>
                <p>لا مركز يطابق البحث.</p>
            </div>
        </div>

        {{-- ═══════════ مودال الإضافة ═══════════ --}}
        <div class="modal fade text-left" id="info-modal-account" tabindex="-1" role="dialog"
            aria-labelledby="addModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document">
                <div class="modal-content">
                    {{--
                        الترويسة كانت بلونٍ مكتوبٍ في السطر (`#7367F0`)، فلا
                        يطالها تبديل الثيم في شاشة المظهر. وزرّ الإغلاق كان
                        بترميز Bootstrap 4 (`class="close"` و`&times;`) مع
                        وسم Bootstrap 5 (`data-bs-dismiss`) — فلا يُغلق.
                    --}}
                    <div class="modal-header">
                        <h5 class="modal-title" id="addModalLabel">أضف مركز تكلفة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>

                    <div class="modal-body">
                        <form id="addAccountForm" action="/cost_centers/store_account" method="POST">
                            @csrf
                            @include('account::cost_centers.partials.form-fields', ['accounts' => $accounts])
                        </form>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        <button type="submit" class="btn btn-primary waves-effect waves-light"
                            form="addAccountForm">حفظ</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════ مودال التعديل ═══════════ --}}
        <div class="modal fade" id="editAccountModal" tabindex="-1" role="dialog"
            aria-labelledby="editAccountModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAccountModalLabel">تعديل مركز التكلفة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>

                    <div class="modal-body">
                        <form id="editAccountForm">
                            @csrf
                            @include('account::cost_centers.partials.form-fields', ['accounts' => $accounts])
                        </form>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        <button type="submit" class="btn btn-primary waves-effect waves-light"
                            form="editAccountForm">حفظ التعديلات</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        (function () {
            'use strict';

            const TREE_STATE_KEY = 'cost_centers.expanded';

            // ═══════════════════ الطيّ والبسط ═══════════════════

            /*
                العقدُ المفتوحة تُحفظ بمعرّفاتها لا بمواضعها: الجدولُ يُعاد
                بناؤه بعد كلّ حفظ، والمواضعُ تتغيّر بينما المعرّفاتُ تثبت.
                فتعود الشجرةُ إلى ما كانت عليه بدل أن تنطبق على المستخدم
                كلّما أضاف مركزاً.
            */
            function readState() {
                try {
                    return new Set(JSON.parse(localStorage.getItem(TREE_STATE_KEY)) || []);
                } catch (e) {
                    return new Set();
                }
            }

            function writeState(set) {
                try {
                    localStorage.setItem(TREE_STATE_KEY, JSON.stringify(Array.from(set)));
                } catch (e) {
                    // خزنةُ المتصفّح قد تكون مغلقة — الشجرةُ تعمل بلا ذاكرة
                }
            }

            let expanded = readState();

            function rows() {
                return Array.from(document.querySelectorAll('#table-body tr.stk-tree__row'));
            }

            function childrenOf(id) {
                return rows().filter(r => r.dataset.parent === String(id));
            }

            function setOpen(row, open) {
                row.dataset.expanded = open ? 'true' : 'false';

                const btn = row.querySelector('.stk-tree__toggle');
                if (btn) {
                    btn.classList.toggle('is-open', open);
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                }

                if (open) {
                    expanded.add(row.dataset.id);
                } else {
                    expanded.delete(row.dataset.id);
                }
            }

            /* الطيُّ يُخفي الذرّية كلَّها؛ والبسطُ يكشف مستوىً واحداً */
            function reveal(id, open) {
                childrenOf(id).forEach(child => {
                    child.hidden = !open;

                    if (!open) {
                        setOpen(child, false);
                        reveal(child.dataset.id, false);
                    } else if (child.dataset.expanded === 'true') {
                        reveal(child.dataset.id, true);
                    }
                });
            }

            /** إعادةُ الشجرة إلى حالتها المحفوظة بعد كلّ بناء */
            function applyState() {
                rows().forEach(row => {
                    if (row.dataset.hasChildren !== 'true') {
                        return;
                    }

                    if (expanded.has(row.dataset.id)) {
                        setOpen(row, true);
                    }
                });

                rows().filter(r => r.dataset.level === '0').forEach(root => {
                    root.hidden = false;
                    reveal(root.dataset.id, root.dataset.expanded === 'true');
                });

                if (window.feather) {
                    window.feather.replace();
                }
            }

            $(document).on('click', '.stk-tree__toggle', function (event) {
                event.stopPropagation();

                const row = this.closest('tr');
                const open = row.dataset.expanded !== 'true';

                setOpen(row, open);
                reveal(row.dataset.id, open);
                writeState(expanded);
            });

            $('#expand_all').on('click', function () {
                rows().forEach(r => {
                    r.hidden = false;
                    if (r.dataset.hasChildren === 'true') setOpen(r, true);
                });
                writeState(expanded);
            });

            $('#collapse_all').on('click', function () {
                rows().forEach(r => {
                    if (r.dataset.level !== '0') r.hidden = true;
                    if (r.dataset.hasChildren === 'true') setOpen(r, false);
                });
                writeState(expanded);
            });

            // ═══════════════════ البحث ═══════════════════

            /*
                المطابقُ يظهر في موضعه من الشجرة: تُكشف آباؤه معه، وإلا رأى
                المستخدمُ اسماً بلا سياقٍ ولا يعرف تحت أيّ مركزٍ يقع.
            */
            $('#treeSearch').on('input', function () {
                const term = this.value.trim().toLowerCase();
                const all = rows();

                if (!term) {
                    all.forEach(r => { r.hidden = r.dataset.level !== '0'; });
                    applyState();
                    $('#searchEmpty').prop('hidden', true);
                    return;
                }

                const byId = {};
                all.forEach(r => { byId[r.dataset.id] = r; });

                let hits = 0;

                all.forEach(r => {
                    const hay = ((r.dataset.name || '') + ' ' + (r.dataset.code || '')).toLowerCase();
                    const match = hay.indexOf(term) !== -1;

                    r.hidden = !match;
                    if (match) hits++;
                });

                // كشفُ سلسلة الآباء لكلّ مطابق
                all.filter(r => !r.hidden).forEach(r => {
                    let parent = byId[r.dataset.parent];

                    while (parent) {
                        parent.hidden = false;
                        setOpen(parent, true);
                        parent = byId[parent.dataset.parent];
                    }
                });

                $('#searchEmpty').prop('hidden', hits > 0);
            });

            // ═══════════════════ إعادة بناء الشجرة ═══════════════════

            /*
                مصدرُ الترميز قالبٌ واحد في الخادم (`tree-row`)، فما يُبنى بعد
                الحفظ لا يفترق عمّا بُني عند فتح الصفحة. وكان التحديثُ سابقاً
                `jstree('refresh')` للشجرة و`/cost_centers/parents` للجدول —
                مصدران لبيانٍ واحد يفترقان.
            */
            window.reloadCostCenterTree = function () {
                return $.getJSON('/cost_centers/tree-rows')
                    .done(function (response) {
                        if (response && response.html) {
                            $('#table-body').html(response.html);
                            applyState();
                            $('#treeSearch').trigger('input');
                        }
                    })
                    .fail(function () {
                        Swal.fire({ title: 'خطأ!', text: 'تعذّر تحديث الشجرة.', icon: 'error' });
                    });
            };

            // ═══════════════════ «رئيسيٌّ» تتبع الأب ═══════════════════

            /*
                الخادمُ يشتقّها من `parent_id` (انظر `resolveParent`)، والشاشةُ
                تُظهر الاشتقاقَ نفسَه حتى لا يرى المستخدمُ حالةً لا تُحفَظ:
                مؤشَّراً «رئيسي» وله أبٌ في الوقت نفسه.
            */
            function syncMainFlag(scope) {
                const parent = scope.find('select[name="parent_id"]');
                const flag = scope.find('input[name="is_main"]');
                const hint = scope.find('.parent-hint');
                const isMain = flag.is(':checked');

                parent.prop('disabled', isMain);

                if (isMain) {
                    parent.val('');
                }

                hint.text(isMain || !parent.val()
                    ? 'بلا أب — يُحفَظ مركزاً رئيسياً.'
                    : 'مركزٌ فرعيّ تحت المركز المختار.');
            }

            $(document).on('change', 'input[name="is_main"]', function () {
                syncMainFlag($(this).closest('form'));
            });

            $(document).on('change', 'select[name="parent_id"]', function () {
                const scope = $(this).closest('form');

                // اختيارُ أبٍ ينفي «رئيسي»، ورفعُه يُعيدها
                scope.find('input[name="is_main"]').prop('checked', !$(this).val());
                syncMainFlag(scope);

                if (scope.attr('id') === 'addAccountForm') {
                    suggestCode(scope, $(this).val());
                }
            });

            $(document).on('shown.bs.modal', '#info-modal-account, #editAccountModal', function () {
                syncMainFlag($(this).find('form'));
            });

            /*
                الحقلُ المعطَّل لا يُرسَل مع النموذج، وذاك مقصود: غيابُ
                `parent_id` هو ما يعنيه «رئيسي». ويُرفَع صراحةً قبل الإرسال
                لئلّا يُرسَل أبٌ قديمٌ بقي في الحقل.
            */
            $(document).on('submit', '#addAccountForm, #editAccountForm', function () {
                if ($(this).find('input[name="is_main"]').is(':checked')) {
                    $(this).find('select[name="parent_id"]').val('');
                }
            });

            // ═══════════════════ الكود المقترَح ═══════════════════

            function suggestCode(scope, parentId) {
                const codeField = scope.find('input[name="code"]');

                if (!parentId) {
                    codeField.val('');
                    return;
                }

                $.getJSON('/cost_centers/' + parentId + '/next-code')
                    .done(function (response) {
                        if (response && response.nextCode) codeField.val(response.nextCode);
                    });
            }

            // ═══════════════════ الإضافة ═══════════════════

            function openAddModal(parentId, parentName) {
                const form = $('#addAccountForm');

                form[0].reset();
                form.find('select[name="parent_id"]').val(parentId || '');
                form.find('input[name="is_main"]').prop('checked', !parentId);

                $('#addModalLabel').text(parentId ? 'أضف فرعاً تحت: ' + parentName : 'أضف مركز تكلفة رئيسي');

                if (parentId) {
                    suggestCode(form, parentId);
                }

                $('#info-modal-account').modal('show');
            }

            $('#addAccountModalButton').on('click', function () {
                // الزرُّ العام يضيف جذراً؛ وزرُّ الصفّ يضيف تحته
                openAddModal(null, null);
            });

            $(document).on('click', '.add-child', function (event) {
                event.stopPropagation();
                openAddModal($(this).data('id'), $(this).data('name'));
            });

            function showValidationErrors(xhr, fallback) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    let html = '<ul dir="rtl" class="text-danger" style="padding-inline-start:1.2rem; text-align:right">';

                    Object.keys(errors).forEach(function (field) {
                        const list = errors[field];
                        html += '<li>' + (Array.isArray(list) ? list.join('، ') : list) + '</li>';
                    });

                    html += '</ul>';

                    Swal.fire({ title: 'خطأ في التحقق!', html: html, icon: 'error' });
                    return;
                }

                Swal.fire({
                    title: 'خطأ!',
                    text: (xhr.responseJSON && xhr.responseJSON.message) || fallback,
                    icon: 'error',
                });
            }

            $('#addAccountForm').on('submit', function (e) {
                e.preventDefault();

                const form = this;

                $.ajax({
                    url: $(form).attr('action'),
                    type: 'POST',
                    data: $(form).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                })
                    .done(function (response) {
                        if (!response.success) {
                            Swal.fire({ title: 'خطأ!', text: response.message || 'حدث خطأ.', icon: 'error' });
                            return;
                        }

                        Swal.fire({
                            title: 'تم بنجاح!',
                            text: response.message || 'تمت العملية بنجاح.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false,
                        });

                        /*
                            المركزُ الجديد يظهر مفتوحاً أبوه: من أضاف فرعاً
                            يريد أن يراه، لا أن يفتّش عنه في شجرةٍ مطويّة.
                        */
                        const parentId = $(form).find('select[name="parent_id"]').val();
                        if (parentId) expanded.add(String(parentId));
                        writeState(expanded);

                        $('#info-modal-account').modal('hide');
                        form.reset();

                        window.reloadCostCenterTree();
                        refreshParentOptions();
                    })
                    .fail(function (xhr) {
                        showValidationErrors(xhr, 'حدث خطأ أثناء التنفيذ. الرجاء المحاولة مرة أخرى.');
                    });
            });

            // ═══════════════════ التعديل ═══════════════════

            $(document).on('click', '.edit-button', function (event) {
                event.stopPropagation();

                const nodeId = $(this).data('id');
                $('#editAccountModal').data('node-id', nodeId);

                $.getJSON('/cost_centers/' + nodeId + '/edit')
                    .done(function (response) {
                        if (!response.success) {
                            Swal.fire({ title: 'خطأ!', text: response.message || 'تعذّر جلب البيانات.', icon: 'error' });
                            return;
                        }

                        const form = $('#editAccountForm');

                        form.find('input[name="name"]').val(response.data.name);
                        form.find('input[name="code"]').val(response.data.code);
                        form.find('select[name="parent_id"]').val(response.data.parent_id || '');

                        /*
                            كان المحدِّدُ `[value="${is_main}"]` — والخانةُ قيمتُها
                            `1` دائماً، فمركزٌ فرعيٌّ (`is_main=0`) لا يطابق شيئاً
                            ولا تُمسَح الخانةُ من فتحةٍ سابقة: تُفتَح شاشةُ فرعٍ
                            بخانةٍ مؤشَّرة.
                        */
                        form.find('input[name="is_main"]').prop('checked', !response.data.parent_id);

                        // المركزُ لا يصلح أباً لنفسه، فلا يُعرض في قائمة آبائه
                        form.find('select[name="parent_id"] option').each(function () {
                            $(this).prop('hidden', this.value === String(nodeId));
                        });

                        $('#editAccountModal').modal('show');
                    })
                    .fail(function () {
                        Swal.fire({ title: 'خطأ!', text: 'حدث خطأ أثناء جلب البيانات.', icon: 'error' });
                    });
            });

            $('#editAccountForm').on('submit', function (e) {
                e.preventDefault();

                const nodeId = $('#editAccountModal').data('node-id');

                if (!nodeId) {
                    Swal.fire({ title: 'خطأ!', text: 'رقم المركز غير موجود.', icon: 'error' });
                    return;
                }

                const form = this;

                $.ajax({
                    url: '/cost_centers/' + nodeId + '/update',
                    type: 'PUT',
                    data: $(form).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                })
                    .done(function (response) {
                        if (!response.success) {
                            Swal.fire({ title: 'خطأ!', text: response.message || 'حدث خطأ.', icon: 'error' });
                            return;
                        }

                        Swal.fire({
                            title: 'تم التعديل!',
                            text: response.message || 'تم تعديل البيانات بنجاح.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false,
                        });

                        /*
                            كان الصفُّ يُعاد بناؤه هنا من `response.data.code`
                            و`balance` و`balance_type` — و`update` لا يردّ
                            `data` أصلاً، ولمراكز التكلفة لا رصيد. فترمي
                            الشيفرةُ عند أوّل سطر، فلا يُغلق المودال ولا يُحدَّث
                            شيء. والشجرةُ تُعاد من الخادم بدل ذلك.
                        */
                        $('#editAccountModal').modal('hide');
                        form.reset();

                        window.reloadCostCenterTree();
                        refreshParentOptions();
                    })
                    .fail(function (xhr) {
                        showValidationErrors(xhr, 'حدث خطأ أثناء التعديل.');
                    });
            });

            // ═══════════════════ الحذف ═══════════════════

            $(document).on('click', '.delete-center', function (event) {
                event.stopPropagation();

                const id = $(this).data('id');
                const name = $(this).data('name');
                const children = Number($(this).data('children')) || 0;

                Swal.fire({
                    title: 'حذف «' + name + '»؟',
                    // عددُ الفروع يُقال قبل الحذف لا بعده: حذفُ أبٍ يمسّ ذرّيّتَه
                    html: children
                        ? '<div dir="rtl">تحته <strong>' + children + '</strong> فرعاً. لن تتمكن من استعادته بعد الحذف.</div>'
                        : '<div dir="rtl">لن تتمكن من استعادته بعد الحذف.</div>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'نعم، احذفه!',
                    cancelButtonText: 'إلغاء',
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: '/cost_centers/' + id + '/delete',
                        type: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    })
                        .done(function (response) {
                            Swal.fire({
                                title: 'تم الحذف!',
                                text: response.message || 'تم حذف المركز بنجاح.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false,
                            });

                            expanded.delete(String(id));
                            writeState(expanded);

                            window.reloadCostCenterTree();
                            refreshParentOptions();
                        })
                        .fail(function (xhr) {
                            showValidationErrors(xhr, 'حدث خطأ أثناء الحذف. الرجاء المحاولة مرة أخرى.');
                        });
                });
            });

            // ═══════════════════ قائمة الآباء ═══════════════════

            /*
                القائمةُ كانت تُملأ من `/cost_centers/parents` — الجذورَ وحدها.
                فلا يمكن جعلُ مركزٍ فرعاً تحت فرع، والشجرةُ في القاعدة تقبل
                أيَّ عمق. وصارت من الشجرة كلِّها، بإزاحةٍ تُظهر العمق.
            */
            function refreshParentOptions() {
                $.getJSON('/cost_centers/tree-rows').done(function (response) {
                    if (!response || !response.html) return;

                    const holder = document.createElement('tbody');
                    holder.innerHTML = response.html;

                    const options = ['<option value="">بلا أب — مركز رئيسي</option>'];

                    holder.querySelectorAll('tr.stk-tree__row').forEach(function (row) {
                        const pad = '   '.repeat(Number(row.dataset.level) || 0);
                        const label = pad + (row.dataset.name || '') +
                            (row.dataset.code ? ' (' + row.dataset.code + ')' : '');

                        options.push('<option value="' + row.dataset.id + '">' + label + '</option>');
                    });

                    $('select[name="parent_id"]').each(function () {
                        const current = $(this).val();
                        $(this).html(options.join('')).val(current);
                    });
                });
            }

            $(document).ready(function () {
                applyState();
                refreshParentOptions();
            });
        })();
    </script>
@endsection
