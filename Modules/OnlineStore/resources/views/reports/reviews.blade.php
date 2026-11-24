@extends('onlinestore::master')

@section('title', 'تقرير التقييمات والمراجعات')

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
<style>
    .star-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: bold;
    }
    .star-5 { background-color: rgba(40, 199, 111, 0.15); color: #28c76f; }
    .star-4 { background-color: rgba(0, 207, 232, 0.15); color: #00cfe8; }
    .star-3 { background-color: rgba(255, 159, 67, 0.15); color: #ff9f43; }
    .star-2 { background-color: rgba(255, 159, 67, 0.15); color: #ff9f43; }
    .star-1 { background-color: rgba(234, 84, 85, 0.15); color: #ea5455; }
    .rating-bar { height: 8px; border-radius: 4px; background: #eee; overflow: hidden; }
    .rating-bar-fill { height: 100%; border-radius: 4px; transition: width 0.5s; }
</style>
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقرير التقييمات والمراجعات',
            'icon' => 'star',
            'sub' => 'رضا العملاء عن المنتجات وأسئلتهم',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => route('onlinestore.reports.index'),
                'التقييمات' => null,
            ],
        ])

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> الفترة الزمنية</h6>
                </div>
                <form method="GET">
                    <div class="row">
                        {{-- الفترة المحاسبية + مدًى حرّ — نفس الجزء المستعمل في تقارير الحسابات --}}
                        @include('partials.accounting-period-filter', [
                            'period' => $accountingPeriod ?? [],
                            'withDates' => true,
                            'fromName' => 'start_date',
                            'toName' => 'end_date',
                            'colClass' => 'col-md-4 col-sm-6',
                        ])
                    </div>
                    <div class="stk-filters__foot">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-filter"></i> تصفية
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-message-square"></i></span>
                <span class="stk-metric__label">إجمالي المراجعات</span>
                <span class="stk-metric__value">{{ number_format($totalReviews) }}</span>
                <small class="text-success">{{ $approvedReviews }} معتمدة</small>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">متوسط التقييم</span>
                <span class="stk-metric__value">{{ number_format($avgRating, 1) }} <i class="feather icon-star"></i></span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-help-circle"></i></span>
                <span class="stk-metric__label">الأسئلة</span>
                <span class="stk-metric__value">{{ number_format($totalQuestions) }}</span>
                <small class="text-info">{{ $answeredQuestions }} مجاب عليها</small>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">بانتظار الاعتماد</span>
                <span class="stk-metric__value">{{ $pendingReviews }}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> المراجعات اليومية</h6>
                    </div>
                    <div class="card-body">
                        <div id="dailyReviewsChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-bar-chart-2"></i> توزيع التقييمات</h6>
                    </div>
                    <div class="card-body">
                        @php $totalRatings = collect($reviewsByRating)->sum('count'); @endphp
                        @for ($i = 5; $i >= 1; $i--)
                            @php
                                $ratingCount = collect($reviewsByRating)->firstWhere('rating', $i)['count'] ?? 0;
                                $percentage = $totalRatings > 0 ? round(($ratingCount / $totalRatings) * 100) : 0;
                            @endphp
                            <div class="d-flex align-items-center mb-2">
                                <div style="width: 60px;">
                                    <span class="star-badge star-{{ $i }}">{{ $i }} <i class="feather icon-star" style="font-size: 12px;"></i></span>
                                </div>
                                <div class="flex-grow-1 mx-2">
                                    <div class="rating-bar">
                                        <div class="rating-bar-fill bg-{{ $i >= 4 ? 'success' : ($i >= 3 ? 'warning' : 'danger') }}"
                                            style="width: {{ $percentage }}%;"></div>
                                    </div>
                                </div>
                                <div style="width: 80px; text-align: left;">
                                    <span class="fw-bold">{{ $ratingCount }}</span>
                                    <small class="text-muted">({{ $percentage }}%)</small>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> المنتجات الأكثر تقييماً</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>المنتج</th>
                                    <th>المراجعات</th>
                                    <th>متوسط التقييم</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mostReviewedProducts as $product)
                                    <tr>
                                        <td class="fw-bold">{{ Str::limit($product->product->name ?? 'منتج محذوف', 30) }}</td>
                                        <td class="stk-num">{{ number_format($product->reviews_count) }}</td>
                                        <td>
                                            <span class="star-badge star-{{ round($product->avg_rating) }}">
                                                {{ number_format($product->avg_rating, 1) }} <i class="feather icon-star" style="font-size: 12px;"></i>
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> آخر المراجعات</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <tbody>
                                @forelse($latestReviews as $review)
                                    <tr>
                                        <td style="width: 200px;">
                                            <strong>{{ $review->client->full_name ?? 'زائر' }}</strong><br>
                                            <small class="text-muted">{{ Str::limit($review->product->name ?? '', 25) }}</small>
                                        </td>
                                        <td>
                                            <span class="star-badge star-{{ $review->rating }}">
                                                {{ $review->rating }} <i class="feather icon-star" style="font-size: 10px;"></i>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $review->created_at->diffForHumans() }}</small>
                                            @if (!$review->is_approved)
                                                <span class="badge badge-light-warning">بانتظار الموافقة</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">لا توجد مراجعات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-smile"></i> ملخص رضا العملاء</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 text-center border-end">
                        @php
                            $positiveReviews = collect($reviewsByRating)->whereIn('rating', [4, 5])->sum('count');
                            $positiveRate = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100) : 0;
                        @endphp
                        <h3 class="text-success fw-bolder">{{ $positiveRate }}%</h3>
                        <p class="mb-0">تقييمات إيجابية</p>
                        <small class="text-muted">(4-5 نجوم)</small>
                    </div>
                    <div class="col-md-3 col-6 text-center border-end">
                        @php
                            $neutralReviews = collect($reviewsByRating)->whereIn('rating', [3])->sum('count');
                            $neutralRate = $totalReviews > 0 ? round(($neutralReviews / $totalReviews) * 100) : 0;
                        @endphp
                        <h3 class="text-warning fw-bolder">{{ $neutralRate }}%</h3>
                        <p class="mb-0">تقييمات محايدة</p>
                        <small class="text-muted">(3 نجوم)</small>
                    </div>
                    <div class="col-md-3 col-6 text-center border-end">
                        @php
                            $negativeReviews = collect($reviewsByRating)->whereIn('rating', [1, 2])->sum('count');
                            $negativeRate = $totalReviews > 0 ? round(($negativeReviews / $totalReviews) * 100) : 0;
                        @endphp
                        <h3 class="text-danger fw-bolder">{{ $negativeRate }}%</h3>
                        <p class="mb-0">تقييمات سلبية</p>
                        <small class="text-muted">(1-2 نجوم)</small>
                    </div>
                    <div class="col-md-3 col-6 text-center">
                        @php $answerRate = $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100) : 0; @endphp
                        <h3 class="text-info fw-bolder">{{ $answerRate }}%</h3>
                        <p class="mb-0">معدل الرد على الأسئلة</p>
                        <small class="text-muted">({{ $answeredQuestions }}/{{ $totalQuestions }})</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/l10n/ar.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Flatpickr
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr-basic', { locale: 'ar', dateFormat: 'Y-m-d' });
    }

    // التحقق من ApexCharts
    if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts not loaded!');
        return;
    }

    // البيانات المحولة من Controller
    var dailyData = @json($dailyReviewsChart ?? []);

    console.log('Daily Reviews Data:', dailyData);

    // المراجعات اليومية
    var dailyReviewsChartEl = document.querySelector("#dailyReviewsChart");
    if (dailyReviewsChartEl && dailyData && dailyData.length > 0) {
        try {
            new ApexCharts(dailyReviewsChartEl, {
                series: [{
                    name: 'المراجعات',
                    data: dailyData.map(function(d) { return d.count || 0; })
                }, {
                    name: 'متوسط التقييم',
                    data: dailyData.map(function(d) { return d.avg_rating || 0; })
                }],
                chart: { type: 'area', height: 300, toolbar: { show: false } },
                xaxis: {
                    categories: dailyData.map(function(d) { return d.date; })
                },
                yaxis: [{
                    title: { text: 'عدد المراجعات' }
                }, {
                    opposite: true,
                    max: 5,
                    min: 0,
                    title: { text: 'متوسط التقييم' }
                }],
                colors: ['#7367f0', '#ff9f43'],
                fill: {
                    type: 'gradient',
                    gradient: { opacityFrom: 0.5, opacityTo: 0.1 }
                },
                stroke: { curve: 'smooth', width: 2 },
                dataLabels: { enabled: false },
                tooltip: {
                    y: {
                        formatter: function(val, { seriesIndex }) {
                            if (seriesIndex === 1) {
                                return val ? val.toFixed(1) + ' نجوم' : '0 نجوم';
                            }
                            return val + ' مراجعة';
                        }
                    }
                }
            }).render();
            console.log('Daily Reviews Chart rendered successfully');
        } catch (e) {
            console.error('Error rendering dailyReviewsChart:', e);
        }
    } else if (dailyReviewsChartEl) {
        dailyReviewsChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }
});
</script>
@endsection
