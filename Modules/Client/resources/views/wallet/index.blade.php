{{--
    محافظ العملاء — القائمة.

    كل عميلٍ يظهر هنا ولو لم تُفتح له محفظةٌ بعد: المحفظة تُنشأ عند أوّل
    إيداع، ولو سُردت الحسابات وحدها لَما وجد المستخدمُ عميلاً يودع له
    أوّل مرّة.

    الرصيد **دائنٌ بطبيعته** — موجبٌ يعني مالاً للعميل عندنا.

    على نظام تصميم الوحدة (`cl-`): `client::master` يحقن `settings-theme`
    ويلفّ المحتوى بـ`.cl-scope`، فالشاشة تكتفي بمكوّناته — الترويسة،
    والإحصاءات، والفلاتر، وبطاقة الجدول — بلا لونٍ مكتوب في الشيفرة.
--}}
{{-- `client::master` لا `master`: نظير شاشة «محافظ الموردين» التي تمتدّ من
     `purchases::master`. وبالجذر كان النقر على «محافظ العملاء» في شريط
     الوحدة المسطَّح يهبط بالمستخدم على قائمة النظام كلّها بمجموعاتها —
     فيتبدّل الشريط تحت يده في منتصف المسار. --}}
@extends('client::master')

@section('title')
    محافظ العملاء
@stop

