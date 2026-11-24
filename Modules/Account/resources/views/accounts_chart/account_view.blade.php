{{--
    كانت هذه الشاشة وحدها تمتدّ `accounts_chart.header` — قالباً مبتوراً:
    بلا وسم `<html>`، وبلا ثيم النظام (`$systemTheme`, `$backgroundColorr`)
    الذي تضبطه شاشة المظهر، وبلا `@stack('scripts')`، ويحمّل مكتباته بنفسه.
    فالشريط الجانبي يظهر بلا ألوان الوحدة، ولا يسري عليها ما يسري على
    باقي الشاشات.

    الآن تمتدّ `account::master` كأخواتها، والقالب المبتور حُذف.
--}}
@extends('account::master')

@section('title')
    دليل الحسابات
@stop

@section('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/account-view.css') }}?v={{ time() }}">
@endsection

@section('content')
        <div class="acv-shell" dir="rtl">

            {{-- ============== Sidebar (شجرة الحسابات) ============== --}}
            <aside class="acv-sidebar">
                <div class="acv-sidebar__header">
                    <div class="acv-title">
                        <i class="feather icon-list"></i>
                        <span>شجرة الحسابات</span>
                    </div>
                    <div class="acv-sidebar__search">
                        <i class="feather icon-search"></i>
                        <input type="text" id="acvTreeSearch" placeholder="ابحث بالاسم أو الكود..." autocomplete="off">
                        <button type="button" id="acvTreeClear" title="مسح" class="acv-icon-btn" style="display:none;">
                            <i class="feather icon-x"></i>
                        </button>
                    </div>
                    <div class="acv-sidebar__filter">
                        <select id="acvBranchFilter" class="form-control form-control-sm">
                            <option value="all">كل الفروع</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @if($branchId == $b->id) selected @endif>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="acv-sidebar__body">
                    <div id="acvTreeLoading" class="acv-loading">
                        <div class="acv-spinner"></div>
                        <span>جار التحميل...</span>
                    </div>
                    <ul id="acvTree" class="acv-tree" role="tree" aria-label="شجرة الحسابات"></ul>
                    <div id="acvTreeEmpty" class="acv-empty" style="display:none;">
                        <i class="feather icon-inbox"></i>
                        <p>لا توجد نتائج</p>
                    </div>
                </div>
            </aside>

            {{-- ============== Main Panel ============== --}}
            <main class="acv-main">

                {{-- Top toolbar - actions globales --}}
                <div class="acv-toolbar">
                    <div class="acv-breadcrumb" id="acvBreadcrumb">
                        <a href="{{ route('accounts_chart.view_root') }}">
                            <i class="feather icon-home"></i> الجذر
                        </a>
                        @foreach($breadcrumb as $i => $crumb)
                            <i class="feather icon-chevron-left acv-bc-sep"></i>
                            @if($i === count($breadcrumb) - 1)
                                <span class="acv-bc-current">{{ $crumb['name'] }}</span>
                            @else
                                <a href="{{ route('accounts_chart.show_account', $crumb['id']) }}">{{ $crumb['name'] }}</a>
                            @endif
                        @endforeach
                    </div>
                    <div class="acv-toolbar__actions">
                        <button type="button" id="acvBtnUpdateAllBalances" class="btn btn-success btn-sm" title="تحديث جميع الأرصدة من القيود + تصحيح أنواع الحسابات (assets/liabilities/...)">
                            <i class="fa fa-sync-alt"></i> تحديث الأرصدة والأنواع
                        </button>
                        <button type="button" id="acvBtnAddAccount" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#acvAddAccountModal">
                            <i class="fa fa-plus"></i> أضف حساب
                        </button>
                    </div>
                </div>

                @if(!$account)
                    {{-- لا يوجد حساب محدد - الصفحة الرئيسية --}}
                    <div class="acv-welcome">
                        <i class="feather icon-folder acv-welcome__icon"></i>
                        <h3>اختر حساباً من الشجرة الجانبية</h3>
                        <p>يتم عرض الأبناء والقيود الخاصة بالحساب فور اختياره.</p>
                    </div>
                @else
                    {{-- ============== رأس الحساب ============== --}}
                    <div class="acv-account-header">
                        <div class="acv-account-header__main">
                            <div class="acv-account-icon">
                                @if($children->count() > 0)
                                    <i class="feather icon-folder"></i>
                                @else
                                    <i class="feather icon-file-text"></i>
                                @endif
                            </div>
                            <div class="acv-account-info">
                                <h2 class="acv-account-name">
                                    {{ $account->name }}
                                    <span class="acv-account-code-inline">#{{ $account->code }}</span>
                                </h2>
                                <div class="acv-account-meta">
                                    <span class="acv-pill acv-pill--code"><i class="fa fa-hashtag"></i> {{ $account->code }}</span>
                                    @if($account->account_type)
                                        <span class="acv-pill acv-pill--type">{{ $account->getAccountTypeTranslationAttribute() }}</span>
                                    @endif
                                    <span class="acv-pill acv-pill--balance acv-pill--{{ $account->balance_type }}">
                                        {{ $account->balance_type === 'debit' ? 'مدين' : 'دائن' }}
                                    </span>
                                    @if($account->is_system)
                                        <span class="acv-pill acv-pill--system"><i class="fa fa-shield-alt"></i> حساب نظام</span>
                                    @endif
                                    @if(!$account->is_active)
                                        <span class="acv-pill acv-pill--inactive">غير نشط</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="acv-account-balance">
                            <div class="acv-balance-label">الرصيد الحالي</div>
                            <div class="acv-balance-value" id="acvCurrentBalance">{{ number_format($account->balance ?? 0, 2) }}</div>
                            <div class="acv-balance-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary acv-btn-recalc" data-id="{{ $account->id }}" title="إعادة حساب رصيد هذا الحساب">
                                    <i class="fa fa-calculator"></i> تحديث الرصيد
                                </button>
                                <a href="{{ route('account_statement.statement', $account->id) }}" class="btn btn-sm btn-outline-info" title="كشف حساب">
                                    <i class="fa fa-file-invoice"></i> كشف حساب
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary acv-btn-edit" data-id="{{ $account->id }}" title="تعديل الحساب">
                                    <i class="fa fa-edit"></i> تعديل
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ============== ملخص الحركة ============== --}}
                    <div class="acv-summary">
                        <div class="acv-summary__card acv-summary__card--debit">
                            <div class="acv-summary__icon"><i class="fa fa-arrow-down"></i></div>
                            <div>
                                <div class="acv-summary__label">إجمالي المدين</div>
                                <div class="acv-summary__value" id="acvTotalDebit">{{ number_format($entriesSummary['total_debit'], 2) }}</div>
                            </div>
                        </div>
                        <div class="acv-summary__card acv-summary__card--credit">
                            <div class="acv-summary__icon"><i class="fa fa-arrow-up"></i></div>
                            <div>
                                <div class="acv-summary__label">إجمالي الدائن</div>
                                <div class="acv-summary__value" id="acvTotalCredit">{{ number_format($entriesSummary['total_credit'], 2) }}</div>
                            </div>
                        </div>
                        <div class="acv-summary__card acv-summary__card--count">
                            <div class="acv-summary__icon"><i class="fa fa-list"></i></div>
                            <div>
                                <div class="acv-summary__label">عدد القيود المباشرة</div>
                                <div class="acv-summary__value" id="acvEntriesCount">{{ $entriesSummary['count'] }}</div>
                            </div>
                        </div>
                        <div class="acv-summary__card acv-summary__card--children">
                            <div class="acv-summary__icon"><i class="fa fa-sitemap"></i></div>
                            <div>
                                <div class="acv-summary__label">عدد الحسابات الفرعية</div>
                                <div class="acv-summary__value">{{ $childrenTotal ?? 0 }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- ============== فلاتر القيود ============== --}}
                    <form method="GET" id="acvFilterForm" class="acv-filters">
                        {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى --}}
                        @include('partials.accounting-period-filter', [
                            'fromName' => 'from_date',
                            'toName' => 'to_date',
                            'colClass' => 'acv-filter-group',
                            'needsGroup' => false,
                        ])

                        <div class="acv-filter-group">
                            <label>من تاريخ</label>
                            <input type="date" name="from_date" value="{{ $fromDate }}">
                        </div>
                        <div class="acv-filter-group">
                            <label>إلى تاريخ</label>
                            <input type="date" name="to_date" value="{{ $toDate }}">
                        </div>
                        <div class="acv-filter-group">
                            <label>الفرع</label>
                            <select name="branch_id">
                                <option value="">كل الفروع</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @if($branchId == $b->id) selected @endif>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="acv-filter-group">
                            <label>قيود لكل صفحة</label>
                            <select name="per_page">
                                @foreach([15,25,50,100,200] as $opt)
                                    <option value="{{ $opt }}" @if($perPage == $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="acv-filter-group">
                            <label>أبناء لكل صفحة</label>
                            <select name="children_per_page">
                                @foreach([25,50,100,200,500] as $opt)
                                    <option value="{{ $opt }}" @if(($childrenPerPage ?? 50) == $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-filter"></i> تطبيق
                        </button>
                        <a href="{{ route('accounts_chart.show_account', $account->id) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-times"></i> مسح
                        </a>
                    </form>

                    {{-- ============== الجدول الموحَّد: أبناء + قيود ============== --}}
                    <div class="acv-table-wrap">
                        <table class="acv-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>التفاصيل</th>
                                    <th style="width:140px;">التاريخ / النوع</th>
                                    <th style="width:140px; text-align:left;">مدين</th>
                                    <th style="width:140px; text-align:left;">دائن</th>
                                    <th style="width:160px; text-align:left;">الرصيد</th>
                                    <th style="width:140px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- ====== أولاً: الحسابات الفرعية (الأبناء) ====== --}}
                                @if($children && $children->total() > 0)
                                    <tr class="acv-row-section">
                                        <td colspan="7">
                                            <i class="feather icon-folder"></i>
                                            الحسابات الفرعية
                                            <span class="acv-badge">{{ $children->total() }}</span>
                                            @if($children->hasPages())
                                                <small class="text-muted ms-2">
                                                    (عرض {{ $children->firstItem() }}-{{ $children->lastItem() }} من {{ $children->total() }})
                                                </small>
                                            @endif
                                        </td>
                                    </tr>
                                    @foreach($children as $child)
                                        <tr class="acv-row-child" data-account-id="{{ $child->id }}">
                                            <td>
                                                @if($child->has_children)
                                                    <i class="feather icon-folder text-warning"></i>
                                                @else
                                                    <i class="feather icon-file-text text-secondary"></i>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('accounts_chart.show_account', $child->id) }}" class="acv-link">
                                                    <strong>{{ $child->name }}</strong>
                                                    <small>#{{ $child->code }}</small>
                                                </a>
                                            </td>
                                            <td>
                                                @if($child->account_type)
                                                    <span class="acv-pill acv-pill--type acv-pill--sm">{{ $child->getAccountTypeTranslationAttribute() }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td colspan="2" class="text-center text-muted">—</td>
                                            <td class="text-left">
                                                <strong class="acv-balance-cell acv-balance-cell--{{ $child->balance_type }}">
                                                    {{ number_format($child->balance ?? 0, 2) }}
                                                </strong>
                                                <small class="d-block text-muted">{{ $child->balance_type === 'debit' ? 'مدين' : 'دائن' }}</small>
                                            </td>
                                            <td>
                                                <div class="acv-actions">
                                                    <a href="{{ route('accounts_chart.show_account', $child->id) }}" class="acv-icon-btn" title="فتح">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="acv-icon-btn acv-btn-recalc" data-id="{{ $child->id }}" title="تحديث الرصيد">
                                                        <i class="fa fa-calculator"></i>
                                                    </button>
                                                    <button type="button" class="acv-icon-btn acv-btn-edit" data-id="{{ $child->id }}" title="تعديل">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach

                                    {{-- صف pagination للأبناء --}}
                                    @if($children->hasPages())
                                        <tr class="acv-row-pagination-inline">
                                            <td colspan="7">
                                                @include('account::accounts_chart._pagination', ['paginator' => $children])
                                            </td>
                                        </tr>
                                    @endif
                                @endif

                                {{-- ====== ثانياً: القيود المباشرة على هذا الحساب ====== --}}
                                @if($directEntries && $directEntries->total() > 0)
                                    <tr class="acv-row-section acv-row-section--entries">
                                        <td colspan="7">
                                            <i class="feather icon-book-open"></i>
                                            القيود المحاسبية المباشرة
                                            <span class="acv-badge">{{ $directEntries->total() }}</span>
                                            <small class="text-muted ms-2">(لا تشمل قيود الحسابات الفرعية)</small>
                                        </td>
                                    </tr>
                                    @php $running = 0; @endphp
                                    @foreach($directEntries as $entry)
                                        @php
                                            $je = $entry->journalEntry;
                                            // الرصيد الجاري (كحركة فقط، ليس الرصيد المخزن)
                                            $running += ($account->balance_type === 'debit')
                                                ? (($entry->debit ?? 0) - ($entry->credit ?? 0))
                                                : (($entry->credit ?? 0) - ($entry->debit ?? 0));
                                        @endphp
                                        <tr class="acv-row-entry acv-clickable" data-entry-id="{{ $je->id ?? '' }}" title="اضغط لعرض تفاصيل القيد">
                                            <td><i class="feather icon-file-text text-info"></i></td>
                                            <td>
                                                <div class="acv-entry-desc">
                                                    <strong>{{ $entry->description ?: ($je->description ?? 'بدون وصف') }}</strong>
                                                    @if($je && $je->reference_number)
                                                        <small class="d-block text-muted">
                                                            مرجع: {{ $je->reference_number }}
                                                            @if($entry->costCenter)
                                                                · مركز تكلفة: {{ $entry->costCenter->name }}
                                                            @endif
                                                        </small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($je)
                                                    <small class="d-block">{{ $je->date ? \Carbon\Carbon::parse($je->date)->format('Y-m-d') : '—' }}</small>
                                                    <span class="acv-pill acv-pill--sm acv-pill--ref">{{ $je->reference_type_name ?? 'يدوي' }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-left">
                                                @if(($entry->debit ?? 0) > 0)
                                                    <strong class="text-success">{{ number_format($entry->debit, 2) }}</strong>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-left">
                                                @if(($entry->credit ?? 0) > 0)
                                                    <strong class="text-danger">{{ number_format($entry->credit, 2) }}</strong>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-left">
                                                <strong class="acv-balance-cell">{{ number_format($running, 2) }}</strong>
                                            </td>
                                            <td>
                                                <div class="acv-actions">
                                                    @if($je)
                                                        <button type="button" class="acv-icon-btn acv-btn-view-entry" data-entry-id="{{ $je->id }}" title="عرض القيد">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                        <a href="{{ route('journal.edit', $je->id) }}" class="acv-icon-btn" title="تعديل القيد" onclick="event.stopPropagation();">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif

                                {{-- ====== لا يوجد بيانات ====== --}}
                                @if((!$children || $children->total() === 0) && (!$directEntries || $directEntries->total() === 0))
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="feather icon-inbox" style="font-size:3rem;color:#ccc;"></i>
                                            <p class="mt-2 text-muted">لا توجد حسابات فرعية ولا قيود مرتبطة بهذا الحساب.</p>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    {{-- ============== Pagination للقيود ============== --}}
                    @if($directEntries && $directEntries->total() > 0)
                        <div class="acv-pagination-wrap">
                            <div class="acv-pagination-info">
                                عرض
                                <strong>{{ $directEntries->firstItem() }}</strong>
                                -
                                <strong>{{ $directEntries->lastItem() }}</strong>
                                من
                                <strong>{{ $directEntries->total() }}</strong>
                                قيد
                            </div>
                            @if($directEntries->hasPages())
                                @include('account::accounts_chart._pagination', ['paginator' => $directEntries])
                            @endif
                        </div>
                    @endif
                @endif
            </main>
        </div>

{{-- ============== Add Account Modal (مبسَّط) ============== --}}
<div class="modal fade" id="acvAddAccountModal" tabindex="-1" role="dialog" aria-labelledby="acvAddAccountTitle">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form id="acvAddAccountForm" method="POST" action="{{ route('accounts_chart.store_account') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="acvAddAccountTitle">إضافة حساب جديد</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>الاسم <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>
                                الكود <span class="text-danger">*</span>
                                <small class="text-muted">(يولَّد تلقائياً)</small>
                            </label>
                            <div class="input-group">
                                <input type="text" name="code" id="acvNewAccountCode" class="form-control" required placeholder="سيُحسب تلقائياً">
                                <div class="input-group-append">
                                    <button type="button" id="acvRefreshCodeBtn" class="btn btn-outline-secondary" title="إعادة توليد الكود">
                                        <i class="fa fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label>نوع الحساب <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                <option value="sub" @if($account) selected @endif>فرعي</option>
                                <option value="main">رئيسي</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الحساب الأب</label>
                            {{--
                                القائمةُ تُملأ من الشجرة المحمَّلة (`state.flat`)
                                في account-view.js — لا تُكتب هنا. كانت مكتوبةً
                                فلا تحوي إلا الحسابَ المعروض، فيتعذّر اختيارُ
                                أبٍ غيره ولا تعمل الشاشةُ إلا بعد الدخول إلى
                                حسابٍ بعينه.

                                وما يبقى هنا هو ما يُعرض قبل اكتمال التحميل.
                            --}}
                            <select name="parent_id" class="form-control">
                                <option value="">— لا يوجد (حساب رئيسي) —</option>
                                @if($account)
                                    <option value="{{ $account->id }}" selected>{{ $account->code }} — {{ $account->name }}</option>
                                @endif
                            </select>
                            <small class="stk-help">اتركه فارغاً لإنشاء حسابٍ رئيسي</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>التصنيف المالي</label>
                            <select name="account_type" class="form-control">
                                <option value="">— حسب الأب —</option>
                                <option value="assets">الأصول</option>
                                <option value="liabilities">الخصوم</option>
                                <option value="equity">حقوق الملكية</option>
                                <option value="revenues">الإيرادات</option>
                                <option value="expenses">المصروفات</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>طبيعة الرصيد <span class="text-danger">*</span></label>
                            <select name="balance_type" class="form-control" required>
                                <option value="debit">مدين</option>
                                <option value="credit">دائن</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الفرع</label>
                            <select name="branch_id" class="form-control">
                                <option value="">— الكل —</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الرصيد الافتتاحي</label>
                            <input type="number" name="opening_balance" class="form-control" value="0" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============== Edit Account Modal ============== --}}
