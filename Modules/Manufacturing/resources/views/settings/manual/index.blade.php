@extends('manufacturing::master')

@section('title', 'الحالات اليدوية لأوامر التصنيع')

@section('content')
    @php $statuses = optional($order_manual_status)->manualOrderStatus ?? collect(); @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'الحالات اليدوية',
            'icon' => 'tool',
            'sub' => 'حالات إضافية يحدّدها المستخدم لأوامر التصنيع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات التصنيع' => route('Manufacturing.settings.index'),
                'الحالات اليدوية' => null,
            ],
            'actions' => '<button type="submit" form="general_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <form id="general_form" action="{{ route('Manufacturing.order_manual_status.update') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-toggle-right"></i> التفعيل</h4>
                </div>
                <div class="card-body">
                    <label class="stk-switch mb-0">
                        <input type="checkbox" name="active" value="1"
                               {{ optional($order_manual_status)->active == 1 ? 'checked' : '' }}>
                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                        <span class="stk-switch__label">
                            <span class="stk-switch__title">تفعيل الحالات اليدوية</span>
                            <span class="stk-switch__hint">تظهر هذه الحالات في أوامر التصنيع عند تفعيلها</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> الحالات
                        <span class="stk-count" id="rowsCount">{{ $statuses->count() }}</span>
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addRow">
                        <i class="feather icon-plus"></i> إضافة حالة
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>الاسم</th>
                                <th style="width:26%">اللون</th>
                                <th class="text-center" style="width:60px">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statuses as $index => $manualStatus)
                                <tr>
                                    <td class="stk-num text-muted row-number">{{ $index + 1 }}</td>
                                    <td>
                                        <input type="text" name="name[]" class="form-control" required
                                               value="{{ $manualStatus->name }}">
                                    </td>
                                    <td>
                                        {{-- منتقي لون المتصفّح بدل مكتبة spectrum الخارجية:
                                             حقلٌ واحد يحمل القيمة ويعرضها معاً. --}}
                                        <div class="stk-color">
                                            <input type="color" name="color[]" class="stk-color__input js-color"
                                                   value="{{ $manualStatus->color ?: '#9a4d40' }}">
                                            <span class="stk-color__value stk-num">{{ $manualStatus->color }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger js-remove"
                                                aria-label="حذف الحالة"><i class="feather icon-trash-2"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-body" id="itemsEmpty" {{ $statuses->count() ? 'hidden' : '' }}>
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-list"></i></div>
                        <h5 class="stk-empty__title">لا حالات يدوية</h5>
                        <p class="stk-empty__hint">اضغط «إضافة حالة» لإضافة أول حالة</p>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('Manufacturing.settings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            function renumber() {
                $('#itemsTable tbody tr').each(function (index) {
                    $(this).find('.row-number').text(index + 1);
                });

                const count = $('#itemsTable tbody tr').length;
                $('#rowsCount').text(count);
                document.getElementById('itemsEmpty').hidden = count > 0;
            }

            $('#addRow').on('click', function () {
                $('#itemsTable tbody').append(
                    '<tr>'
                    + '<td class="stk-num text-muted row-number"></td>'
                    + '<td><input type="text" name="name[]" class="form-control" required></td>'
                    + '<td><div class="stk-color">'
                    + '  <input type="color" name="color[]" class="stk-color__input js-color" value="#9a4d40">'
                    + '  <span class="stk-color__value stk-num">#9a4d40</span>'
                    + '</div></td>'
                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger js-remove"'
                    + '  aria-label="حذف الحالة"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                renumber();
            });

            /* الحذف بلا حدٍّ أدنى مصطنع: كان يرفض حذف آخر صفّ بحجّة
               «لا يمكنك حذف جميع الصفوف» — ومن أراد إلغاءها كلّها لا يستطيع. */
            $('#itemsTable').on('click', '.js-remove', function () {
                $(this).closest('tr').remove();
                renumber();
            });

            $('#itemsTable').on('input', '.js-color', function () {
                $(this).siblings('.stk-color__value').text(this.value);
            });

            renumber();
        });
    </script>
@endsection
