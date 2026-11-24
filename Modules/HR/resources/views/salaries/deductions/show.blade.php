@extends('hr::master')

@section('title', 'خصمية ' . ($deduction->code ?: '#' . $deduction->id))

@php
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
    $emp = $deduction->employee;
    $empName = trim(($emp->first_name ?? '') . ' ' . ($emp->middle_name ?? '')) ?: '—';
    $tone = [
        'pending' => 'warn', 'approved' => 'neutral', 'partially_settled' => 'neutral',
        'settled' => 'on', 'rejected' => 'off', 'cancelled' => 'off',
    ][$deduction->status] ?? 'neutral';

    $canEdit = $deduction->canBeEdited() && hasPermission('deductions.edit');
    $canApprove = $deduction->canBeApproved() && hasPermission('deductions.approve');
    $canReject = $deduction->canBeRejected() && hasPermission('deductions.approve');
    $canSettle = $deduction->canBeSettled() && hasPermission('deductions.settle');
    $canCancel = $deduction->canBeCancelled() && hasPermission('deductions.edit');
    $canDelete = $deduction->canBeDeleted() && ! $deduction->journal_entry_id && hasPermission('deductions.destroy');
    $canHistory = hasPermission('deductions.show');
    $anyAction = $canEdit || $canApprove || $canReject || $canSettle || $canCancel || $canDelete;
@endphp

@section('page-actions')
    {{-- ترتيبٌ واحد لكل شاشات العرض: إجراء الدورة ← تعديل ← المزيد (والحذف آخرها) --}}
    @if ($canApprove)
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#approveModal" @disabled($postingBlock)>
            <i class="fas fa-check"></i> اعتماد وقيد الخصمية
        </button>
    @endif
    @if ($canEdit)
        <a href="{{ route('hr.deductions.edit', $deduction->id) }}" class="btn btn-outline-primary">
            <i class="fas fa-edit"></i> تعديل
        </a>
    @endif
    @if ($canReject || $canSettle || $canHistory || $canCancel || $canDelete)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if ($canSettle)
                    <li><button type="button" class="dropdown-item" data-toggle="modal" data-target="#settleModal"><i class="fas fa-check-circle me-2 text-success"></i>تسوية يدوية</button></li>
                @endif
                @if ($canHistory)
                    <li><a class="dropdown-item" href="{{ route('hr.deductions.history', $deduction->id) }}"><i class="fas fa-history me-2 text-info"></i>سجل العمليات</a></li>
                @endif
                @if ($canReject || $canCancel || $canDelete)
                    @if ($canSettle || $canHistory)<li><hr class="dropdown-divider"></li>@endif
                    @if ($canReject)
                        <li><button type="button" class="dropdown-item text-danger" data-toggle="modal" data-target="#rejectModal"><i class="fas fa-times me-2"></i>رفض</button></li>
                    @endif
                    @if ($canCancel)
                        <li><button type="button" class="dropdown-item text-danger" data-toggle="modal" data-target="#cancelModal"><i class="fas fa-ban me-2"></i>إلغاء الخصمية</button></li>
                    @endif
                    @if ($canDelete)
                        <li>
                            <form method="POST" action="{{ route('hr.deductions.destroy', $deduction->id) }}" onsubmit="return confirm('حذف هذه الخصمية نهائياً؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>حذف</button>
                            </form>
                        </li>
                    @endif
                @endif
            </ul>
        </div>
    @endif
@endsection

