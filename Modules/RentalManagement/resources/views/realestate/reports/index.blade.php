@extends('rentalmanagement::realestate.master')

@section('title', 'مركز التقارير')

@section('content')
    @php
        use Modules\RentalManagement\Services\ReportService;
        $catalog = ReportService::catalog();
    @endphp

    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'مركز التقارير',
            'icon' => 'bar-chart-2',
            'sub' => 'تقارير المحفظة والعقود والمالية والصيانة — كلّ رقمٍ فيها قابلٌ للتنقيب إلى معاملاته',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'التقارير' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @forelse ($groups as $group => $reports)
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-folder"></i> {{ $group }}</h4>
                    <span class="stk-count">{{ $reports->count() }}</span>
                </div>
                <div class="card-body">
                    <div class="stk-tiles">
                        @foreach ($reports as $item)
                            <a href="{{ route('realestate.reports.show', $item['key']) }}" class="stk-tile">
                                <span class="stk-tile__icon"><i class="feather icon-{{ $item['icon'] }}"></i></span>
                                <span class="stk-tile__body">
                                    <span class="stk-tile__title d-block">
                                        {{ $item['title'] }}
                                        @if ($item['financial']) <span class="badge badge-light-warning">مالي</span> @endif
                                    </span>
                                    <span class="stk-tile__hint d-block">{{ $item['description'] }}</span>
                                    @if (! empty($item['needs'])) <span class="stk-tile__hint d-block"><i class="feather icon-user"></i> يتطلّب اختيار عميل</span> @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                @include('rentalmanagement::realestate.components.empty', ['icon' => 'bar-chart-2', 'title' => 'لا تقارير متاحة', 'hint' => 'لا تملك صلاحية أيّ تقريرٍ في هذا القسم'])
            </div>
        @endforelse

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-bookmark"></i> تقاريري المحفوظة <span class="stk-count">{{ $saved->count() }}</span></h6>
            </div>

            @if ($saved->isEmpty())
                @include('rentalmanagement::realestate.components.empty', ['icon' => 'bookmark', 'title' => 'لا تقارير محفوظة', 'hint' => 'افتح تقريراً واضبط فلاتره ثم اختر «حفظ الإعداد» ليظهر هنا'])
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>التقرير</th>
                                <th>الفلاتر</th>
                                <th>تاريخ الحفظ</th>
                                <th class="text-center" style="width:120px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($saved as $item)
                                {{-- تقريرٌ أُزيل من الكتالوج أو مالي بلا صلاحية: يبقى السطر ظاهراً للحذف دون رابطٍ مكسور --}}
                                @php
                                    $known = $catalog[$item->report_key] ?? null;
                                    $allowed = $known && hasPermission($known['financial'] ? 'realestate.reports.financial' : 'realestate.reports.view');
                                    $savedFilters = (array) ($item->filters ?? []);
                                @endphp
                                <tr>
                                    <td>
                                        @if ($allowed)
                                            <a href="{{ route('realestate.reports.show', [$item->report_key] + $savedFilters) }}" class="font-weight-bold">{{ $item->name }}</a>
                                        @else
                                            <span class="font-weight-bold">{{ $item->name }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $known['title'] ?? $item->report_key }}</td>
                                    <td>
                                        @if (! empty($savedFilters['from_date']) || ! empty($savedFilters['to_date']))
                                            <span class="stk-tag stk-num">{{ $savedFilters['from_date'] ?? '…' }} ← {{ $savedFilters['to_date'] ?? '…' }}</span>
                                        @endif
                                        @php $extra = count(array_diff_key($savedFilters, ['from_date' => 1, 'to_date' => 1])); @endphp
                                        @if ($extra) <span class="stk-tag">+{{ $extra }} فلتر</span> @endif
                                    </td>
                                    <td class="stk-num">{{ $item->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="text-center">
                                        <div class="d-inline-flex" style="gap:.35rem">
                                            @if ($allowed)
                                                <a href="{{ route('realestate.reports.show', [$item->report_key] + $savedFilters) }}" class="btn btn-sm btn-outline-primary" title="فتح"><i class="feather icon-external-link"></i></a>
                                            @endif
                                            @permission('realestate.reports.export')
                                                <form method="POST" action="{{ route('realestate.reports.saved.destroy', $item->id) }}" onsubmit="return confirm('حذف التقرير المحفوظ؟');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف"><i class="feather icon-trash-2"></i></button>
                                                </form>
                                            @endpermission
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
