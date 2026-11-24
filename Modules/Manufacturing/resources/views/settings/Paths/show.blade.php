@extends('manufacturing::master')

@section('title', $path->name)

@section('content')
    @php $stages = $path->stages ?? collect(); @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('manufacturing.paths.edit', $path->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> تعديل
            </a>
            <button type="button" class="btn btn-outline-danger"
                    data-toggle="modal" data-bs-toggle="modal"
                    data-target="#deletePathModal" data-bs-target="#deletePathModal">
                <i class="feather icon-trash-2"></i> حذف
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => $path->name,
            'icon' => 'git-merge',
            'sub' => 'مسار إنتاج #' . $path->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'مسارات الإنتاج' => route('manufacturing.paths.index'),
                $path->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-hash"></i></span>
                <span class="stk-metric__value stk-num">{{ $path->code }}</span>
                <span class="stk-metric__label">الكود</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__value stk-num">{{ $stages->count() }}</span>
                <span class="stk-metric__label">عدد المراحل</span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#stages" role="tab">
                            <i class="feather icon-git-merge"></i> المراحل
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#activity" role="tab">
                            <i class="feather icon-activity"></i> سجلّ النشاط
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                <div class="tab-pane active" id="stages" role="tabpanel">
                    <div class="card stk-table-card">
                        @if ($stages->count() > 0)
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-list"></i> مراحل المسار
                                    <span class="stk-count">{{ $stages->count() }}</span>
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:60px">#</th>
                                            <th>اسم المرحلة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($stages as $stage)
                                            <tr>
                                                <td class="stk-num text-muted">{{ $loop->iteration }}</td>
                                                <td><span class="stk-media__title">{{ $stage->stage_name }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-git-merge"></i></div>
                                    <h5 class="stk-empty__title">لا مراحل</h5>
                                    <p class="stk-empty__hint">أضف مراحل لهذا المسار من شاشة التعديل</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            @if (isset($logs) && count($logs) > 0)
                                <ul class="stk-timeline">
                                    @foreach ($logs as $date => $dayLogs)
                                        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp

                                        <li class="stk-timeline__day">
                                            <span>{{ $day->translatedFormat('l، d F Y') }}</span>
                                        </li>

                                        @foreach ($dayLogs as $log)
                                            @continue (! $log)

                                            <li class="stk-timeline__item">
                                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                                    <i class="feather icon-edit-3"></i>
                                                </span>

                                                <div class="stk-timeline__body">
                                                    <p class="stk-timeline__head">
                                                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                        <span class="stk-timeline__time stk-num">
                                                            <i class="feather icon-clock"></i>
                                                            {{ $log->created_at->format('H:i:s') }}
                                                        </span>
                                                    </p>

                                                    <div class="stk-timeline__text">
                                                        @include('manufacturing::partials.log-description', ['log' => $log])
                                                    </div>

                                                    <p class="stk-timeline__meta">
                                                        <span>
                                                            <i class="feather icon-map-pin"></i>
                                                            {{ $log->user->branch->name ?? 'فرع غير معروف' }}
                                                        </span>
                                                    </p>
                                                </div>
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                    <h5 class="stk-empty__title">لا نشاط مسجَّل</h5>
                                    <p class="stk-empty__hint">تظهر هنا كل عملية على هذا المسار</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deletePathModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف مسار الإنتاج</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">سيُحذف المسار <strong>{{ $path->name }}</strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    {{-- نموذج DELETE برمز CSRF بدل رابط GET يُنفَّذ بمجرّد فتحه --}}
                    <form method="POST" action="{{ route('manufacturing.paths.delete', $path->id) }}" class="d-inline">
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
