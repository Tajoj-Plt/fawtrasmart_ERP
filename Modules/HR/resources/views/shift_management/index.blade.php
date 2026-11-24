@extends('hr::master')

@section('title')
    الورديات
@stop

@section('page-actions')
    <div class="btn-group">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-ellipsis-h"></i>
            <span class="d-none d-md-inline">إجراءات</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">التصدير</h6></li>
            <li>
                {{-- `#export-btn` تقرؤه شيفرة الصفحة --}}
                <button type="button" id="export-btn" class="dropdown-item">
                    <i class="fas fa-file-excel me-2 text-success"></i>تصدير النتائج
                </button>
            </li>
        </ul>
    </div>
    @permission('shifts.create')
        <a href="{{ route('shift_management.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">أضف وردية</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'الورديات',
        'icon' => 'clock',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'الورديات' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'shifts'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @php
        $hasFilters = collect(request()->only(['keywords', 'type', 'days']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    {{-- التصفيةُ بـAJAX: `#search-form` وحقولُه و`#search-btn` و`#clear-filters` تقرؤها شيفرةُ الصفحة --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form id="search-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="search-keywords">البحث بكلمة مفتاحية</label>
                            <input type="text"
                                   id="search-keywords"
                                   class="form-control"
                                   placeholder="ابحث باسم الوردية…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="filter-type">نوع الوردية</label>
                            <select id="filter-type" class="form-control">
                                <option value="">الكل</option>
                                <option value="1">أساسي</option>
                                <option value="2">متقدم</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="filter-days">حسب أيام العمل</label>
                            <select id="filter-days" class="form-control">
                                <option value="">الكل</option>
                                <option value="5">5 أيام عمل</option>
                                <option value="6">6 أيام عمل</option>
                                <option value="7">7 أيام عمل</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <button type="button" id="clear-filters" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </button>
                    <button type="button" id="search-btn" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card">
        {{-- الترويسةُ خارج `#shifts-table-container`: العدّاد `#total-shifts` تُحدّثه شيفرةُ البحث --}}
        <div class="stk-table-head" id="shifts-count">
            <h6 class="stk-table-head__title">
                <i class="feather icon-list"></i> قائمة الورديات
                <span class="stk-count" id="total-shifts">{{ $shifts->total() }}</span>
            </h6>
            @include('stock::components.per-page', [
                'id' => 'shiftsPerPage',
                'current' => request('per_page', 10),
                'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
            ])
        </div>

        <div id="loading-indicator" class="text-center py-3" style="display: none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="sr-only">جارٍ التحميل...</span>
            </div>
            <span class="text-muted ms-1">جارٍ البحث...</span>
        </div>

        <div id="shifts-table-container">
            @include('hr::shift_management.partials.shifts_table', ['shifts' => $shifts])
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
class ShiftIndexManager {
    constructor() {
        this.searchForm = document.getElementById('search-form');
        this.searchKeywords = document.getElementById('search-keywords');
        this.filterType = document.getElementById('filter-type');
        this.filterDays = document.getElementById('filter-days');
        this.searchBtn = document.getElementById('search-btn');
        this.clearBtn = document.getElementById('clear-filters');
        this.exportBtn = document.getElementById('export-btn');
        this.tableContainer = document.getElementById('shifts-table-container');
        this.loadingIndicator = document.getElementById('loading-indicator');
        this.totalShiftsSpan = document.getElementById('total-shifts');

        this.currentFilters = {};
        this.searchTimeout = null;

        this.init();
    }

    init() {
        this.bindEvents();
        // أزرارُ الحذف في الصفوف المرسومة مع الصفحة — لا بعد البحث وحده
        this.bindTableEvents();
        this.loadUrlParameters();
    }

    bindEvents() {
        // البحث الفوري عند الكتابة
        this.searchKeywords.addEventListener('input', () => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performSearch();
            }, 500);
        });

        // الفلترة عند تغيير الخيارات
        this.filterType.addEventListener('change', () => this.performSearch());
        this.filterDays.addEventListener('change', () => this.performSearch());

        // زر البحث
        this.searchBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.performSearch();
        });

        // زر مسح الفلاتر
        this.clearBtn.addEventListener('click', () => this.clearFilters());

        // زر التصدير
        this.exportBtn.addEventListener('click', () => this.exportData());

        // منع إرسال الفورم
        this.searchForm.addEventListener('submit', (e) => e.preventDefault());
    }

    loadUrlParameters() {
        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.get('keywords')) {
            this.searchKeywords.value = urlParams.get('keywords');
        }
        if (urlParams.get('type')) {
            this.filterType.value = urlParams.get('type');
        }
        if (urlParams.get('days')) {
            this.filterDays.value = urlParams.get('days');
        }

        // تنفيذ البحث إذا كان هناك معاملات في URL
        if (urlParams.toString()) {
            this.performSearch();
        }
    }

    async performSearch() {
        this.showLoading();

        const filters = {
            keywords: this.searchKeywords.value.trim(),
            type: this.filterType.value,
            days: this.filterDays.value
        };

        this.currentFilters = filters;
        this.updateUrlParameters(filters);

        try {
            const response = await fetch('{{ route("shift_management.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(filters)
            });

            if (!response.ok) {
                throw new Error('خطأ في الشبكة');
            }

            const data = await response.json();
            this.updateTable(data.html);
            this.updateCounter(data.count);

        } catch (error) {
            console.error('خطأ في البحث:', error);
            this.showError('حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.');
        } finally {
            this.hideLoading();
        }
    }

    updateTable(html) {
        this.tableContainer.innerHTML = html;
        this.bindTableEvents();
    }

    updateCounter(count) {
        this.totalShiftsSpan.textContent = count;
    }

    bindTableEvents() {
        // ربط أحداث الحذف مع SweetAlert2
        const deleteButtons = this.tableContainer.querySelectorAll('.delete-shift-btn');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const shiftId = btn.getAttribute('data-shift-id');
                const shiftName = btn.getAttribute('data-shift-name');
                this.confirmDelete(shiftId, shiftName);
            });
        });
    }

    confirmDelete(shiftId, shiftName) {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            html: `سيتم حذف الوردية <strong>"${shiftName}"</strong><br>هذا الإجراء لا يمكن التراجع عنه`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return this.deleteShift(shiftId);
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'تم الحذف!',
                    text: 'تم حذف الوردية بنجاح',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }

    async deleteShift(shiftId) {
        try {
            const response = await fetch(`{{ route('shift_management.delete', '') }}/${shiftId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (!response.ok) {
                throw new Error('خطأ في الحذف');
            }

            // تحديث الجدول بعد الحذف
            this.performSearch();

            return true;
        } catch (error) {
            console.error('خطأ في الحذف:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ في الحذف',
                text: 'حدث خطأ أثناء حذف الوردية. يرجى المحاولة مرة أخرى.'
            });
            throw error;
        }
    }

    clearFilters() {
        this.searchKeywords.value = '';
        this.filterType.value = '';
        this.filterDays.value = '';
        this.currentFilters = {};

        // تحديث URL
        window.history.pushState({}, '', window.location.pathname);

        // إعادة تحميل البيانات
        this.performSearch();
    }

    async exportData() {
        try {
            Swal.fire({
                title: 'جارٍ التصدير...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('{{ route("shift_management.export") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(this.currentFilters)
            });

            if (!response.ok) {
                throw new Error('خطأ في التصدير');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `الورديات_${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            Swal.fire({
                icon: 'success',
                title: 'تم التصدير!',
                text: 'تم تصدير البيانات بنجاح',
                timer: 2000,
                showConfirmButton: false
            });

        } catch (error) {
            console.error('خطأ في التصدير:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ في التصدير',
                text: 'حدث خطأ أثناء تصدير البيانات. يرجى المحاولة مرة أخرى.'
            });
        }
    }

    updateUrlParameters(filters) {
        const params = new URLSearchParams();

        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                params.set(key, filters[key]);
            }
        });

        const newUrl = params.toString() ?
                      `${window.location.pathname}?${params.toString()}` :
                      window.location.pathname;

        window.history.pushState({}, '', newUrl);
    }

    showLoading() {
        this.loadingIndicator.style.display = 'block';
        this.tableContainer.style.opacity = '0.5';
    }

    hideLoading() {
        this.loadingIndicator.style.display = 'none';
        this.tableContainer.style.opacity = '1';
    }

    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }
}

// تشغيل الكلاس عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    window.shiftIndexManager = new ShiftIndexManager();

    // معالجة رسائل النجاح والخطأ من السيشن
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'تمت العملية بنجاح',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'حدث خطأ',
            text: '{{ session('error') }}',
            timer: 3000,
            showConfirmButton: false
        });
    @endif
});
</script>

@endsection
