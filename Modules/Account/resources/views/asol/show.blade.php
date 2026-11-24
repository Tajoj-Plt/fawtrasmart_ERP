@extends('account::master')

@section('title', 'تفاصيل الأصل')

@section('content')
    @php
        $bookValue = $asset->depreciation?->book_value ?? $asset->purchase_value;
        $accumulated = max(0, ($asset->purchase_value ?? 0) - $bookValue);

        $states = [
            1 => ['stk-state--on', 'في الخدمة'],
            2 => ['stk-state--neutral', 'تم البيع'],
            3 => ['stk-state--off', 'مهلك'],
        ];

        [$stateClass, $stateLabel] = $states[$asset->status] ?? ['stk-state--neutral', 'غير معروف'];

        $methods = [
            1 => 'القسط الثابت',
            2 => 'القسط المتناقص',
            3 => 'وحدات الإنتاج',
            4 => 'بدون إهلاك',
        ];

        $operations = $operationsPaginator ?? collect();
    @endphp

    <div class="content-body">

        @section('page-actions')
            <button type="button" class="btn btn-outline-secondary print-button">
                <i class="feather icon-printer"></i> طباعة
            </button>
            <a href="{{ route('Assets.generatePdf', $asset->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-file-text"></i> PDF
            </a>
            @can('account.assets.sell')
                @if ($asset->status != 2)
                    <a href="{{ route('Assets.showSell', $asset->id) }}" class="btn btn-outline-primary">
                        <i class="feather icon-dollar-sign"></i> بيع الأصل
                    </a>
                @endif
            @endcan
            @can('account.assets.edit')
                <a href="{{ route('Assets.edit', $asset->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => $asset->name,
            'icon' => 'box',
            'sub' => 'أصل رقم ' . $asset->code . ' — ' . $stateLabel,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأصول الثابتة' => route('Assets.index'),
                $asset->name => null,
            ],
        ])


        @if (session('success_details'))
            <div class="alert alert-success" role="alert">
                <p class="mb-1"><i class="feather icon-check-circle"></i>
                    <strong>{{ session('success_message') ?? 'تمّت العملية' }}</strong>
                </p>
                <ul class="mb-0 ps-3 pe-3">
                    @foreach (session('success_details') as $label => $value)
                        <li>{{ $label }}: {{ $value }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                <span class="stk-metric__label">قيمة الشراء</span>
                <span class="stk-metric__value">@money($asset->purchase_value ?? 0, $asset)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-book"></i></span>
                <span class="stk-metric__label">القيمة الدفترية</span>
                <span class="stk-metric__value">@money($bookValue, $asset)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">مجمّع الإهلاك</span>
                <span class="stk-metric__value">@money($accumulated, $asset)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">طريقة الإهلاك</span>
                <span class="stk-metric__value">
                    {{ $methods[$asset->depreciation?->dep_method ?? 4] ?? 'بدون إهلاك' }}
                </span>
            </div>
        </div>

        {{--
            الألسنة كانت تستعمل `data-toggle="tab"` — سمة بوتستراب 4 —
            والصفحة تُحمّل بوتستراب 5 الذي يقرأ `data-bs-toggle`. أي أنّ
            الألسنة الأربعة **لم تكن تعمل إطلاقاً**: تُفتح الصفحة على «الأصل»
            ولا يُفتح غيره مهما نُقر.

            ولسان «الأصل» كان يحشر ملف PDF داخل لوح رمادي بارتفاع الشاشة
            كاملاً (`background: lightslategray; min-height: 100vh`) ثم يُصغّره
            إلى 0.8 — نسخةٌ مصغّرة من ورقة طباعة بدل بياناتٍ تُقرأ.
        --}}
        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab"
                            data-bs-target="#details" type="button" role="tab" aria-controls="details"
                            aria-selected="true">
                            <i class="feather icon-file-text"></i> بيانات الأصل
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab"
                            data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions"
                            aria-selected="false">
                            <i class="feather icon-activity"></i> الحركات
                            <span class="stk-count">{{ count($operations) }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attachments-tab" data-bs-toggle="tab"
                            data-bs-target="#attachments" type="button" role="tab" aria-controls="attachments"
                            aria-selected="false">
                            <i class="feather icon-paperclip"></i> المرفقات
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity"
                            type="button" role="tab" aria-controls="activity" aria-selected="false">
                            <i class="feather icon-clock"></i> سجلّ النشاط
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-2">

                    {{-- ═══════════ بيانات الأصل ═══════════ --}}
                    <div class="tab-pane fade show active" id="details" role="tabpanel"
                        aria-labelledby="details-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="stk-def">
                                    <div class="stk-def__row"><span>الكود</span><strong class="stk-num">{{ $asset->code }}</strong></div>
                                    <div class="stk-def__row"><span>الاسم</span><strong>{{ $asset->name }}</strong></div>
                                    <div class="stk-def__row"><span>الحالة</span>
                                        <span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span>
                                    </div>
                                    <div class="stk-def__row"><span>تاريخ الشراء</span><strong class="stk-num">{{ $asset->date_price ?? '—' }}</strong></div>
                                    <div class="stk-def__row"><span>تاريخ الخدمة</span><strong class="stk-num">{{ $asset->date_service ?? '—' }}</strong></div>
                                    <div class="stk-def__row"><span>الكمية</span><strong class="stk-num">{{ $asset->quantity ?? 1 }}</strong></div>
                                    <div class="stk-def__row"><span>الموقع</span><strong>{{ $asset->place ?: '—' }}</strong></div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="stk-def">
                                    <div class="stk-def__row"><span>حساب الأصل</span><strong>{{ $asset->account?->name ?? '—' }}</strong></div>
                                    <div class="stk-def__row"><span>حساب الدفع</span><strong>{{ $asset->cashAccountRelation?->name ?? '—' }}</strong></div>
                                    <div class="stk-def__row"><span>الموظف المسؤول</span><strong>{{ $asset->employee?->full_name ?? '—' }}</strong></div>
                                    <div class="stk-def__row"><span>العميل</span><strong>{{ $asset->client?->trade_name ?? '—' }}</strong></div>
                                    <div class="stk-def__row"><span>العملة</span><strong class="stk-num">{{ $asset->currency }}</strong></div>
                                    <div class="stk-def__row"><span>قيمة الخردة</span><strong>@money($asset->depreciation?->salvage_value ?? 0, $asset)</strong></div>
                                    <div class="stk-def__row"><span>مدّة الإهلاك</span>
                                        <strong class="stk-num">
                                            {{--
                                                `?->` لا `->`: أصلٌ لم يُسجَّل
                                                له إهلاك يجعل العلاقة `null`،
                                                وقراءةُ خاصيّةٍ منها تحذيرٌ
                                                يرفعه لارافيل استثناءً — فتردّ
                                                الصفحة ٥٠٠ بدل أن تعرض شرطة.
                                            --}}
                                            {{ $asset->depreciation?->duration ? $asset->depreciation->duration . ' فترة' : '—' }}
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            @if ($asset->description)
                                <div class="col-12">
                                    <div class="stk-writein mt-1">
                                        <strong>الوصف:</strong> {{ $asset->description }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ═══════════ الحركات ═══════════ --}}
                    <div class="tab-pane fade" id="transactions" role="tabpanel"
                        aria-labelledby="transactions-tab">
                        @if (count($operations) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>العملية</th>
                                            <th class="text-center">مدين</th>
                                            <th class="text-center">دائن</th>
                                            <th class="text-center">الرصيد بعدها</th>
                                            <th class="text-center">التاريخ</th>
                                            <th class="text-center" style="width:90px">القيد</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($operations as $operation)
                                            <tr>
                                                <td>{{ $operation['operation'] }}</td>
                                                <td class="text-center">@money($operation['deposit'], $asset)</td>
                                                <td class="text-center">@money($operation['withdraw'], $asset)</td>
                                                <td class="text-center"><strong>@money($operation['balance_after'], $asset)</strong></td>
                                                <td class="text-center stk-num">
                                                    {{ \Carbon\Carbon::parse($operation['date'])->format('Y-m-d') }}
                                                </td>
                                                <td class="text-center">
                                                    @if (isset($operation['journalEntry']))
                                                        <a href="{{ route('journal.show', $operation['journalEntry']) }}"
                                                            class="btn btn-sm btn-outline-secondary" title="عرض القيد">
                                                            <i class="feather icon-eye"></i>
                                                        </a>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-activity"></i></span>
                                <h6 class="stk-empty__title">لا حركات مالية على هذا الأصل</h6>
                                <p class="stk-empty__hint">تظهر هنا قيود الشراء والإهلاك والبيع.</p>
                            </div>
                        @endif
                    </div>

                    {{-- ═══════════ المرفقات ═══════════ --}}
                    <div class="tab-pane fade" id="attachments" role="tabpanel" aria-labelledby="attachments-tab">
                        @if ($asset->attachments)
                            <a href="{{ asset('storage/' . $asset->attachments) }}" target="_blank"
                                rel="noopener" class="stk-photo d-inline-block">
                                <img src="{{ asset('storage/' . $asset->attachments) }}" alt="مرفق الأصل">
                            </a>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-paperclip"></i></span>
                                <h6 class="stk-empty__title">لا مرفقات</h6>
                                <p class="stk-empty__hint">أرفق فاتورة الشراء أو صورة الأصل من شاشة التعديل.</p>
                            </div>
                        @endif
                    </div>

                    {{-- ═══════════ سجلّ النشاط ═══════════ --}}
                    <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                        <div class="stk-timeline">
                            @if ($asset->created_at)
                                <div class="stk-timeline__item">
                                    <span class="stk-timeline__dot stk-timeline__dot--in">
                                        <i class="feather icon-plus"></i>
                                    </span>
                                    <div class="stk-timeline__body">
                                        <div class="stk-timeline__text">
                                            أُضيف الأصل تحت حساب
                                            <strong>{{ $asset->account?->name ?? '—' }}</strong>
                                            بقيمة شراء @money($asset->purchase_value, $asset)
                                            @if ($asset->cashAccountRelation)
                                                من حساب <strong>{{ $asset->cashAccountRelation->name }}</strong>
                                            @endif
                                        </div>
                                        <div class="stk-timeline__meta">
                                            <span class="stk-timeline__day">{{ $asset->created_at->format('Y-m-d') }}</span>
                                            <span class="stk-timeline__time">{{ $asset->created_at->format('H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($asset->updated_at && $asset->updated_at->ne($asset->created_at))
                                <div class="stk-timeline__item">
                                    <span class="stk-timeline__dot stk-timeline__dot--log">
                                        <i class="feather icon-edit-2"></i>
                                    </span>
                                    <div class="stk-timeline__body">
                                        <div class="stk-timeline__text">عُدِّلت بيانات الأصل</div>
                                        <div class="stk-timeline__meta">
                                            <span class="stk-timeline__day">{{ $asset->updated_at->format('Y-m-d') }}</span>
                                            <span class="stk-timeline__time">{{ $asset->updated_at->format('H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{--
                            كان اللسان يعرض «أضاف المستخدم الحالي» — أي أنّ من
                            يفتح الشاشة هو من يظهر بوصفه المنشئ، لا من أنشأها
                            فعلاً. حُذف الاسم بدل نسبة الفعل إلى غير صاحبه.
                        --}}
                        <p class="stk-help mt-1">
                            السجلّ الكامل بمن نفّذ كل عملية في «سجلّ النشاط» بالإعدادات.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.querySelectorAll('.print-button').forEach(function (btn) {
            btn.addEventListener('click', function () { window.print(); });
        });
    </script>
@endsection
