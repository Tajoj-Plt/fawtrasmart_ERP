@php
    $enableEmailNotifications = $settings->where('key', 'enable_email_notifications')->first();
    $enableSmsNotifications = $settings->where('key', 'enable_sms_notifications')->first();
    $enablePushNotifications = $settings->where('key', 'enable_push_notifications')->first();
@endphp

<h5 class="mb-3"><i class="feather icon-bell"></i> قنوات الإشعارات</h5>

<div class="row">
    <div class="col-md-4">
        <div class="card border">
            <div class="card-body text-center">
                <div class="notification-icon mb-3">
                    <i class="feather icon-mail text-primary" style="font-size: 48px;"></i>
                </div>
                <h5>البريد الإلكتروني</h5>
                <p class="text-muted">إرسال إشعارات بالبريد الإلكتروني</p>
                <label class="stk-switch stk-switch--sm d-inline-flex" for="enable_email_notifications">
                    <input type="checkbox" id="enable_email_notifications" name="enable_email_notifications" value="1"
                           {{ ($enableEmailNotifications && in_array($enableEmailNotifications->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                </label>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border">
            <div class="card-body text-center">
                <div class="notification-icon mb-3">
                    <i class="feather icon-smartphone text-success" style="font-size: 48px;"></i>
                </div>
                <h5>الرسائل النصية</h5>
                <p class="text-muted">إرسال إشعارات SMS للعملاء</p>
                <label class="stk-switch stk-switch--sm d-inline-flex" for="enable_sms_notifications">
                    <input type="checkbox" id="enable_sms_notifications" name="enable_sms_notifications" value="1"
                           {{ ($enableSmsNotifications && in_array($enableSmsNotifications->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                </label>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border">
            <div class="card-body text-center">
                <div class="notification-icon mb-3">
                    <i class="feather icon-bell text-warning" style="font-size: 48px;"></i>
                </div>
                <h5>الإشعارات المباشرة</h5>
                <p class="text-muted">إشعارات Push للتطبيق</p>
                <label class="stk-switch stk-switch--sm d-inline-flex" for="enable_push_notifications">
                    <input type="checkbox" id="enable_push_notifications" name="enable_push_notifications" value="1"
                           {{ ($enablePushNotifications && in_array($enablePushNotifications->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                </label>
            </div>
        </div>
    </div>
</div>

@if (hasPermission('online_store.settings.manage'))
    <hr>
    <h5 class="mb-3"><i class="feather icon-send"></i> اختبار الإشعارات</h5>

    <div class="card bg-light">
        <div class="card-body">
            {{-- ملاحظة: هذا العنصر <div> وليس <form> عن قصد — نموذج إعدادات
                 المتجر (#settingsForm) يلفّ هذا الجزء، وأي <form> متداخل يجعل
                 المتصفح يُغلق النموذج الخارجي مبكراً فيتعطل زر «حفظ الإعدادات». --}}
            <div id="testEmailForm" class="form-inline">
                <div class="form-group ml-2">
                    <label for="test_email" class="ml-2">اختبار البريد الإلكتروني:</label>
                    <input type="email" id="test_email" class="form-control" placeholder="example@email.com">
                </div>
                <button type="button" id="testEmailBtn" class="btn btn-outline-primary">
                    <i class="feather icon-send"></i> إرسال رسالة اختبارية
                </button>
            </div>
        </div>
    </div>
@endif

<hr>
<h5 class="mb-3"><i class="feather icon-list"></i> أنواع الإشعارات</h5>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>نوع الإشعار</th>
                <th class="text-center">البريد</th>
                <th class="text-center">SMS</th>
                <th class="text-center">Push</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>طلب جديد</td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
            </tr>
            <tr>
                <td>تأكيد الطلب</td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
            </tr>
            <tr>
                <td>شحن الطلب</td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
            </tr>
            <tr>
                <td>تسليم الطلب</td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
            </tr>
            <tr>
                <td>عروض وتخفيضات</td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
                <td class="text-center"><i class="feather icon-x text-danger"></i></td>
                <td class="text-center"><i class="feather icon-check text-success"></i></td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.getElementById('testEmailBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    var email = document.getElementById('test_email').value;
    
    if (!email) {
        toastr.warning('يرجى إدخال البريد الإلكتروني');
        return;
    }
    
    fetch('{{ route("store_settings.test-email") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success(data.message);
        } else {
            toastr.error(data.message);
        }
    })
    .catch(error => {
        toastr.error('حدث خطأ أثناء الإرسال');
    });
});
</script>
