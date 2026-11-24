{{--
    تفاصيل دَينٍ مشكوك فيه — أُعيدت بنظام تصميم وحدة العملاء
    (`.cl-scope` + `partials/settings-theme` + `components/page-head`) بدل
    تدرّجات وألوانٍ مكتوبةٍ في الشيفرة (`#667eea`..) لا تتبع «مظهر النظام».
    التبويبات صارت محلّية بالجافاسكربت على نمط `cl-tabs` في شاشة إعدادات
    وحدات العملاء بدل تبويبات Bootstrap الجاهزة. المنطق والراوتات لم تُمسّ.
--}}
@extends('client::master')

@section('title')
    تفاصيل الدين المشكوك فيه
@stop


@push('styles')
    <style>
        /* التبويب زرٌّ لا رابط: التبويبات هنا أجزاءُ صفحةٍ واحدة تتبدّل
           محلياً بالجافاسكربت، على نمط cl-tabs في إعدادات وحدات العملاء.
           client::master يعرض stack الأنماط لا يوجد قسم css فيه —
           لذا الأنماط هنا تُدفَع إلى المكدّس بدل قسمٍ لا يُعرَض إطلاقاً. */
        .cl-scope button.cl-tab {
            background: none;
            border: 0;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-family: inherit;
        }

        .cl-scope .cl-tabpanel[hidden] { display: none; }

        .cl-scope .cl-tab__badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; padding: 0 .3rem;
            border-radius: 999px; font-size: .68rem; font-weight: 700;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }
    </style>
@endpush