<div class="modal fade" id="acvEditAccountModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header acv-modal-header-primary">
                <h5 class="modal-title"><i class="fa fa-edit"></i> تعديل الحساب</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="acvEditLoading" class="text-center py-4" style="display:none;">
                    <div class="acv-spinner"></div>
                    <p class="mt-2 text-muted">جار التحميل...</p>
                </div>
                <form id="acvEditAccountForm" data-id="">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="id" id="acvEditId">
                    {{-- المتحكم يقرأ هذا الحقل ويحفظه - يحفظ القيمة الأصلية إذا لم يغيِّرها المستخدم --}}
                    <input type="hidden" name="balance" id="acvEditBalanceHidden" value="0">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>الاسم <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="acvEditName" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الكود <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="acvEditCode" class="form-control" required maxlength="10">
                        </div>
                        <div class="form-group col-md-6">
                            <label>نوع الحساب <span class="text-danger">*</span></label>
                            <select name="type" id="acvEditType" class="form-control" required>
                                <option value="sub">فرعي</option>
                                <option value="main">رئيسي</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الحساب الأب</label>
                            <select name="parent_id" id="acvEditParentId" class="form-control">
                                <option value="">— لا يوجد —</option>
                                {{-- يُعبَّأ ديناميكياً --}}
                            </select>
                            <small class="text-muted">يُحمَّل الحساب الأب الحالي افتراضياً</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>التصنيف المالي</label>
                            <select name="account_type" id="acvEditAccountType" class="form-control">
                                <option value="">— حسب الأب —</option>
                                <option value="assets">الأصول</option>
                                <option value="liabilities">الخصوم</option>
                                <option value="equity">حقوق الملكية</option>
                                <option value="revenues">الإيرادات</option>
                                <option value="expenses">المصروفات</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>طبيعة الرصيد <span class="text-danger">*</span></label>
                            <select name="balance_type" id="acvEditBalanceType" class="form-control" required>
                                <option value="debit">مدين</option>
                                <option value="credit">دائن</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الفرع</label>
                            <select name="branch_id" id="acvEditBranchId" class="form-control">
                                <option value="">— الكل —</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الرصيد الافتتاحي</label>
                            <input type="number" name="opening_balance" id="acvEditOpeningBalance" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group col-md-6">
                            <label>نوع الرصيد الافتتاحي</label>
                            <select name="opening_balance_type" id="acvEditOpeningBalanceType" class="form-control">
                                <option value="">— نفس طبيعة الرصيد —</option>
                                <option value="debit">مدين</option>
                                <option value="credit">دائن</option>
                            </select>
                        </div>
                    </div>
                    <div id="acvEditError" class="alert alert-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="acvEditSubmit">
                    <i class="fa fa-save"></i> حفظ التعديلات
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============== Journal Entry Details Modal ============== --}}
<div class="modal fade" id="acvJournalEntryModal" tabindex="-1" role="dialog" aria-labelledby="acvJournalEntryModalTitle">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header acv-modal-header-primary">
                <h5 class="modal-title" id="acvJournalEntryModalTitle">
                    <i class="fa fa-file-invoice"></i> تفاصيل القيد
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="acvJournalEntryBody">
                <div class="text-center py-4" id="acvJournalEntryLoading">
                    <div class="acv-spinner"></div>
                    <p class="mt-2 text-muted">جار تحميل القيد...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> إغلاق
                </button>
                <button type="button" class="btn btn-outline-secondary" id="acvJournalEntryPrint">
                    <i class="fa fa-print"></i> طباعة
                </button>
                <a href="#" class="btn btn-primary" id="acvJournalEntryEditLink" target="_blank">
                    <i class="fa fa-edit"></i> تعديل القيد
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