@section('content')
    @include('stock::components.page-head', [
        'title' => 'خصمية ' . ($deduction->code ?: '#' . $deduction->id),
        'icon' => 'minus-circle',
        'crumbs' => [
            'الخصميات' => route('hr.deductions.index'),
            ($deduction->code ?: '#' . $deduction->id) => null,
        ],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
                            <div>
                                <h4 class="mb-1">{{ $empName }}</h4>
                                <span class="stk-tag">{{ $deduction->type_label }}</span>
                                <span class="text-muted small stk-num ml-1"><i class="feather icon-calendar"></i> {{ optional($deduction->date)->format('Y-m-d') }}</span>
                            </div>
                            <span class="stk-state stk-state--{{ $tone }}">{{ $deduction->status_label }}</span>
                        </div>
                    </div>
                    <div class="stk-metrics">
                        <div class="stk-metric">
                            <span class="stk-metric__label">المبلغ</span>
                            <span class="stk-metric__value stk-num">{{ $money($deduction->amount) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--success">
                            <span class="stk-metric__label">المسوّى</span>
                            <span class="stk-metric__value stk-num">{{ $money($deduction->settled_amount) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--danger">
                            <span class="stk-metric__label">المتبقي</span>
                            <span class="stk-metric__value stk-num">{{ $money($deduction->remaining_amount) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--info">
                            <span class="stk-metric__label">نسبة التسوية</span>
                            <span class="stk-metric__value stk-num">{{ rtrim(rtrim(number_format($deduction->progress_percentage, 2), '0'), '.') }}%</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-file-text"></i>
                                <h6 class="stk-section__title">التفاصيل</h6>
                            </div>
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>السبب</dt><dd>{{ $deduction->reason }}</dd></div>
                                @if ($deduction->notes)
                                    <div class="stk-def__row"><dt>ملاحظات</dt><dd>{{ $deduction->notes }}</dd></div>
                                @endif
                                <div class="stk-def__row"><dt>أنشأها</dt><dd>{{ $deduction->creator_label }} <small class="text-muted stk-num">{{ optional($deduction->created_at)->format('Y-m-d H:i') }}</small></dd></div>
                                @if ($deduction->approved_at)
                                    <div class="stk-def__row"><dt>اعتمدها</dt><dd>{{ $deduction->approver_label }} <small class="text-muted stk-num">{{ $deduction->approved_at->format('Y-m-d H:i') }}</small></dd></div>
                                @endif
                                @if ($deduction->isRejected())
                                    <div class="stk-def__row"><dt>سبب الرفض</dt><dd class="text-danger">{{ $deduction->rejection_reason }} <small class="text-muted">{{ optional($deduction->rejecter)->name }}</small></dd></div>
                                @endif
                                @if ($deduction->isCancelled())
                                    <div class="stk-def__row"><dt>سبب الإلغاء</dt><dd>{{ $deduction->cancellation_reason ?: '—' }} <small class="text-muted">{{ optional($deduction->canceller)->name }}</small></dd></div>
                                @endif
                                @if ($deduction->settled_at)
                                    <div class="stk-def__row"><dt>سُوِّيت بالكامل</dt><dd class="stk-num">{{ $deduction->settled_at->format('Y-m-d H:i') }}</dd></div>
                                @endif
                            </dl>
                        </div>

                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-book"></i>
                                <h6 class="stk-section__title">الأثر المحاسبي</h6>
                                <i class="feather icon-help-circle stk-tip" title="عند الاعتماد: مدين مستحقات الموظف / دائن حساب نوع الخصمية. عند صرف الراتب تُقتطع من الصافي دون قيدٍ ثانٍ. الإلغاء يعكس ما لم يُقتطع."></i>
                            </div>
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>حساب النوع</dt>
                                    <dd>
                                        @if ($typeAccount)
                                            {{ $typeAccount->code }} — {{ $typeAccount->name }}
                                        @else
                                            <span class="text-danger">غير مربوط</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="stk-def__row"><dt>ذمّة الموظف</dt><dd>{{ optional($emp?->salaryPayableAccount)->name ?? '—' }}</dd></div>
                                <div class="stk-def__row"><dt>المُقيَّد</dt><dd class="stk-num">{{ $money($deduction->posted_amount) }}
                                    @if ($deduction->posted_outstanding > 0.009)
                                        <small class="text-muted">— منه {{ $money($deduction->posted_outstanding) }} ينتظر الاقتطاع من الراتب</small>
                                    @endif
                                </dd></div>
                            </dl>
                            @if ($postingBlock)
                                <div class="alert alert-warning small mt-2 mb-0"><i class="feather icon-alert-triangle"></i> {{ $postingBlock }}</div>
                            @endif
                            @if ($journals->isNotEmpty())
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>القيد</th><th>التاريخ</th><th>البيان</th></tr></thead>
                                        <tbody>
                                            @foreach ($journals as $je)
                                                <tr>
                                                    <td class="stk-strong">{{ $je->reference_number }}</td>
                                                    <td class="stk-num">{{ \Illuminate\Support\Carbon::parse($je->date)->format('Y-m-d') }}</td>
                                                    <td><small>{{ $je->description }}</small></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="stk-section__head">
                            <i class="feather icon-clock"></i>
                            <h6 class="stk-section__title">آخر العمليات</h6>
                            @if ($canHistory && $deduction->histories->count() > 6)
                                <a href="{{ route('hr.deductions.history', $deduction->id) }}" class="small ml-auto">السجل الكامل ({{ $deduction->histories->count() }})</a>
                            @endif
                        </div>
                        @if ($deduction->histories->isEmpty())
                            <p class="stk-help mb-0">لا عمليات مسجّلة.</p>
                        @else
                            <ul class="stk-timeline">
                                @foreach ($deduction->histories->take(6) as $h)
                                    <li class="stk-timeline__item">
                                        <span class="stk-timeline__dot stk-timeline__dot--log"><i class="feather icon-activity"></i></span>
                                        <div class="stk-timeline__body">
                                            <div class="stk-timeline__head">
                                                <span>{{ $h->description }}</span>
                                                @if ($h->amount)<span class="stk-timeline__qty">{{ $money($h->amount) }}</span>@endif
                                                <span class="stk-timeline__time">{{ optional($h->created_at)->format('Y-m-d H:i') }}</span>
                                            </div>
                                            <div class="stk-timeline__meta small text-muted">{{ optional($h->user)->name ?? 'النظام' }}{{ $h->notes ? ' — ' . $h->notes : '' }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                @if (! $anyAction)
                    <div class="card">
                        <div class="card-body">
                            <p class="stk-help mb-0">
                                <i class="feather icon-lock"></i>
                                @if ($deduction->isSettled())
                                    خصميةٌ مسوّاة بالكامل — لا عمليات مالية عليها. ما اقتُطع في راتبٍ مصروف يُصحَّح بعكس ذلك الصرف.
                                @else
                                    لا إجراءات متاحة لك على هذه الخصمية في حالتها الحالية.
                                @endif
                            </p>
                        </div>
                    </div>
                @endif

                @if ($deduction->settled_amount > 0 && $deduction->remaining_amount > 0)
                    <div class="card">
                        <div class="card-body">
                            <div class="stk-bar stk-bar--success" title="{{ $deduction->progress_percentage }}%">
                                <span class="stk-bar__fill" style="width: {{ $deduction->progress_percentage }}%"></span>
                            </div>
                            <p class="stk-help mb-0">سُوّي {{ $money($deduction->settled_amount) }} من {{ $money($deduction->amount) }} — والباقي يُقتطع من الراتب التالي.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════ النوافذ ═══════════════ --}}
    @if ($canApprove)
        <div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('hr.deductions.approve', $deduction->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">اعتماد الخصمية</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            <div class="alert alert-info small">
                                سيُنشأ قيد بمبلغ {{ $money($deduction->amount) }}: مدين مستحقات {{ $empName }} / دائن
                                {{ $typeAccount ? $typeAccount->name : 'حساب نوع الخصمية' }} — ويُبلَّغ الموظف.
                            </div>
                            <label for="approval_notes">ملاحظات الاعتماد</label>
                            <textarea name="approval_notes" id="approval_notes" class="form-control" rows="2" maxlength="1000">{{ old('approval_notes') }}</textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-success"><i class="feather icon-check"></i> تأكيد الاعتماد</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canReject)
        <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('hr.deductions.reject', $deduction->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">رفض الخصمية</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            <label for="rejection_reason" class="stk-req">سبب الرفض</label>
                            <textarea name="rejection_reason" id="rejection_reason" class="form-control @error('rejection_reason') is-invalid @enderror" rows="3" required maxlength="1000">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-danger">تأكيد الرفض</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canCancel)
        <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('hr.deductions.cancel', $deduction->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">إلغاء الخصمية</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            @if ($deduction->posted_outstanding > 0.009)
                                <div class="alert alert-warning small">سيُنشأ قيدٌ عكسي بمبلغ {{ $money($deduction->posted_outstanding) }} — ما قُيِّد ولم يُقتطع بعد.</div>
                            @endif
                            @if ($deduction->settled_amount > 0)
                                <p class="stk-help">ما سُوّي ({{ $money($deduction->settled_amount) }}) لا يُلغى هنا — خرج فعلاً في راتبٍ مصروف.</p>
                            @endif
                            <label for="cancellation_reason">سبب الإلغاء</label>
                            <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" maxlength="1000">{{ old('cancellation_reason') }}</textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-warning">تأكيد الإلغاء</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canSettle)
        <div class="modal fade" id="settleModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('hr.deductions.settle', $deduction->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">تسوية يدوية خارج الراتب</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            <p class="stk-help">التسويةُ من الراتب تقع تلقائياً عند صرفه. هنا ما يُسوّى خارجه، ولكلِّ طريقةٍ قيدُها.</p>
                            <div class="form-group">
                                <label class="stk-req">طريقة التسوية</label>
                                @php $method = old('settlement_method', 'cash'); @endphp
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="method_cash" name="settlement_method" value="cash" class="custom-control-input" @checked($method === 'cash')>
                                    <label class="custom-control-label" for="method_cash">تحصيل نقدي من الموظف <small class="text-muted">(مدين الخزينة / دائن ذمّته)</small></label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="method_waiver" name="settlement_method" value="waiver" class="custom-control-input" @checked($method === 'waiver')>
                                    <label class="custom-control-label" for="method_waiver">إعفاء <small class="text-muted">(عكس القيد بالمبلغ المُعفى)</small></label>
                                </div>
                                @error('settlement_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group" id="settle_treasury_group">
                                <label for="settle_treasury_id" class="stk-req">الخزينة المستلِمة</label>
                                <select name="treasury_id" id="settle_treasury_id" class="form-control hr-s2 @error('treasury_id') is-invalid @enderror">
                                    <option value="">— اختر —</option>
                                    @foreach ($treasuries as $t)
                                        <option value="{{ $t->id }}" @selected((string) old('treasury_id') === (string) $t->id)>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                @error('treasury_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label for="settle_amount" class="stk-req">المبلغ</label>
                                <input type="number" name="settle_amount" id="settle_amount" step="0.01" min="0.01" max="{{ $deduction->remaining_amount }}"
                                       class="form-control @error('settle_amount') is-invalid @enderror"
                                       value="{{ old('settle_amount', $deduction->remaining_amount) }}" required>
                                <span class="stk-help">المتبقي: {{ $money($deduction->remaining_amount) }}</span>
                                @error('settle_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group mb-0">
                                <label for="settlement_notes">ملاحظات</label>
                                <textarea name="settlement_notes" id="settlement_notes" class="form-control" rows="2" maxlength="1000">{{ old('settlement_notes') }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-success">تأكيد التسوية</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(function () {
            function toggleTreasury() {
                var cash = $('input[name="settlement_method"]:checked').val() === 'cash';
                $('#settle_treasury_group').toggle(cash);
                $('#settle_treasury_id').prop('required', cash);
            }
            $('input[name="settlement_method"]').on('change', toggleTreasury);
            toggleTreasury();

            // إعادةُ فتح النافذة التي رُدّ منها النموذجُ بخطأ تحقّق
            @if ($errors->hasAny(['settle_amount', 'settlement_method', 'treasury_id']))
                $('#settleModal').modal('show');
            @elseif ($errors->has('rejection_reason'))
                $('#rejectModal').modal('show');
            @endif
        });
    </script>
@endpush
