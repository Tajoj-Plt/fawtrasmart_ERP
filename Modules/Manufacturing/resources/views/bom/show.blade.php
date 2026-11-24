@extends('manufacturing::master')

@section('title', $productionMaterial->name)

@section('content')
    @php
        $items = $productionMaterialItems ?? collect();

        $rawMaterials = $items->whereNotNull('raw_product_id');
        $expenses = $items->whereNotNull('expenses_account_id');
        $workstations = $items->whereNotNull('workstation_id');
        $endLifeProducts = $items->whereNotNull('end_life_product_id');

        $rawTotal = $rawMaterials->sum('raw_total');
        $expensesTotal = $expenses->sum('expenses_total');
        $workstationsTotal = $workstations->sum('manu_total');

        /*
            كان ملخّص التكاليف يجمع «المهدورات» من مجموعة محطات العمل
            (`$workstations->sum('end_life_total')`) وهي دائماً صفر، ويطرحها
            كذلك من الإجمالي النهائي — فيخرج رقمٌ لا يطابق المكوّنات المعروضة.
            الصواب مجموعة المواد الهالكة. والقاعدة: مواد + مصروفات + تشغيل − هالك
            — وهي ما يحسبه الخادم عند الحفظ في `last_total_cost`.
        */
        $endLifeTotal = $endLifeProducts->sum('end_life_total');
        $finalTotal = $rawTotal + $expensesTotal + $workstationsTotal - $endLifeTotal;

        // نوعُ التكلفة باسمه لا برقمه — كان العمود يعرض «1» و«2»
        $costTypes = [1 => 'مبلغ ثابت', 2 => 'بناءً على الكمية', 3 => 'معادلة'];

        /*
            وحدةُ سطر المادة الخام: اسمُ وحدة المنتج المحفوظة، وإلّا معاملُ
            التحويل وحده (وحدةُ التصنيف لا معرّفَ لها في السطر)، وإلّا
            الوحدة الأساسية.
        */
        $unitLabel = function ($item) {
            $name = optional(optional(optional($item->rawUnit)->categoryUnit)->unit)->name;
            $factor = (float) ($item->raw_conversion_factor ?: 1);

            if ($name) {
                return $name . ($factor != 1.0 ? ' × ' . ($factor + 0) : '');
            }

            return $factor != 1.0 ? 'وحدة × ' . ($factor + 0) : 'الوحدة الأساسية';
        };

        // المكوّنات الأربعة: عنوان وأيقونة ولون وأعمدة
        $sections = [
            [
                'key' => 'raw',
                'title' => 'المواد الخام',
                'icon' => 'package',
                'tone' => 'primary',
                'rows' => $rawMaterials,
                'total' => $rawTotal,
                'headers' => ['المنتج', 'الوحدة', 'الكمية', 'سعر الوحدة', 'المرحلة', 'الإجمالي'],
            ],
            [
                'key' => 'expenses',
                'title' => 'المصروفات',
                'icon' => 'dollar-sign',
                'tone' => 'warning',
                'rows' => $expenses,
                'total' => $expensesTotal,
                'headers' => ['الحساب', 'نوع التكلفة', 'المبلغ', 'المرحلة', 'الوصف', 'الإجمالي'],
            ],
            [
                'key' => 'workstations',
                'title' => 'محطات العمل والتشغيل',
                'icon' => 'settings',
                'tone' => 'success',
                'rows' => $workstations,
                'total' => $workstationsTotal,
                'headers' => ['محطة العمل', 'وقت التشغيل (ساعة)', 'تكلفة الساعة', 'نوع التكلفة', 'المرحلة', 'الوصف', 'الإجمالي'],
            ],
            [
                'key' => 'endlife',
                // هوالكُ ونواتجُ ثانوية — كان العنوان «المنتجات النهائية» وهي ليست كذلك
                'title' => 'المواد الهالكة',
                'icon' => 'trash-2',
                'tone' => 'info',
                'rows' => $endLifeProducts,
                'total' => $endLifeTotal,
                'headers' => ['المنتج', 'الكمية', 'سعر الوحدة', 'المرحلة', 'الإجمالي'],
            ],
        ];
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('Bom.edit', $productionMaterial->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> تعديل
            </a>

            {{--
                حُذف زرّ «إنهاء الإنتاج» — المرحلة ١.

                كان يُرسل إلى `Bom.finish`، وهي تصرف مواداً وتُنتج منتجاً تاماً
                وتُرحّل قيداً من داخل شاشة **قائمة المواد** — وهي تعريفٌ لا
                عملية. الإنهاء موضعه أمر التصنيع وحده.
            --}}

            <button type="button" class="btn btn-outline-danger" id="deleteBtn"
                    data-id="{{ $productionMaterial->id }}" data-name="{{ $productionMaterial->name }}">
                <i class="feather icon-trash-2"></i> حذف
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => $productionMaterial->name,
            'icon' => 'layers',
            'sub' => 'قائمة مواد إنتاج ' . (optional($productionMaterial->product)->name ?? '—'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قوائم المواد' => route('BOM.index'),
                $productionMaterial->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- ══════════ الخلاصة ══════════ --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-box"></i></span>
                <span class="stk-metric__value">{{ optional($productionMaterial->product)->name ?? '—' }}</span>
                <span class="stk-metric__label">المنتج الرئيسي</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-hash"></i></span>
                <span class="stk-metric__value stk-num">{{ number_format($productionMaterial->quantity, 2) }}</span>
                <span class="stk-metric__label">كمية الإنتاج</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num">@money($productionMaterial->last_total_cost)</span>
                <span class="stk-metric__label">آخر تكلفة إجمالية</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-toggle-right"></i></span>
                <span class="stk-metric__value">
                    <span class="stk-state stk-state--{{ $productionMaterial->status == 1 ? 'on' : 'off' }}">
                        {{ $productionMaterial->status == 1 ? 'نشط' : 'غير نشط' }}
                    </span>
                    @if ($productionMaterial->default == 1)
                        <span class="badge badge-light-info">افتراضية</span>
                    @endif
                </span>
                <span class="stk-metric__label">الحالة</span>
            </div>
        </div>

        {{-- ══════════ التبويبات ══════════ --}}
        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#home" role="tab">
                            <i class="feather icon-info"></i> المعلومات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#materials" role="tab">
                            <i class="feather icon-layers"></i> مكوّنات الإنتاج
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#activate" role="tab">
                            <i class="feather icon-activity"></i> سجلّ النشاط
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                {{-- ═════ المعلومات ═════ --}}
                <div class="tab-pane active" id="home" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-file-text"></i> بيانات القائمة</h4>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>الاسم</dt>
                                    <dd>{{ $productionMaterial->name }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>المنتج</dt>
                                    <dd>{{ optional($productionMaterial->product)->name ?? '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>كمية الإنتاج</dt>
                                    <dd class="stk-num">{{ number_format($productionMaterial->quantity, 2) }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الحساب</dt>
                                    <dd>{{ optional($productionMaterial->account)->name ?? '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>مسار الإنتاج</dt>
                                    <dd>{{ optional($productionMaterial->productionPath)->name ?? '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>أُنشئت بواسطة</dt>
                                    <dd>{{ optional($productionMaterial->createdByUser)->name ?? '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>آخر تحديث بواسطة</dt>
                                    <dd>{{ optional($productionMaterial->updatedByUser)->name ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- ═════ المكوّنات ═════ --}}
                <div class="tab-pane" id="materials" role="tabpanel">
                    @if ($items->count() > 0)
                        @foreach ($sections as $section)
                            @continue ($section['rows']->count() === 0)

                            <div class="card stk-table-card">
                                <div class="stk-table-head">
                                    <h6 class="stk-table-head__title">
                                        <i class="feather icon-{{ $section['icon'] }}"></i> {{ $section['title'] }}
                                        <span class="stk-count">{{ $section['rows']->count() }}</span>
                                    </h6>
                                    <span class="stk-balance stk-num">@money($section['total'])</span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                @foreach ($section['headers'] as $header)
                                                    <th class="{{ $loop->first ? '' : 'text-center' }}">{{ $header }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($section['rows'] as $item)
                                                <tr>
                                                    @switch ($section['key'])
                                                        @case ('raw')
                                                            <td>{{ optional($item->rawProduct)->name ?? '—' }}</td>
                                                            <td class="text-center">{{ $unitLabel($item) }}</td>
                                                            <td class="text-center stk-num">{{ (float) $item->raw_quantity }}</td>
                                                            <td class="text-center stk-num">@money($item->raw_unit_price)</td>
                                                            <td class="text-center">{{ optional($item->rawProductionStage)->stage_name ?? '—' }}</td>
                                                            <td class="text-center stk-num"><strong>@money($item->raw_total)</strong></td>
                                                            @break

                                                        @case ('expenses')
                                                            <td>{{ optional($item->expensesAccount)->name ?? '—' }}</td>
                                                            <td class="text-center">{{ $costTypes[$item->expenses_cost_type] ?? '—' }}</td>
                                                            <td class="text-center stk-num">@money($item->expenses_price)</td>
                                                            <td class="text-center">{{ optional($item->expensesProductionStage)->stage_name ?? '—' }}</td>
                                                            <td class="text-center">
                                                                <span class="stk-media__sub">{{ $item->expenses_description ?: '—' }}</span>
                                                            </td>
                                                            <td class="text-center stk-num"><strong>@money($item->expenses_total)</strong></td>
                                                            @break

                                                        @case ('workstations')
                                                            <td>{{ optional($item->workStation)->name ?? '—' }}</td>
                                                            <td class="text-center stk-num">{{ (float) $item->operating_time ?: '—' }}</td>
                                                            <td class="text-center stk-num">@money($item->manu_total_cost)</td>
                                                            <td class="text-center">{{ $costTypes[$item->manu_cost_type] ?? '—' }}</td>
                                                            <td class="text-center">{{ optional($item->workshopProductionStage)->stage_name ?? '—' }}</td>
                                                            <td class="text-center">
                                                                <span class="stk-media__sub">{{ $item->manu_description ?: '—' }}</span>
                                                            </td>
                                                            <td class="text-center stk-num"><strong>@money($item->manu_total)</strong></td>
                                                            @break

                                                        @case ('endlife')
                                                            <td>{{ optional($item->endLifeProduct)->name ?? '—' }}</td>
                                                            <td class="text-center stk-num">{{ (float) $item->end_life_quantity }}</td>
                                                            <td class="text-center stk-num">@money($item->end_life_unit_price)</td>
                                                            <td class="text-center">{{ optional($item->endLifeProductionStage)->stage_name ?? '—' }}</td>
                                                            <td class="text-center stk-num"><strong>@money($item->end_life_total)</strong></td>
                                                            @break
                                                    @endswitch
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="stk-total-row">
                                                <td colspan="{{ count($section['headers']) - 1 }}">
                                                    إجمالي {{ $section['title'] }}
                                                </td>
                                                <td class="text-center stk-num">@money($section['total'])</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endforeach

                        {{-- ملخّص التكاليف --}}
                        <div class="card stk-table-card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-bar-chart-2"></i> ملخّص التكاليف
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <tbody>
                                        @foreach ([
                                            ['المواد الخام', $rawTotal, '+'],
                                            ['المصروفات', $expensesTotal, '+'],
                                            ['عمليات التصنيع', $workstationsTotal, '+'],
                                            ['المواد الهالكة (تُطرح)', $endLifeTotal, '−'],
                                        ] as [$label, $value, $sign])
                                            <tr>
                                                <td>{{ $label }}</td>
                                                <td class="text-center" style="width:44px">{{ $sign }}</td>
                                                <td class="text-end stk-num" style="width:24%">@money($value)</td>
                                            </tr>
                                        @endforeach

                                        <tr class="stk-total-row">
                                            <td colspan="2"><strong>إجمالي التكلفة النهائية</strong></td>
                                            <td class="text-end stk-num stk-balance">@money($finalTotal)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="card">
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-layers"></i></div>
                                    <h5 class="stk-empty__title">لا مكوّنات</h5>
                                    <p class="stk-empty__hint">لم تُضَف مكوّنات إنتاج لهذه القائمة بعد</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ═════ سجلّ النشاط ═════ --}}
                <div class="tab-pane" id="activate" role="tabpanel">
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
                                    <p class="stk-empty__hint">تظهر هنا كل عملية على هذه القائمة</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            $('#deleteBtn').on('click', function () {
                const id = $(this).data('id');
                const name = $(this).data('name');

                Swal.fire({
                    title: 'حذف قائمة المواد؟',
                    text: name,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (! result.isConfirmed) return;

                    $.ajax({
                        url: '{{ route('Bom.destroy', ':id') }}'.replace(':id', id),
                        method: 'POST',
                        data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') },
                    })
                        // الرفضُ يصل 422 برسالته (قائمةٌ مستعملة في أوامر أو خطط) فيقع في `fail`
                        .done(() => window.location.href = '{{ route('BOM.index') }}')
                        .fail(xhr => Swal.fire({
                            icon: 'error',
                            title: 'تعذّر الحذف',
                            text: (xhr.responseJSON && xhr.responseJSON.message) || '',
                        }));
                });
            });
        });
    </script>
@endsection
