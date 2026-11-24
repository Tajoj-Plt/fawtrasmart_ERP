{{--
    جدول تقرير الملاحظات الشهرية — جزءٌ يُحمَّل عبر AJAX داخل
    `monthly-notes.blade.php` (`ItineraryController::monthlyNotesReport()`).

    ثلاث هيئات حسب `$viewType`:
      - matrix:  مصفوفة (١٢ عموداً شهرياً) لكل عنصر مُجمَّع (`$groupBy`)
      - summary: ملخّص عدد/مبلغ لكل عنصر مُجمَّع
      - detailed (افتراضي): سطرٌ واحد لكل (تاريخ + عميل + موظف)

    شكل `$reportData` موثّق أعلى كل دالّة توليدٍ في المتحكّم — راجعها قبل أي
    تعديل هنا فهي مصدر الحقيقة الوحيد لأسماء المفاتيح.
--}}

@if(empty($reportData))
    <div class="alert alert-info text-center py-4 mb-0">
        <i class="fas fa-info-circle me-2"></i> لا توجد بيانات ضمن الفترة والفلاتر المحدَّدة.
    </div>
@else
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle mb-0">
            @if($viewType === 'matrix')
                @php
                    $monthNames = ['1'=>'يناير','2'=>'فبراير','3'=>'مارس','4'=>'أبريل','5'=>'مايو','6'=>'يونيو','7'=>'يوليو','8'=>'أغسطس','9'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'];
                @endphp
                <thead class="table-light">
                    <tr>
                        <th>{{ $groupByOptions[$groupBy] ?? 'التصنيف' }}</th>
                        @foreach($monthNames as $m)
                            <th class="text-center">{{ $m }}</th>
                        @endforeach
                        <th class="text-center">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['name'] }}</strong>
                                @if(! empty($row['client_code']))
                                    <br><small class="text-muted">{{ $row['client_code'] }}</small>
                                @endif
                            </td>
                            @foreach($monthNames as $mKey => $mName)
                                <td class="text-center">
                                    @php $c = $row['months'][(int) $mKey] ?? 0; @endphp
                                    @if($c > 0)
                                        <span class="badge bg-primary">{{ $c }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center"><strong>{{ number_format($row['total']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>

            @elseif($viewType === 'summary')
                <thead class="table-light">
                    <tr>
                        <th>{{ $groupByOptions[$groupBy] ?? 'التصنيف' }}</th>
                        <th class="text-center">عدد الملاحظات</th>
                        <th class="text-center">عدد الفواتير</th>
                        <th class="text-center">مبلغ الفواتير</th>
                        <th class="text-center">عدد التحصيلات</th>
                        <th class="text-center">مبلغ التحصيلات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData as $row)
                        <tr>
                            <td><strong>{{ $row['name'] }}</strong></td>
                            <td class="text-center">{{ number_format($row['notes_count']) }}</td>
                            <td class="text-center">{{ number_format($row['invoices_count']) }}</td>
                            <td class="text-center">{{ number_format($row['invoices_amount'], 2) }}</td>
                            <td class="text-center">{{ number_format($row['collections_count']) }}</td>
                            <td class="text-center">{{ number_format($row['collections_amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>

            @else
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>العميل</th>
                        <th>الفرع</th>
                        <th>الموظف</th>
                        <th>الحالة</th>
                        <th>ملاحظات</th>
                        <th class="text-center">الفواتير</th>
                        <th class="text-center">التحصيلات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>
                                {{ $row['client_name'] }}
                                <br><small class="text-muted">{{ $row['client_code'] }}</small>
                            </td>
                            <td>{{ $row['branch_name'] ?? '-' }}</td>
                            <td>{{ $row['employee_name'] }}</td>
                            <td>
                                <span class="badge" style="background-color: {{ $row['client_status_color'] }};">
                                    {{ $row['client_status_name'] }}
                                </span>
                            </td>
                            <td>
                                @if(! empty($row['notes']))
                                    <span title="{{ implode(' | ', $row['notes']) }}">{{ count($row['notes']) }} ملاحظة</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($row['invoices_count'] > 0)
                                    {{ $row['invoices_count'] }} / {{ number_format($row['invoices_amount'], 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($row['collections_count'] > 0)
                                    {{ $row['collections_count'] }} / {{ number_format($row['collections_amount'], 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endif

            @if(isset($totals))
                <tfoot class="table-light">
                    <tr>
                        <th colspan="{{ $viewType === 'matrix' ? 14 : ($viewType === 'summary' ? 6 : 8) }}">
                            الإجمالي — الزيارات: {{ number_format($totals['visits']) }}،
                            الفواتير: {{ number_format($totals['invoices_count']) }} ({{ number_format($totals['invoices_amount'], 2) }})،
                            التحصيلات: {{ number_format($totals['collections_count']) }} ({{ number_format($totals['collections_amount'], 2) }})
                        </th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endif