{{-- ============== JS Logic ============== --}}
@push('scripts')
<script>
window.ACV = {
    flatTreeUrl: "{{ route('accounts_chart.api.flat_tree') }}",
    showAccountUrl: "{{ url('/' . app()->getLocale() . '/Accounts/accounts_chart/index') }}",
    recalcAllUrl: "{{ route('accounts.update_balances_from_journal_entries') }}",
    recalcOneUrl: function(id){ return "{{ url('/' . app()->getLocale() . '/Accounts/accounts_chart/api') }}/" + id + "/recalculate"; },
    editAccountUrl: function(id){ return "/accounts/" + id + "/edit"; },
    journalEntryDetailsUrl: function(id){ return "{{ url('/accounts/journal-entry') }}/" + id + "/details"; },
    journalEntryEditUrl: function(id){ return "{{ url('/' . app()->getLocale() . '/Accounts/journal/edit') }}/" + id; },
    csrf: "{{ csrf_token() }}",
    currentAccountId: {{ $account->id ?? 'null' }},
    currentBranch: "{{ $branchId ?? 'all' }}"
};
</script>
{{-- المكتبات الأساسية يحمّلها القالب الرئيسي؛ هنا ما يخصّ الشاشة وحدها --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/account-view.js') }}?v={{ time() }}"></script>
<script>
    if (window.feather) { try { feather.replace(); } catch (e) {} }
</script>
@endpush
