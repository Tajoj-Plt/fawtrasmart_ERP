@extends('stock::master')

@section('title', 'مجموعات المنتجات')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'مجموعات المنتجات',
            'icon' => 'grid',
            'sub' => 'شجرة تجميع الأصناف لتسهيل الفرز والتقارير',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات المخزون' => route('product_settings.index'),
                'المجموعات' => null,
            ],
            'actions' => hasPermission('stock.product_groups.create')
                ? '<a href="' . route('product_groups.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> مجموعة جديدة</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-table-card">
            @if (count($groups) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-git-merge"></i> شجرة المجموعات
                        <span class="stk-count">{{ count($groups) }}</span>
                    </h6>

                    {{-- طيّ الشجرة كلها أو بسطها: بديل الضغط على كل عقدة --}}
                    <div class="d-flex" style="gap:.4rem">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="expand_all">
                            <i class="feather icon-maximize-2"></i> بسط الكل
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="collapse_all">
                            <i class="feather icon-minimize-2"></i> طيّ الكل
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 stk-tree">
                        <thead>
                            <tr>
                                <th style="width:40%">اسم المجموعة</th>
                                <th class="text-center" style="width:12%">الكود</th>
                                <th class="text-center" style="width:10%">المستوى</th>
                                <th class="text-center" style="width:14%">التصنيفات</th>
                                <th class="text-center" style="width:11%">الحالة</th>
                                <th class="text-center" style="width:13%">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groups as $group)
                                @include('stock::product_groups.partials.tree-row', ['group' => $group, 'level' => 0])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-grid"></i></div>
                        <h5 class="stk-empty__title">لا توجد مجموعات</h5>
                        <p class="stk-empty__hint">ابدأ بإنشاء مجموعة لتنظيم أصنافك في شجرة</p>
                        @permission('stock.product_groups.create')
                        <a href="{{ route('product_groups.create') }}" class="btn btn-primary">
                            <i class="feather icon-plus"></i> إضافة مجموعة
                        </a>
                        @endpermission
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- نافذة الحذف — واحدة تُعاد تعبئتها بدل واحدة لكل صفّ --}}
    <div class="modal fade" id="deleteGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف المجموعة</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">ستُحذف المجموعة <strong id="del_name"></strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                    <div class="alert alert-warning mt-3 mb-0 d-none" id="del_warn">
                        <i class="feather icon-alert-triangle"></i>
                        <span id="del_warn_text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    <form method="POST" id="del_form" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-trash-2"></i> حذف نهائي
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            /* خريطة الأبناء: الطيّ والبسط يمسّان الذرّية كلها لا الأبناء المباشرين */
            const kids = {};
            document.querySelectorAll('tr.stk-tree__row').forEach(r => {
                const p = r.dataset.parent;
                if (p) (kids[p] = kids[p] || []).push(r);
            });

            function setOpen(row, open) {
                row.dataset.expanded = open ? 'true' : 'false';
                const btn = row.querySelector('.stk-tree__toggle');
                if (btn) {
                    btn.classList.toggle('is-open', open);
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
            }

            function show(id, open) {
                (kids[id] || []).forEach(child => {
                    child.hidden = !open;
                    // الطيّ يُخفي الذرّية كلها؛ البسط يكشف مستوىً واحداً
                    if (!open) {
                        setOpen(child, false);
                        show(child.dataset.id, false);
                    }
                });
            }

            $(document).on('click', '.stk-tree__toggle', function () {
                const row = this.closest('tr');
                const open = row.dataset.expanded !== 'true';
                setOpen(row, open);
                show(row.dataset.id, open);
            });

            $('#expand_all').on('click', function () {
                document.querySelectorAll('tr.stk-tree__row').forEach(r => {
                    r.hidden = false;
                    if (r.dataset.hasChildren === 'true') setOpen(r, true);
                });
            });

            $('#collapse_all').on('click', function () {
                document.querySelectorAll('tr.stk-tree__row').forEach(r => {
                    if (r.dataset.level !== '0') r.hidden = true;
                    if (r.dataset.hasChildren === 'true') setOpen(r, false);
                });
            });

            /* تبديل الحالة */
            $(document).on('click', '.toggle-status', function () {
                const $b = $(this).prop('disabled', true);
                $.ajax({
                    url: '{{ url("/stock/product_groups/toggle-status") }}/' + $b.data('id'),
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: r => r.success ? location.reload() : $b.prop('disabled', false),
                    error: () => $b.prop('disabled', false),
                });
            });

            /* الحذف */
            $(document).on('click', '.delete-group', function () {
                const id = $(this).data('id'),
                      name = $(this).data('name'),
                      children = parseInt($(this).data('children'), 10) || 0;

                $('#del_name').text(name);
                $('#del_form').attr('action', '{{ url("/stock/product_groups/delete") }}/' + id);

                $('#del_warn').toggleClass('d-none', children === 0);
                if (children) {
                    $('#del_warn_text').text('تحتها ' + children + ' مجموعة فرعية');
                }

                $('#deleteGroupModal').modal('show');
            });
        });
    </script>
@endsection
