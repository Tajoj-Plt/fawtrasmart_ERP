{{--
    دليل موظفي العملاء («جهات الاتصال») — أُعيد بناؤها بنظام تصميم الوحدة
    الحقيقي (`.cl-scope` + `partials/settings-theme` + `components/page-head`)
    بدل تصميمٍ خاصٍّ بهذه الشاشة وحدها: تدرّجات ألوان مضمَّنة، صورة رمزية
    تُرسم بـ`<canvas>`، ونافذة تكبير صورةٍ مبنيّة يدوياً — لا شيء من هذا في
    أي شاشة أخرى بالوحدة.

    البطاقات صارت صفوف جدولٍ (`cl-table-card`/`cl-media`) — نفس ما تعرضه
    `contacts/contact_mang.blade.php` لجهات اتصال العملاء أنفسهم، فتتّسق
    الشاشتان. الشبكة والترقيم انتقلا إلى `partials/employees_grid.blade.php`
    ليُستدعيا مرّةً واحدة سواء عند أول تحميلٍ أو عند كل تحديث AJAX، بدل أن
    يتكرّر نفس الترميز في الملفَّين وقد يتباعدان مع أول تعديل.

    منطق البحث/الفلترة/الترقيم عبر AJAX (`ClientEmployeeController::clientEmployeesDirectory`)
    لم يُمسّ إطلاقاً.
--}}
@extends('client::master')

@section('title')
    دليل موظفي العملاء
@stop

@section('content')
@include('client::partials.settings-theme')

