@extends('hr::master')

@section('title')
    الورديات
@stop

@php
    $arabicDays = [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ];
    $workingCount = $shift->days->where('working_day', 1)->count();
@endphp

@section('page-actions')
    @permission('shifts.edit')
        <a href="{{ route('shift_management.edit', $shift->id) }}" class="btn btn-primary">
            <i class="fas fa-edit"></i> تعديل
        </a>
    @endpermission
    @permission('shifts.destroy')
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $shift->id }}">
                        <i class="fas fa-trash me-2"></i>حذف
                    </a>
                </li>
            </ul>
        </div>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $shift->name,
        'icon' => 'clock',
        'crumbs' => ['الرئيسية' => url('/'), 'الورديات' => route('shift_management.index'), $shift->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-clock"></i></span>
                    <h4 class="mb-25">{{ $shift->name }}</h4>
                    <p class="stk-help mt-0 mb-1">وردية رقم #{{ $shift->id }}</p>
                    <span class="stk-state stk-state--on">{{ $shift->type == 1 ? 'أساسي' : 'متقدم' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">أيام العمل</span>
                        <span class="stk-metric__value">{{ $workingCount }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">أيام الراحة</span>
                        <span class="stk-metric__value">{{ $shift->days->count() - $workingCount }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>النوع</dt>
                            <dd>{{ $shift->type == 1 ? 'أساسي — أوقاتٌ واحدة لكل الأيام' : 'متقدم — أوقاتٌ لكل يوم' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أيام العمل</dt>
                            <dd>
                                {{ $shift->days->where('working_day', 1)->map(fn ($d) => $arabicDays[$d->day] ?? $d->day)->implode('، ') ?: '—' }}
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أُنشئ / آخر تعديل</dt>
                            <dd class="stk-num">{{ optional($shift->created_at)->format('Y-m-d H:i') ?? '—' }} / {{ optional($shift->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" aria-controls="home" role="tab" aria-selected="true">
                                <i class="feather icon-calendar"></i> أيام العمل
                                <span class="stk-count">{{ $shift->days->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activate-tab" data-toggle="tab" href="#activate" aria-controls="activate" role="tab" aria-selected="false">
                                <i class="feather icon-activity"></i> سجل النشاطات
                                <span class="stk-count">{{ $logs->flatten()->count() }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane active" id="home" aria-labelledby="home-tab" role="tabpanel">
                        @if ($shift->days->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>اليوم</th>
                                            <th>نوع اليوم</th>
                                            <th>بداية الوردية</th>
                                            <th>نهاية الوردية</th>
                                            <th>تسجيل الدخول</th>
                                            <th>تسجيل الخروج</th>
                                            <th>فترة السماح</th>
                                            <th>حساب التأخير</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($shift->days as $key)
                                            <tr>
                                                <td class="font-weight-bold">{{ $arabicDays[$key->day] ?? $key->day }}</td>
                                                <td>
                                                    <span class="stk-state stk-state--{{ $key->working_day ? 'on' : 'off' }}">{{ $key->working_day ? 'يوم عمل' : 'يوم راحة' }}</span>
                                                </td>
                                                <td class="stk-num">{{ $key->start_time }}</td>
                                                <td class="stk-num">{{ $key->end_time }}</td>
                                                <td class="stk-num text-nowrap">{{ $key->login_start_time }} — {{ $key->login_end_time }}</td>
                                                <td class="stk-num text-nowrap">{{ $key->logout_start_time }} — {{ $key->logout_end_time }}</td>
                                                <td class="stk-num text-nowrap">{{ $key->grace_period }} دقيقة</td>
                                                <td>
                                                    @if ($key->delay_calculation == 1)
                                                        بعد موعد بداية الوردية + مهلة التأخير
                                                    @else
                                                        من موعد بداية الوردية
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                                    <h5 class="stk-empty__title">لا أيام معرَّفة</h5>
                                    <p class="stk-empty__hint">عرّف أيام العمل وأوقاتها من «تعديل».</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="activate" aria-labelledby="activate-tab" role="tabpanel">
                        <div class="card-body">
                            @include('hr::organizational_structure.partials._activity', ['logs' => $logs])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @permission('shifts.destroy')
        <div class="modal fade" id="modal_DELETE{{ $shift->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">حذف {{ $shift->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">هل انت متاكد من انك تريد الحذف ؟</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <form method="POST" action="{{ route('shift_management.delete', $shift->id) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> تأكيد</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endpermission
</div>
@endsection
