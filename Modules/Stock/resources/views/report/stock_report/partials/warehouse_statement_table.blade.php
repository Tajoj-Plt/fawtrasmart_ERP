{{--
    جدول كشف حساب المستودع — يُعرض داخل الصفحة ويُعاد عبر AJAX أيضاً.
    الترقيم الطويل المكتوب يدوياً (70 سطراً) استُبدل بمكوّن الترقيم المشترك.
--}}

@foreach ($reportData['data'] as $warehouseData)
    @php
        $operations = $warehouseData['operations'];
        $account = $warehouseData['account'] ?? null;
    @endphp

    <div class="card stk-table-card js-warehouse-section"
         data-warehouse-name="{{ $warehouseData['warehouse']->name }}"
         data-account-name="{{ $account->name ?? '' }}">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-home"></i> {{ $warehouseData['warehouse']->name }}
                @if ($account)
                    <span class="stk-state stk-state--neutral">{{ $account->name }}</span>
                @endif
                <span class="stk-count">{{ $operations->total() }}</span>
            </h6>

            <span class="stk-balance stk-num">@money($warehouseData['finalBalance'])</span>
        </div>

        @if ($operations->isEmpty())
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-home"></i></div>
                    <h5 class="stk-empty__title">لا عمليات في هذا المستودع</h5>
                    <p class="stk-empty__hint">راجع الفلاتر أو وسّع الفترة الزمنية</p>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:10%">التاريخ</th>
                            <th style="width:13%">رقم المرجع</th>
                            <th>الوصف</th>
                            <th class="text-center" style="width:11%">مدين</th>
                            <th class="text-center" style="width:11%">دائن</th>
                            <th class="text-center" style="width:13%">الرصيد الجاري</th>
                            <th style="width:14%">المستخدم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operations as $operation)
                            <tr class="{{ $operation['is_opening'] ? 'stk-total-row' : '' }}">
                                <td class="stk-num">
                                    {{ $operation['date'] ? \Illuminate\Support\Carbon::parse($operation['date'])->format('Y-m-d') : '—' }}
                                </td>
                                <td class="stk-num">
                                    @if (! $operation['is_opening'] && $operation['journal_entry_id'])
                                        <button type="button" class="btn btn-sm btn-link p-0 view-entry-btn"
                                                data-entry-id="{{ $operation['journal_entry_id'] }}">
                                            {{ $operation['reference_number'] ?? 'عرض القيد' }}
                                        </button>
                                    @else
                                        {{ $operation['reference_number'] ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $operation['description'] ?? '—' }}</td>
                                <td class="text-center stk-num">
                                    @if ($operation['debit'] > 0)
                                        <span class="stk-move--success">@moneyPlain($operation['debit'])</span>
                                    @else
                                        <span class="stk-move--muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center stk-num">
                                    @if ($operation['credit'] > 0)
                                        <span class="stk-move--danger">@moneyPlain($operation['credit'])</span>
                                    @else
                                        <span class="stk-move--muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center stk-num"><strong>@money($operation['balance'])</strong></td>
                                <td>{{ $operation['user'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($operations->hasPages())
                <div class="stk-table-foot">
                    <span class="stk-help">
                        {{ $operations->firstItem() }}–{{ $operations->lastItem() }} من {{ $operations->total() }}
                    </span>

                    {{ $operations->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
@endforeach
