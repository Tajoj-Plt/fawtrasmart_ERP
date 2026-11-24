{{--
    إدارة تبريرات/إعفاءات الزيارات — شاشةٌ موحَّدة بتبويبين:
      - «فترات التبرير/الإعفاء» (VisitJustification): الإدارة الكاملة —
        الأصل الجديد الذي يحلّ مشكلة إعادة فتح فترةٍ سبق اعتمادها عند تغيّر
        صلاحية الموظف، لأنها سجلٌّ تاريخي بمعزلٍ عن الصلاحية الحالية.
      - «تبريرات الزيارات الفردية»: مراجعة التبرير النصّي الذي يقدّمه الموظف
        نفسه على صفّ زيارةٍ واحدة حين يُحجَب بمعرفة CheckIncompleteVisits —
        آليتها لم تتغيّر، فقط استفادت من استثناء الفترات المعتمدة.
--}}
@extends('client::master')

@section('title')
    إدارة تبريرات الزيارات
@stop

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .empty-state { text-align: center; padding: 40px 0; }
        .empty-state i { font-size: 3.5rem; color: #b8c2cc; margin-bottom: 15px; }
    </style>
@endpush

@section('content')
@include('client::partials.settings-theme')

<div class="cl-scope">
    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::components.page-head', [
        'title' => 'إدارة تبريرات الزيارات',
        'icon' => 'alert-circle',
        'sub' => 'فترات إعفاء/تبريرٍ كاملة للموظف مع عميلٍ ونطاق تاريخ، ومراجعة تبريرات الزيارات الفردية.',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'العملاء' => route('clients.index'),
            'تبريرات الزيارات' => null,
        ],
    ])

    @include('client::components.guide', [
        'key' => 'visit-justifications.index',
        'open' => false,
        'title' => 'ما الفرق بين القسمين؟',
        'lead' => '«فترات التبرير/الإعفاء» عملٌ إداري: تمنح موظفاً إعفاءً لفترةٍ كاملة (شهر، أسبوع، يوم) مع عميلٍ بعينه — ويبقى هذا الإعفاء صالحاً لتلك الفترة حتى لو تغيّرت صلاحيات الموظف لاحقاً.',
        'blocks' => [
            [
                'تداخل فترتين معتمدتين يُعَدّ الفترة بينهما مغطّاةً بالكامل — لا حاجة لتبريرٍ إضافي.',
                'محاولة إضافة فترةٍ تُغطّى بالكامل بتبريرٍ سابق معتمَد تُرفَض برسالةٍ واضحة.',
                '«تبريرات الزيارات الفردية» تبقى كما كانت: نصٌّ يقدّمه الموظف بنفسه عن زيارةٍ فائتة واحدة من شاشة «تبريراتي».',
            ],
        ],
    ])

    <div class="content-body">
        {{-- بطاقات إحصائيات فترات التبرير --}}
        <div class="row mb-3">
            <div class="col-md-4">
                <a href="{{ route('admin.visit-justifications.index', ['status' => 'pending']) }}" class="text-decoration-none">
                    <div class="card {{ request('status') == 'pending' || !request('status') ? 'border-warning' : '' }}" style="border-width: 2px;">
                        <div class="card-body">
                            <h3 class="mb-0">{{ $periodStats['pending'] ?? 0 }}</h3>
                            <small class="text-muted">فترات بانتظار الاعتماد</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.visit-justifications.index', ['status' => 'approved']) }}" class="text-decoration-none">
                    <div class="card {{ request('status') == 'approved' ? 'border-success' : '' }}" style="border-width: 2px;">
                        <div class="card-body">
                            <h3 class="mb-0">{{ $periodStats['approved'] ?? 0 }}</h3>
                            <small class="text-muted">فترات معتمدة</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.visit-justifications.index', ['status' => 'rejected']) }}" class="text-decoration-none">
                    <div class="card {{ request('status') == 'rejected' ? 'border-danger' : '' }}" style="border-width: 2px;">
                        <div class="card-body">
                            <h3 class="mb-0">{{ $periodStats['rejected'] ?? 0 }}</h3>
                            <small class="text-muted">فترات مرفوضة</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <ul class="nav nav-tabs mb-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#periods-tab" role="tab">
                    <i class="fas fa-calendar-alt me-1"></i> فترات التبرير/الإعفاء
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#rows-tab" role="tab">
                    <i class="fas fa-clipboard-check me-1"></i> تبريرات الزيارات الفردية
                    @if(($rowStats['pending'] ?? 0) > 0)
                        <span class="badge bg-warning ms-1">{{ $rowStats['pending'] }}</span>
                    @endif
                </a>
            </li>
        </ul>

        <div class="tab-content">
            {{-- ═══════════════════ تبويب فترات التبرير/الإعفاء ═══════════════════ --}}
            <div class="tab-pane fade show active" id="periods-tab" role="tabpanel">

                <div class="card">
                    <div class="card-body d-flex justify-content-end gap-2">
                        @permission('clients_visit_justifications_create')
                        <a href="{{ route('admin.visit-justifications.bulk') }}" class="btn btn-outline-primary">
                            <i class="fas fa-layer-group"></i> تبرير جماعي بالفترة
                        </a>
                        <a href="{{ route('admin.visit-justifications.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> إضافة فترة تبرير/إعفاء
                        </a>
                        @endpermission
                    </div>
                </div>

                <div class="card cl-filters">
                    <div class="cl-filters__head">
                        <div class="cl-filters__title">
                            <i class="feather icon-sliders"></i>
                            <span>بحث وتصفية</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.visit-justifications.index') }}" method="GET">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label>الفرع</label>
                                    <select name="branch_id" class="form-control select2">
                                        <option value="">-- جميع الفروع --</option>
                                        @foreach($branches ?? [] as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }} @if($branch->is_main) (رئيسي) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>الموظف</label>
                                    <select name="employee_id" class="form-control select2">
                                        <option value="">-- جميع الموظفين --</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>العميل</label>
                                    <select name="client_id" class="form-control select2">
                                        <option value="">-- جميع العملاء --</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                                {{ $client->trade_name }} ({{ $client->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control select2">
                                        <option value="">-- كل الحالات --</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>بانتظار الاعتماد</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>معتمد</option>
                                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>مرفوض</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>من تاريخ</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                                <div class="col-md-2">
                                    <label>إلى تاريخ</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                                <div class="col-md-4">
                                    <label>بحث (السبب / اسم الموظف / اسم العميل)</label>
                                    <input type="text" name="search" class="form-control" value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="cl-filters__foot mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> بحث
                                </button>
                                <a href="{{ route('admin.visit-justifications.index') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i> إلغاء
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        @if($periods->isEmpty())
                            <div class="empty-state">
                                <i class="fas fa-calendar-check"></i>
                                <h4 class="mb-1">لا توجد فترات تبرير/إعفاء</h4>
                                <p class="mb-0">لم يُعثر على فتراتٍ مطابقة لبحثك.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>الموظف</th>
                                            <th>العميل</th>
                                            <th>من تاريخ</th>
                                            <th>إلى تاريخ</th>
                                            <th>السبب</th>
                                            <th>أُنشئت بواسطة</th>
                                            <th>الحالة</th>
                                            <th style="width: 12%">الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($periods as $period)
                                            <tr>
                                                <td>{{ $period->id }}</td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong>{{ $period->employee->name ?? 'غير محدد' }}</strong>
                                                        <small class="text-muted">{{ $period->employee->branch->name ?? '' }}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong>{{ $period->client->trade_name ?? 'غير محدد' }}</strong>
                                                        <small class="text-muted">{{ $period->client->code ?? '' }}</small>
                                                    </div>
                                                </td>
                                                <td>{{ $period->date_from->format('Y-m-d') }}</td>
                                                <td>{{ $period->date_to->format('Y-m-d') }}</td>
                                                <td class="text-wrap" style="max-width: 200px;">{{ $period->reason }}</td>
                                                <td><small>{{ $period->createdBy->name ?? '---' }}</small></td>
                                                <td>
                                                    @if($period->isApproved())
                                                        <span class="badge bg-success"><i class="fa fa-check me-1"></i>معتمد</span>
                                                    @elseif($period->isRejected())
                                                        <span class="badge bg-danger"><i class="fa fa-times me-1"></i>مرفوض</span>
                                                    @else
                                                        <span class="badge bg-warning"><i class="fa fa-clock me-1"></i>بانتظار الاعتماد</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn bg-gradient-info fa fa-ellipsis-v btn-sm" type="button"
                                                            id="periodActions{{ $period->id }}" data-toggle="dropdown" data-bs-toggle="dropdown"
                                                            aria-haspopup="true" aria-expanded="false"></button>
                                                        <div class="dropdown-menu" aria-labelledby="periodActions{{ $period->id }}">
                                                            @permission('clients_visit_justifications_view_all')
                                                            <a class="dropdown-item" href="{{ route('admin.visit-justifications.show', $period->id) }}">
                                                                <i class="fa fa-eye me-2 text-info"></i>عرض
                                                            </a>
                                                            @endpermission
                                                            @permission('clients_visit_justifications_edit')
                                                            <a class="dropdown-item" href="{{ route('admin.visit-justifications.edit', $period->id) }}">
                                                                <i class="fa fa-edit me-2 text-warning"></i>تعديل
                                                            </a>
                                                            @endpermission
                                                            @permission('clients_visit_justifications_approve')
                                                                @if(! $period->isApproved())
                                                                <button type="button" class="dropdown-item text-success period-approve-btn"
                                                                    data-id="{{ $period->id }}">
                                                                    <i class="fa fa-check me-2"></i>اعتماد
                                                                </button>
                                                                @endif
                                                                @if(! $period->isRejected())
                                                                <button type="button" class="dropdown-item text-danger period-reject-btn"
                                                                    data-id="{{ $period->id }}">
                                                                    <i class="fa fa-times me-2"></i>رفض
                                                                </button>
                                                                @endif
                                                            @endpermission
                                                            @permission('clients_visit_justifications_delete')
                                                            <button type="button" class="dropdown-item text-danger period-delete-btn"
                                                                data-id="{{ $period->id }}">
                                                                <i class="fa fa-trash me-2"></i>حذف
                                                            </button>
                                                            @endpermission
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-center mt-3">
                                {{ $periods->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════════════════ تبويب تبريرات الزيارات الفردية ═══════════════════ --}}
            <div class="tab-pane fade" id="rows-tab" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        @if($rowJustifications->isEmpty())
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h4 class="mb-1">ممتاز!</h4>
                                <p class="mb-0">لا توجد تبريرات فردية بانتظار الموافقة.</p>
                            </div>
                        @else
                            @permission('clients_visit_justifications_approve')
                            <div class="d-flex justify-content-end mb-3">
                                <button id="approve-all-btn" class="btn btn-primary btn-sm">
                                    <i class="fas fa-check-double me-1"></i> موافقة الكل
                                </button>
                            </div>
                            @endpermission
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>الموظف</th>
                                            <th>العميل</th>
                                            <th>التاريخ</th>
                                            <th>التبرير</th>
                                            <th>الحالة</th>
                                            <th style="width: 10%">الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rowJustifications as $index => $visit)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $visit->employee->name ?? 'غير محدد' }}</td>
                                                <td>{{ $visit->client->trade_name ?? 'غير محدد' }}</td>
                                                <td>{{ $visit->visit_date?->format('Y-m-d') ?? '---' }}</td>
                                                <td class="text-wrap" style="max-width: 200px;">{{ $visit->justification }}</td>
                                                <td><span class="badge bg-warning"><i class="fa fa-clock me-1"></i>معلّق</span></td>
                                                <td>
                                                    @permission('clients_visit_justifications_approve')
                                                    <div class="dropdown">
                                                        <button class="btn bg-gradient-info fa fa-ellipsis-v btn-sm" type="button"
                                                            id="rowActions{{ $visit->id }}" data-toggle="dropdown" data-bs-toggle="dropdown"></button>
                                                        <div class="dropdown-menu" aria-labelledby="rowActions{{ $visit->id }}">
                                                            <button class="dropdown-item text-success row-approve-btn" type="button"
                                                                data-id="{{ $visit->id }}"><i class="fa fa-check me-2"></i>موافقة</button>
                                                            <button class="dropdown-item text-danger row-reject-btn" type="button"
                                                                data-id="{{ $visit->id }}"><i class="fa fa-times me-2"></i>رفض</button>
                                                        </div>
                                                    </div>
                                                    @endpermission
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function confirmAndPost(url, text) {
        Swal.fire({
            title: 'هل أنت متأكد؟', text: text, icon: 'question', showCancelButton: true,
            confirmButtonColor: '#28C76F', cancelButtonColor: '#82868b',
            confirmButtonText: 'نعم', cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                const token = document.createElement('input');
                token.type = 'hidden'; token.name = '_token'; token.value = '{{ csrf_token() }}';
                form.appendChild(token);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function confirmAndDelete(url, text) {
        Swal.fire({
            title: 'تأكيد الحذف', text: text, icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#EA5455', cancelButtonColor: '#82868b',
            confirmButtonText: 'نعم، احذف', cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">
                                   <input type="hidden" name="_method" value="DELETE">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    document.querySelectorAll('.period-approve-btn').forEach(btn => btn.addEventListener('click', function () {
        confirmAndPost('{{ url("/admin/visit-justifications") }}/' + this.dataset.id + '/approve-period', 'سيتم اعتماد فترة التبرير — تصبح فعّالةً فوراً.');
    }));
    document.querySelectorAll('.period-reject-btn').forEach(btn => btn.addEventListener('click', function () {
        confirmAndPost('{{ url("/admin/visit-justifications") }}/' + this.dataset.id + '/reject-period', 'سيتم رفض فترة التبرير.');
    }));
    document.querySelectorAll('.period-delete-btn').forEach(btn => btn.addEventListener('click', function () {
        confirmAndDelete('{{ url("/admin/visit-justifications") }}/' + this.dataset.id, 'سيُحذف سجلّ فترة التبرير نهائياً — إن كانت معتمدة فقد تُطلَب تبريراتٌ للزيارات التي كانت تغطّيها من جديد.');
    }));
    document.querySelectorAll('.row-approve-btn').forEach(btn => btn.addEventListener('click', function () {
        confirmAndPost('{{ url("/admin/visit-justifications") }}/' + this.dataset.id + '/approve', 'سيتم اعتماد هذا التبرير.');
    }));
    document.querySelectorAll('.row-reject-btn').forEach(btn => btn.addEventListener('click', function () {
        confirmAndPost('{{ url("/admin/visit-justifications") }}/' + this.dataset.id + '/reject', 'سيتم رفض هذا التبرير.');
    }));

    const approveAllBtn = document.getElementById('approve-all-btn');
    if (approveAllBtn) {
        approveAllBtn.addEventListener('click', function () {
            confirmAndPost('{{ route("admin.visit-justifications.approve-all") }}', 'سيتم اعتماد كل التبريرات الفردية المعلّقة.');
        });
    }
});
</script>
@endsection
