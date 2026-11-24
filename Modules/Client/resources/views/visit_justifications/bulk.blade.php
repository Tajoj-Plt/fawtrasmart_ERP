@extends('client::master')

{{--
    تبرير جماعي بالفترة — بدل تبرير كل عميلٍ على حدة (500 زيارةٍ في شهرٍ
    واحد تعني 500 نموذجاً)، يختار المستخدم موظفاً وفترة تاريخ، فيكتشف
    النظام كل عميلٍ له زياراتٌ غير مكتملة ضمنها (نفس تعريف
    EmployeeClientVisit::needingJustificationQuery() المستعمَل في
    CheckIncompleteVisits)، ويُنشئ فترة تبريرٍ واحدة لكل عميلٍ مُختار
    بضغطةٍ واحدة.

    نفس نمط bulk_status.blade.php: تصفية → جلب AJAX → قائمة قابلة
    للتحديد → تطبيقٌ جماعي.
--}}

@section('title')
    تبرير جماعي بالفترة
@stop

@section('content')
<div class="cl-scope">
    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @include('client::components.page-head', [
        'title' => 'تبرير جماعي بالفترة',
        'icon' => 'calendar',
        'sub' => 'اختر الموظف والفترة — يكتشف النظام كل عميلٍ له زياراتٌ غير مكتملة ضمنها، وتُنشئ فترة تبريرٍ واحدة لكلٍّ منها دفعةً واحدة.',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'العملاء' => route('clients.index'),
            'تبريرات الزيارات' => route('admin.visit-justifications.index'),
            'تبرير جماعي' => null,
        ],
    ])

    <div class="content-body">
        <form id="bulkJustifyForm">
            {{-- ① الموظف والفترة --}}
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="fas fa-search me-1"></i> البحث عن الزيارات غير المكتملة</h4></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>الموظف <span class="text-danger">*</span></label>
                            <select id="employee_id" class="form-control select2" required>
                                <option value="">-- اختر الموظف --</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>من تاريخ <span class="text-danger">*</span></label>
                            <input type="date" id="date_from" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>إلى تاريخ <span class="text-danger">*</span></label>
                            <input type="date" id="date_to" class="form-control" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" id="searchBtn">
                            <i class="fas fa-search me-1"></i> بحث عن العملاء غير المكتملين
                        </button>
                    </div>
                </div>
            </div>

            {{-- ② العملاء المكتشَفون --}}
            <div class="card" id="resultsCard" style="display:none">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0"><i class="fas fa-users me-1"></i> العملاء غير المكتملين</h4>
                    <span class="badge badge-info" id="resultsCount">0 عميل</span>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">تحديد الكل</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNoneBtn">إلغاء التحديد</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width:40px"></th>
                                    <th>العميل</th>
                                    <th>الكود</th>
                                    <th class="text-center">عدد الزيارات غير المكتملة</th>
                                </tr>
                            </thead>
                            <tbody id="resultsBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ③ السبب والتطبيق --}}
            <div class="card" id="applyCard" style="display:none">
                <div class="card-header"><h4 class="card-title"><i class="fas fa-check-circle me-1"></i> سبب التبرير/الإعفاء وتنفيذه</h4></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label>سبب التبرير/الإعفاء <span class="text-danger">*</span></label>
                            <input type="text" id="reason" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-md-12">
                            <label>ملاحظات</label>
                            <textarea id="notes" class="form-control" rows="3" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-success" id="applyBtn" disabled>
                            <i class="fas fa-check me-1"></i> إنشاء فترات التبرير للعملاء المحدَّدين
                        </button>
                        <a href="{{ route('admin.visit-justifications.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    var clients = [];

    function updateApplyState() {
        var checked = $('#resultsBody input[type=checkbox]:checked').length;
        $('#applyBtn').prop('disabled', checked === 0);
    }

    $('#searchBtn').on('click', function () {
        var employeeId = $('#employee_id').val();
        var dateFrom = $('#date_from').val();
        var dateTo = $('#date_to').val();

        if (!employeeId || !dateFrom || !dateTo) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى اختيار الموظف والفترة كاملة.' });
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> جارٍ البحث...');

        $.ajax({
            url: '{{ route('admin.visit-justifications.bulk-search') }}',
            method: 'GET',
            data: { employee_id: employeeId, date_from: dateFrom, date_to: dateTo },
        }).done(function (response) {
            clients = response.clients || [];
            renderResults();
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'تعذّر البحث.';
            Swal.fire({ icon: 'error', title: 'خطأ', text: message });
        }).always(function () {
            btn.prop('disabled', false).html('<i class="fas fa-search me-1"></i> بحث عن العملاء غير المكتملين');
        });
    });

    function renderResults() {
        var body = $('#resultsBody').empty();

        if (!clients.length) {
            $('#resultsCard').show();
            $('#applyCard').hide();
            $('#resultsCount').text('0 عميل');
            body.append('<tr><td colspan="4" class="text-center text-muted py-3">لا عملاء بزياراتٍ غير مكتملة ضمن هذه الفترة لهذا الموظف.</td></tr>');
            return;
        }

        clients.forEach(function (client) {
            body.append(
                '<tr>' +
                '<td><input type="checkbox" class="client-check" value="' + client.id + '" checked></td>' +
                '<td>' + client.name + '</td>' +
                '<td>' + (client.code || '—') + '</td>' +
                '<td class="text-center">' + client.incomplete_count + '</td>' +
                '</tr>'
            );
        });

        $('#resultsCount').text(clients.length + ' عميل');
        $('#resultsCard').show();
        $('#applyCard').show();
        updateApplyState();
    }

    $('#resultsBody').on('change', '.client-check', updateApplyState);

    $('#selectAllBtn').on('click', function () {
        $('#resultsBody .client-check').prop('checked', true);
        updateApplyState();
    });

    $('#selectNoneBtn').on('click', function () {
        $('#resultsBody .client-check').prop('checked', false);
        updateApplyState();
    });

    $('#bulkJustifyForm').on('submit', function (e) {
        e.preventDefault();

        var clientIds = $('#resultsBody .client-check:checked').map(function () { return this.value; }).get();
        var reason = $('#reason').val();

        if (!clientIds.length) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى تحديد عميلٍ واحد على الأقل.' });
            return;
        }
        if (!reason) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'سبب التبرير مطلوب.' });
            return;
        }

        Swal.fire({
            title: 'تأكيد التبرير الجماعي',
            html: 'سيُنشأ فترة تبريرٍ لـ<strong>' + clientIds.length + '</strong> عميل. متابعة؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، أنشئ الفترات',
            cancelButtonText: 'إلغاء',
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            var form = $('<form>', { method: 'POST', action: '{{ route('admin.visit-justifications.bulk-store') }}' });
            form.append('{{ csrf_field() }}');
            form.append($('<input>', { type: 'hidden', name: 'employee_id', value: $('#employee_id').val() }));
            form.append($('<input>', { type: 'hidden', name: 'date_from', value: $('#date_from').val() }));
            form.append($('<input>', { type: 'hidden', name: 'date_to', value: $('#date_to').val() }));
            form.append($('<input>', { type: 'hidden', name: 'reason', value: reason }));
            form.append($('<input>', { type: 'hidden', name: 'notes', value: $('#notes').val() }));
            clientIds.forEach(function (id) {
                form.append($('<input>', { type: 'hidden', name: 'client_ids[]', value: id }));
            });
            $('body').append(form);
            form.submit();
        });
    });
});
</script>
@endsection