@section('content')
    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $statusMeta = [
            'pending' => ['badge' => 'badge-warning', 'icon' => 'icon-clock', 'label' => 'قيد التحصيل'],
            'collected' => ['badge' => 'badge-success', 'icon' => 'icon-check-circle', 'label' => 'تم تحصيله'],
            'reversed' => ['badge' => 'badge-info', 'icon' => 'icon-rotate-ccw', 'label' => 'تم إرجاعه'],
            'written_off' => ['badge' => 'badge-danger', 'icon' => 'icon-trash-2', 'label' => 'تم شطبه'],
        ];
        $meta = $statusMeta[$doubtfulDebt->status] ?? ['badge' => 'badge-secondary', 'icon' => 'icon-help-circle', 'label' => $doubtfulDebt->status];
    @endphp

    <div class="cl-scope">
        @php
            $pageActions = '<a href="'.route('clients.show', $doubtfulDebt->client->id).'" class="btn btn-outline-primary">'
                .'<i class="feather icon-user"></i> <span>ملف العميل</span></a>'
                .'<a href="'.route('clients.statement', $doubtfulDebt->client->id).'" class="btn btn-outline-primary">'
                .'<i class="feather icon-file-text"></i> <span>كشف الحساب</span></a>'
                .'<a href="'.route('doubtful-debts.index').'" class="btn btn-outline-secondary">'
                .'<i class="feather icon-arrow-right"></i> <span>العودة</span></a>';
        @endphp

        @include('client::components.page-head', [
            'title' => 'دين مشكوك فيه — '.$doubtfulDebt->client->trade_name,
            'icon' => 'alert-triangle',
            'sub' => 'كود العميل #'.$doubtfulDebt->client->code.' — سُجِّل بتاريخ '.$doubtfulDebt->created_at->format('Y-m-d'),
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'الديون المشكوك فيها' => route('doubtful-debts.index'),
                $doubtfulDebt->client->trade_name => null,
            ],
            'actions' => $pageActions,
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @include('client::components.guide', [
            'key' => 'clients.doubtful-debts.show',
            'open' => false,
            'title' => 'ما الخيارات المتاحة هنا؟',
            'lead' => 'تحصيل المديونية يسجّل قيدَ قبضٍ فعلياً على الخزينة أو البنك؛ أمّا إرجاعها فيحذف قيدها الأصلي بلا أي أثر نقدي.',
            'blocks' => [
                'تحصيل المديونية' => 'دفعٌ فعلي من العميل — ينشئ قيداً عكسياً لإرجاع المديونية ثم قيد تحصيلٍ يقيّد الخزينة المضبوطة في «الحسابات المحاسبية».',
                'إرجاع المديونية' => 'تصحيحٌ إداري بلا تحصيل — يحذف القيد الأصلي ويعيد الرصيد للعميل دون أن يمسّ أي خزينة.',
            ],
            'facts' => [
                'الحساب المستعمَل: '.($doubtfulDebt->account->name ?? 'حساب العميل'),
            ],
        ])

        {{-- ══════════ بطاقة الحالة ══════════ --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--danger"><i class="feather icon-alert-triangle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ number_format($doubtfulDebt->original_balance, 2) }}</p>
                    <p class="cl-stat__label">المبلغ الأصلي</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--warning"><i class="feather icon-shield"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ number_format($doubtfulDebt->provision_amount, 2) }}</p>
                    <p class="cl-stat__label">مبلغ المخصص ({{ $doubtfulDebt->provision_percentage }}%)</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="feather icon-check"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ number_format($doubtfulDebt->collected_amount ?? 0, 2) }}</p>
                    <p class="cl-stat__label">المحصل حتى الآن</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon"><i class="feather {{ $meta['icon'] }}"></i></span>
                <div class="cl-stat__body">
                    <span class="badge {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                    <p class="cl-stat__label" style="margin-top:.35rem">الحالة الحالية</p>
                </div>
            </div>
        </div>

        {{-- ══════════ التبويبات ══════════ --}}
        <div class="card">
            <div class="card-body">
                <div class="cl-tabs" role="tablist">
                    <button type="button" role="tab" data-cl-tab="details" class="cl-tab is-active" aria-selected="true">
                        <i class="feather icon-info"></i> التفاصيل
                    </button>
                    <button type="button" role="tab" data-cl-tab="collection" class="cl-tab" aria-selected="false">
                        <i class="feather icon-dollar-sign"></i> تحصيل المديونية
                        @if($doubtfulDebt->status == 'pending' && $doubtfulDebt->getRemainingBalance() > 0)
                            <span class="cl-tab__badge">جديد</span>
                        @endif
                    </button>
                    <button type="button" role="tab" data-cl-tab="reversal" class="cl-tab" aria-selected="false">
                        <i class="feather icon-rotate-ccw"></i> إرجاع المديونية
                        @if($doubtfulDebt->status == 'pending')
                            <span class="cl-tab__badge">جديد</span>
                        @endif
                    </button>
                    <button type="button" role="tab" data-cl-tab="notes" class="cl-tab" aria-selected="false">
                        <i class="feather icon-file-text"></i> الملاحظات
                    </button>
                </div>

                {{-- ─── تبويب: التفاصيل ─── --}}
                <div class="cl-tabpanel" data-cl-panel="details">
                    <div class="cl-section">
                        <div class="cl-section__head">
                            <i class="feather icon-trending-up"></i>
                            <h5 class="cl-section__title">نسبة التحصيل</h5>
                        </div>
                        @php
                            $percentage = $doubtfulDebt->getCollectionPercentage();
                            $barColor = $percentage < 50 ? 'danger' : ($percentage < 100 ? 'warning' : 'success');
                        @endphp
                        <div class="progress" style="height: 26px;">
                            <div class="progress-bar bg-{{ $barColor }}" style="width: {{ $percentage }}%">{{ $percentage }}%</div>
                        </div>
                        <p class="cl-help" style="margin-top:.5rem" data-hint-keep>المتبقي: {{ number_format($doubtfulDebt->getRemainingBalance(), 2) }} ريال</p>
                    </div>

                    <div class="cl-section">
                        <div class="cl-section__head">
                            <i class="feather icon-clock"></i>
                            <h5 class="cl-section__title">معلومات التسجيل</h5>
                        </div>
                        <div class="cl-ctx__grid">
                            <div class="cl-ctx__item">
                                <span class="cl-ctx__icon"><i class="feather icon-calendar"></i></span>
                                <div class="cl-ctx__body">
                                    <span class="cl-ctx__label">تاريخ التسجيل</span>
                                    <span class="cl-ctx__value">{{ $doubtfulDebt->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                            </div>
                            <div class="cl-ctx__item">
                                <span class="cl-ctx__icon"><i class="feather icon-user"></i></span>
                                <div class="cl-ctx__body">
                                    <span class="cl-ctx__label">سجل بواسطة</span>
                                    <span class="cl-ctx__value">{{ $doubtfulDebt->createdByUser->name ?? 'النظام' }}</span>
                                </div>
                            </div>
                            @if($doubtfulDebt->collected_date)
                                <div class="cl-ctx__item">
                                    <span class="cl-ctx__icon"><i class="feather icon-check"></i></span>
                                    <div class="cl-ctx__body">
                                        <span class="cl-ctx__label">تاريخ التحصيل</span>
                                        <span class="cl-ctx__value">{{ $doubtfulDebt->collected_date->format('Y-m-d') }}</span>
                                    </div>
                                </div>
                            @endif
                            @if($doubtfulDebt->journalEntry)
                                <div class="cl-ctx__item">
                                    <span class="cl-ctx__icon"><i class="feather icon-book"></i></span>
                                    <div class="cl-ctx__body">
                                        <span class="cl-ctx__label">القيد الأصلي</span>
                                        <span class="cl-ctx__value">{{ $doubtfulDebt->journalEntry->reference_number }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ─── تبويب: تحصيل المديونية ─── --}}
                <div class="cl-tabpanel" data-cl-panel="collection" hidden>
                    @if($doubtfulDebt->status == 'pending' && $doubtfulDebt->getRemainingBalance() > 0)
                        <div class="alert alert-success">
                            <i class="feather icon-info"></i>
                            استخدم هذا النموذج لتسجيل دفعٍ فعلي من العميل. المبلغ المتبقي:
                            <strong>{{ number_format($doubtfulDebt->getRemainingBalance(), 2) }}</strong> ريال.
                        </div>
                        <form action="{{ route('doubtful-debts.update-collection', $doubtfulDebt->id) }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="cl-req">المبلغ المدفوع</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" name="collected_amount" class="form-control" max="{{ $doubtfulDebt->getRemainingBalance() }}" placeholder="0.00" required>
                                            <span class="input-group-text">ريال</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="cl-req">تاريخ الدفع</label>
                                        <input type="date" name="collection_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="cl-req">طريقة الدفع</label>
                                        <select name="payment_method" class="form-select select2" required>
                                            <option value="">-- اختر --</option>
                                            <option value="cash">كاش</option>
                                            <option value="bank">تحويل بنكي</option>
                                            <option value="check">شيك</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>رقم المرجع</label>
                                        <input type="text" name="reference_number" class="form-control" placeholder="مثال: INV-2024-001">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>ملاحظات</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="أضف أي ملاحظات إضافية..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="feather icon-check-circle"></i> تأكيد التحصيل
                            </button>
                        </form>
                    @else
                        <div class="cl-empty">
                            <span class="cl-empty__icon"><i class="feather icon-info"></i></span>
                            <p class="cl-empty__hint">لا توجد عمليات تحصيل متاحة حالياً</p>
                        </div>
                    @endif
                </div>

                {{-- ─── تبويب: إرجاع المديونية ─── --}}
                <div class="cl-tabpanel" data-cl-panel="reversal" hidden>
                    @if($doubtfulDebt->status == 'pending')
                        <div class="alert alert-info">
                            <i class="feather icon-info"></i>
                            هذا الخيار يُرجع المديونية للعميل بقيدٍ عكسي بدون دفعٍ فعلي (مثل طلب كشف حساب). المبلغ:
                            <strong>{{ number_format($doubtfulDebt->provision_amount, 2) }}</strong> ريال.
                        </div>
                        <form action="{{ route('doubtful-debts.reverse-without-collection', $doubtfulDebt->id) }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="cl-req">تاريخ القيد العكسي</label>
                                        <input type="date" name="reversal_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="cl-req">السبب</label>
                                        <select name="reason" class="form-select select2" required>
                                            <option value="">-- اختر السبب --</option>
                                            <option value="طلب كشف حساب">طلب كشف حساب</option>
                                            <option value="تصحيح رصيد خاطئ">تصحيح رصيد خاطئ</option>
                                            <option value="خطأ في التحويل">خطأ في التحويل</option>
                                            <option value="إرجاع للنقاش">إرجاع للنقاش</option>
                                            <option value="أخرى">أخرى</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="cl-req">شرح السبب بالتفصيل</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="اشرح السبب بالتفصيل..." required></textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-info" onclick="return confirm('هل أنت متأكد من إرجاع المديونية؟')">
                                <i class="feather icon-rotate-ccw"></i> إرجاع المديونية
                            </button>
                        </form>
                    @else
                        <div class="cl-empty">
                            <span class="cl-empty__icon"><i class="feather icon-info"></i></span>
                            <p class="cl-empty__hint">لا توجد عمليات إرجاع متاحة حالياً</p>
                        </div>
                    @endif
                </div>

                {{-- ─── تبويب: الملاحظات ─── --}}
                <div class="cl-tabpanel" data-cl-panel="notes" hidden>
                    @if($doubtfulDebt->notes)
                        <div class="alert alert-info" style="white-space: pre-wrap; word-wrap: break-word;">
                            {{ $doubtfulDebt->notes }}
                        </div>
                    @else
                        <div class="cl-empty">
                            <span class="cl-empty__icon"><i class="feather icon-file-text"></i></span>
                            <p class="cl-empty__hint">لا توجد ملاحظات حالياً</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-cl-tab]');
            if (!tabs.length) { return; }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var key = tab.getAttribute('data-cl-tab');

                    tabs.forEach(function (other) {
                        var isTarget = other === tab;
                        other.classList.toggle('is-active', isTarget);
                        other.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                    });

                    document.querySelectorAll('[data-cl-panel]').forEach(function (panel) {
                        panel.hidden = panel.getAttribute('data-cl-panel') !== key;
                    });
                });
            });
        })();
    </script>
@endsection
