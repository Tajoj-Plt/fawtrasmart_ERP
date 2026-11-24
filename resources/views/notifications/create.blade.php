{{--
    إرسال إشعار جديد — على قياس شاشات المبيعات (`sl-*`): ترويسةٌ بفتات
    المسار، بطاقةٌ بأقسامٍ معنونة، وذيلٌ فيه الأزرار.

    الحقول وأسماؤها كما يقرؤها `NotificationsController::sendNotification()`
    بلا تغيير؛ الذي تغيّر التصميم وحده، ومعه:
      • قائمة الموظفين كانت فارغةً دائماً (`users.role = 'employee'` شرطٌ لا
        يطابق صفّاً) — صارت من `employeeRecipients()`.
      • `@section('styles')` لا يقرؤه القالب الأب، فأنماطها لم تكن تصل.
      • نسخةٌ ثانية من select2 من CDN فوق نسخة القالب.
--}}

@extends('sales::master')

@section('title', 'إرسال إشعار جديد')

@section('content')
    @include('sales::partials.settings-theme')

    <style>
        /* خيارٌ من مجموعة — بطاقةٌ صغيرة تُضاء حين تُختار، بدل نقطة راديو عارية */
        .ntf-choices { display: flex; flex-wrap: wrap; gap: .6rem; }
        .sl-scope label.ntf-choice {
            display: flex; align-items: center; gap: .5rem; margin: 0; cursor: pointer;
            padding: .55rem .9rem; min-width: 190px; flex: 1 1 190px; max-width: 320px;
            border: 1px solid var(--app-border, #e6e9f0); border-radius: var(--app-radius, 8px);
            background: var(--app-surface, #fff); color: var(--app-text, #4b4b4b);
            font-size: .87rem; font-weight: 600;
        }
        .ntf-choice > input { accent-color: var(--app-primary, #7367f0); width: 16px; height: 16px; flex: none; }
        .ntf-choice:has(> input:checked) {
            border-color: var(--app-primary, #7367f0);
            background: color-mix(in srgb, var(--app-primary, #7367f0) 7%, transparent);
        }
        .ntf-choice i { color: var(--app-primary, #7367f0); }
        .ntf-nested {
            margin-top: 1rem; padding: 1rem 1rem .25rem;
            border-inline-start: 3px solid var(--app-primary, #7367f0);
            background: color-mix(in srgb, var(--app-primary, #7367f0) 4%, transparent);
            border-radius: var(--app-radius, 8px);
        }
        .sl-scope .select2-container { width: 100% !important; }
    </style>

    <div class="content-body sl-scope">

        @include('sales::components.page-head', [
            'title' => 'إرسال إشعار جديد',
            'icon' => 'send',
            'sub' => 'اكتب الإشعار، اختر من يستلمه، ثم أرسله الآن أو حدّد له موعداً.',
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                'الإشعارات' => route('notifications.index'),
                'إرسال إشعار جديد' => null,
            ],
        ])

        <form id="sendNotificationForm" novalidate>
            @csrf
            <input type="hidden" name="target_type" value="employees">

            <div class="card">
                <div class="card-body">

                    {{-- ══════════ ١ — نص الإشعار ══════════ --}}
                    <div class="sl-section">
                        <div class="sl-section__head">
                            <i class="feather icon-edit-3"></i>
                            <h6 class="sl-section__title">نص الإشعار</h6>
                        </div>

                        <div class="row">
                            <div class="col-lg-8 col-md-7">
                                <div class="form-group">
                                    <label for="title" class="sl-req">عنوان الإشعار</label>
                                    <input type="text" class="form-control" id="title" name="title" maxlength="255"
                                           placeholder="مثال: اجتماع المبيعات الأسبوعي">
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-5">
                                <div class="form-group">
                                    <label for="notification_type">نوع الإشعار</label>
                                    <select class="form-control" id="notification_type" name="notification_type">
                                        <option value="general">عام</option>
                                        <option value="collection">تحصيل</option>
                                        <option value="visit">زيارة عميل</option>
                                        <option value="task">مهمة</option>
                                        <option value="reminder">تذكير</option>
                                        <option value="urgent">عاجل</option>
                                        <option value="client_popup">إشعار عميل (يظهر للمندوب عند فتح العميل)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group">
                                    <label for="message" class="sl-req">نص الإشعار</label>
                                    <textarea class="form-control" id="message" name="description" rows="4" maxlength="1000"
                                              placeholder="اكتب ما تريد إبلاغه…"></textarea>
                                    <small class="sl-help"><span id="messageCount">0</span> / 1000 حرف</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ══════════ ٢ — المستلمون ══════════ --}}
                    <div class="sl-section" id="employeesOptionsSection">
                        <div class="sl-section__head">
                            <i class="feather icon-users"></i>
                            <h6 class="sl-section__title">من يستلم الإشعار؟</h6>
                        </div>

                        <div class="ntf-choices">
                            <label class="ntf-choice" for="sendToAllEmployees">
                                <input type="radio" id="sendToAllEmployees" name="employee_scope" value="all" checked>
                                <i class="feather icon-users"></i> جميع الموظفين ({{ $employees->count() }})
                            </label>
                            <label class="ntf-choice" for="sendToSpecificEmployees">
                                <input type="radio" id="sendToSpecificEmployees" name="employee_scope" value="specific">
                                <i class="feather icon-user"></i> موظف محدد
                            </label>
                        </div>

                        <div class="ntf-nested" id="employeesList" style="display: none;">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="employees" class="sl-req">الموظف</label>
                                        {{-- بلا صنف `select2`: `form-select2.js` في القالب يهيّئ ذلك الصنف، وهنا تهيئةٌ بالمعرّف --}}
                                        <select class="form-control" id="employees" name="employees[]" multiple>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="sl-help">موظف واحد في كل إرسال.</small>
                                    </div>
                                </div>
                            </div>

                            {{-- تظهر بعد اختيار الموظف --}}
                            <div class="form-group" id="sendTypeSection" style="display: none;">
                                <label>يُرسَل بخصوص</label>
                                <div class="ntf-choices">
                                    <label class="ntf-choice" for="sendToEmployeeOnly">
                                        <input type="radio" id="sendToEmployeeOnly" name="send_type" value="employee_only" checked>
                                        <i class="feather icon-user"></i> الموظف نفسه فقط
                                    </label>
                                    <label class="ntf-choice" for="sendToAllEmployeeClients">
                                        <input type="radio" id="sendToAllEmployeeClients" name="send_type" value="all_clients">
                                        <i class="feather icon-users"></i> كل عملاء الموظف
                                        <span id="allClientsCountBadge"></span>
                                    </label>
                                    <label class="ntf-choice" for="sendToSpecificClients">
                                        <input type="radio" id="sendToSpecificClients" name="send_type" value="specific_clients">
                                        <i class="feather icon-user-check"></i> عملاء محددين
                                    </label>
                                </div>
                                <small class="sl-help">عند اختيار العملاء يصل للموظف إشعارٌ عن كل عميل، ويظهر له عند فتح ذلك العميل.</small>

                                <div id="allClientsWarning" class="alert alert-warning mt-2 mb-0" style="display: none;">
                                    <i class="feather icon-alert-triangle"></i>
                                    سيُرسَل <strong><span id="warningClientsCount">0</span></strong> إشعاراً — واحدٌ عن كل عميل. تأكّد أنك تريد ذلك.
                                </div>
                            </div>

                            <div class="row" id="employeeClientsList" style="display: none;">
                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <label for="client_ids" class="sl-req">العملاء</label>
                                        <span id="clientsCountBadge"></span>
                                        <select class="form-control" id="client_ids" name="client_ids[]" multiple></select>
                                        <small class="sl-help">ابحث بالاسم أو الكود — تُعرض النتائج خمسين خمسين.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ══════════ ٣ — وقت الإرسال ══════════ --}}
                    <div class="sl-section">
                        <div class="sl-section__head">
                            <i class="feather icon-clock"></i>
                            <h6 class="sl-section__title">وقت الإرسال</h6>
                        </div>

                        <div class="ntf-choices">
                            <label class="ntf-choice" for="sendNow">
                                <input type="radio" id="sendNow" name="sendTime" value="now" checked>
                                <i class="feather icon-send"></i> إرسال فوري
                            </label>
                            <label class="ntf-choice" for="sendScheduled">
                                <input type="radio" id="sendScheduled" name="sendTime" value="scheduled">
                                <i class="feather icon-calendar"></i> جدولة الإرسال
                            </label>
                        </div>

                        <div class="ntf-nested" id="scheduledSection" style="display: none;">
                            <div class="row">
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label for="scheduled_date" class="sl-req">التاريخ</label>
                                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" min="{{ date('Y-m-d') }}">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label for="scheduled_time" class="sl-req">الوقت</label>
                                        <input type="time" class="form-control" id="scheduled_time" name="scheduled_time">
                                    </div>
                                </div>
                            </div>
                            <small class="sl-help mb-2">يُرسَل الإشعار تلقائياً في هذا الموعد، ويبقى «مجدولاً» في القائمة حتى يحين.</small>
                        </div>
                    </div>
                </div>

                <div class="sl-form-foot">
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-arrow-right"></i> رجوع إلى الإشعارات
                    </a>
                    <button type="submit" class="btn btn-primary" id="sendNotificationBtn">
                        <i class="feather icon-send"></i> إرسال الإشعار
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const clientsUrl = @json(route('notifications.get-clients-by-employees'));
    const direction = @json(\App\Support\LanguageSettings::direction());
    let currentEmployeeId = null;
    let totalClientsCount = 0;

    // رسالةٌ عائمة — SweetAlert محمَّلٌ في القالب الأب
    function notify(icon, text) {
        Swal.fire({ icon: icon, text: text, toast: true, position: 'top-end', timer: 3500, showConfirmButton: false });
    }

    $('#employees').select2({
        placeholder: 'اختر الموظف',
        allowClear: true,
        width: '100%',
        dir: direction,
        maximumSelectionLength: 1, // موظف واحد في كل إرسال
    });

    // العملاء يُجلبون بالبحث والصفحات: آلافُ الصفوف لا تُحمَّل مع الشاشة
    $('#client_ids').select2({
        placeholder: 'ابحث عن العملاء…',
        allowClear: true,
        width: '100%',
        dir: direction,
        minimumInputLength: 0,
        ajax: {
            url: clientsUrl,
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    employee_ids: currentEmployeeId ? [currentEmployeeId] : [],
                    search: params.term || '',
                    page: params.page || 1,
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                totalClientsCount = data.total || 0;
                updateClientsCount();

                return {
                    results: (data.data || []).map(function (client) {
                        return { id: client.id, text: client.code + ' - ' + client.trade_name };
                    }),
                    pagination: { more: data.has_more || false },
                };
            },
            cache: true,
        },
    });

    $('#message').on('input', function () {
        $('#messageCount').text($(this).val().length);
    });

    function updateClientsCount() {
        const badge = totalClientsCount > 0
            ? '<span class="badge badge-light-info">' + totalClientsCount + ' عميل</span>'
            : '';

        $('#clientsCountBadge').html(badge);
        $('#allClientsCountBadge').html(badge);
        $('#warningClientsCount').text(totalClientsCount);
    }

    function updateAllClientsWarning() {
        const sendType = $('input[name="send_type"]:checked').val();

        $('#allClientsWarning').toggle(sendType === 'all_clients' && totalClientsCount > 100);
    }

    function fetchClientsCount(employeeId) {
        if (!employeeId) {
            totalClientsCount = 0;
            updateClientsCount();
            updateAllClientsWarning();
            return;
        }

        $.ajax({ url: clientsUrl, method: 'GET', data: { employee_ids: [employeeId], page: 1 } })
            .done(function (response) {
                totalClientsCount = response.total || 0;
                updateClientsCount();
                updateAllClientsWarning();
            });
    }

    function updateEmployeeScopeUI() {
        if ($('input[name="employee_scope"]:checked').val() === 'specific') {
            $('#employeesList').slideDown(150);
            return;
        }

        $('#employeesList').slideUp(150);
        $('#employees').val(null).trigger('change');
    }

    function updateSendTypeUI() {
        if ($('input[name="send_type"]:checked').val() === 'specific_clients') {
            $('#employeeClientsList').slideDown(150);
        } else {
            $('#employeeClientsList').slideUp(150);
            $('#client_ids').val(null).trigger('change');
        }

        updateAllClientsWarning();
    }

    $('input[name="employee_scope"]').on('change', updateEmployeeScopeUI);
    $('input[name="send_type"]').on('change', updateSendTypeUI);

    $('input[name="sendTime"]').on('change', function () {
        $('#scheduledSection')[$(this).val() === 'scheduled' ? 'slideDown' : 'slideUp'](150);
    });

    $('#employees').on('change', function () {
        const employeeIds = $(this).val();

        $('#client_ids').val(null).trigger('change');

        if (employeeIds && employeeIds.length > 0) {
            currentEmployeeId = employeeIds[0];
            fetchClientsCount(currentEmployeeId);
            $('#sendTypeSection').slideDown(150);
            updateSendTypeUI();
            return;
        }

        currentEmployeeId = null;
        totalClientsCount = 0;
        updateClientsCount();
        $('#sendToEmployeeOnly').prop('checked', true);
        $('#sendTypeSection, #employeeClientsList').slideUp(150);
        $('#allClientsWarning').hide();
    });

    function resetForm() {
        $('#sendNotificationForm')[0].reset();
        $('#employees, #client_ids').val(null).trigger('change');
        $('#messageCount').text('0');
        $('#scheduledSection').hide();
        updateEmployeeScopeUI();
    }

    $('#sendNotificationForm').on('submit', function (e) {
        e.preventDefault();

        const title = $('#title').val().trim();
        const description = $('#message').val().trim();
        const employeeScope = $('input[name="employee_scope"]:checked').val();
        const sendType = $('input[name="send_type"]:checked').val();
        const sendTime = $('input[name="sendTime"]:checked').val();
        const employees = $('#employees').val();
        const clientIds = $('#client_ids').val();

        if (!title) { notify('error', 'اكتب عنوان الإشعار'); $('#title').focus(); return; }
        if (!description) { notify('error', 'اكتب نص الإشعار'); $('#message').focus(); return; }

        if (employeeScope === 'specific') {
            if (!employees || employees.length === 0) {
                notify('error', 'اختر الموظف');
                return;
            }

            if (sendType === 'specific_clients' && (!clientIds || clientIds.length === 0)) {
                notify('error', 'اختر عميلاً واحداً على الأقل');
                return;
            }
        }

        if (sendTime === 'scheduled' && (!$('#scheduled_date').val() || !$('#scheduled_time').val())) {
            notify('error', 'حدّد تاريخ الجدولة ووقتها');
            return;
        }

        const data = {
            _token: csrfToken,
            title: title,
            description: description,
            notification_type: $('#notification_type').val(),
            is_scheduled: sendTime === 'scheduled' ? 1 : 0,
            scheduled_date: $('#scheduled_date').val(),
            scheduled_time: $('#scheduled_time').val(),
            target_type: 'employees',
            send_to: employeeScope, // all | specific
        };

        if (employeeScope === 'specific') {
            data.employees = employees;
            data.send_type = sendType; // employee_only | all_clients | specific_clients

            if (sendType === 'specific_clients') {
                data.client_ids = clientIds;
            }
        }

        const submitBtn = $('#sendNotificationBtn');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> جاري الإرسال…');

        $.ajax({ url: @json(route('notifications.send')), method: 'POST', data: data })
            .done(function (response) {
                if (!response.success) {
                    notify('error', response.message || 'تعذّر الإرسال');
                    return;
                }

                notify('success', response.message);
                resetForm();
            })
            .fail(function (xhr) {
                const errors = xhr.responseJSON && xhr.responseJSON.errors;
                const first = errors ? Object.values(errors)[0][0] : null;

                notify('error', first || (xhr.responseJSON && xhr.responseJSON.message) || 'تعذّر الإرسال');
            })
            .always(function () {
                submitBtn.prop('disabled', false).html(originalText);
            });
    });

    updateEmployeeScopeUI();
});
</script>
@endsection
