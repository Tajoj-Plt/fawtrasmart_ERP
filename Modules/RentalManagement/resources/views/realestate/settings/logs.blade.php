@extends('rentalmanagement::realestate.master')

@section('title', 'سجلّ نشاط العقارات')

@section('content')
    @php
        use Illuminate\Support\Str;

        $hasFilters = request()->hasAny(['type', 'user_id', 'from_date', 'to_date']) && collect(request()->only(['type', 'user_id', 'from_date', 'to_date']))->filter()->isNotEmpty();
        // العمود يُعرَض إن كان النموذج يحمله فعلاً — لا افتراض على مخطّط `logs`
        $showIp = $logs->isNotEmpty() && array_key_exists('ip_address', $logs->first()->getAttributes());
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if (Route::has('ActivityLog.index'))
                <a href="{{ route('ActivityLog.index') }}" class="btn btn-outline-secondary"><i class="feather icon-external-link"></i> سجلّ النشاط المركزي</a>
            @endif
            @permission('realestate.settings.view')
                <a href="{{ route('realestate.settings.index') }}" class="btn btn-outline-secondary"><i class="feather icon-settings"></i> الإعدادات</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'سجلّ نشاط العقارات',
            'icon' => 'activity',
            'sub' => 'كلّ ما سُجّل من عمليات على العقود والدفعات والوحدات وسائر سجلّات القسم',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'سجلّ النشاط' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($hasFilters) <span class="stk-count">فلاتر مفعّلة</span> @endif
                </div>

                <form method="GET" action="{{ route('realestate.settings.logs') }}" novalidate data-re-validate>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="type">القسم</label>
                                <select id="type" name="type" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($types as $value => $label) <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="user_id">المستخدم</label>
                                <select id="user_id" name="user_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="from_date">من تاريخ</label>
                                <input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="to_date">إلى تاريخ</label>
                                <input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('realestate.settings.logs') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</a>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-activity"></i> السجلّ <span class="stk-count">{{ number_format($logs->total()) }}</span></h6>
            </div>

            @if ($logs->isEmpty())
                @include('rentalmanagement::realestate.components.empty', ['icon' => 'activity', 'title' => 'لا نشاط مسجَّل', 'hint' => $hasFilters ? 'لا شيء يطابق معايير البحث الحالية' : 'لم تُسجَّل عمليات في قسم العقارات بعد'])
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:150px">الوقت</th>
                                <th style="width:170px">المستخدم</th>
                                <th style="width:130px">القسم</th>
                                <th>الوصف</th>
                                @if ($showIp) <th style="width:130px">عنوان IP</th> @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="re-num">
                                        {{ $log->created_at?->format('Y-m-d') ?? '—' }}
                                        <span class="text-muted d-block small">{{ $log->created_at?->format('H:i') }}</span>
                                    </td>
                                    <td>{{ $log->user->name ?? 'النظام' }}</td>
                                    <td><span class="badge badge-light-info">{{ $types[$log->type] ?? $log->type }}</span></td>
                                    {{-- الوصف ماركداون من OperationLog — يُهرَّب HTML الخام كما في components/timeline --}}
                                    <td class="re-log-text">{!! Str::markdown($log->description ?? '', ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</td>
                                    @if ($showIp) <td class="re-num text-muted" dir="ltr">{{ $log->ip_address ?? '—' }}</td> @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- تنقّل بروابط عادية تحفظ الفلاتر (withQueryString) — لا مكوّن ajax هنا --}}
                @include('stock::components.table-foot', ['paginator' => $logs, 'unit' => 'سجلاً'])
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <style>.re-log-text p { margin: 0; }</style>
@endsection