@section('content')
    @php
        use App\Support\CurrencySettings;

        $openedCount = $wallets->count();
        $fundedCount = $wallets->filter(fn ($b) => (float) $b > 0)->count();
    @endphp

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'محافظ العملاء',
            'icon' => 'credit-card',
            'sub' => 'أرصدةٌ للعملاء عندنا — تُودَع وتُسحَب ويُسدَّد منها',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'محافظ العملاء' => null,
            ],
            // التقريرُ في «تقارير العملاء»، ورابطُه هنا أيضاً لمن يملك الوصول إليه
            'actions' => hasPermission(['clients.reports.view', 'client_reports_view']) && hasPermission(['clients.reports.wallets', 'clients.wallet.view'])
                ? '<a href="'.e(route('ClientReport.customerWallets')).'" class="btn btn-outline-primary"><i class="fas fa-chart-bar me-1"></i> تقرير المحافظ</a>'
                : null,
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- الأب غير مربوط: المحفظة لا تُفتح أصلاً، فيُقال ذلك قبل أن
             يحاول المستخدم الإيداع ويصطدم برسالة خطأ --}}
        @unless ($walletsParentMapped)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                حساب <strong>«أرصدة محافظ العملاء»</strong> غير مربوط بعد — لا يمكن فتح محفظة لأي عميل.
                اربطه من: <a href="{{ url('/accounting-settings') }}">الإعدادات ← الحسابات المحاسبية</a>.
                <div class="mt-1 small">
                    وهو حسابٌ تحت <strong>الخصوم</strong>، فالمحفظة مالٌ للعميل عندنا لا عليه.
                </div>
            </div>
        @endunless

        @include('client::components.guide', [
            'key' => 'clients.wallet.index',
            'open' => false,
            'title' => 'ما محفظة العميل؟',
            'lead' => 'مالٌ للعميل عندنا — يودعه مقدّماً ثم يُسدَّد منه أو يُسحب. لكل عميلٍ حسابٌ مستقلّ تحت «أرصدة محافظ العملاء» في الخصوم، يُفتح عند أوّل إيداع.',
            'blocks' => [
                'الحركات' => [
                    'إيداع — نقدٌ يدخل الخزينة ويُقيَّد للعميل. لا إيراد ولا مبيعات.',
                    'سحب — نقدٌ يخرج من الخزينة وتنقص المحفظة. لا يُسحب على المكشوف.',
                    'سداد فاتورة — تنقص المحفظة وذمّة العميل معاً، ولا تمرّ الخزينة بالقيد.',
                ],
            ],
            'facts' => [
                'رصيد المحفظة منفصلٌ عن رصيد ذمم العميل ولا يُجمع معه',
                'كل حركةٍ لها قيدٌ متوازن يُراجَع من كشف المحفظة',
            ],
        ])

        {{-- ══════════ الإحصاءات ══════════
             إجمالي ما عندنا للعملاء — التزامٌ على الشركة، فيُصدَّر أوّلاً --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="feather icon-credit-card"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ CurrencySettings::format($totalBalance) }}</p>
                    <p class="cl-stat__label">إجمالي أرصدة المحافظ — مالٌ للعملاء عندنا (التزام)</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon"><i class="feather icon-users"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $fundedCount }}</p>
                    <p class="cl-stat__label">عملاء لهم رصيد في المحفظة</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--info"><i class="feather icon-folder"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ $openedCount }}</p>
                    <p class="cl-stat__label">محافظ مفتوحة</p>
                </div>
            </div>
        </div>

        {{-- ══════════ الفلاتر ══════════ --}}
        <div class="card cl-filters">
            <div class="card-body">
                <div class="cl-filters__head">
                    <h4 class="cl-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h4>
                </div>

                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label for="search">بحث</label>
                        <input type="text" name="search" id="search" class="form-control"
                            value="{{ request('search') }}" placeholder="اسم العميل أو كوده أو هاتفه">
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
                                <option value="{{ $size }}" {{ $clients->perPage() === $size ? 'selected' : '' }}>
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        @if ($hasFilters)
                            <a href="{{ route('clients.wallet.index') }}" class="btn btn-outline-secondary" title="إلغاء التصفية">
                                <i class="feather icon-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ جدول المحافظ ══════════ --}}
        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h4 class="cl-table-head__title">
                    <i class="feather icon-list"></i>
                    العملاء ومحافظهم
                    <span class="cl-count">{{ $clients->total() }}</span>
                </h4>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>العميل</th>
                                <th>الهاتف</th>
                                <th class="text-center">رصيد المحفظة</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:90px">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($clients as $client)
                                @php
                                    $hasWallet = $wallets->has($client->id);
                                    $balance = (float) ($wallets[$client->id] ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="cl-media">
                                            <span class="cl-media__thumb"><i class="feather icon-user"></i></span>
                                            <div class="cl-media__body">
                                                <a href="{{ route('clients.wallet.show', $client->id) }}"
                                                    class="cl-media__title text-decoration-none">
                                                    {{ $client->display_name }}
                                                </a>
                                                <span class="cl-media__sub">{{ $client->code ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-num>{{ $client->phone ?: '—' }}</td>
                                    <td class="text-center" data-num>
                                        <strong class="{{ $balance > 0 ? 'text-success' : 'text-muted' }}">
                                            {{ CurrencySettings::format($balance) }}
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        {{-- أغلب العملاء بلا محفظة: شارةٌ ملوّنة على كل صفٍّ منهم
                                             تُغرق الصفوف التي لها رصيدٌ فعلاً، فتبقى نصّاً باهتاً --}}
                                        @if ($balance > 0)
                                            <span class="cl-state cl-state--on">له رصيد</span>
                                        @elseif ($hasWallet)
                                            <span class="cl-state cl-state--off">محفظة فارغة</span>
                                        @else
                                            <span class="text-muted small">لم تُفتح بعد</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="cl-actions">
                                            <a href="{{ route('clients.wallet.show', $client->id) }}"
                                                class="btn btn-outline-primary" title="عرض المحفظة">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="cl-empty">
                                            <span class="cl-empty__icon"><i class="feather icon-inbox"></i></span>
                                            <p class="cl-empty__title">لا يوجد عملاء مطابقون</p>
                                            @if ($hasFilters)
                                                <p class="cl-empty__hint">جرّب تغيير كلمات البحث أو إلغاء التصفية.</p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($clients->total() > 0)
                <div class="cl-table-foot">
                    <p class="cl-table-foot__count">
                        عرض {{ $clients->firstItem() }} إلى {{ $clients->lastItem() }} من {{ $clients->total() }} عميل
                    </p>
                    @if ($clients->hasPages())
                        {{ $clients->links('pagination::bootstrap-5') }}
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