<div class="cl-scope">
    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::components.page-head', [
        'title' => 'دليل موظفي العملاء',
        'icon' => 'users',
        'sub' => 'من نتواصل معه في كل شركةٍ عميلة — الاسم والوظيفة ورقم الهاتف المباشر',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'العملاء' => route('clients.index'),
            'دليل الموظفين' => null,
        ],
    ])

    @include('client::components.guide', [
        'key' => 'clients.employees-directory',
        'open' => false,
        'title' => 'ما هذه الشاشة؟',
        'lead' => 'دليل موظفي كل عميل — لا موظفي شركتك، بل الأشخاص الذين تتواصل معهم داخل الشركة العميلة نفسها (جهة الاتصال المسؤولة).',
        'facts' => [
            'الموظف المميَّز بشارة «رئيسي» هو جهة التواصل الأساسية لدى ذلك العميل.',
        ],
    ])

    <div class="cl-stats">
        <div class="cl-stat">
            <span class="cl-stat__icon"><i class="fas fa-users"></i></span>
            <div class="cl-stat__body">
                <p class="cl-stat__value" id="totalCount">{{ number_format($employees->total()) }}</p>
                <p class="cl-stat__label">إجمالي موظفي العملاء</p>
            </div>
        </div>
        @php
            $headerNodeId = \App\Support\HeaderBranchScope::nodeId();
            $headerBranch = $headerNodeId ? \App\Models\Branch::findCached($headerNodeId) : null;
        @endphp
        <div class="cl-stat">
            <span class="cl-stat__icon"><i class="fas fa-sitemap"></i></span>
            <div class="cl-stat__body">
                <p class="cl-stat__value" style="font-size:1rem">{{ $headerBranch ? $headerBranch->name : 'كل الفروع' }}</p>
                <p class="cl-stat__label">{{ $headerBranch ? 'الفرع المختار في الشريط العلوي وما تحته' : 'المنشأة كلها' }}</p>
            </div>
        </div>
    </div>

    {{-- ===== الفلاتر ===== --}}
    <div class="card cl-filters">
        <div class="cl-filters__head">
            <h4 class="cl-filters__title"><i class="feather icon-search"></i> بحث وتصفية</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('clients.employees_directory') }}" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="employee_name">اسم الموظف</label>
                            <input type="text" id="employee_name" name="employee_name" class="form-control"
                                   placeholder="ابحث باسم الموظف..." value="{{ request('employee_name') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="client_id">العميل</label>
                            <select id="client_id" name="client_id" class="form-control select2">
                                <option value="">-- جميع العملاء --</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                        {{ $client->trade_name }} ({{ $client->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="position">الوظيفة</label>
                            <select id="position" name="position" class="form-control">
                                <option value="">-- جميع الوظائف --</option>
                                @foreach ($positions as $position)
                                    <option value="{{ $position }}" {{ request('position') == $position ? 'selected' : '' }}>
                                        {{ $position }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="phone">رقم الجوال</label>
                            <input type="text" id="phone" name="phone" class="form-control"
                                   placeholder="رقم الجوال..." value="{{ request('phone') }}">
                        </div>
                    </div>
                </div>
            </form>
            <div class="cl-filters__foot">
                <button type="submit" form="filterForm" class="btn btn-primary">
                    <i class="feather icon-search"></i> بحث
                </button>
                <a href="{{ route('clients.employees_directory') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
            </div>
        </div>
    </div>

    {{-- ===== الجدول ===== --}}
    <div class="card cl-table-card">
        <div class="cl-table-head">
            <h6 class="cl-table-head__title">
                <i class="fas fa-users"></i>
                موظفو العملاء
                <span class="cl-count">{{ $employees->total() }}</span>
            </h6>
        </div>

        <div class="card-body p-0" id="employeesContainer">
            @include('client::partials.employees_grid', compact('employees'))
        </div>
    </div>

    {{-- ===== نافذة تكبير الصورة ===== --}}
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-2">
                    <img id="modalImage" src="" alt="" style="max-width:100%;border-radius:var(--cl-r)">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    @parent
    <script>
        (function () {
            window.openImageModal = function (src) {
                document.getElementById('modalImage').src = src;
                var modalEl = document.getElementById('imageModal');
                (bootstrap.Modal.getOrCreateInstance(modalEl)).show();
            };

            window.copyPhone = function (phone, btn) {
                function done() {
                    var icon = btn.querySelector('i');
                    var original = icon.className;
                    icon.className = 'fa fa-check';
                    if (typeof toastr !== 'undefined') { toastr.success('تم نسخ الرقم: ' + phone); }
                    setTimeout(function () { icon.className = original; }, 1500);
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(phone).then(done).catch(function () { fallbackCopy(phone, done); });
                } else {
                    fallbackCopy(phone, done);
                }
            };

            function fallbackCopy(phone, done) {
                var textArea = document.createElement('textarea');
                textArea.value = phone;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                try { document.execCommand('copy'); done(); }
                catch (e) { if (typeof toastr !== 'undefined') { toastr.error('فشل نسخ الرقم'); } }
                document.body.removeChild(textArea);
            }

            function getParameterByName(name, url) {
                name = name.replace(/[\[\]]/g, '\\$&');
                var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
                    results = regex.exec(url);
                if (!results) return null;
                if (!results[2]) return '';
                return decodeURIComponent(results[2].replace(/\+/g, ' '));
            }

            function loadEmployees(page) {
                var formData = $('#filterForm').serialize() + '&page=' + page;

                $.ajax({
                    url: '{{ route("clients.employees_directory") }}',
                    type: 'GET',
                    data: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function (response) {
                        if (response.html) {
                            $('#employeesContainer').html(response.html);
                            $('#totalCount').text(response.total);
                            var newUrl = '{{ route("clients.employees_directory") }}?' + formData;
                            window.history.pushState({ path: newUrl }, '', newUrl);
                        } else {
                            location.reload();
                        }
                    },
                    error: function () {
                        if (typeof toastr !== 'undefined') { toastr.error('حدث خطأ أثناء البحث'); }
                    },
                });
            }

            $(function () {
                if ($.fn.select2) {
                    $('#client_id').select2({ dir: 'rtl', placeholder: 'اختر عميل...', allowClear: true });
                }

                $('#filterForm').on('submit', function (e) {
                    e.preventDefault();
                    loadEmployees(1);
                });

                $(document).on('click', '#paginationContainer .page-link', function (e) {
                    e.preventDefault();
                    var url = $(this).attr('href');
                    if (url) { loadEmployees(getParameterByName('page', url) || 1); }
                });
            });
        })();
    </script>
@endsection
