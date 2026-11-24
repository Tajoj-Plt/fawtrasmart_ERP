@extends('client::master')

@section('title')
    تبريراتي
@stop

{{-- client::master لا يملك @yield('css') — @push('styles') هو ما يقرؤه فعلاً (master.blade.php:12) --}}
@push('styles')
    <style>
        .empty-state {
            text-align: center;
            padding: 40px 0;
        }
        .empty-state i {
            font-size: 3.5rem;
            color: #b8c2cc;
            margin-bottom: 15px;
        }
    </style>
    <!-- إضافة مكتبة SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- ترويسة الصفحة -->
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تبريراتي</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{route('dashboard_sales.index')}}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">تبريراتي</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        <!-- بطاقة العنوان -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-list-alt me-1"></i>
                        جميع تبريراتي
                        @if(!$justifications->isEmpty())
                            <span class="badge bg-primary ms-1">{{ $justifications->total() }}</span>
                        @endif
                    </h4>
                </div>
            </div>
        </div>

        {{-- فترات الإعفاء/التبرير التي منحتها الإدارة — سجلٌّ تاريخيّ لا
             يتغيّر بتغيّر صلاحياتك الحالية، راجع App\Models\VisitJustification. --}}
        @if(isset($periods) && $periods->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-calendar-check me-1"></i>
                    فترات الإعفاء/التبرير الممنوحة لك
                    <span class="badge bg-primary ms-1">{{ $periods->count() }}</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>العميل</th>
                                <th>من تاريخ</th>
                                <th>إلى تاريخ</th>
                                <th>السبب</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($periods as $period)
                                <tr>
                                    <td>{{ $period->client->trade_name ?? 'غير محدد' }}</td>
                                    <td>{{ $period->date_from->format('Y-m-d') }}</td>
                                    <td>{{ $period->date_to->format('Y-m-d') }}</td>
                                    <td class="text-wrap" style="max-width: 250px;">{{ $period->reason }}</td>
                                    <td>
                                        @if($period->isApproved())
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-25"></i>معتمد</span>
                                        @elseif($period->isRejected())
                                            <span class="badge bg-danger"><i class="fas fa-times-circle me-25"></i>مرفوض</span>
                                        @else
                                            <span class="badge bg-warning"><i class="fas fa-clock me-25"></i>بانتظار الاعتماد</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- نموذج البحث -->
        <div class="card">
            <div class="card-body">
                <form action="{{ route('employee.visit-justifications.index') }}" method="GET">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="status">حالة التبرير</label>
                            <select name="status" class="form-control select2">
                                <option value="">-- جميع الحالات --</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>بانتظار الموافقة</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>معتمد</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>مرفوض</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="client">العميل</label>
                            <input type="text" name="client" class="form-control" value="{{ request('client') }}" placeholder="اسم العميل أو الكود">
                        </div>
                        <div class="col-md-4">
                            <label for="date">التاريخ</label>
                            <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fa fa-search"></i> بحث
                        </button>
                        <a href="{{ route('employee.visit-justifications.index') }}" class="btn btn-outline-warning">
                            <i class="fa fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- جدول التبريرات -->
        <div class="card">
            <div class="card-body">
                @if($justifications->isEmpty())
                    <!-- حالة فارغة -->
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h4 class="mb-1">ممتاز!</h4>
                        <p class="mb-0">لا توجد تبريرات مقدمة في الوقت الحالي.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العميل</th>
                                    <th>اليوم</th>
                                    <th>التاريخ</th>
                                    <th>التبرير</th>
                                    <th>الحالة</th>
                                    <th style="width: 10%">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($justifications as $index => $justification)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <!-- معلومات العميل -->
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong class="mb-25">{{ $justification->client->trade_name ?? 'غير محدد' }}</strong>
                                                <small class="text-muted">
                                                    <i class="fas fa-hashtag me-25"></i>
                                                    {{ $justification->client->code ?? '---' }}
                                                </small>
                                            </div>
                                        </td>

                                        <!-- اليوم -->
                                        <td>
                                            <span class="badge bg-light-primary">{{ $justification->day_of_week }}</span>
                                        </td>

                                        <!-- التاريخ -->
                                        <td>
                                            <div class="d-flex flex-column">
                                                <small class="fw-bold">{{ $justification->year }}</small>
                                                <small class="text-muted">الأسبوع {{ $justification->week_number }}</small>
                                            </div>
                                        </td>

                                        <!-- التبرير -->
                                        <td>
                                            <div class="text-wrap" style="max-width: 200px;">
                                                {{ Str::limit($justification->justification, 50) }}
                                            </div>
                                        </td>

                                        <!-- الحالة -->
                                        <td class="text-center">
                                            @if($justification->isJustificationApproved())
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-25"></i>
                                                    معتمد
                                                </span>
                                            @elseif($justification->isJustificationRejected())
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times-circle me-25"></i>
                                                    مرفوض
                                                </span>
                                            @else
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock me-25"></i>
                                                    بانتظار الموافقة
                                                </span>
                                            @endif
                                        </td>

                                        <!-- الإجراءات -->
                                        <td>
                                            <div class="btn-group">
                                                <div class="dropdown">
                                                    <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                        type="button" id="dropdownMenuButton303" data-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false"></button>
                                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton303">
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('employee.visit-justifications.show', $justification->id) }}">
                                                                <i class="fa fa-eye me-2 text-info"></i>عرض
                                                            </a>
                                                        </li>
                                                        @if($justification->isJustificationPending())
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('employee.visit-justifications.edit', $justification->id) }}">
                                                                <i class="fa fa-edit me-2 text-warning"></i>تعديل
                                                            </a>
                                                        </li>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $justifications->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <!-- إضافة مكتبة SweetAlert2 قبل كود JavaScript الخاص بنا -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // عرض رسائل الجلسة عند وجودها
            @if(session('success'))
                Swal.fire({
                    title: 'تم بنجاح!',
                    text: "{{ session('success') }}",
                    icon: 'success',
                    confirmButtonText: 'حسناً'
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    title: 'خطأ!',
                    text: "{{ session('error') }}",
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
            @endif
        });
    </script>
@endsection