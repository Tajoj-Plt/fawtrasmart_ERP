@extends('stock::master')

@section('title', 'ورقة الجرد — ' . $storehouse->name)

@section('content')
    {{--
        ورقة الجرد تُطبع وتُملأ بخطّ اليد: عمودا «الكمية» و«ملاحظات» يُتركان
        فارغين عمداً. لذلك تُرسَم مربّعاتٍ للكتابة لا خلايا فارغة يظنّها
        القارئ بياناتٍ ناقصة.

        وحُذف من نهاية الملف حرف «a» شارد كان يُطبع في أسفل كل صفحة.
    --}}

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'ورقة الجرد',
            'icon' => 'clipboard',
            'sub' => $storehouse->name . ' — تُطبع وتُملأ يدوياً أثناء الجرد',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستودعات' => route('storehouse.index'),
                $storehouse->name => route('storehouse.show', $storehouse->id),
                'ورقة الجرد' => null,
            ],
            'actions' => '<button type="button" id="printReport" class="btn btn-outline-secondary">'
                . '<i class="feather icon-printer"></i> طباعة</button>'
                . '<button type="button" id="exportExcel" class="btn btn-outline-secondary">'
                . '<i class="feather icon-download"></i> تصدير Excel</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-table-card">
            {{-- ترويسة تظهر عند الطباعة وحدها: الورقة المطبوعة بلا سياق لا تُقرأ --}}
            <div class="stk-print-head">
                <h3>ورقة الجرد — {{ $storehouse->name }}</h3>
                <p>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</p>
            </div>

            @if (count($products) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-package"></i> الأصناف
                        <span class="stk-count">{{ count($products) }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="reportTable">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th class="text-center" style="width:15%">الكود</th>
                                <th>الصنف</th>
                                <th style="width:16%">التصنيف</th>
                                <th class="text-center" style="width:12%">العدد بالنظام</th>
                                <th class="text-center" style="width:12%">العدد الفعلي</th>
                                <th style="width:18%">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $index => $row)
                                <tr>
                                    <td class="stk-num text-muted">{{ $index + 1 }}</td>
                                    <td class="text-center stk-num">{{ optional($row->product)->barcode ?: '—' }}</td>
                                    <td>
                                        <span class="stk-media__title">{{ optional($row->product)->name ?? 'منتج محذوف' }}</span>
                                    </td>
                                    <td>{{ optional(optional($row->product)->category)->name ?? '—' }}</td>
                                    <td class="text-center stk-num stk-balance">{{ number_format($row->quantity) }}</td>
                                    <td><span class="stk-writein"></span></td>
                                    <td><span class="stk-writein"></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-clipboard"></i></div>
                        <h5 class="stk-empty__title">لا أصناف في هذا المستودع</h5>
                        <p class="stk-empty__hint">لا شيء يُجرَد بعد</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#reportTable',
                fileName: 'ورقة_الجرد',
                title: 'ورقة الجرد',
                subTitle: @json($storehouse->name),
                numericColumns: [0, 2],
                cards: [
                    { title: 'عدد الأصناف', value: @json((string) count($products)), unit: ' صنف' },
                ],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 1,
            });
        });
    </script>

@endsection
