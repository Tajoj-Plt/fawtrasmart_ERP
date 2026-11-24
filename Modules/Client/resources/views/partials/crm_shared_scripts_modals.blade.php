        // ====== إضافة العميل إلى خط السير ======
        let selectedClientId = null;

        // حفظ واسترجاع آخر إعدادات من localStorage
        const ITINERARY_SETTINGS_KEY = 'lastItinerarySettings';

        function saveItinerarySettings(settings) {
            localStorage.setItem(ITINERARY_SETTINGS_KEY, JSON.stringify(settings));
        }

        function getLastItinerarySettings() {
            const saved = localStorage.getItem(ITINERARY_SETTINGS_KEY);
            return saved ? JSON.parse(saved) : null;
        }

        /* نافذتا «إضافة إلى خط السير» (عميلٌ واحد / جماعيّة) بحجمٍ مضغوط:
           حقلان في كل سطر، وأسماءُ العملاء المحدَّدين في شريطٍ قصيرٍ يُمرَّر
           — كانت قائمةُ الأسماء تمدّ النافذة أطولَ من الشاشة. */
        const CRM_ITIN_MODAL_CSS = `<style>
            .crm-itin-dialog { max-width: 460px; }
            .crm-itin-dialog .modal-header { padding: 10px 16px !important; }
            .crm-itin-dialog .modal-title { font-size: .92rem !important; }
            .crm-itin-body { padding: 12px 16px; }
            .crm-itin-info { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 6px; font-size: .8rem; padding: 6px 10px; margin-bottom: 10px; border-radius: 8px; background: color-mix(in srgb, var(--app-info, #00cfe8) 10%, transparent); color: var(--app-text, #111827); }
            .crm-itin-names { width: 100%; max-height: 40px; overflow-y: auto; font-size: .72rem; line-height: 1.5; color: var(--app-muted, #6b7280); }
            .crm-itin-body .form-group { margin-bottom: 8px; }
            .crm-itin-body label { font-size: .76rem; font-weight: 700; margin-bottom: 3px; }
            .crm-itin-body .form-control { height: 32px; padding: 3px 8px; font-size: .82rem; }
            .crm-itin-foot { padding: 8px 16px !important; }
            .crm-itin-foot .btn { padding: 4px 12px; font-size: .82rem; }
        </style>`;

        /* ══════ حُذفت «الإضافة السريعة لخط السير» ══════

           كانت نافذةٌ كبيرة (`quickAddItineraryModal`) ببطاقتَي حالة
           للأسبوعين، واختيار أسبوعٍ بأزرار راديو، وأيامٍ بمربّعات — وتفتح
           من نفس الزرّ (`#quickAddToItineraryBtn`) الذي يفتح النافذة
           الصغيرة، فكان للزرّ معالجان: هذا المفوَّض هنا، وواحدٌ في
           `crm_shared_scripts_details.blade.php`. نافذتان لنفس المهمّة
           تُفتحان من ضغطةٍ واحدة.

           بقيت الصغيرة وحدها (`showAddToItineraryModal` أدناه)، وحُذف معها
           كلُّ ما لم يعد يناديه أحد: `showQuickAddModal`،
           `renderQuickAddModal`، `loadQuickEmployees`، `confirmQuickAdd`،
           و`selectAllDays`/`deselectAllDays`/`selectWorkDays`. */

        /* معالجٌ واحدٌ مفوَّض للزرّين معاً.

           كان `#addToItineraryBtn` يُعالَج هنا و`#quickAddToItineraryBtn`
           في `crm_shared_scripts_details.blade.php` — وذاك الملفّ يربط
           **الزرّين** كليهما عند كل رسمٍ لبطاقة العميل. فالنتيجة: الزرّ
           الأوّل له معالجان يفتحان النافذة مرّتين ويطلقان طلبَي موظفين
           متسابقين على نفس القائمة، والثاني يتراكم عليه معالجٌ جديد كلّما
           بُدّل العميل. التفويض على `document` يربط مرّةً واحدة ويغطّي كل
           رسمٍ لاحق. */
        $(document).on('click', '#addToItineraryBtn, #quickAddToItineraryBtn', function() {
            // الصنفان معاً: العنصر يحمل `rm-client-item client-item`، وشاشةٌ
            // أخرى قد تحمل أحدَهما فقط
            const clientItem = document.querySelector('.rm-client-item.selected, .client-item.selected');
            if (!clientItem) {
                Swal.fire({
                    title: 'تنبيه',
                    text: 'الرجاء اختيار عميل أولاً',
                    icon: 'warning',
                    confirmButtonText: 'حسناً'
                });
                return;
            }

            const clientName = (
                clientItem.querySelector('.rm-client-name')?.textContent ||
                clientItem.querySelector('.client-name')?.textContent ||
                'العميل'
            ).trim();

            showAddToItineraryModal(clientItem.dataset.clientId, clientName);
        });

        // دالة عرض Modal لإضافة إلى خط السير
        function showAddToItineraryModal(clientId, clientName) {
            /* المعرّفُ يُمرَّر إلى `initializeModalSelects` صراحةً.

               كانت تقرؤه من `selectedClientId` العامّ الذي يكتبه معالجان
               مختلفان في ملفّين (`crm_shared_scripts_modals` و
               `crm_shared_scripts_details`) — وكلاهما يُطلَق على نفس الزرّ.
               فإن سبق أحدُهما الآخر أو خرج مبكّراً بقي المتغيّر `null`
               فيذهب الطلبُ إلى `/clients/get-employees/null` ويعود فارغاً:
               قائمةُ الموظفين خالية بلا سببٍ ظاهر. */
            selectedClientId = clientId;

            const html = `
                ${CRM_ITIN_MODAL_CSS}
                <div class="modal fade" id="addToItineraryModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered crm-itin-dialog" role="document">
                        <div class="modal-content" style="border-radius: var(--app-radius, 10px); border: 1px solid var(--app-border, #e5e7eb); background: var(--app-surface, #fff); color: var(--app-text, #111827); box-shadow: var(--app-card-shadow, 0 4px 16px rgba(17,24,39,0.08));">
                            <div class="modal-header" style="background: var(--app-surface, #fff); color: var(--app-text, #111827); border: none; border-bottom: 1px solid var(--app-border, #e5e7eb); border-radius: var(--app-radius, 10px) var(--app-radius, 10px) 0 0; padding: 16px 20px;">
                                <h5 class="modal-title" style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--app-text, #111827);">
                                    <i class="fas fa-route me-2"></i>إضافة إلى خط السير
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity: 1; color: var(--app-muted, #6b7280); background: transparent; border: none; font-size: 1.4rem;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body crm-itin-body">
                                <div class="crm-itin-info">
                                    <i class="fas fa-info-circle"></i>إضافة العميل: <a href="${`{{ route('clients.show', ':id') }}`.replace(':id', clientId)}" target="_blank" class="fw-bold text-primary" style="text-decoration: underline; cursor: pointer;">${clientName} <i class="fas fa-external-link-alt fa-sm"></i></a>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-7">
                                        <label for="modalEmployeeSelect">الموظف</label>
                                        <select id="modalEmployeeSelect" class="form-control select2">
                                            <option value="">-- اختر موظف --</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-5">
                                        <label for="modalDaySelect">اليوم</label>
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

                                <div class="form-row">
                                    <div class="form-group col-4 mb-0">
                                        <label for="modalYearSelect">السنة</label>
                                        <select id="modalYearSelect" class="form-control select2">
                                            <option value="">-- اختر سنة --</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-8 mb-0">
                                        <label for="modalWeekSelect">الأسبوع</label>
                                        <select id="modalWeekSelect" class="form-control select2">
                                            <option value="">-- اختر أسبوع --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer crm-itin-foot" style="border-top: 1px solid var(--app-border, #f0f0f0);">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
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

            // تأخير صغير للتأكد من أن الـ DOM تم تحديثه
            setTimeout(() => {
                // استخدام jQuery لفتح الـ Modal (متوافق مع Bootstrap 4)
                $('#addToItineraryModal').modal('show');

                // إزالة الـ Modal من DOM بعد إخفائه
                $('#addToItineraryModal').on('hidden.bs.modal', function() {
                    const container = document.getElementById('itineraryModalContainer');
                    if (container) {
                        container.innerHTML = '';
                    }
                });

                initializeModalSelects(clientId);
            }, 100);
        }

        // تهيئة اختيارات Modal
        function initializeModalSelects(clientIdArg) {
            // جلب الموظفين المربوطين بالعميل المختار
            const employeeSelect = document.getElementById('modalEmployeeSelect');
            const clientId = clientIdArg || selectedClientId;
            const lastSettings = getLastItinerarySettings();

            if (!employeeSelect || !clientId) {
                return;
            }

            employeeSelect.innerHTML = '<option value="">جاري التحميل...</option>';

            /* مجموعتان لا قائمةٌ واحدة: «موظفو العميل» (المسنَدون له صراحةً
               في `client_employee`) ثمّ «موظفو الفرع وأبنائه». الخادم يرسل
               `group` مع كل صفّ — راجع `CRMController::getEmployeesForClient`.
               وبلا الفصل يذوب الموظّفُ المسنَد بين عشرات موظفي الفرع، وهو
               أوّلُ من يُقصَد عند إضافة عميله لخطّ السير. */
            $.ajax({
                url: `{{ route('clients.get-employees', ':id') }}`.replace(':id', clientId),
                method: 'GET',
                success: function(response) {
                    if (response.success && response.employees && response.employees.length > 0) {
                        employeeSelect.innerHTML = '<option value="">-- اختر موظف --</option>';

                        const groups = {
                            assigned: { label: 'موظفو العميل المعيَّنون', el: null },
                            branch: { label: 'التابعون لعقدة العميل (منطقة/فرع/مجموعة/منفذ)', el: null }
                        };

                        let selectedEmployeeId = null;

                        response.employees.forEach(emp => {
                            const key = groups[emp.group] ? emp.group : 'branch';

                            if (!groups[key].el) {
                                groups[key].el = document.createElement('optgroup');
                                groups[key].el.label = groups[key].label;
                                employeeSelect.appendChild(groups[key].el);
                            }

                            const option = document.createElement('option');
                            option.value = emp.id;
                            option.textContent = emp.name;
                            // تحديد آخر موظف مختار إن وجد
                            if (lastSettings && lastSettings.employee_id == emp.id) {
                                option.selected = true;
                                selectedEmployeeId = emp.id;
                            }
                            groups[key].el.appendChild(option);
                        });

                        /* الافتراضي أوّلُ **مسنَدٍ** لا أوّلُ الكلّ: الترتيب من
                           الخادم يضع المسنَدين أوّلاً، فإن وُجد أحدهم كان هو
                           الاختيار الأوّليّ. */
                        if (!selectedEmployeeId) {
                            employeeSelect.value = response.employees[0].id;
                        }

                        // تحديث الأسبوع بعد تحديد الموظف
                        updateModalWeekOptions();
                    } else {
                        employeeSelect.innerHTML = '<option value="">-- لا يوجد موظفون من قسم موظفي العملاء مُسنَدون لهذا العميل أو تابعون لعقدته --</option>';
                    }
                },
                error: function() {
                    employeeSelect.innerHTML = '<option value="">-- خطأ في جلب الموظفين --</option>';
                }
            });

            // تهيئة السنة والأسبوع
            updateModalYearOptions();

            // معالج تغيير السنة
            document.getElementById('modalYearSelect').addEventListener('change', updateModalWeekOptions);
        }

        // تحديث خيارات السنة في Modal
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

            // تحديث الأسابيع بعد تحديد السنة
            updateModalWeekOptions();
        }

        // تحديث خيارات الأسبوع في Modal
        function updateModalWeekOptions() {
            const yearSelect = document.getElementById('modalYearSelect');
            const weekSelect = document.getElementById('modalWeekSelect');
            const selectedYear = yearSelect.value;

            weekSelect.innerHTML = '<option value="">-- اختر أسبوع --</option>';

            if (!selectedYear) return;

            // حساب الأسابيع
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

        // حساب عدد الأسابيع في السنة (أسبوع يبدأ من السبت) - مطابق لصفحة إنشاء خط السير
        function getWeeksInYear(year) {
            // الحصول على أول يوم من السنة
            const jan1 = new Date(year, 0, 1);
            const dayOfWeek = jan1.getDay();

            // حساب أول سبت في السنة أو قبلها
            let firstSaturday = new Date(jan1);
            if (dayOfWeek !== 6) {
                if (dayOfWeek === 0) {
                    firstSaturday.setDate(jan1.getDate() - 1);
                } else {
                    firstSaturday.setDate(jan1.getDate() - dayOfWeek - 1);
                }
            }

            // آخر يوم في السنة
            const dec31 = new Date(year, 11, 31);

            // حساب عدد الأيام من أول سبت إلى آخر يوم في السنة
            const daysDiff = Math.floor((dec31 - firstSaturday) / (1000 * 60 * 60 * 24));

            // عدد الأسابيع = عدد الأيام / 7 (مقربة للأعلى)
            const weeksCount = Math.ceil((daysDiff + 1) / 7);

            return weeksCount;
        }

        // الحصول على نطاق تاريخ الأسبوع - مطابق لصفحة إنشاء خط السير
        function getWeekDateRange(year, week) {
            // الحصول على أول يوم من السنة
            const jan1 = new Date(year, 0, 1);
            // حساب أول يوم سبت في السنة أو قبلها
            const dayOfWeek = jan1.getDay();
            // تعديل للبدء من السبت (6 = السبت في JavaScript)
            let firstSaturday = new Date(jan1);

            // إذا كان أول يوم هو السبت، نستخدمه مباشرة، وإلا نذهب للسبت السابق
            if (dayOfWeek !== 6) {
                if (dayOfWeek === 0) {
                    // الأحد - نرجع يوم للسبت السابق
                    firstSaturday.setDate(jan1.getDate() - 1);
                } else {
                    // باقي الأيام - نرجع للسبت السابق
                    firstSaturday.setDate(jan1.getDate() - dayOfWeek - 1);
                }
            }

            // حساب بداية الأسبوع المطلوب
            const weekStart = new Date(firstSaturday);
            weekStart.setDate(firstSaturday.getDate() + (week - 1) * 7);

            // حساب نهاية الأسبوع (الجمعة)
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);

            const formatDate = (d) => {
                return `${d.getDate()}/${d.getMonth() + 1}`;
            };

            return {
                start: formatDate(weekStart),
                end: formatDate(weekEnd)
            };
        }

        // الحصول على رقم الأسبوع الحالي (يبدأ من السبت) - مطابق لصفحة إنشاء خط السير
        function getCurrentWeek(year = null) {
            const now = new Date();
            const targetYear = year ? parseInt(year) : now.getFullYear();

            // إذا كانت السنة المختارة ليست السنة الحالية، نرجع الأسبوع الأول
            if (targetYear !== now.getFullYear()) {
                return 1;
            }

            // الحصول على أول يوم من السنة
            const jan1 = new Date(targetYear, 0, 1);
            const dayOfWeek = jan1.getDay();

            // حساب أول سبت في السنة أو قبلها
            let firstSaturday = new Date(jan1);
            if (dayOfWeek !== 6) {
                if (dayOfWeek === 0) {
                    firstSaturday.setDate(jan1.getDate() - 1);
                } else {
                    firstSaturday.setDate(jan1.getDate() - dayOfWeek - 1);
                }
            }

            // حساب الفرق بالأيام بين اليوم الحالي وأول سبت
            const timeDiff = now - firstSaturday;
            const daysDiff = Math.floor(timeDiff / (1000 * 60 * 60 * 24));

            // حساب رقم الأسبوع
            const weekNumber = Math.floor(daysDiff / 7) + 1;

            return Math.max(1, weekNumber);
        }

        // تأكيد الإضافة إلى خط السير
        window.confirmAddToItinerary = function(clientId) {
            const employeeId = document.getElementById('modalEmployeeSelect').value;
            const year = document.getElementById('modalYearSelect').value;
            const week = document.getElementById('modalWeekSelect').value;
            const day = document.getElementById('modalDaySelect').value;

            console.log('Values:', {employeeId, year, week, day});

            // التحقق من الحقول بطريقة أفضل
            if (!employeeId || employeeId.trim() === '') {
                Swal.fire({
                    title: 'خطأ',
                    text: 'الرجاء اختيار موظف',
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
                return;
            }

            if (!year || year.trim() === '') {
                Swal.fire({
                    title: 'خطأ',
                    text: 'الرجاء اختيار السنة',
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
                return;
            }

            if (!week || week.trim() === '') {
                Swal.fire({
                    title: 'خطأ',
                    text: 'الرجاء اختيار الأسبوع',
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
                return;
            }

            if (!day || day.trim() === '') {
                Swal.fire({
                    title: 'خطأ',
                    text: 'الرجاء اختيار اليوم',
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
                return;
            }

            // إرسال الطلب للإضافة إلى قاعدة البيانات
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
                    console.log('Response:', response);
                    if (response.success) {
                        // حفظ الإعدادات للاستخدام لاحقاً
                        saveItinerarySettings({
                            employee_id: employeeId,
                            year: year,
                            week: week,
                            day: day
                        });

                        // إغلاق Modal فوراً باستخدام jQuery (متوافق مع Bootstrap 4 و 5)
                        $('#addToItineraryModal').modal('hide');

                        // إزالة الـ backdrop يدوياً إذا بقي
                        setTimeout(function() {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                            $('body').css('padding-right', '');
                        }, 100);

                        // إظهار رسالة نجاح صغيرة في أعلى الصفحة (Toast) بدلاً من Popup
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: response.message || 'تم الإضافة بنجاح!',
                            showConfirmButton: false,
                            timer: 3500,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'خطأ',
                            text: response.message || 'حدث خطأ أثناء الإضافة',
                            icon: 'error',
                            confirmButtonText: 'حسناً'
                        });
                    }
                },
                error: function(xhr) {
                    console.log('Error:', xhr);
                    let message = xhr.responseJSON?.message || 'حدث خطأ في الاتصال';
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        const errors = xhr.responseJSON.errors;
                        let detailedMessage = '';
                        for (let key in errors) {
                            detailedMessage += `${errors[key].join(', ')}\n`;
                        }
                        message = detailedMessage || message;
                    }
                    Swal.fire({
                        title: 'خطأ',
                        text: message,
                        icon: 'error',
                        confirmButtonText: 'حسناً'
                    });
                }
            });
        }

        // ====== إرسال إشعار للعميل ======

        // معالج زر إرسال إشعار للعميل
        $(document).on('click', '#sendNotificationBtn', function() {
            const clientItem = document.querySelector('.client-item.selected');
            if (!clientItem) {
                Swal.fire({
                    title: 'تنبيه',
                    text: 'الرجاء اختيار عميل أولاً',
                    icon: 'warning',
                    confirmButtonText: 'حسناً'
                });
                return;
            }

            const clientId = clientItem.dataset.clientId;
            const clientName = clientItem.querySelector('.client-name')?.textContent || 'العميل';
            const clientCode = clientItem.querySelector('.client-number')?.textContent || '';

            // عرض Modal لإرسال الإشعار
            showSendNotificationModal(clientId, clientName, clientCode);
        });

        // دالة عرض Modal لإرسال الإشعار
        function showSendNotificationModal(clientId, clientName, clientCode) {
            const html = `
                <div class="modal fade" id="sendClientNotificationModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content" style="border-radius: var(--app-radius, 10px); border: 1px solid var(--app-border, #e5e7eb); background: var(--app-surface, #fff); color: var(--app-text, #111827); box-shadow: var(--app-card-shadow, 0 4px 16px rgba(17,24,39,0.08));">
                            <div class="modal-header" style="background: var(--app-surface, #fff); color: var(--app-text, #111827); border: none; border-bottom: 1px solid var(--app-border, #e5e7eb); border-radius: var(--app-radius, 10px) var(--app-radius, 10px) 0 0; padding: 16px 20px;">
                                <h5 class="modal-title" style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--app-text, #111827);">
                                    <i class="fas fa-bell me-2"></i>إرسال إشعار للعميل
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity: 1; color: var(--app-muted, #6b7280); background: transparent; border: none; font-size: 1.4rem;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="alert alert-info" role="alert">
                                    <i class="fas fa-user me-2"></i>
                                    إرسال إشعار للعميل:
                                    <strong>${clientName}</strong>
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
                                            <label class="form-label fw-bold">
                                                <i class="fas fa-clock text-primary me-1"></i>وقت الإرسال
                                            </label>
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
                            <div class="modal-footer" style="border-top: 1px solid var(--app-border, #f0f0f0);">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
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

            // إنشاء أو تحديث container الـ Modal
            let modalContainer = document.getElementById('notificationModalContainer');
            if (!modalContainer) {
                modalContainer = document.createElement('div');
                modalContainer.id = 'notificationModalContainer';
                document.body.appendChild(modalContainer);
            }
            modalContainer.innerHTML = html;

            // تأخير صغير للتأكد من أن الـ DOM تم تحديثه
            setTimeout(() => {
                $('#sendClientNotificationModal').modal('show');

                // إزالة الـ Modal من DOM بعد إخفائه
                $('#sendClientNotificationModal').on('hidden.bs.modal', function() {
                    const container = document.getElementById('notificationModalContainer');
                    if (container) {
                        container.innerHTML = '';
                    }
                });

                // تحميل الموظفين المربوطين بالعميل
                loadEmployeesForNotification(clientId);

                // معالجة تغيير وقت الإرسال
                $('input[name="notificationSendTime"]').change(function() {
                    if ($(this).val() === 'scheduled') {
                        $('#scheduledNotificationSection').slideDown();
                    } else {
                        $('#scheduledNotificationSection').slideUp();
                    }
                });
            }, 100);
        }

        // دالة إخفاء/إظهار حقل العنوان المخصص
        window.toggleCustomTitleInput = function(select) {
            const customContainer = document.getElementById('customTitleContainer');
            if (select.value === 'custom') {
                $(customContainer).slideDown();
            } else {
                $(customContainer).slideUp();
                $('#notificationTitleCustom').val('');
            }
        };

        // دالة تحميل الموظفين للإشعار
        function loadEmployeesForNotification(clientId) {
            const employeeSelect = document.getElementById('notificationEmployeeSelect');
            employeeSelect.innerHTML = '<option value="">جاري التحميل...</option>';

            $.ajax({
                url: `/clients/get-employees/${clientId}`,
                method: 'GET',
                success: function(response) {
                    if (response.success && response.employees && response.employees.length > 0) {
                        let options = '<option value="">-- اختر موظف --</option>';
                        response.employees.forEach(function(employee) {
                            options += `<option value="${employee.id}">${employee.name}</option>`;
                        });
                        employeeSelect.innerHTML = options;
                        // تحديد أول موظف تلقائياً إن وجد
                        if (response.employees.length > 0) {
                            employeeSelect.value = response.employees[0].id;
                        }
                    } else {
                        employeeSelect.innerHTML = '<option value="">-- لا يوجد موظفون من قسم موظفي العملاء مُسنَدون لهذا العميل أو تابعون لعقدته --</option>';
                    }
                },
                error: function() {
                    employeeSelect.innerHTML = '<option value="">-- خطأ في جلب الموظفين --</option>';
                }
            });
        }

        // دالة تأكيد إرسال الإشعار
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

            // تعطيل الزر
            const btn = $('#confirmSendNotificationBtn');
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جاري الإرسال...');

            // تحضير البيانات
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

            // إضافة الموظف إذا تم اختياره
            if (employeeId) {
                data.employees = [employeeId];
            }

            $.ajax({
                url: '/notifications/send',
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
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: response.message || 'حدث خطأ أثناء إرسال الإشعار'
                        });
                    }
                },
                error: function(xhr) {
                    console.error('Error:', xhr);
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: xhr.responseJSON?.message || 'حدث خطأ في الاتصال'
                    });
                },
                complete: function() {
                    btn.prop('disabled', false).html(originalText);
                }
            });
        }

        // ====== إضافة موعد للعميل — appointments.store الحقيقي (فرع JSON) ======
        window.showAddAppointmentModal = function(clientId, clientName) {
            const today = new Date().toISOString().split('T')[0];
            const html = `
                <div class="modal fade" id="addAppointmentModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content" style="border-radius: var(--app-radius, 10px); border: 1px solid var(--app-border, #e5e7eb); background: var(--app-surface, #fff); color: var(--app-text, #111827); box-shadow: var(--app-card-shadow, 0 4px 16px rgba(17,24,39,0.08));">
                            <div class="modal-header" style="background: var(--app-surface, #fff); color: var(--app-text, #111827); border: none; border-bottom: 1px solid var(--app-border, #e5e7eb); border-radius: var(--app-radius, 10px) var(--app-radius, 10px) 0 0; padding: 16px 20px;">
                                <h5 class="modal-title" style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--app-text, #111827);">
                                    <i class="fas fa-calendar-plus me-2"></i>إضافة موعد
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity: 1; color: var(--app-muted, #6b7280); background: transparent; border: none; font-size: 1.4rem;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="alert alert-info" role="alert">
                                    <i class="fas fa-user me-2"></i>موعد للعميل: <strong>${clientName}</strong>
                                </div>
                                <div id="addAppointmentErrors"></div>
                                <div class="form-row">
                                    <div class="form-group col-md-6 mb-3">
                                        <label class="form-label fw-bold">التاريخ <span class="text-danger">*</span></label>
                                        <input type="date" id="appointmentDate" class="form-control" value="${today}"@unless (\App\Helpers\ClientSettingsHelper::appointmentsAllowPastDates()) min="${today}"@endunless>
                                    </div>
                                    <div class="form-group col-md-6 mb-3">
                                        <label class="form-label fw-bold">الوقت <span class="text-danger">*</span></label>
                                        <input type="time" id="appointmentTime" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label fw-bold">نوع الإجراء <span class="text-danger">*</span></label>
                                    <input type="text" id="appointmentActionType" class="form-control" placeholder="مثال: زيارة متابعة">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label fw-bold">المدة (دقائق)</label>
                                    <input type="number" id="appointmentDuration" class="form-control" min="1" max="1440" placeholder="{{ \App\Helpers\ClientSettingsHelper::appointmentDefaultDurationMinutes() }}">
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label fw-bold">ملاحظات</label>
                                    <textarea id="appointmentNotes" class="form-control" rows="3" maxlength="500"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid var(--app-border, #f0f0f0);">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>إلغاء
                                </button>
                                <button type="button" class="btn btn-primary" id="confirmAddAppointmentBtn" onclick="confirmAddAppointment(${clientId})">
                                    <i class="fas fa-save me-2"></i>حفظ الموعد
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            let modalContainer = document.getElementById('appointmentModalContainer');
            if (!modalContainer) {
                modalContainer = document.createElement('div');
                modalContainer.id = 'appointmentModalContainer';
                document.body.appendChild(modalContainer);
            }
            modalContainer.innerHTML = html;

            setTimeout(() => {
                $('#addAppointmentModal').modal('show');
                $('#addAppointmentModal').on('hidden.bs.modal', function() {
                    modalContainer.innerHTML = '';
                });
            }, 100);
        };

        window.confirmAddAppointment = function(clientId) {
            const date = $('#appointmentDate').val();
            const time = $('#appointmentTime').val();
            const actionType = $('#appointmentActionType').val().trim();
            const duration = $('#appointmentDuration').val();
            const notes = $('#appointmentNotes').val();
            const errorsBox = $('#addAppointmentErrors');
            errorsBox.html('');

            if (!date || !time || !actionType) {
                errorsBox.html('<div class="alert alert-warning py-2 mb-3">يرجى تعبئة التاريخ والوقت ونوع الإجراء.</div>');
                return;
            }

            const btn = $('#confirmAddAppointmentBtn');
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...');

            $.ajax({
                url: '{{ route("appointments.store") }}',
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                data: {
                    _token: '{{ csrf_token() }}',
                    client_id: clientId,
                    date: date,
                    time: time,
                    action_type: actionType,
                    duration: duration || null,
                    notes: notes || null
                },
                success: function(response) {
                    if (response.success) {
                        $('#addAppointmentModal').modal('hide');
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: response.message || 'تم إضافة الموعد بنجاح',
                            showConfirmButton: false, timer: 3000, timerProgressBar: true
                        });
                        const pane = document.getElementById(`appointments-${clientId}`);
                        if (pane) {
                            const tab = document.querySelector(`.rm-nav-tab[data-tab-target="appointments-${clientId}"]`);
                            if (tab) delete tab.dataset.appointmentsLoaded;
                            loadAppointmentsTab(clientId);
                        }
                    } else {
                        errorsBox.html('<div class="alert alert-danger py-2 mb-3">' + (response.message || 'تعذّر حفظ الموعد') + '</div>');
                    }
                },
                error: function(xhr) {
                    let msg = xhr.responseJSON?.message || 'حدث خطأ أثناء حفظ الموعد';
                    if (xhr.responseJSON?.errors) {
                        msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    }
                    errorsBox.html('<div class="alert alert-danger py-2 mb-3">' + msg + '</div>');
                },
                complete: function() {
                    btn.prop('disabled', false).html(originalText);
                }
            });
        };

        // ===== نظام الإضافة الجماعية لخط السير =====
        function showBulkAddModal() {
            if (selectedClients.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'الرجاء تحديد عميل واحد على الأقل'
                });
                return;
            }

            const clientsNames = selectedClients.map(c => c.name).join('، ');

            const html = `
                ${CRM_ITIN_MODAL_CSS}
                <div class="modal fade" id="bulkAddItineraryModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered crm-itin-dialog" role="document">
                        <div class="modal-content" style="border-radius: var(--app-radius, 10px); border: 1px solid var(--app-border, #e5e7eb); background: var(--app-surface, #fff); color: var(--app-text, #111827); box-shadow: var(--app-card-shadow, 0 4px 16px rgba(17,24,39,0.08));">
                            <div class="modal-header" style="background: var(--app-surface, #fff); color: var(--app-text, #111827); border: none; border-bottom: 1px solid var(--app-border, #e5e7eb); border-radius: var(--app-radius, 10px) var(--app-radius, 10px) 0 0; padding: 16px 20px;">
                                <h5 class="modal-title" style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--app-text, #111827);">
                                    <i class="fas fa-route me-2"></i>إضافة إلى خط السير
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity: 1; color: var(--app-muted, #6b7280); background: transparent; border: none; font-size: 1.4rem;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body crm-itin-body">
                                <div class="crm-itin-info">
                                    <i class="fas fa-info-circle"></i>إضافة العملاء: <span class="fw-bold">${selectedClients.length} عميل</span>
                                    <div class="crm-itin-names">${clientsNames}</div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-7">
                                        <label for="bulkModalEmployeeSelect">الموظف</label>
                                        <select id="bulkModalEmployeeSelect" class="form-control select2">
                                            <option value="">-- اختر موظف --</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-5">
                                        <label for="bulkModalDaySelect">اليوم</label>
                                        <select id="bulkModalDaySelect" class="form-control select2">
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

                                <div class="form-row">
                                    <div class="form-group col-4 mb-0">
                                        <label for="bulkModalYearSelect">السنة</label>
                                        <select id="bulkModalYearSelect" class="form-control select2">
                                            <option value="">-- اختر سنة --</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-8 mb-0">
                                        <label for="bulkModalWeekSelect">الأسبوع</label>
                                        <select id="bulkModalWeekSelect" class="form-control select2">
                                            <option value="">-- اختر أسبوع --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer crm-itin-foot" style="border-top: 1px solid var(--app-border, #f0f0f0);">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>إلغاء
                                </button>
                                <button type="button" class="btn btn-primary" id="confirmBulkAddBtn" onclick="confirmBulkAddToItinerary()">
                                    <i class="fas fa-check me-2"></i>إضافة إلى خط السير
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            let modalContainer = document.getElementById('bulkAddModalContainer');
            if (!modalContainer) {
                modalContainer = document.createElement('div');
                modalContainer.id = 'bulkAddModalContainer';
                document.body.appendChild(modalContainer);
            }
            modalContainer.innerHTML = html;

            setTimeout(() => {
                $('#bulkAddItineraryModal').modal('show');

                $('#bulkAddItineraryModal').on('hidden.bs.modal', function() {
                    const container = document.getElementById('bulkAddModalContainer');
                    if (container) container.innerHTML = '';
                });

                // تهيئة الاختيارات
                initializeBulkModalSelects();
            }, 100);
        }

        // تهيئة اختيارات Modal الإضافة الجماعية
        function initializeBulkModalSelects() {
            const employeeSelect = document.getElementById('bulkModalEmployeeSelect');
            const lastSettings = getLastItinerarySettings();

            employeeSelect.innerHTML = '<option value="">جاري التحميل...</option>';

            /* موظفو العملاء المحدَّدين: من قسم موظفي العملاء في الإعدادات، ومُسنَدٌ
               لأحدهم أو تابعٌ لعقدته — `CRMController::getEmployeesForGroup`.
               كانت بلا مجموعةٍ محدَّدة تجلب كلَّ موظفي النطاق. */
            const ajaxUrl = '{{ route("clients.get-employees-by-group") }}';
            const ajaxData = { group_id: selectedGroupId, client_ids: selectedClients.map(c => c.id) };

            $.ajax({
                url: ajaxUrl,
                method: 'GET',
                data: ajaxData,
                success: function(response) {
                    if (response.success && response.employees && response.employees.length > 0) {
                        employeeSelect.innerHTML = '<option value="">-- اختر موظف --</option>';
                        response.employees.forEach(emp => {
                            const option = document.createElement('option');
                            option.value = emp.id;
                            option.textContent = emp.name;
                            if (lastSettings && lastSettings.employee_id == emp.id) {
                                option.selected = true;
                            }
                            employeeSelect.appendChild(option);
                        });
                        if (!lastSettings && response.employees.length > 0) {
                            employeeSelect.value = response.employees[0].id;
                        }
                    } else {
                        employeeSelect.innerHTML = '<option value="">-- لا يوجد موظفون من قسم موظفي العملاء مُسنَدون لهؤلاء العملاء أو تابعون لعُقدهم --</option>';
                    }
                },
                error: function() {
                    employeeSelect.innerHTML = '<option value="">-- خطأ في جلب الموظفين --</option>';
                }
            });

            // تهيئة السنة
            const yearSelect = document.getElementById('bulkModalYearSelect');
            yearSelect.innerHTML = '<option value="">-- اختر سنة --</option>';
            const currentYear = new Date().getFullYear();
            for (let year = currentYear - 1; year <= currentYear + 1; year++) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === currentYear) option.selected = true;
                yearSelect.appendChild(option);
            }

            // تهيئة الأسابيع
            updateBulkModalWeekOptions();
            document.getElementById('bulkModalYearSelect').addEventListener('change', updateBulkModalWeekOptions);
        }

        // تحديث خيارات الأسابيع
        function updateBulkModalWeekOptions() {
            const yearSelect = document.getElementById('bulkModalYearSelect');
            const weekSelect = document.getElementById('bulkModalWeekSelect');
            const selectedYear = parseInt(yearSelect.value) || new Date().getFullYear();

            weekSelect.innerHTML = '<option value="">-- اختر أسبوع --</option>';

            const now = new Date();
            const currentWeek = getWeekNumber(now);
            const isCurrentYear = selectedYear === now.getFullYear();

            for (let week = 1; week <= 52; week++) {
                const option = document.createElement('option');
                option.value = week;

                // حساب تواريخ الأسبوع
                const weekStart = getDateOfISOWeek(week, selectedYear);
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekEnd.getDate() + 6);

                const startStr = `${weekStart.getDate()}/${weekStart.getMonth() + 1}`;
                const endStr = `${weekEnd.getDate()}/${weekEnd.getMonth() + 1}`;

                option.textContent = `الأسبوع ${week} (${startStr} - ${endStr})`;

                if (isCurrentYear && week === currentWeek) {
                    option.selected = true;
                }

                weekSelect.appendChild(option);
            }
        }

        // دالة حساب رقم الأسبوع
        function getWeekNumber(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7;
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        }

        // دالة الحصول على تاريخ بداية الأسبوع
        function getDateOfISOWeek(week, year) {
            const simple = new Date(year, 0, 1 + (week - 1) * 7);
            const dow = simple.getDay();
            const ISOweekStart = simple;
            if (dow <= 4) {
                ISOweekStart.setDate(simple.getDate() - simple.getDay() + 1);
            } else {
                ISOweekStart.setDate(simple.getDate() + 8 - simple.getDay());
            }
            return ISOweekStart;
        }

        // تأكيد الإضافة الجماعية
        function confirmBulkAddToItinerary() {
            const employeeId = document.getElementById('bulkModalEmployeeSelect').value;
            const year = document.getElementById('bulkModalYearSelect').value;
            const weekNumber = document.getElementById('bulkModalWeekSelect').value;
            const dayOfWeek = document.getElementById('bulkModalDaySelect').value;

            // التحقق من البيانات
            if (!employeeId) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'الرجاء اختيار موظف' });
                return;
            }
            if (!year) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'الرجاء اختيار السنة' });
                return;
            }
            if (!weekNumber) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'الرجاء اختيار الأسبوع' });
                return;
            }
            if (!dayOfWeek) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'الرجاء اختيار اليوم' });
                return;
            }

            // حفظ الإعدادات
            saveItinerarySettings({ employee_id: employeeId });

            const btn = document.getElementById('confirmBulkAddBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الإضافة...';

            const clientIds = selectedClients.map(c => c.id);

            $.ajax({
                url: '{{ route("itinerary.bulk-add-clients") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    client_ids: clientIds,
                    employee_ids: [employeeId],
                    weeks: [{ year: parseInt(year), week_number: parseInt(weekNumber) }],
                    days: [dayOfWeek]
                },
                success: function(response) {
                    if (response.success) {
                        $('#bulkAddItineraryModal').modal('hide');

                        setTimeout(() => {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css('padding-right', '');
                        }, 100);

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: response.message,
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });

                        // إلغاء التحديدات
                        clearAllSelections();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: response.message || 'حدث خطأ أثناء الإضافة'
                        });
                    }
                },
                error: function(xhr) {
                    console.error('Error:', xhr);
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: xhr.responseJSON?.message || 'حدث خطأ في الاتصال'
                    });
                },
                complete: function() {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
        }
