@extends('rentalmanagement::realestate.master')

@section('title', 'المجمعات')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $canEdit = hasPermission('realestate.buildings.edit');
        $canDelete = hasPermission('realestate.buildings.delete');
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('realestate.buildings.index') }}" class="btn btn-outline-secondary"><i class="feather icon-home"></i> المباني</a>
            @permission('realestate.buildings.create')
                <button type="button" class="btn btn-primary js-complex-form" data-url="{{ route('realestate.buildings.complexes.store') }}" data-method="POST"
                        data-title="مجمع جديد"><i class="feather icon-plus"></i> مجمع جديد</button>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'المجمعات',
            'icon' => 'layers',
            'sub' => 'المجمع حاويةٌ فوق المباني: كل عمارة تُسجَّل مبنىً وتُربط بمجمعها',
            'crumbs' => ['الرئيسية' => url('/'), 'المباني والمجمعات' => route('realestate.buildings.index'), 'المجمعات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> المجمعات <span class="stk-count">{{ $complexes->count() }}</span></h6>
            </div>
            @if ($complexes->isEmpty())
                @include('rentalmanagement::realestate.components.empty', ['icon' => 'layers', 'title' => 'لا مجمعات', 'hint' => 'أضف مجمعاً ثم اربط به المباني من نموذج المبنى'])
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>الرمز</th><th>الاسم</th><th>المدينة</th><th>الاستخدام</th><th class="text-center">المباني</th><th>أنشأه</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($complexes as $complex)
                                <tr>
                                    <td class="stk-num">{{ $complex->code }}</td>
                                    <td>{{ $complex->name_ar }}</td>
                                    <td>{{ $complex->city ?: '—' }}</td>
                                    <td>{{ V::label(V::USAGE_TYPES, $complex->usage_type) }}</td>
                                    <td class="text-center stk-num">
                                        @if ($complex->buildings_count)
                                            <a href="{{ route('realestate.buildings.index', ['complex_id' => $complex->id, 'view' => 'table']) }}">{{ $complex->buildings_count }}</a>
                                        @else
                                            0
                                        @endif
                                    </td>
                                    <td>{{ $complex->creator->name ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($canEdit)
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-complex-form" title="تعديل" aria-label="تعديل {{ $complex->name_ar }}"
                                                    data-url="{{ route('realestate.buildings.complexes.update', $complex->id) }}" data-method="PUT" data-title="تعديل المجمع"
                                                    data-name="{{ $complex->name_ar }}" data-city="{{ $complex->city }}" data-usage="{{ $complex->usage_type }}"><i class="feather icon-edit"></i></button>
                                        @endif
                                        @if ($canDelete)
                                            @if ($complex->buildings_count)
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="فيه مبانٍ — انقلها أو افصلها عنه أولاً"><i class="feather icon-trash-2"></i></button>
                                            @else
                                                <a href="#" class="btn btn-sm btn-outline-danger re-action" data-url="{{ route('realestate.buildings.complexes.destroy', $complex->id) }}"
                                                   data-method="DELETE" data-tone="danger" data-reload="1" data-confirm="حذف المجمع «{{ $complex->name_ar }}»؟" aria-label="حذف {{ $complex->name_ar }}"><i class="feather icon-trash-2"></i></a>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="complexModal" tabindex="-1" aria-labelledby="complexModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content re-ajax-form" id="complexForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="complexModalTitle">مجمع</h5>
                    <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="re-ajax-errors alert alert-danger d-none"></div>
                    <div class="form-group">
                        <label for="complex_name_ar" class="stk-req">اسم المجمع</label>
                        <input type="text" id="complex_name_ar" name="name_ar" class="form-control" maxlength="191">
                    </div>
                    <div class="form-group">
                        <label for="complex_city">المدينة</label>
                        <input type="text" id="complex_city" name="city" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group mb-0">
                        <label for="complex_usage_type">الاستخدام</label>
                        <select id="complex_usage_type" name="usage_type" class="form-control">
                            @foreach (V::USAGE_TYPES as $value => $meta) <option value="{{ $value }}">{{ $meta['label'] }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.ajax-form-scripts')
    <script>
        $(function () {
            // نافذة واحدة للإضافة والتعديل: العنوان والوجهة من الزرّ
            $(document).on('click', '.js-complex-form', function () {
                const $btn = $(this), $form = $('#complexForm');

                $form.data('url', $btn.data('url')).data('method', $btn.data('method'));
                $form.find('.re-ajax-errors').addClass('d-none').empty();
                $('#complexModalTitle').text($btn.data('title'));
                $('#complex_name_ar').val($btn.data('name') || '');
                $('#complex_city').val($btn.data('city') || '');
                $('#complex_usage_type').val($btn.data('usage') || 'residential');
                reModal($('#complexModal'), true);
            });
        });
    </script>
@endsection
