@extends('client::master')

@section('title')
    تفاصيل التبرير
@stop

{{-- client::master لا يملك @yield('css') — @push('styles') هو ما يقرؤه فعلاً (master.blade.php:12) --}}
@push('styles')
    <style>
        .status-card {
            border-radius: 10px;
        }
        .status-icon {
            font-size: 2rem;
            margin-right: 15px;
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
                    <h2 class="content-header-title float-left mb-0">تفاصيل التبرير</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{route('dashboard_sales.index')}}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('employee.visit-justifications.index') }}">تبريراتي</a></li>
                            <li class="breadcrumb-item active">تفاصيل التبرير</li>
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
                        <i class="fas fa-file-alt me-1"></i>
                        تفاصيل التبرير للزيارة
                    </h4>

                    @if($justification->isJustificationPending())
                        <a href="{{ route('employee.visit-justifications.edit', $justification->id) }}" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i> تعديل التبرير
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- معلومات العميل والزيارة -->
        <div class="row">
            <!-- معلومات العميل -->
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-building text-primary me-1"></i>
                            معلومات العميل
                        </h5>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="fw-bold" style="width: 40%;">
                                    <i class="fas fa-user me-1"></i>اسم العميل:
                                </td>
                                <td>{{ $justification->client->trade_name ?? 'غير محدد' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">
                                    <i class="fas fa-hashtag me-1"></i>كود العميل:
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ $justification->client->code ?? '---' }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- معلومات الزيارة -->
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-calendar-check text-info me-1"></i>
                            معلومات الزيارة
                        </h5>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="fw-bold" style="width: 40%;">
                                    <i class="fas fa-calendar-day me-1"></i>اليوم:
                                </td>
                                <td>
                                    <span class="badge bg-light-primary">{{ $justification->day_of_week }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">
                                    <i class="fas fa-calendar-week me-1"></i>التاريخ:
                                </td>
                                <td>{{ $justification->year }} - الأسبوع {{ $justification->week_number }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- نص التبرير -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="fas fa-comment-alt text-warning me-1"></i>
                    نص التبرير
                </h5>
                <div class="alert alert-secondary mb-0">
                    <p class="mb-0" style="white-space: pre-wrap;">{{ $justification->justification }}</p>
                </div>
            </div>
        </div>

        <!-- حالة التبرير والإجراءات -->
        <div class="row mt-2">
            <!-- حالة التبرير -->
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            حالة التبرير
                        </h5>

                        @if($justification->isJustificationApproved())
                            <div class="alert alert-success d-flex align-items-start mb-0">
                                <div class="me-2" style="font-size: 2rem;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading mb-1">
                                        <i class="fas fa-thumbs-up me-1"></i>معتمد
                                    </h5>
                                    <p class="mb-1">
                                        تمت الموافقة على التبرير من قبل:
                                        <strong>{{ $justification->approvedBy->name ?? 'مدير النظام' }}</strong>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        تاريخ الموافقة: {{ $justification->updated_at->format('Y-m-d H:i') }}
                                    </small>
                                </div>
                            </div>
                        @elseif($justification->isJustificationRejected())
                            <div class="alert alert-danger d-flex align-items-start mb-0">
                                <div class="me-2" style="font-size: 2rem;">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading mb-1">
                                        <i class="fas fa-ban me-1"></i>مرفوض
                                    </h5>
                                    <p class="mb-1">
                                        تم رفض التبرير من قبل:
                                        <strong>{{ $justification->approvedBy->name ?? 'مدير النظام' }}</strong>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        تاريخ الرفض: {{ $justification->updated_at->format('Y-m-d H:i') }}
                                    </small>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning d-flex align-items-start mb-0">
                                <div class="me-2" style="font-size: 2rem;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading mb-1">
                                        <i class="fas fa-hourglass-half me-1"></i>بانتظار الموافقة
                                    </h5>
                                    <p class="mb-1">التبرير قيد المراجعة من قبل الإدارة</p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        تاريخ التقديم: {{ $justification->justification_date ? $justification->justification_date->format('Y-m-d H:i') : '---' }}
                                    </small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- بطاقة الإجراءات -->
            @if($justification->isJustificationPending())
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-cogs text-primary me-1"></i>
                                الإجراءات المتاحة
                            </h5>
                            <a href="{{ route('employee.visit-justifications.edit', $justification->id) }}"
                                class="btn btn-warning w-100">
                                <i class="fas fa-edit me-1"></i>
                                تعديل التبرير
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- زر العودة -->
        <div class="d-flex justify-content-start mt-4 pt-3 border-top">
            <a href="{{ route('employee.visit-justifications.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة إلى التبريرات
            </a>
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