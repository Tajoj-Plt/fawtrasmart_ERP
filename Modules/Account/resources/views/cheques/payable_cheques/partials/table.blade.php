@php
    use App\Enums\ChequeStatus;
@endphp

{{--
    جدول الشيكات الصادرة — نظير جدول المستلمة.

    كان يحمل ترميز DataTables كاملاً بلا مكتبته، ونافذة حذفٍ بسمات بوتستراب 4
    (`data-toggle`, `data-dismiss`) والصفحة تُحمّل بوتستراب 5 — فزرّ الحذف لا
    يفتح شيئاً. والنماذج المخفيّة كانت داخل `<td>`، وهو ترميزٌ ينقله المتصفّح
    خارج الجدول عند التحليل فينكسر ربطها.
--}}

@if ($payable_cheques->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> الشيكات الصادرة
            <span class="stk-count">{{ $payable_cheques->count() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>رقم الشيك</th>
                    <th>الدفتر</th>
                    <th>البنك</th>
                    <th>المستفيد</th>
                    <th class="text-center">الإصدار</th>
                    <th class="text-center">الاستحقاق</th>
                    <th class="text-center">المبلغ</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($payable_cheques as $cheque)
                    @php
                        $status = $cheque->status ?? ChequeStatus::ISSUED;
                        $isEnum = $status instanceof ChequeStatus;
                        $statusName = $isEnum ? $status->getName() : 'صادر';

                        // الصرف والرفض والإلغاء كلّها من حالة «صادر» وحدها
                        $isIssued = $isEnum && $status === ChequeStatus::ISSUED;

                        $isOverdue = $cheque->due_date && $cheque->due_date->isBefore(today()) && $isIssued;
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-file-text"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title stk-num">{{ $cheque->cheque_number }}</span>
                                    <span class="stk-media__sub">شيك صادر</span>
                                </span>
                            </div>
                        </td>

                        <td class="stk-num">{{ $cheque->cheque_book->cheque_book_number ?? '—' }}</td>
                        <td>{{ $cheque->bank->name ?? '—' }}</td>
                        <td>{{ $cheque->supplier->name ?? $cheque->payee_name ?? '—' }}</td>

                        <td class="text-center stk-num">{{ $cheque->issue_date?->format('Y-m-d') ?? '—' }}</td>

                        <td class="text-center stk-num">
                            {{ $cheque->due_date?->format('Y-m-d') ?? '—' }}
                            @if ($isOverdue)
                                <span class="stk-state stk-state--off d-block">تجاوز الاستحقاق</span>
                            @endif
                        </td>

                        <td class="text-center"><strong>@money($cheque->amount, $cheque)</strong></td>

                        <td class="text-center">
                            <span class="stk-state {{ $isEnum && $status === ChequeStatus::CASHED ? 'stk-state--on' : ($isEnum && in_array($status, [ChequeStatus::REJECTED, ChequeStatus::CANCELLED]) ? 'stk-state--off' : 'stk-state--neutral') }}">
                                {{ $statusName }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('payable_cheques.show', $cheque->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    {{--
                                        كانت الإجراءات كلّها خلف
                                        `check_cycle_manage_payable_checks` — وهي صلاحية
                                        **غير معرَّفة في القاعدة**. و`@can` بصلاحية لا وجود
                                        لها تُرجع `false` دائماً، فلم يظهر أيٌّ من هذه
                                        الأزرار لأحدٍ قطّ — ولا رسالة تشير إلى السبب.

                                        صارت كلٌّ بصلاحيتها المعرَّفة فعلاً، فيُمنَح الصرف
                                        لمن يُصرَّف له وحده لا لمن يُعدِّل.
                                    --}}
                                    @if ($isIssued)
                                        @canany(['account.payable_cheques.cash', 'account.payable_cheques.reject', 'account.payable_cheques.cancel'])
                                            <div class="dropdown-divider"></div>
                                        @endcanany

                                        @can('account.payable_cheques.cash')
                                            <button type="submit" form="cash-form-{{ $cheque->id }}"
                                                class="dropdown-item"
                                                onclick="return confirm('تأكيد صرف الشيك؟ يُخصم من الحساب البنكي.')">
                                                <i class="feather icon-check-circle"></i> صرف
                                            </button>
                                        @endcan

                                        @can('account.payable_cheques.reject')
                                            <button type="submit" form="reject-form-{{ $cheque->id }}"
                                                class="dropdown-item text-danger"
                                                onclick="return confirm('إثبات رفض البنك للشيك؟')">
                                                <i class="feather icon-x-circle"></i> رفض البنك
                                            </button>
                                        @endcan

                                        @can('account.payable_cheques.cancel')
                                            <button type="submit" form="cancel-form-{{ $cheque->id }}"
                                                class="dropdown-item"
                                                onclick="return confirm('إلغاء الشيك؟ يُعكس قيد إصداره.')">
                                                <i class="feather icon-slash"></i> إلغاء
                                            </button>
                                        @endcan
                                    @endif

                                    @canany(['account.payable_cheques.edit', 'account.payable_cheques.delete'])
                                        <div class="dropdown-divider"></div>
                                    @endcanany

                                    @can('account.payable_cheques.edit')
                                        <a class="dropdown-item" href="{{ route('payable_cheques.edit', $cheque->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endcan

                                    {{-- الحذف متاحٌ فقط لشيكٍ لم يُعالَج بعد إصداره (لا رفض ولا إلغاء ولا صرف) —
                                         غيره له قيدٌ عكسي خاص بإجرائه بالفعل، وحذفه بعد ذلك يُنشئ عكساً زائداً --}}
                                    @can('account.payable_cheques.delete')
                                        @if ($isIssued)
                                            <form method="POST" action="{{ route('payable_cheques.delete', $cheque->id) }}" class="d-inline"
                                                onsubmit="if (!confirm('حذف الشيك رقم {{ $cheque->cheque_number }}؟\n\nيُنشأ قيدٌ عكسي يلغي أثره المحاسبي.')) return false; this.querySelector('button').disabled = true;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather icon-trash-2"></i> حذف
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="stk-table-foot">
        <span class="stk-table-foot__count">
            إجمالي القيمة: <strong>@money($payable_cheques->sum('amount'))</strong>
        </span>
    </div>

    {{-- النماذج خارج الجدول: النموذج داخل `<td>` ينقله المتصفّح فينكسر ربطه --}}
    {{-- النماذج المخفيّة لأزرار الصرف والرفض والإلغاء --}}
    @canany(['account.payable_cheques.cash', 'account.payable_cheques.reject', 'account.payable_cheques.cancel'])
        @foreach ($payable_cheques as $cheque)
            @php
                $status = $cheque->status ?? ChequeStatus::ISSUED;
                $isIssued = $status instanceof ChequeStatus && $status === ChequeStatus::ISSUED;
            @endphp

            @if ($isIssued)
                <form id="cash-form-{{ $cheque->id }}" method="POST"
                    action="{{ route('payable_cheques.cash', $cheque->id) }}" class="d-none">@csrf</form>

                <form id="reject-form-{{ $cheque->id }}" method="POST"
                    action="{{ route('payable_cheques.reject', $cheque->id) }}" class="d-none">@csrf</form>

                <form id="cancel-form-{{ $cheque->id }}" method="POST"
                    action="{{ route('payable_cheques.cancel', $cheque->id) }}" class="d-none">@csrf</form>
            @endif
        @endforeach
    @endcan
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-upload"></i></span>
        <h6 class="stk-empty__title">لا شيكات صادرة مطابقة</h6>
        <p class="stk-empty__hint">
            @if (request()->hasAny(['status', 'from_date', 'to_date', 'bank_id']))
                لا نتائج لهذه الفلاتر — جرّب توسيعها أو أعِد تعيينها.
            @else
                الشيك الصادر يُحرَّر من دفترٍ مسجَّل، ثم يُصرَف أو يُرفَض أو يُلغى.
            @endif
        </p>

        @can('account.payable_cheques.create')
            <a href="{{ route('payable_cheques.create') }}" class="btn btn-primary mt-1">
                <i class="feather icon-plus"></i> إصدار شيك
            </a>
        @endcan
    </div>
@endif
