@extends('rentalmanagement::realestate.master')

@section('title', 'طلبات الصيانة')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $hasFilters = request()->hasAny(['category', 'building_id', 'assigned_to_user_id', 'charge_to', 'late']);
        $statusIcons = ['open' => 'inbox', 'waiting' => 'clock', 'in_progress' => 'tool', 'completed' => 'check-circle'];
        $maxCategory = max(1, (int) $topCategories->max());

        $kpiItems = [];
        foreach ($statusIcons as $status => $icon) {
            $kpiItems[] = ['label' => V::label(V::MAINTENANCE_STATUSES, $status), 'value' => number_format((int) ($counts[$status] ?? 0)),
                'icon' => $icon, 'tone' => V::tone(V::MAINTENANCE_STATUSES, $status), 'url' => route('realestate.maintenance.index', ['status' => $status])];
        }
        $kpiItems[] = ['label' => 'تكلفة الصيانة هذا الشهر', 'value' => \Modules\RentalManagement\Support\RealEstateMoney::format($monthCost), 'icon' => 'dollar-sign', 'tone' => 'warning', 'note' => 'الطلبات المكتملة خلال الشهر'];
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.maintenance.create')
                <a href="{{ route('realestate.maintenance.create', ['scope' => 'building']) }}" class="btn btn-outline-primary"><i class="feather icon-home"></i> صيانة مبنى</a>
                <a href="{{ route('realestate.maintenance.create', ['scope' => 'unit']) }}" class="btn btn-primary"><i class="feather icon-grid"></i> صيانة وحدة</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'طلبات الصيانة',
            'icon' => 'tool',
            'sub' => 'البلاغات من الفتح إلى الإغلاق: الإسناد والجدولة والتكاليف',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'طلبات الصيانة' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => $kpiItems])

        <div class="row">
            <div class="col-lg-5 col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-bar-chart-2"></i> أكثر الأعطال شيوعاً</h4></div>
                    <div class="card-body">
                        @forelse ($topCategories as $category => $count)
                            <div class="mb-1">
                                <div class="d-flex justify-content-between">
                                    <span><i class="feather icon-{{ V::MAINTENANCE_CATEGORIES[$category]['icon'] ?? 'tool' }} text-muted"></i> {{ V::label(V::MAINTENANCE_CATEGORIES, $category) }}</span>
                                    <span class="stk-num">{{ $count }}</span>
                                </div>
                                <div class="re-progress mt-50"><span style="width:{{ round($count / $maxCategory * 100) }}%"></span></div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">لا بلاغات مسجّلة بعد.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-7 col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-calendar"></i> الصيانة القادمة</h4></div>
                    <div class="card-body">
                        @if ($upcoming->isEmpty())
                            <p class="text-muted mb-0">لا زيارات صيانة مجدولة.</p>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($upcoming as $next)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-50" style="gap:.75rem">
                                        <span class="min-width-0">
                                            @if (hasPermission('realestate.maintenance.show'))
                                                <a href="{{ route('realestate.maintenance.show', $next->id) }}" class="stk-num">{{ $next->code }}</a>
                                            @else
                                                <span class="stk-num">{{ $next->code }}</span>
                                            @endif
                                            — {{ $next->title }}
                                            <small class="text-muted d-block">{{ $next->location_label }}</small>
                                        </span>
                                        <span class="badge badge-light-info stk-num">{{ $next->scheduled_at?->format('Y-m-d H:i') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters) <span class="stk-count">فلاتر مفعّلة</span> @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-bs-toggle="collapse"
                                data-target="#advancedSearchForm" data-bs-target="#advancedSearchForm" aria-expanded="false">
                            <i class="feather icon-sliders"></i> بحث متقدّم
                        </button>
                    </div>
                </div>

                <form id="searchForm">
                    <input type="hidden" name="scope" id="scope" value="{{ request('scope') }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="رقم الطلب أو العنوان أو الوصف…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::MAINTENANCE_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(request('status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="priority">الأولوية</label>
                                <select id="priority" name="priority" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::PRIORITIES as $value => $meta) <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="category">التصنيف</label>
                                    <select id="category" name="category" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::MAINTENANCE_CATEGORIES as $value => $meta) <option value="{{ $value }}" @selected(request('category') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="building_id">العقار</label>
                                    <select id="building_id" name="building_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) request('building_id') === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="assigned_to_user_id">المسؤول</label>
                                    <select id="assigned_to_user_id" name="assigned_to_user_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) request('assigned_to_user_id') === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="charge_to">التكلفة على</label>
                                    <select id="charge_to" name="charge_to" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::CHARGE_TO as $value => $meta) <option value="{{ $value }}" @selected(request('charge_to') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="custom-control custom-checkbox form-check mb-1">
                                    <input type="checkbox" class="custom-control-input form-check-input" id="late" name="late" value="1" @checked(request()->boolean('late'))>
                                    <label class="custom-control-label form-check-label" for="late">متجاوزة الموعد فقط</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- الصيانة قسمان: ما يخصّ وحدةً بعينها، وما يخصّ المبنى ومرافقه المشتركة --}}
        <ul class="nav nav-pills mb-1" id="scopeTabs">
            @foreach (['' => ['الكل', 'list', 'all'], 'unit' => ['صيانة الوحدات', 'grid', 'unit'], 'building' => ['صيانة المباني', 'home', 'building']] as $value => [$label, $icon, $key])
                <li class="nav-item">
                    <a href="{{ route('realestate.maintenance.index', array_filter(['scope' => $value])) }}" data-scope="{{ $value }}"
                       class="nav-link {{ (string) request('scope') === (string) $value ? 'active' : '' }}">
                        <i class="feather icon-{{ $icon }}"></i> {{ $label }} <span class="badge badge-pill badge-light-secondary stk-num">{{ number_format($scopeCounts[$key]) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-tool"></i> الطلبات <span class="stk-count">{{ $requests->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.maintenance.partials.table', ['requests' => $requests])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            // `reset()` يعيد المربّع إلى حالته عند التحميل — وقد يكون مؤشَّراً من الرابط؛ فيُفرَّغ قبل معالج `reList`
            $('#resetSearch').on('click', () => $('#late').removeAttr('checked').prop('checked', false));

            reList({ url: @json(route('realestate.maintenance.index')) });

            // التبويب فلترٌ كسائر الفلاتر: يُكتب في النموذج ثم يُرسَل
            $('#scopeTabs').on('click', 'a', function (e) {
                e.preventDefault();
                $('#scopeTabs a').removeClass('active');
                $(this).addClass('active');
                $('#scope').val($(this).data('scope'));
                $('#searchForm').trigger('submit');
            });

            $('#resetSearch').on('click', function () {
                $('#scope').val('');
                $('#scopeTabs a').removeClass('active').first().addClass('active');
            });
        });
    </script>
@endsection
