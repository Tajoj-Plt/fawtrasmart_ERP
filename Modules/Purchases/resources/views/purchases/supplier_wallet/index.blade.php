{{--
    محافظ الموردين — القائمة.

    المحفظة هنا **أصل**: مالٌ لنا لدى المورد (دفعةٌ مقدَّمة)، عكسُ محفظة
    العميل. فالرصيد الموجب يعني رصيداً لنا نستهلكه في فواتير الشراء.

    وكلُّ مورّدٍ يظهر ولو لم تُفتح له محفظةٌ بعد: تُنشأ عند أوّل دفعة.

    ══════════════ التصميم ══════════════

    على قياس «محافظ العملاء» بطلب المستخدم — الإحصاءات، وبطاقة التصفية،
    وبطاقة الجدول بترويستها وذيلها. وكانت على نظام `po-` (بطاقةُ تفاصيل
    وجدولُ سرد)، فتخرج الشاشتان المتناظرتان بمقاسين مختلفين.

    والبادئة هنا `sl-` لا `cl-`: هي نسخةُ الوحدة من نظام التصميم نفسِه
    (`purchase_requests/partials/sales-theme` — والقواعد الناقصة نُقلت
    إليها)، فلا يشير قالبُ المشتريات إلى وحدة العملاء ولا ينكسر بإطفائها.
--}}
@extends('purchases::master')

@section('title')
    محافظ الموردين
@stop

