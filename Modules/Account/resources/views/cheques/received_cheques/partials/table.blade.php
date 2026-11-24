@php
    use App\Enums\ChequeStatus;
@endphp

{{--
    جدول الشيكات المستلمة.

    **ما كان**

    ترميز DataTables كامل بلا مكتبة DataTables، ونافذة حذفٍ لكل صفّ بسمات
    بوتستراب 4 (`data-toggle`, `data-target`, `data-dismiss`) بينما الصفحة
    تُحمّل بوتستراب 5 — فزرّ الحذف لا يفتح شيئاً، والنافذة إن فُتحت لا تُغلق.
    (نافذة التظهير في الملف نفسه كانت بسمات `data-bs-*` صحيحة — فالخطأ
    ليس اطّراداً بل سهواً في موضعين.)

    **ما صار**

    الحذف تأكيدٌ مباشر برسالةٍ تذكر ما سيقع — قيدٌ عكسي — بدل نافذةٍ معطَّلة.
--}}

@if ($received_cheques->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> الشيكات المستلمة
            <span class="stk-count">{{ $received_cheques->count() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>رقم الشيك</th>
                    <th>العميل</th>
                    <th>بنك الساحب</th>
                    <th>حساب الإيداع</th>
                    <th class="text-center">الإصدار</th>
                    <th class="text-center">الاستحقاق</th>
                    <th class="text-center">المبلغ</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($received_cheques as $cheque)
                    @php
                        $status = $cheque->status ?? ChequeStatus::PENDING;
                        $isEnum = $status instanceof ChequeStatus;
                        $statusName = $isEnum ? $status->getName() : 'معلق';

                        $canDeposit = $isEnum && $status === ChequeStatus::PENDING;
                        $canCollect = $isEnum && $status === ChequeStatus::UNDER_COLLECTION;
                        $canReject = $isEnum && in_array($status, [ChequeStatus::PENDING, ChequeStatus::UNDER_COLLECTION]);
                        $canEndorse = $isEnum && $status === ChequeStatus::PENDING;

                        $isOverdue = $cheque->due_date && $cheque->due_date->isBefore(today()) && $canReject;
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-file-text"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title stk-num">{{ $cheque->cheque_number }}</span>
                                    <span class="stk-media__sub">شيك مستلم</span>
                                </span>
                            </div>
                        </td>

                        <td>{{ $cheque->client->name ?? $cheque->payee_name ?? '—' }}</td>
                        <td>{{ $cheque->drawer_bank ?? '—' }}</td>
                        {{-- حساب الإيداع لا يُختار إلا عند «إيداع للتحصيل»؛ يبقى فارغاً قبلها --}}
                        <td>{{ $cheque->bankAccount->name ?? 'لم يُحدَّد بعد' }}</td>

                        <td class="text-center stk-num">{{ $cheque->issue_date?->format('Y-m-d') ?? '—' }}</td>

                        <td class="text-center stk-num">
                            {{ $cheque->due_date?->format('Y-m-d') ?? '—' }}
                            @if ($isOverdue)
                                <span class="stk-state stk-state--off d-block">تجاوز الاستحقاق</span>
                            @endif
                        </td>

                        <td class="text-center"><strong>@money($cheque->amount, $cheque)</strong></td>

                        <td class="text-center">
                            <span class="stk-state {{ $isEnum && in_array($status, [ChequeStatus::COLLECTED]) ? 'stk-state--on' : ($isEnum && $status === ChequeStatus::REJECTED ? 'stk-state--off' : 'stk-state--neutral') }}">
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
                                    <a class="dropdown-item" href="{{ route('received_cheques.show', $cheque->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    @canany(['account.received_cheques.deposit', 'account.received_cheques.collect', 'account.received_cheques.reject', 'account.received_cheques.endorse', 'account.received_cheques.edit', 'account.received_cheques.delete'])
                                        <div class="dropdown-divider"></div>
                                    @endcanany

                                    @can('account.received_cheques.deposit')
                                        @if ($canDeposit)
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                data-bs-target="#depositModal{{ $cheque->id }}">
                                                <i class="feather icon-briefcase"></i> إيداع للتحصيل
                                            </button>
                                        @endif
                                    @endcan

                                    @can('account.received_cheques.collect')
                                        @if ($canCollect)
                                            <button type="submit" form="collect-form-{{ $cheque->id }}"
                                                class="dropdown-item"
                                                onclick="return confirm('تأكيد تحصيل الشيك؟ يُكتب قيد التحصيل.')">
                                                <i class="feather icon-check-circle"></i> تحصيل
                                            </button>
                                        @endif
                                    @endcan

                                    @can('account.received_cheques.reject')
                                        @if ($canReject)
                                            <button type="submit" form="reject-form-{{ $cheque->id }}"
                                                class="dropdown-item text-danger"
                                                onclick="return confirm('إثبات ارتداد الشيك؟ يُكتب قيدٌ يعكس أثره.')">
                                                <i class="feather icon-x-circle"></i> ارتدّ الشيك
                                            </button>
                                        @endif
                                    @endcan

                                    @can('account.received_cheques.endorse')
                                        @if ($canEndorse)
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                data-bs-target="#endorseModal{{ $cheque->id }}">
                                                <i class="feather icon-repeat"></i> تظهير
                                            </button>
                                        @endif
                                    @endcan

                                    @can('account.received_cheques.edit')
                                        <a class="dropdown-item" href="{{ route('received_cheques.edit', $cheque->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endcan

                                    @can('account.received_cheques.delete')
                                        {{-- الحذف متاحٌ فقط لشيكٍ لم يُعالَج بعد استلامه (لا إيداع ولا تحصيل ولا رفض ولا تظهير) —
                                             غيره له قيدٌ عكسي خاص بإجرائه بالفعل، وحذفه بعد ذلك يُنشئ عكساً زائداً --}}
                                        @if ($canDeposit)
                                            <form method="POST" action="{{ route('received_cheques.delete', $cheque->id) }}" class="d-inline"
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
            إجمالي القيمة: <strong>@money($received_cheques->sum('amount'))</strong>
        </span>
    </div>

    {{--
        النماذج والنوافذ خارج الجدول عمداً: النموذج داخل `<td>` ترميزٌ غير
        سليم، والمتصفّح ينقله خارج الجدول عند التحليل فينكسر ربطه بأزراره.
        الأزرار تشير إليها بـ`form="…"`.
    --}}
    @canany(['account.received_cheques.deposit', 'account.received_cheques.collect', 'account.received_cheques.reject', 'account.received_cheques.endorse'])
        @foreach ($received_cheques as $cheque)
            @php
                $status = $cheque->status ?? ChequeStatus::PENDING;
                $isEnum = $status instanceof ChequeStatus;
            @endphp

            @if ($isEnum && $status === ChequeStatus::PENDING)
                <div class="modal fade" id="depositModal{{ $cheque->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('received_cheques.deposit', $cheque->id) }}" method="POST">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">إيداع الشيك {{ $cheque->cheque_number }} للتحصيل</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted mb-3">سيُنقل الشيك من "شيكات تحت التحصيل" إلى "شيكات برسم التحصيل" في البنك المختار</p>
                                    <div class="form-group">
                                        <label for="deposit_bank_{{ $cheque->id }}">
                                            حساب البنك للإيداع <span class="stk-req"></span>
                                        </label>
                                        <select name="bank_account_id" id="deposit_bank_{{ $cheque->id }}"
                                            class="form-control" required>
                                            <option value="">اختر البنك</option>
                                            @foreach ($bank_accounts ?? [] as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ $cheque->bank_account_id == $account->id ? 'selected' : '' }}>
                                                    {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($cheque->bank_account_id)
                                            <small class="stk-help">مُحدَّد مسبقاً عند الاستلام — يمكن تغييره هنا</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                                    <button type="submit" class="btn btn-primary">إيداع للتحصيل</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if ($isEnum && $status === ChequeStatus::UNDER_COLLECTION)
                <form id="collect-form-{{ $cheque->id }}" method="POST"
                    action="{{ route('received_cheques.collect', $cheque->id) }}" class="d-none">@csrf</form>
            @endif

            @if ($isEnum && in_array($status, [ChequeStatus::PENDING, ChequeStatus::UNDER_COLLECTION]))
                <form id="reject-form-{{ $cheque->id }}" method="POST"
                    action="{{ route('received_cheques.reject', $cheque->id) }}" class="d-none">@csrf</form>
            @endif

            @if ($isEnum && $status === ChequeStatus::PENDING)
                <div class="modal fade" id="endorseModal{{ $cheque->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('received_cheques.endorse', $cheque->id) }}" method="POST">
                                @csrf

                                <div class="modal-header">
                                    <h5 class="modal-title">تظهير الشيك {{ $cheque->cheque_number }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="إغلاق"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="endorse_to_{{ $cheque->id }}">
                                            حساب المظهَّر إليه <span class="stk-req"></span>
                                        </label>
                                        <select name="endorsed_to_account_id" id="endorse_to_{{ $cheque->id }}"
                                            class="form-control" required>
                                            <option value="">اختر الحساب</option>
                                            @foreach ($suppliers ?? [] as $supplier)
                                                <option value="{{ $supplier->account_id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="stk-help">
                                            التظهير ينقل الشيك إلى طرفٍ آخر ويُقفل ذمّته عندك.
                                        </small>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary"
                                        data-bs-dismiss="modal">إلغاء</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-repeat"></i> تظهير
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endcanany
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-download"></i></span>
        <h6 class="stk-empty__title">لا شيكات مستلمة مطابقة</h6>
        <p class="stk-empty__hint">
            @if (request()->hasAny(['status', 'from_date', 'to_date']))
                لا نتائج لهذه الفلاتر — جرّب توسيعها أو أعِد تعيينها.
            @else
                الشيك المستلَم يُسجَّل هنا ثم يمرّ بدورته: إيداعٌ فتحصيلٌ أو ارتداد.
            @endif
        </p>

        @can('account.received_cheques.create')
            <a href="{{ route('received_cheques.create') }}" class="btn btn-primary mt-1">
                <i class="feather icon-plus"></i> استلام شيك
            </a>
        @endcan
    </div>
@endif
