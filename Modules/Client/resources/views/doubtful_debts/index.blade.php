{{--
    قائمة الديون المشكوك فيها — أُعيدت بنظام تصميم وحدة العملاء الحقيقي
    (`.cl-scope` + `partials/settings-theme` + `components/page-head`) بدل
    نظامٍ مخترَعٍ كان يستعمل `@extends('master')` (تخطيط الوحدة الصحيح
    `client::master`) وبطاقاتٍ وألواناً مكتوبةً في الشيفرة لا تتبع «مظهر
    النظام». المنطق (الفلترة، الترقيم، الإحصاءات) لم يُمسّ.
--}}
@extends('client::master')

@section('title')
    الديون المشكوك فيها
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @php
            $statusBadge = [
                'pending' => ['bg' => 'badge-warning', 'label' => 'قيد التحصيل'],
                'collected' => ['bg' => 'badge-success', 'label' => 'تم تحصيله'],
                'written_off' => ['bg' => 'badge-danger', 'label' => 'تم شطبه'],
            ];
        @endphp

        @include('client::components.page-head', [
            'title' => 'الديون المشكوك فيها',
            'icon' => 'alert-triangle',
            'sub' => 'متابعة تصنيف مديونيات العملاء كمشكوك فيها وتحصيلها أو شطبها'
                .($headerBranch ? ' — فرع «'.$headerBranch->name.'» وما تحته (من الشريط العلوي)' : ''),
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'الديون المشكوك فيها' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @include('client::components.guide', [
            'key' => 'clients.doubtful-debts.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'كل صفٍّ هنا مديونيةُ عميلٍ صُنِّفت كمشكوك في تحصيلها: يُصفَّر حساب العميل فوراً مقابل مخصَّصٍ يُنشأ له، ثم يُتابَع تحصيله أو شطبه لاحقاً من صفحة التفاصيل.',
            'blocks' => [
                'الحالات' => [
                    'قيد التحصيل — لم يُحصَّل من المديونية شيء بعد، أو حُصِّل جزء منها.',
                    'تم تحصيله — حُصِّل كامل مبلغ المخصَّص فعلياً (نقداً أو بنكاً أو شيكاً).',
                    'تم شطبه — أُلغي المخصَّص نهائياً في حساب «الديون المشكوك فيها» بلا تحصيل.',
                ],
            ],
            'facts' => [
                'الحسابات المحاسبية تأتي من شاشة «الحسابات المحاسبية» فقط',
                'الشطب لا يمسّ الخزينة — طرفاه حساب العميل ومخصّص الديون فقط',
            ],
        ])

        {{-- ══════════ إحصاءات الحالات ══════════ --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon"><i class="feather icon-list"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $stats['total'] }}</p>
                    <p class="cl-stat__label">إجمالي الديون</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--warning"><i class="feather icon-clock"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $stats['pending'] }}</p>
                    <p class="cl-stat__label">قيد التحصيل</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="feather icon-check-circle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $stats['collected'] }}</p>
                    <p class="cl-stat__label">تم تحصيله</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--danger"><i class="feather icon-trash-2"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $stats['written_off'] }}</p>
                    <p class="cl-stat__label">تم شطبه</p>
                </div>
            </div>
        </div>

        {{-- ══════════ إحصاءات المبالغ ══════════ --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--danger"><i class="feather icon-trending-down"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ number_format($stats['total_amount'], 2) }}</p>
                    <p class="cl-stat__label">المبلغ الكلي المديون</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--warning"><i class="feather icon-shield"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ number_format($stats['total_provision'], 2) }}</p>
                    <p class="cl-stat__label">المخصص الإجمالي</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="feather icon-check"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ number_format($stats['total_collected'], 2) }}</p>
                    <p class="cl-stat__label">المبلغ المحصل</p>
                </div>
            </div>
        </div>

        {{-- ══════════ الفلاتر ══════════ --}}
        <div class="card cl-filters">
            <div class="card-body">
                <div class="cl-filters__head">
                    <h4 class="cl-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h4>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clDdToggleFilters">
                        <i class="feather icon-eye-off"></i>
                        <span>إخفاء</span>
                    </button>
                </div>

                <form method="GET" class="row g-3" id="clDdFilterForm">
                    <div class="col-md-3">
                        <label>البحث عن عميل</label>
                        <input type="text" name="search" class="form-control" placeholder="اكتب اسم العميل..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <label>الحالة</label>
                        <select name="status" class="form-select select2">
                            <option value="">-- جميع الحالات --</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>قيد التحصيل</option>
                            <option value="collected" {{ request('status') == 'collected' ? 'selected' : '' }}>تم تحصيله</option>
                            <option value="written_off" {{ request('status') == 'written_off' ? 'selected' : '' }}>تم شطبه</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>العميل</label>
                        <select name="client_id" class="form-select select2">
                            <option value="">-- اختر عميل --</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                    {{ $client->trade_name }}-{{ $client->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        <a href="{{ route('doubtful-debts.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ جدول الديون ══════════ --}}
        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-list"></i>
                    الديون المشكوك فيها
                    <span class="cl-count">{{ $doubtfulDebts->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم العميل</th>
                                <th>الرصيد الأصلي</th>
                                <th>المخصص</th>
                                <th>المحصل</th>
                                <th>المتبقي</th>
                                <th>نسبة التحصيل</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($doubtfulDebts as $debt)
                                <tr>
                                    <td data-num>#{{ $debt->id }}</td>
                                    <td>
                                        <a href="{{ route('clients.show', $debt->client->id) }}" class="cl-media__title text-decoration-none">
                                            {{ $debt->client->trade_name }}
                                        </a>
                                    </td>
                                    <td data-num><strong>{{ number_format($debt->original_balance, 2) }}</strong></td>
                                    <td data-num>{{ number_format($debt->provision_amount, 2) }}</td>
                                    <td data-num class="text-success"><strong>{{ number_format($debt->collected_amount ?? 0, 2) }}</strong></td>
                                    <td data-num class="text-danger"><strong>{{ number_format($debt->getRemainingBalance(), 2) }}</strong></td>
                                    <td>
                                        @php
                                            $percentage = $debt->getCollectionPercentage();
                                            $barColor = $percentage < 50 ? 'danger' : ($percentage < 100 ? 'warning' : 'success');
                                        @endphp
                                        <div class="progress" style="min-width: 100px; height: 22px;">
                                            <div class="progress-bar bg-{{ $barColor }}" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                                                {{ $percentage }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusBadge[$debt->status]['bg'] ?? 'badge-secondary' }}">
                                            {{ $statusBadge[$debt->status]['label'] ?? $debt->status }}
                                        </span>
                                    </td>
                                    <td>{{ $debt->created_at->format('Y-m-d') }}</td>
                                    <td>
                                        <div class="cl-actions">
                                            <a href="{{ route('doubtful-debts.show', $debt->id) }}" class="btn btn-outline-primary" title="عرض التفاصيل">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="cl-empty">
                                            <span class="cl-empty__icon"><i class="feather icon-inbox"></i></span>
                                            <p class="cl-empty__title">لا توجد ديون مشكوك فيها</p>
                                            <p class="cl-empty__hint">جميع الديون تم تسويتها بنجاح</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($doubtfulDebts->hasPages())
                <div class="cl-table-foot">
                    <p class="cl-table-foot__count">
                        عرض {{ $doubtfulDebts->currentPage() * $doubtfulDebts->perPage() - $doubtfulDebts->perPage() + 1 }}
                        إلى {{ min($doubtfulDebts->currentPage() * $doubtfulDebts->perPage(), $doubtfulDebts->total()) }}
                        من {{ $doubtfulDebts->total() }} سجل
                    </p>
                    {{ $doubtfulDebts->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var toggle = document.getElementById('clDdToggleFilters');
            var form = document.getElementById('clDdFilterForm');
            if (!toggle || !form) { return; }

            toggle.addEventListener('click', function () {
                var hidden = form.style.display === 'none';
                form.style.display = hidden ? '' : 'none';
                toggle.querySelector('span').textContent = hidden ? 'إخفاء' : 'إظهار';
                toggle.querySelector('i').className = hidden ? 'feather icon-eye-off' : 'feather icon-eye';
            });
        })();
    </script>
@endsection