@section('content')
    @php
        use App\Support\CurrencySettings;

        $openedCount = $wallets->count();
        $fundedCount = $wallets->filter(fn ($b) => (float) $b > 0)->count();
    @endphp

    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">
        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => 'محافظ الموردين',
            'icon' => 'wallet',
            'sub' => 'أرصدةٌ لنا لدى الموردين — تُدفع مقدّماً وتُخصَم منها الفواتير',
            'crumbs' => [
                'المشتريات' => route('purchases.dashboard'),
                'محافظ الموردين' => null,
            ],
            // التقريرُ في «تقارير المشتريات»، ورابطُه هنا أيضاً
            'actions' => hasPermission(['purchases.reports.supplier_wallets', 'purchases.supplier_wallet.view'])
                ? '<a href="'.e(route('ReportsPurchases.supplierWallets')).'" class="btn btn-outline-primary"><i class="fas fa-chart-bar me-1"></i> تقرير المحافظ</a>'
                : null,
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- الأب غير مربوط: المحفظة لا تُفتح أصلاً، فيُقال ذلك قبل أن
             يحاول المستخدم الدفع ويصطدم برسالة خطأ --}}
        @unless ($walletsParentMapped)
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                حساب <strong>«دفعات مقدَّمة للموردين»</strong> غير مربوط بعد — لا يمكن فتح محفظة لأي مورد.
                اربطه من: <a href="{{ url('/accounting-settings') }}">الإعدادات ← الحسابات المحاسبية</a>.
                <div class="mt-1 small">
                    وهو حسابٌ تحت <strong>الأصول</strong>، فالمحفظة مالٌ لنا لدى المورد لا عليه.
                </div>
            </div>
        @endunless

        @include('purchases::components.guide', [
            'key' => 'supplier-wallets.index',
            'open' => false,
            'title' => 'ما محفظة المورد؟',
            'lead' => 'رصيدٌ لنا لدى المورد — دفعةٌ مقدَّمة سلّمناها ولم نستهلكها بعد، تُخصَم منها فواتير الشراء. لكل موردٍ حسابٌ مستقلّ تحت «دفعات مقدَّمة للموردين» في الأصول، يُفتح عند أوّل دفعة.',
            'blocks' => [
                'الحركات' => [
                    'دفعة مقدَّمة — من ح/ محفظة المورد إلى ح/ الخزينة: النقد يخرج ويصير رصيداً لنا لديه.',
                    'استرداد نقدي — من ح/ الخزينة إلى ح/ محفظة المورد: يعود النقد وينقص الرصيد.',
                    'سداد فاتورة شراء — من ح/ المورد إلى ح/ محفظة المورد: يَنقص ما علينا وما لنا لديه معاً.',
                ],
            ],
            'facts' => [
                'المحفظة حسابٌ تحت الأصول، بخلاف محفظة العميل فهي تحت الخصوم',
                'لا يُسحَب ولا يُسدَّد أكثر من الرصيد',
            ],
        ])

        {{-- ══════════ الإحصاءات ══════════
             إجمالي ما لنا لدى الموردين — أصلٌ للشركة، فيُصدَّر أوّلاً --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-wallet"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ CurrencySettings::format($totalBalance) }}</p>
                    <p class="sl-stat__label">إجمالي أرصدة المحافظ — مالٌ لنا لدى الموردين (أصل)</p>
                </div>
            </div>
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-truck"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $fundedCount }}</p>
                    <p class="sl-stat__label">موردون لهم رصيد في المحفظة</p>
                </div>
            </div>
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--info"><i class="fas fa-folder-open"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $openedCount }}</p>
                    <p class="sl-stat__label">محافظ مفتوحة</p>
                </div>
            </div>
        </div>

        {{-- ══════════ الفلاتر ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h4 class="sl-filters__title"><i class="fas fa-filter"></i> بحث وتصفية</h4>
                </div>

                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label for="search">بحث</label>
                        <input type="text" name="search" id="search" class="form-control"
                            value="{{ request('search') }}" placeholder="اسم المورد أو هاتفه">
                    </div>
                    <div class="col-md-3">
                        <label for="balance">الرصيد</label>
                        <select name="balance" id="balance" class="form-control">
                            <option value="">الكل</option>
                            <option value="nonzero" {{ request('balance') === 'nonzero' ? 'selected' : '' }}>
                                ذوو الرصيد فقط
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="per_page">عدد الصفوف</label>
                        <select name="per_page" id="per_page" class="form-control">
                            @foreach ($pageSizes as $size)
                                <option value="{{ $size }}" {{ $suppliers->perPage() === $size ? 'selected' : '' }}>
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search"></i> بحث
                        </button>
                        @if ($hasFilters)
                            <a href="{{ route('purchases.supplier-wallet.index') }}" class="btn btn-outline-secondary" title="إلغاء التصفية">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ جدول المحافظ ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h4 class="sl-table-head__title">
                    <i class="fas fa-list"></i>
                    الموردون ومحافظهم
                    <span class="sl-count">{{ $suppliers->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>المورد</th>
                                <th>الهاتف</th>
                                <th class="text-center">رصيد المحفظة</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:90px">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suppliers as $supplier)
                                @php
                                    $hasWallet = $wallets->has($supplier->id);
                                    $balance = (float) ($wallets[$supplier->id] ?? 0);
                                    $displayName = $supplier->trade_name
                                        ?: (trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: '#'.$supplier->id);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="sl-media">
                                            <span class="sl-media__thumb"><i class="fas fa-truck"></i></span>
                                            <div class="sl-media__body">
                                                <a href="{{ route('purchases.supplier-wallet.show', $supplier->id) }}"
                                                    class="sl-media__title text-decoration-none">
                                                    {{ $displayName }}
                                                </a>
                                                <span class="sl-media__sub">#{{ $supplier->id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-num>{{ $supplier->phone ?: '—' }}</td>
                                    <td class="text-center" data-num>
                                        <strong class="{{ $balance > 0 ? 'text-success' : 'text-muted' }}">
                                            {{ CurrencySettings::format($balance) }}
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        {{-- أغلب الموردين بلا محفظة: شارةٌ ملوّنة على كل صفٍّ منهم
                                             تُغرق الصفوف التي لها رصيدٌ فعلاً، فتبقى نصّاً باهتاً --}}
                                        @if ($balance > 0)
                                            <span class="sl-state sl-state--on">له رصيد</span>
                                        @elseif ($hasWallet)
                                            <span class="sl-state sl-state--off">محفظة فارغة</span>
                                        @else
                                            <span class="text-muted small">لم تُفتح بعد</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="sl-actions">
                                            <a href="{{ route('purchases.supplier-wallet.show', $supplier->id) }}"
                                                class="btn btn-outline-primary" title="عرض المحفظة">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="sl-empty">
                                            <span class="sl-empty__icon"><i class="fas fa-inbox"></i></span>
                                            <p class="sl-empty__title">لا يوجد موردون مطابقون</p>
                                            @if ($hasFilters)
                                                <p class="sl-empty__hint">جرّب تغيير كلمات البحث أو إلغاء التصفية.</p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($suppliers->total() > 0)
                <div class="sl-table-foot">
                    <p class="sl-table-foot__count">
                        عرض {{ $suppliers->firstItem() }} إلى {{ $suppliers->lastItem() }} من {{ $suppliers->total() }} مورد
                    </p>
                    @if ($suppliers->hasPages())
                        {{ $suppliers->links('pagination::bootstrap-5') }}
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
