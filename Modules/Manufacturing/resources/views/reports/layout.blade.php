@extends('manufacturing::master')

@section('title', $reportTitle)

@section('content')
    {{--
        هيكلٌ واحد لتقارير التصنيع الخمسة.

        كانت كل شاشة تقريرٍ في المشروع تكتب ترويستها وشريط مرشِّحاتها
        وجدولها من جديد — فيختلف موضع حقل التاريخ ونصّ زرّ البحث من تقريرٍ
        إلى آخر. هنا يُكتب مرّةً: الترويسة، والمرشِّحات، والطباعة، وذيل
        الترقيم. ولا يبقى للتقرير إلّا جدولُه.

        @section('table')   جدول التقرير
        @section('metrics') شريط الأرقام (اختياري)

        $reportTitle  عنوان التقرير
        $reportIcon   أيقونة feather بلا بادئة
        $reportSub    سطرٌ شارح
        $noDates      إخفاء مرشِّح المدى لتقريرٍ لا زمن له
    --}}
    @php
        $noDates = $noDates ?? false;
        $showStatus = $showStatus ?? true;
        $showProduct = $showProduct ?? true;
        // يمرّرهما `ManufacturingReportsController::render()`؛ والاحتياط هنا
        // كي لا يسقط القالب على تقريرٍ يُصيَّر من طريقٍ آخر
        $branches = $branches ?? collect();
        $currencies = $currencies ?? collect();
    @endphp

    @include('stock::components.page-head', [
        'title' => $reportTitle,
        'icon' => $reportIcon ?? 'bar-chart-2',
        'sub' => $reportSub ?? null,
        'crumbs' => [
            'الرئيسية' => url('/'),
            'التصنيع' => route('manufacturing.dashboard'),
            'التقارير' => route('manufacturing.reports.index'),
            $reportTitle => null,
        ],
    ])

    <div class="content-body">
        <section id="manufacturing-report">

            {{-- ══════════ المرشِّحات ══════════ --}}
            <div class="card mb-2 d-print-none">
                <div class="card-body py-2">
                    <form method="GET" class="row align-items-end" style="gap:.5rem 0;">
                        @unless ($noDates)
                            <div class="col-md-2 col-6">
                                <label class="form-label mb-0" style="font-size:.78rem;">من تاريخ</label>
                                <input type="date" name="from" value="{{ $f['from'] }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2 col-6">
                                <label class="form-label mb-0" style="font-size:.78rem;">إلى تاريخ</label>
                                <input type="date" name="to" value="{{ $f['to'] }}" class="form-control form-control-sm">
                            </div>
                        @endunless

                        @if ($showStatus && ! $noDates)
                            <div class="col-md-2 col-6">
                                <label class="form-label mb-0" style="font-size:.78rem;">الحالة</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">الكلّ</option>
                                    @foreach ($statuses as $key => $label)
                                        <option value="{{ $key }}" @selected($f['status'] === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($showProduct && ! $noDates && $products->count())
                            <div class="col-md-2 col-6">
                                <label class="form-label mb-0" style="font-size:.78rem;">الصنف</label>
                                <select name="product_id" class="form-control form-control-sm">
                                    <option value="">الكلّ</option>
                                    @foreach ($products as $id => $name)
                                        <option value="{{ $id }}" @selected($f['product_id'] === (int) $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- «تابع لـ» — المُنتقي المتدرّج نفسُه في شاشات القوائم.
                             مرشِّحُ العملة أُزيل بطلب المستخدم. --}}
                        @include('manufacturing::partials.scope-filters', ['ajax' => true])

                        <div class="col-md-2 col-8">
                            <label class="form-label mb-0" style="font-size:.78rem;">بحث</label>
                            <input type="text" name="q" value="{{ $f['q'] }}" class="form-control form-control-sm"
                                placeholder="الاسم أو الكود">
                        </div>

                        <div class="col-md-2 col-4 d-flex" style="gap:.4rem;">
                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="feather icon-search"></i> عرض
                            </button>
                            <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary" title="إلغاء المرشِّحات">
                                <i class="feather icon-x"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="طباعة">
                                <i class="feather icon-printer"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @hasSection('metrics')
                @yield('metrics')
            @endif

            {{-- ══════════ الجدول ══════════ --}}
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-{{ $reportIcon ?? 'bar-chart-2' }}"></i>
                        {{ $reportTitle }}
                        <span class="stk-count">{{ number_format($rows->total()) }}</span>
                    </h6>
                    @unless ($noDates)
                        <span class="text-muted d-print-none" style="font-size:.78rem;">
                            {{ $f['from'] }} — {{ $f['to'] }}
                        </span>
                    @endunless
                </div>

                <div class="table-responsive">
                    @yield('table')
                </div>

                @if ($rows->hasPages())
                    <div class="stk-table-foot d-print-none">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
