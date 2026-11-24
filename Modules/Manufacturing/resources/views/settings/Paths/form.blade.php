{{--
    نموذج مسار الإنتاج — مشترك بين الإنشاء والتعديل.

    كان الملفّان (426 و585 سطراً) نسختين من النموذج نفسه وجدول المراحل نفسه
    ومئة سطر جافاسكربت مكرّرة.

    الوسائط: $action، $path (عند التعديل)، $stages، $submitLabel
--}}

@php
    $path = $path ?? null;
    $stages = $stages ?? collect();
    $isEdit = (bool) $path;
@endphp

<form id="pathForm" action="{{ $action }}" method="POST">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-info"></i> بيانات المسار</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group mb-0">
                        <label for="name" class="stk-req">اسم المسار</label>
                        <input type="text" id="name" name="name" required
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $isEdit ? $path->name : '') }}"
                               placeholder="مثال: خطّ التجميع">
                        @error('name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label for="code" class="stk-req">الكود</label>
                        <input type="text" id="code" name="code" required
                               class="form-control text-center stk-num @error('code') is-invalid @enderror"
                               value="{{ old('code', $isEdit ? $path->code : ($serial_number ?? '')) }}">
                        @error('code')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- «تابع لـ» — الجهة التي يُنسب إليها المسار --}}
            <div class="row mt-1">
                @include('manufacturing::partials.affiliation-field', ['value' => $isEdit ? $path->branch_id : null])
            </div>
        </div>
    </div>

    {{-- ══════════ المراحل ══════════ --}}
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-git-merge"></i> مراحل المسار
                <span class="stk-count" id="rowsCount">{{ $stages->count() }}</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRow">
                <i class="feather icon-plus"></i> إضافة مرحلة
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>اسم المرحلة</th>
                        <th class="text-center" style="width:60px">حذف</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stages as $index => $stage)
                        <tr>
                            <td class="stk-num text-muted row-number">{{ $index + 1 }}</td>
                            <td>
                                <input type="text" name="stage_name[]" class="form-control" required
                                       value="{{ old('stage_name.' . $index, $stage->stage_name) }}">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove"
                                        aria-label="حذف المرحلة"><i class="feather icon-trash-2"></i></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body" id="itemsEmpty" {{ $stages->count() ? 'hidden' : '' }}>
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-git-merge"></i></div>
                <h5 class="stk-empty__title">لا مراحل</h5>
                <p class="stk-empty__hint">اضغط «إضافة مرحلة» لإضافة أول مرحلة إنتاجية</p>
            </div>
        </div>

        <div class="stk-form-foot">
            <span class="stk-help me-auto">ترتيب المراحل هنا هو ترتيب تنفيذها</span>
            <a href="{{ route('manufacturing.paths.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-check"></i> {{ $submitLabel }}
            </button>
        </div>
    </div>
</form>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    + '<td><input type="text" name="stage_name[]" class="form-control" required'
                    + '  placeholder="اسم المرحلة الإنتاجية"></td>'
                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger js-remove"'
                    + '  aria-label="حذف المرحلة"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                renumber();
                $('#itemsTable tbody tr:last input').trigger('focus');
            });

            $('#itemsTable').on('click', '.js-remove', function () {
                $(this).closest('tr').remove();
                renumber();
            });

            $('#pathForm').on('submit', function (e) {
                const named = $('#itemsTable tbody input[name="stage_name[]"]')
                    .filter(function () { return this.value.trim() !== ''; }).length;

                if (! named) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text: 'أضف مرحلة إنتاجية واحدة على الأقل' });
                }
            });

            @if (! $isEdit)
                if (! $('#itemsTable tbody tr').length) $('#addRow').trigger('click');
            @endif

            renumber();
        });
    </script>
@endpush
