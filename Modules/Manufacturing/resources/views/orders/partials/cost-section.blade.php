{{--
    قسم تكلفة داخل أمر التصنيع — يخدم المواد الخام والمصروفات وعمليات
    التصنيع والمواد الهالكة.

    كانت الأربعة أربعة ملفات متطابقة البنية (بطاقة + جدول + ذيل إجمالي)
    يختلف كلٌّ منها في أعمدته وحدها.

    الوسائط:
      $rows     البنود المعروضة
      $title    عنوان القسم
      $icon     أيقونة feather
      $headers  رؤوس الأعمدة
      $total    إجمالي القسم
      $kind     مفتاح يحدّد أعمدة الصفّ
--}}

@php
    $costTypes = [1 => 'مبلغ ثابت', 2 => 'بناءً على الكمية', 3 => 'معادلة'];
@endphp

@if ($rows->count() > 0)
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-{{ $icon }}"></i> {{ $title }}
                <span class="stk-count">{{ $rows->count() }}</span>
            </h6>
            <span class="stk-balance stk-num">@money($total)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th class="{{ $loop->first ? '' : 'text-center' }}">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $item)
                        <tr>
                            @switch ($kind)
                                @case ('raw')
                                    <td><span class="stk-media__title">{{ optional($item->rawProduct)->name ?? '—' }}</span></td>
                                    <td class="text-center stk-num">@money($item->raw_unit_price)</td>
                                    <td class="text-center stk-num">{{ $item->raw_quantity }}</td>
                                    <td class="text-center">{{ optional($item->rawProductionStage)->stage_name ?? '—' }}</td>
                                    <td class="text-center stk-num"><strong>@money($item->raw_total)</strong></td>
                                    @break

                                @case ('expenses')
                                    <td><span class="stk-media__title">{{ optional($item->expensesAccount)->name ?? '—' }}</span></td>
                                    <td class="text-center">
                                        <span class="badge badge-light-primary">
                                            {{ $costTypes[$item->expenses_cost_type] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center stk-num">@money($item->expenses_price)</td>
                                    <td class="text-center">{{ optional($item->expensesProductionStage)->stage_name ?? '—' }}</td>
                                    <td class="text-center"><span class="stk-media__sub">{{ $item->expenses_description ?: '—' }}</span></td>
                                    <td class="text-center stk-num"><strong>@money($item->expenses_total)</strong></td>
                                    @break

                                @case ('operations')
                                    <td><span class="stk-media__title">{{ optional($item->workStation)->name ?? '—' }}</span></td>
                                    <td class="text-center">
                                        <span class="badge badge-light-primary">
                                            {{ $costTypes[$item->manu_cost_type] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center stk-num">{{ $item->operating_time ?: '—' }}</td>
                                    <td class="text-center">{{ optional($item->workshopProductionStage)->stage_name ?? '—' }}</td>
                                    <td class="text-center"><span class="stk-media__sub">{{ $item->manu_description ?: '—' }}</span></td>
                                    <td class="text-center stk-num"><strong>@money($item->manu_total_cost)</strong></td>
                                    @break

                                @case ('waste')
                                    <td><span class="stk-media__title">{{ optional($item->endLifeProduct)->name ?? '—' }}</span></td>
                                    <td class="text-center stk-num">@money($item->end_life_unit_price)</td>
                                    <td class="text-center stk-num">{{ $item->end_life_quantity }}</td>
                                    <td class="text-center">{{ optional($item->endLifeProductionStage)->stage_name ?? '—' }}</td>
                                    <td class="text-center stk-num"><strong>@money($item->end_life_total)</strong></td>
                                    @break
                            @endswitch
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="stk-total-row">
                        <td colspan="{{ count($headers) - 1 }}">إجمالي {{ $title }}</td>
                        <td class="text-center stk-num">@money($total)</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
