@extends('client::master')

@section('title', 'تقرير الملاحظات الشهرية')

@section('css')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@if(view()->exists('sales::sals_report.partials.report_filters_balance_like_styles'))
    @include('sales::sals_report.partials.report_filters_balance_like_styles')
@endif
<style>
    /* تنسيق أزرار الفترات الزمنية */
    .period-tabs { display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; }
    .period-tabs .btn { flex: 1; min-width: 80px; border-radius: 8px; padding: 10px 12px; font-weight: 600; transition: all 0.3s ease; font-size: 0.85rem; }
    .period-tabs .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .period-tabs .btn.btn-primary { box-shadow: 0 4px 15px rgba(115, 103, 240, 0.4); }

    .stat-card { border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .filter-card { border-radius: 10px; margin-bottom: 20px; }

    /* تحسين جدول الملخص - مطابق للموحد */
    .tbl-unified { border-radius: 10px; overflow: hidden; white-space: nowrap; margin-bottom: 0; width: 100%; }
    .tbl-unified thead th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; padding: 12px 15px; border: none; text-align: center; }
    .tbl-unified tbody tr { transition: all 0.2s ease; }
    .tbl-unified tbody tr:hover { background-color: rgba(115, 103, 240, 0.08); transform: scale(1.01); }
    .tbl-unified tbody td { padding: 10px 15px; vertical-align: middle; text-align: center; border-bottom: 1px solid #e9ecef; }
    .tbl-unified .customer-name { font-weight: 600; color: #2d3748; text-decoration: none; }
    .tbl-unified .customer-name:hover { text-decoration: underline; color: #667eea; }
    .tbl-unified .amount-cell { font-weight: 600; font-family: 'Roboto', sans-serif; }
    .tbl-unified tfoot tr { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); font-weight: 700; }
    .tbl-unified tfoot td { padding: 15px; font-size: 1.05rem; border-top: 2px solid #dee2e6; color: #000; }

    .unified-badge { font-size: 0.78rem; padding: 4px 9px; border-radius: 12px; font-weight: 600; }

    @media print { .no-print { display: none !important; } .tbl-unified thead th { background: #667eea !important; -webkit-print-color-adjust: exact; } .stat-card { break-inside: avoid; } }

    /* تحسين شكل الـ Tooltip ليدعم الأسطر المتعددة */
    .tooltip-inner {
        white-space: pre-wrap !important;
        max-width: 400px !important;
        text-align: right !important;
        direction: rtl !important;
    }

    #custom-dates-row { display: none; }
    .mn-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #6c757d;
    }
</style>
@endsection

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تقرير الملاحظات الشهرية</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('itinerary.list') }}">خطوط السير</a></li>
                        <li class="breadcrumb-item active">الملاحظات الشهرية</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    <!-- قسم الفلاتر -->
    <div class="card filter-card no-print">
        <div class="card-header">
            <h4 class="card-title"><i class="fa fa-filter"></i> خيارات البحث</h4>
            <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
            <div class="heading-elements">
                <ul class="list-inline mb-0">
                    <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                </ul>
            </div>
        </div>
        <div class="card-content collapse show">
            <div class="card-body">
                <form id="filtersForm">

                    <!-- أزرار الفترات الزمنية -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="period-tabs">
                                <button type="button" class="btn period-btn {{ $period == '1'      ? 'btn-primary' : 'btn-outline-primary' }}" data-period="1">
                                    <i class="fa fa-calendar-day"></i> اليوم
                                </button>
                                <button type="button" class="btn period-btn {{ $period == '7'      ? 'btn-primary' : 'btn-outline-primary' }}" data-period="7">
                                    <i class="fa fa-calendar-week"></i> أسبوع
                                </button>
                                <button type="button" class="btn period-btn {{ $period == '30'     ? 'btn-primary' : 'btn-outline-primary' }}" data-period="30">
                                    <i class="fa fa-calendar-alt"></i> شهر
                                </button>
                                <button type="button" class="btn period-btn {{ $period == '90'     ? 'btn-primary' : 'btn-outline-primary' }}" data-period="90">
                                    <i class="fa fa-calendar"></i> 3 أشهر
                                </button>
                                <button type="button" class="btn period-btn {{ $period == '180'    ? 'btn-primary' : 'btn-outline-primary' }}" data-period="180">
                                    <i class="fa fa-calendar"></i> 6 أشهر
                                </button>
                                <button type="button" class="btn period-btn {{ $period == '365'    ? 'btn-primary' : 'btn-outline-primary' }}" data-period="365">
                                    <i class="fa fa-calendar-check"></i> سنة
                                </button>
                                <button type="button" class="btn period-btn {{ $period == 'custom' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="custom">
                                    <i class="fa fa-sliders-h"></i> مخصص
                                </button>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="period" id="periodInput" value="{{ $period }}">

                    <!-- التواريخ المخصصة -->
                    <div class="row mt-2" id="custom-dates-row">
                        <div class="col-md-3 col-sm-6 mb-1">
                            <label>من تاريخ</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $startDate->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-1">
                            <label>إلى تاريخ</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $endDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    <!-- الشجرة الهرمية للفروع -->
                    <input type="hidden" name="cascade_node" id="cascade_node" value="{{ request('cascade_node') }}">
                    <div class="row mt-2" id="cascadeContainer">
                        {{-- تُبنى ديناميكياً عبر JS --}}
                    </div>

                    <!-- نوع العرض والتجميع -->
                    <div class="row mt-2">
                        <div class="col-md-4 col-sm-12 mb-1">
                            <label>نوع العرض</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" style="width: 100%;">
                                <label class="btn btn-outline-primary {{ ($viewType ?? 'details') === 'details' ? 'active' : '' }}" style="flex:1; border-radius: 0 4px 4px 0;">
                                    <input type="radio" name="view_type" value="details" autocomplete="off" {{ ($viewType ?? 'details') === 'details' ? 'checked' : '' }}> 
                                    <i class="fa fa-list"></i> تفاصيل
                                </label>
                                <label class="btn btn-outline-primary {{ ($viewType ?? '') === 'summary' ? 'active' : '' }}" style="flex:1; border-radius: 0;">
                                    <input type="radio" name="view_type" value="summary" autocomplete="off" {{ ($viewType ?? '') === 'summary' ? 'checked' : '' }}> 
                                    <i class="fa fa-th-large"></i> ملخص
                                </label>
                                <label class="btn btn-outline-primary {{ ($viewType ?? '') === 'matrix' ? 'active' : '' }}" style="flex:1; border-radius: 4px 0 0 4px;">
                                    <input type="radio" name="view_type" value="matrix" autocomplete="off" {{ ($viewType ?? '') === 'matrix' ? 'checked' : '' }}> 
                                    <i class="fa fa-calendar-alt"></i> شهري
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-1">
                            <label>تجميع حسب</label>
                            <select name="group_by" class="form-control">
                                @foreach($groupByOptions ?? [] as $val => $label)
                                    <option value="{{ $val }}" {{ ($groupBy ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- باقي الفلاتر -->
                    <div class="row mt-2">
                        <div class="col-md-3 col-sm-6 mb-1">
                            <label>الموظف</label>
                            <select name="employee_id" class="form-control select2">
                                <option value="">الكل</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-1">
                            <label>العميل</label>
                            <select name="client_id" class="form-control select2">
                                <option value="">الكل</option>
                                @if(isset($clients))
                                    @foreach($clients as $c)
                                        <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>{{ $c->trade_name }} - {{ $c->code }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-1">
                            <label>كود العميل</label>
                            <input type="text" name="code" class="form-control" placeholder="بحث بالكود..." value="{{ request('code') }}">
                        </div>
                        <div class="col-md-2 col-sm-6 mb-1">
                            <label>حالة العميل</label>
                            <select name="status" class="form-control">
                                <option value="">الكل</option>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption->id }}" {{ (string) request('status') === (string) $statusOption->id ? 'selected' : '' }}>
                                        {{ $statusOption->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row mt-2">
                         <!-- أزرار البحث -->
                         <div class="col-md-6 col-sm-12 mb-1">
                             <div class="d-flex align-items-center">
                                 <button type="button" class="btn btn-primary mr-1" onclick="loadNotes()">
                                     <i class="fa fa-search"></i> بحث
                                 </button>
                                 <a href="{{ route('itinerary.monthly-notes') }}" class="btn btn-outline-secondary mr-2">
                                     <i class="fa fa-refresh"></i> إلغاء البحث
                                 </a>
                             </div>
                         </div>
    
                        <!-- أزرار الطباعة والتصدير -->
                        <div class="col-md-6 col-sm-12 mb-1">
                            <div class="text-right">
                                <button type="button" onclick="window.print()" class="btn btn-success">
                                    <i class="fa fa-print"></i> طباعة
                                </button>
                                <button type="button" class="btn btn-info ml-1" onclick="alert('جاري تجهيز التصدير قريباً')">
                                    <i class="fa fa-file-excel-o"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- منطقة النتائج -->
    <div id="notesContainer">
        @include('client::Itinerary.partials.monthly-notes-table')
    </div>

</div>
@endsection

@section('scripts')
<script>
const CASCADE_BRANCHES = @json($cascadeBranches ?? []);

/* ===== Cascade Tree ===== */
(function () {
    function getRoots() { return CASCADE_BRANCHES.filter(b => !b.parent_id); }
    function getChildren(pid) { return CASCADE_BRANCHES.filter(b => b.parent_id == pid); }
    function hasChildren(id) { return getChildren(id).length > 0; }
    function getPath(id) {
        var path = [], cur = CASCADE_BRANCHES.find(x => x.id == id), s = 20;
        while (cur && s-- > 0) { path.unshift(cur); cur = cur.parent_id ? CASCADE_BRANCHES.find(x => x.id == cur.parent_id) : null; }
        return path;
    }
    function addLevel(level, parentId, selVal) {
        var opts = level === 0 ? getRoots() : getChildren(parentId);
        if (!opts.length) return false;
        var $col = $('<div class="col-md-3 col-sm-6 mb-1 cascade-col"></div>');
        $col.append($('<label></label>').text(level === 0 ? 'الفرع الرئيسي (شجرة)' : 'المستوى ' + (level + 1)));
        var $sel = $('<select class="form-control select2 cascade-select"></select>').attr('data-level', level);
        $sel.append('<option value="">-- اختر --</option>');
        opts.forEach(b => {
            var lbl = b.name + (level > 0 ? (b.is_territory == 1 ? ' [مجموعة]' : ' [فرع]') : '');
            $sel.append('<option value="b_' + b.id + '"' + (selVal === 'b_' + b.id ? ' selected' : '') + '>' + lbl + '</option>');
        });
        $col.append($sel);
        $('#cascadeContainer').append($col);
        if ($.fn.select2) $sel.select2({ dir: 'rtl', allowClear: true, width: '100%', language: { noResults: () => 'لا توجد نتائج' } });
        $sel.on('change', function () {
            var val = $(this).val();
            $('#cascadeContainer .cascade-col').each(function () {
                if (parseInt($(this).find('select').attr('data-level'), 10) > level) {
                    var $s = $(this).find('select');
                    if ($.fn.select2 && $s.data('select2')) $s.select2('destroy');
                    $(this).remove();
                }
            });
            if (val) { $('#cascade_node').val(val); var bid = parseInt(val.replace('b_', '')); if (hasChildren(bid)) addLevel(level + 1, bid, null); }
            else { var $p = $('#cascadeContainer select[data-level="' + (level - 1) + '"]'); $('#cascade_node').val($p.length ? ($p.val() || '') : ''); }
        });
        return true;
    }
    function initCascade() {
        $('#cascadeContainer .cascade-col select').each(function () { if ($.fn.select2 && $(this).data('select2')) $(this).select2('destroy'); });
        $('#cascadeContainer').empty();
        var v = $('#cascade_node').val();
        if (v && v.indexOf('b_') === 0) {
            var path = getPath(parseInt(v.replace('b_', '')));
            addLevel(0, null, path.length ? 'b_' + path[0].id : null);
            for (var i = 0; i < path.length; i++) addLevel(i + 1, path[i].id, path[i + 1] ? 'b_' + path[i + 1].id : 'b_' + path[i].id);
        } else { addLevel(0, null, null); }
    }
    $(document).ready(() => initCascade());
})();

/* ===== Period Buttons ===== */
$(document).ready(function () {
    if ($('#periodInput').val() === 'custom') $('#custom-dates-row').show();

    $('.period-btn').on('click', function () {
        var p = String($(this).data('period'));
        $('#periodInput').val(p);
        $('.period-btn').removeClass('btn-primary').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary');

        if (p === 'custom') {
            $('#custom-dates-row').slideDown(200);
        } else {
            $('#custom-dates-row').slideUp(200);
            var today = new Date(), from = new Date(today);
            if (p !== '1') from.setDate(today.getDate() - parseInt(p));
            var fmt = d => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            $('#date_from').val(fmt(from));
            $('#date_to').val(fmt(today));
            loadNotes();
        }
    });

    if ($.fn.select2) $('select.select2:not(.cascade-select)').select2({ placeholder: 'اختر...', allowClear: true, dir: 'rtl' });
});

/* ===== AJAX Load Notes ===== */
function loadNotes() {
    var params = {};
    $('#filtersForm').find('input, select').each(function () { if ($(this).attr('name')) params[$(this).attr('name')] = $(this).val(); });
    params['cascade_node'] = $('#cascade_node').val() || '';

    $('#notesContainer').html('<div class="mn-loading"><div class="spinner-border text-primary" style="width:2rem;height:2rem;"></div><p class="mt-2">جاري تحميل البيانات...</p></div>');

    $.ajax({
        url: '{{ route("itinerary.monthly-notes") }}?' + $.param(params),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        success: function (data) { $('#notesContainer').html(data.success ? data.html : '<div class="alert alert-danger">' + (data.message || 'حدث خطأ') + '</div>'); },
        error: function () { $('#notesContainer').html('<div class="alert alert-danger">حدث خطأ في الاتصال بالخادم</div>'); }
    });
}
</script>
@endsection
