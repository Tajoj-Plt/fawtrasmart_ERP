@extends('client::master')

@section('title')
    تفاصيل فترة تبرير/إعفاء
@stop

@section('content')
<div class="cl-scope">
    @include('client::components.page-head', [
        'title' => 'تفاصيل فترة تبرير/إعفاء #'.$period->id,
        'icon' => 'alert-circle',
        'sub' => '',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'العملاء' => route('clients.index'),
            'تبريرات الزيارات' => route('admin.visit-justifications.index'),
            'التفاصيل' => null,
        ],
    ])

    <div class="content-body">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    @if($period->isApproved())
                        <span class="badge bg-success"><i class="fa fa-check me-1"></i>معتمد</span>
                    @elseif($period->isRejected())
                        <span class="badge bg-danger"><i class="fa fa-times me-1"></i>مرفوض</span>
                    @else
                        <span class="badge bg-warning"><i class="fa fa-clock me-1"></i>بانتظار الاعتماد</span>
                    @endif
                </h4>
                <div class="d-flex gap-2">
                    @permission('clients_visit_justifications_edit')
                    <a href="{{ route('admin.visit-justifications.edit', $period->id) }}" class="btn btn-warning btn-sm">
                        <i class="fa fa-edit me-1"></i> تعديل
                    </a>
                    @endpermission
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>الموظف</strong>
                        <p class="mb-0">{{ $period->employee->name ?? 'غير محدد' }}</p>
                        <small class="text-muted">{{ $period->employee->branch->name ?? '' }}</small>
                    </div>
                    <div class="col-md-6">
                        <strong>العميل</strong>
                        <p class="mb-0">{{ $period->client->trade_name ?? 'غير محدد' }} ({{ $period->client->code ?? '---' }})</p>
                    </div>
                    <div class="col-md-6">
                        <strong>من تاريخ</strong>
                        <p class="mb-0">{{ $period->date_from->format('Y-m-d') }}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>إلى تاريخ</strong>
                        <p class="mb-0">{{ $period->date_to->format('Y-m-d') }}</p>
                    </div>
                    <div class="col-md-12">
                        <strong>السبب</strong>
                        <p class="mb-0">{{ $period->reason }}</p>
                    </div>
                    <div class="col-md-12">
                        <strong>الملاحظات</strong>
                        <p class="mb-0">{{ $period->notes ?: '---' }}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>أُنشئ بواسطة</strong>
                        <p class="mb-0">{{ $period->createdBy->name ?? '---' }}</p>
                        <small class="text-muted">{{ $period->created_at->format('Y-m-d H:i') }}</small>
                    </div>
                    <div class="col-md-6">
                        <strong>آخر تعديل</strong>
                        <p class="mb-0">{{ $period->updatedBy->name ?? '---' }}</p>
                        <small class="text-muted">{{ $period->updated_at->format('Y-m-d H:i') }}</small>
                    </div>
                    @if($period->approved_by)
                    <div class="col-md-6">
                        <strong>{{ $period->isRejected() ? 'رُفض بواسطة' : 'اعتُمد بواسطة' }}</strong>
                        <p class="mb-0">{{ $period->approvedBy->name ?? '---' }}</p>
                        <small class="text-muted">{{ $period->approved_at?->format('Y-m-d H:i') }}</small>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <a href="{{ route('admin.visit-justifications.index') }}" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-right me-1"></i> رجوع للقائمة
        </a>
    </div>
</div>
@endsection
