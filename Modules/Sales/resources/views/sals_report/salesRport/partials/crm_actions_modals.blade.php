<script>
    // متغيرات عامة
    let selectedClientId = null;
    const ITINERARY_SETTINGS_KEY = 'lastItinerarySettings';

    function saveItinerarySettings(settings) {
        localStorage.setItem(ITINERARY_SETTINGS_KEY, JSON.stringify(settings));
    }

    function getLastItinerarySettings() {
        const saved = localStorage.getItem(ITINERARY_SETTINGS_KEY);
        return saved ? JSON.parse(saved) : null;
    }

    // ربط الأزرار في الجدول
    $(document).on('click', '.add-to-itinerary-btn', function(e) {
        e.preventDefault();
        selectedClientId = $(this).data('client-id');
        const clientName = $(this).data('client-name');
        showAddToItineraryModal(selectedClientId, clientName);
    });

    $(document).on('click', '.send-notification-btn', function(e) {
        e.preventDefault();
        const clientId = $(this).data('client-id');
        const clientName = $(this).data('client-name');
        const clientCode = $(this).data('client-code') || '';
        showSendNotificationModal(clientId, clientName, clientCode);
    });

    // دالة عرض Modal لإضافة إلى خط السير
    function showAddToItineraryModal(clientId, clientName) {
        const html = `
            <div class="modal fade" id="addToItineraryModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content" style="border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 4px 16px rgba(17,24,39,0.08);">
                        <div class="modal-header" style="background: #ffffff; color: #111827; border: none; border-bottom: 1px solid #e5e7eb; border-radius: 10px 10px 0 0; padding: 16px 20px;">
                            <h5 class="modal-title" style="margin: 0; font-size: 1rem; font-weight: 700; color: #111827;">
                                <i class="fas fa-route me-2"></i>إضافة إلى خط السير
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity: 1; color: #6b7280; background: transparent; border: none; font-size: 1.4rem;">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>إضافة العميل: <a href="{{ url('clients') }}/${clientId}" target="_blank" class="fw-bold text-primary" style="text-decoration: underline; cursor: pointer;">${clientName} <i class="fas fa-external-link-alt fa-sm"></i></a>
                            </div>

                            <div class="form-group mb-3">
                                <label for="modalEmployeeSelect" class="form-label fw-bold">اختر الموظف</label>
                                <select id="modalEmployeeSelect" class="form-control select2">
                                    <option value="">-- اختر موظف --</option>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6 mb-3">
                                    <label for="modalYearSelect" class="form-label fw-bold">السنة</label>
                                    <select id="modalYearSelect" class="form-control select2">
                                        <option value="">-- اختر سنة --</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6 mb-3">
                                    <label for="modalWeekSelect" class="form-label fw-bold">الأسبوع</label>
                                    <select id="modalWeekSelect" class="form-control select2">
                                        <option value="">-- اختر أسبوع --</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="modalDaySelect" class="form-label fw-bold">اختر اليوم</label>
                                <select id="modalDaySelect" class="form-control select2">
                                    <option value="">-- اختر يوم --</option>
                                    <option value="saturday">السبت</option>
                                    <option value="sunday">الأحد</option>
                                    <option value="monday">الاثنين</option>
                                    <option value="tuesday">الثلاثاء</option>
                                    <option value="wednesday">الأربعاء</option>
                                    <option value="thursday">الخميس</option>
                                    <option value="friday">الجمعة</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid #f0f0f0;">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>إلغاء
                            </button>
                            <button type="button" class="btn btn-primary" id="confirmAddToItineraryBtn" onclick="confirmAddToItinerary('${clientId}')">
                                <i class="fas fa-check me-2"></i>إضافة إلى خط السير
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const modalContainer = document.getElementById('itineraryModalContainer');
        if (modalContainer) {
            modalContainer.innerHTML = html;
        } else {
            const div = document.createElement('div');
            div.id = 'itineraryModalContainer';
            document.body.appendChild(div);
            div.innerHTML = html;
        }

        setTimeout(() => {
            $('#addToItineraryModal').modal('show');
            $('#addToItineraryModal').on('hidden.bs.modal', function() {
                const container = document.getElementById('itineraryModalContainer');
                if (container) {
                    container.innerHTML = '';
                }
            });
            initializeModalSelects();
        }, 100);
    }

    function initializeModalSelects() {
        const employeeSelect = document.getElementById('modalEmployeeSelect');
        const clientId = selectedClientId;
        const lastSettings = getLastItinerarySettings();

        employeeSelect.innerHTML = '<option value="">جاري التحميل...</option>';

        $.ajax({
            url: `{{ url('clients/get-employees') }}/${clientId}`,
            method: 'GET',
            success: function(response) {
                if (response.success && response.employees && response.employees.length > 0) {
                    employeeSelect.innerHTML = '<option value="">-- اختر موظف --</option>';
                    let selectedEmployeeId = null;

                    response.employees.forEach(emp => {
                        const option = document.createElement('option');
                        option.value = emp.id;
                        option.textContent = emp.name;
                        if (lastSettings && lastSettings.employee_id == emp.id) {
                            option.selected = true;
                            selectedEmployeeId = emp.id;
                        }
                        employeeSelect.appendChild(option);
                    });

                    if (!selectedEmployeeId && response.employees.length > 0) {
                        employeeSelect.value = response.employees[0].id;
                    }

                    updateModalWeekOptions();
                } else {
                    employeeSelect.innerHTML = '<option value="">-- لا توجد موظفين مربوطين بهذا العميل --</option>';
                }
            },
            error: function() {
                employeeSelect.innerHTML = '<option value="">-- خطأ في جلب الموظفين --</option>';
            }
        });

        updateModalYearOptions();
        document.getElementById('modalYearSelect').addEventListener('change', updateModalWeekOptions);
    }

    function updateModalYearOptions() {
        const yearSelect = document.getElementById('modalYearSelect');
        yearSelect.innerHTML = '<option value="">-- اختر سنة --</option>';

        const currentYear = new Date().getFullYear();
        for (let year = currentYear - 2; year <= currentYear + 2; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (year === currentYear) {
                option.selected = true;
            }
            yearSelect.appendChild(option);
        }

        updateModalWeekOptions();
    }

    function updateModalWeekOptions() {
        const yearSelect = document.getElementById('modalYearSelect');
        const weekSelect = document.getElementById('modalWeekSelect');
        const selectedYear = yearSelect.value;

        weekSelect.innerHTML = '<option value="">-- اختر أسبوع --</option>';

        if (!selectedYear) return;

        const year = parseInt(selectedYear);
        const weeks = getWeeksInYear(year);
        const currentWeek = getCurrentWeek(year);

        for (let week = 1; week <= weeks; week++) {
            const option = document.createElement('option');
            option.value = week;
            const dateRange = getWeekDateRange(year, week);
            option.textContent = `الأسبوع ${week} (${dateRange.start} - ${dateRange.end})`;
            if (week === currentWeek) {
                option.selected = true;
            }
            weekSelect.appendChild(option);
        }
    }

    function getWeeksInYear(year) {
        const jan1 = new Date(year, 0, 1);
        const dayOfWeek = jan1.getDay();
        let firstSaturday = new Date(jan1);
        if (dayOfWeek !== 6) {
            if (dayOfWeek === 0) {
                firstSaturday.setDate(jan1.getDate() - 1);
            } else {
                firstSaturday.setDate(jan1.getDate() - dayOfWeek - 1);
            }
        }
        const dec31 = new Date(year, 11, 31);
        const daysDiff = Math.floor((dec31 - firstSaturday) / (1000 * 60 * 60 * 24));
        return Math.ceil((daysDiff + 1) / 7);
    }

    function getWeekDateRange(year, week) {
        const jan1 = new Date(year, 0, 1);
        const dayOfWeek = jan1.getDay();
        let firstSaturday = new Date(jan1);
        if (dayOfWeek !== 6) {
            if (dayOfWeek === 0) {
                firstSaturday.setDate(jan1.getDate() - 1);
            } else {
                firstSaturday.setDate(jan1.getDate() - dayOfWeek - 1);
            }
        }
        const weekStart = new Date(firstSaturday);
        weekStart.setDate(firstSaturday.getDate() + (week - 1) * 7);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        const formatDate = (d) => { return `${d.getDate()}/${d.getMonth() + 1}`; };
        return { start: formatDate(weekStart), end: formatDate(weekEnd) };
    }

    function getCurrentWeek(year = null) {
        const now = new Date();
        const targetYear = year ? parseInt(year) : now.getFullYear();
        if (targetYear !== now.getFullYear()) { return 1; }
        const jan1 = new Date(targetYear, 0, 1);
        const dayOfWeek = jan1.getDay();
        let firstSaturday = new Date(jan1);
        if (dayOfWeek !== 6) {
            if (dayOfWeek === 0) {
                firstSaturday.setDate(jan1.getDate() - 1);
            } else {
                firstSaturday.setDate(jan1.getDate() - dayOfWeek - 1);
            }
        }
        const timeDiff = now - firstSaturday;
        const daysDiff = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
        return Math.max(1, Math.floor(daysDiff / 7) + 1);
    }

    window.confirmAddToItinerary = function(clientId) {
        const employeeId = document.getElementById('modalEmployeeSelect').value;
        const year = document.getElementById('modalYearSelect').value;
        const week = document.getElementById('modalWeekSelect').value;
        const day = document.getElementById('modalDaySelect').value;

        if (!employeeId || employeeId.trim() === '') {
            Swal.fire({ title: 'خطأ', text: 'الرجاء اختيار موظف', icon: 'error', confirmButtonText: 'حسناً' });
            return;
        }
        if (!year || year.trim() === '') {
            Swal.fire({ title: 'خطأ', text: 'الرجاء اختيار السنة', icon: 'error', confirmButtonText: 'حسناً' });
            return;
        }
        if (!week || week.trim() === '') {
            Swal.fire({ title: 'خطأ', text: 'الرجاء اختيار الأسبوع', icon: 'error', confirmButtonText: 'حسناً' });
            return;
        }
        if (!day || day.trim() === '') {
            Swal.fire({ title: 'خطأ', text: 'الرجاء اختيار اليوم', icon: 'error', confirmButtonText: 'حسناً' });
            return;
        }

        $.ajax({
            url: '{{ route("itinerary.add-client") }}',
            method: 'POST',
            data: {
                client_id: clientId,
                employee_ids: [employeeId],
                year: year,
                week_number: week,
                day_of_week: day,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    saveItinerarySettings({ employee_id: employeeId, year: year, week: week, day: day });
                    $('#addToItineraryModal').modal('hide');
                    setTimeout(function() {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        $('body').css('padding-right', '');
                    }, 100);

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: response.message || 'تم الإضافة بنجاح!',
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({ title: 'خطأ', text: response.message || 'حدث خطأ أثناء الإضافة', icon: 'error', confirmButtonText: 'حسناً' });
                }
            },
            error: function(xhr) {
                let message = xhr.responseJSON?.message || 'حدث خطأ في الاتصال';
                Swal.fire({ title: 'خطأ', text: message, icon: 'error', confirmButtonText: 'حسناً' });
            }
        });
    };

    // ====== إرسال إشعار للعميل ======
    function showSendNotificationModal(clientId, clientName, clientCode) {
        const html = `
            <div class="modal fade" id="sendClientNotificationModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content" style="border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 4px 16px rgba(17,24,39,0.08);">
                        <div class="modal-header" style="background: #ffffff; color: #111827; border: none; border-bottom: 1px solid #e5e7eb; border-radius: 10px 10px 0 0; padding: 16px 20px;">
                            <h5 class="modal-title" style="margin: 0; font-size: 1rem; font-weight: 700; color: #111827;">
                                <i class="fas fa-bell me-2"></i>إرسال إشعار للعميل
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity: 1; color: #6b7280; background: transparent; border: none; font-size: 1.4rem;">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-user me-2"></i> إرسال إشعار للعميل: <strong>${clientName}</strong>
                                ${clientCode ? '<span class="badge bg-secondary ms-2">' + clientCode + '</span>' : ''}
                            </div>
                            <div class="form-group mb-3">
                                <label for="notificationTitleSelect" class="form-label fw-bold">
                                    <i class="fas fa-heading text-primary me-1"></i>عنوان الإشعار <span class="text-danger">*</span>
                                </label>
                                <select id="notificationTitleSelect" class="form-control" onchange="toggleCustomTitleInput(this)">
                                    <option value="متابعة العميل">متابعة العميل</option>
                                    <option value="تنبيه هام">تنبيه هام</option>
                                    <option value="طلب بضاعة">طلب بضاعة</option>
                                    <option value="ملاحظة زيارة">ملاحظة زيارة</option>
                                    <option value="تعديل جدول الزيارات">تعديل جدول الزيارات</option>
                                    <option value="سند / تحصيل">سند / تحصيل</option>
                                    <option value="custom">أخرى (كتابة عنوان مخصص)...</option>
                                </select>
                            </div>
                            <div class="form-group mb-3" id="customTitleContainer" style="display: none;">
                                <label for="notificationTitleCustom" class="form-label fw-bold">
                                    <i class="fas fa-edit text-primary me-1"></i>اكتب العنوان المخصص <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="notificationTitleCustom" class="form-control" placeholder="أدخل عنوان الإشعار المخصص">
                            </div>
                            <div class="form-group mb-3">
                                <label for="notificationMessage" class="form-label fw-bold">
                                    <i class="fas fa-comment-alt text-primary me-1"></i>نص الإشعار <span class="text-danger">*</span>
                                </label>
                                <textarea id="notificationMessage" class="form-control" rows="4" placeholder="أدخل نص الإشعار"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label fw-bold"><i class="fas fa-clock text-primary me-1"></i>وقت الإرسال</label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="notificationSendTime" id="sendNow" value="now" checked>
                                                <label class="form-check-label" for="sendNow">إرسال فوري</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="notificationSendTime" id="sendScheduled" value="scheduled">
                                                <label class="form-check-label" for="sendScheduled">جدولة</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="notificationEmployeeSelect" class="form-label fw-bold">
                                            <i class="fas fa-user-tie text-primary me-1"></i>اختر الموظف المسؤول
                                        </label>
                                        <select id="notificationEmployeeSelect" class="form-control">
                                            <option value="">-- اختر موظف --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div id="scheduledNotificationSection" class="row" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="scheduledDate" class="form-label fw-bold">تاريخ الإرسال</label>
                                        <input type="date" id="scheduledDate" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="scheduledTime" class="form-label fw-bold">وقت الإرسال</label>
                                        <input type="time" id="scheduledTime" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid #f0f0f0;">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>إلغاء
                            </button>
                            <button type="button" class="btn btn-primary" id="confirmSendNotificationBtn" onclick="confirmSendNotification('${clientId}', '${clientName}')">
                                <i class="fas fa-paper-plane me-2"></i>إرسال الإشعار
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        let modalContainer = document.getElementById('notificationModalContainer');
        if (!modalContainer) {
            modalContainer = document.createElement('div');
            modalContainer.id = 'notificationModalContainer';
            document.body.appendChild(modalContainer);
        }
        modalContainer.innerHTML = html;

        setTimeout(() => {
            $('#sendClientNotificationModal').modal('show');
            $('#sendClientNotificationModal').on('hidden.bs.modal', function() {
                if (document.getElementById('notificationModalContainer')) {
                    document.getElementById('notificationModalContainer').innerHTML = '';
                }
            });
            loadEmployeesForNotification(clientId);
            $('input[name="notificationSendTime"]').change(function() {
                if ($(this).val() === 'scheduled') {
                    $('#scheduledNotificationSection').slideDown();
                } else {
                    $('#scheduledNotificationSection').slideUp();
                }
            });
        }, 100);
    }

    window.toggleCustomTitleInput = function(select) {
        const customContainer = document.getElementById('customTitleContainer');
        if (select.value === 'custom') {
            $(customContainer).slideDown();
        } else {
            $(customContainer).slideUp();
            $('#notificationTitleCustom').val('');
        }
    };

    function loadEmployeesForNotification(clientId) {
        const employeeSelect = document.getElementById('notificationEmployeeSelect');
        employeeSelect.innerHTML = '<option value="">جاري التحميل...</option>';

        $.ajax({
            url: `{{ url('clients/get-employees') }}/${clientId}`,
            method: 'GET',
            success: function(response) {
                if (response.success && response.employees && response.employees.length > 0) {
                    let options = '<option value="">-- اختر موظف --</option>';
                    response.employees.forEach(function(employee) {
                        options += `<option value="${employee.id}">${employee.name}</option>`;
                    });
                    employeeSelect.innerHTML = options;
                    if (response.employees.length > 0) {
                        employeeSelect.value = response.employees[0].id;
                    }
                } else {
                    employeeSelect.innerHTML = '<option value="">-- لا توجد موظفين مربوطين بهذا العميل --</option>';
                }
            },
            error: function() {
                employeeSelect.innerHTML = '<option value="">-- خطأ في جلب الموظفين --</option>';
            }
        });
    }

    window.confirmSendNotification = function(clientId, clientName) {
        const message = $('#notificationMessage').val().trim();
        let title = $('#notificationTitleSelect').val();
        if (title === 'custom') {
            title = $('#notificationTitleCustom').val().trim();
        } else {
            title = title + ': ' + clientName;
        }
        const sendTime = $('input[name="notificationSendTime"]:checked').val();
        const employeeId = $('#notificationEmployeeSelect').val();
        const scheduledDate = $('#scheduledDate').val();
        const scheduledTime = $('#scheduledTime').val();

        if (!title) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى تحديد أو كتابة عنوان الإشعار' });
            return;
        }
        if (!message) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى إدخال نص الإشعار' });
            return;
        }
        if (sendTime === 'scheduled' && (!scheduledDate || !scheduledTime)) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى تحديد تاريخ ووقت الجدولة' });
            return;
        }

        const btn = $('#confirmSendNotificationBtn');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جاري الإرسال...');

        const data = {
            _token: '{{ csrf_token() }}',
            title: title,
            description: message,
            notification_type: 'client_popup',
            send_to: 'specific',
            send_type: 'specific_clients',
            client_ids: [clientId],
            is_scheduled: sendTime === 'scheduled' ? 1 : 0,
            scheduled_date: scheduledDate,
            scheduled_time: scheduledTime
        };

        if (employeeId) {
            data.employees = [employeeId];
        }

        $.ajax({
            url: '{{ url("notifications/send") }}',
            method: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#sendClientNotificationModal').modal('hide');
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: response.message || 'تم إرسال الإشعار بنجاح',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: response.message || 'حدث خطأ أثناء إرسال الإشعار' });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'خطأ', text: xhr.responseJSON?.message || 'حدث خطأ في الاتصال' });
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    }
</script>
