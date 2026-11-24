        // Cache لتفاصيل العميل — مشترك بين سطح المكتب والجوال (كان مفقوداً
        // على الجوال فعلياً: selectClient لم يكن جزءاً من الملفات المشتركة
        // القديمة، فزر اختيار العميل لم يكن يحمّل شيئاً على الجوال)
        const clientsCache = new Map();
        const CACHE_DURATION = 5 * 60 * 1000; // 5 دقائق

        // دالة للتحقق من صلاحية الـ cache
        function isCacheValid(timestamp) {
            return (Date.now() - timestamp) < CACHE_DURATION;
        }

        /* قائمة "المزيد" — تبديل يدوي بسيط بدل مكوّن Bootstrap dropdown، لأن
           الصفحة تحمّل Bootstrap 4 و5 معاً وسلوك dropdown/modal بينهما غير
           موثوق هنا (نفس فخّ Modals الموثّق في هذه الصفحة). */
        document.addEventListener('click', function(e) {
            const toggle = e.target.closest('#crmMoreToggle');
            const openMenu = document.querySelector('.rm-qa-more.open');
            if (toggle) {
                const menu = toggle.closest('.rm-qa-more');
                const wasOpen = menu.classList.contains('open');
                if (openMenu) openMenu.classList.remove('open');
                if (!wasOpen) menu.classList.add('open');
                return;
            }
            if (openMenu && !e.target.closest('.rm-qa-more')) {
                openMenu.classList.remove('open');
            }
        });

        // دالة لاختيار العميل وعرض تفاصيله
        window.selectClient = function(clientId) {
            console.log('selectClient called with ID:', clientId);

            // التحقق من الـ cache أولاً
            const cachedData = clientsCache.get(clientId);
            if (cachedData && isCacheValid(cachedData.timestamp)) {
                console.log('Using cached data for client:', clientId);
                displayClientDetails(cachedData.data);

                // تحديث العناصر المحددة في القائمة الجانبية
                document.querySelectorAll('.client-item').forEach(item => {
                    item.classList.remove('selected');
                    if (item.dataset.clientId == clientId) {
                        item.classList.add('selected');
                    }
                });

                // إخفاء الرسالة الافتراضية
                const defaultMessage = document.getElementById('defaultMessage');
                if (defaultMessage) {
                    defaultMessage.style.display = 'none';
                }

                // تحديث زر التعديل
                const editButton = document.getElementById('editClientButton');
                if (editButton) {
                    editButton.classList.remove('disabled');
                    editButton.href = getClientEditUrl(clientId);
                }

                // المجموعات تبقى ظاهرة دائماً
                return;
            }

            // Show loading overlay
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
            }

            // تحديث العناصر المحددة في القائمة الجانبية
            document.querySelectorAll('.client-item').forEach(item => {
                item.classList.remove('selected');
                if (item.dataset.clientId == clientId) {
                    item.classList.add('selected');
                }
            });

            // إخفاء الرسالة الافتراضية
            const defaultMessage = document.getElementById('defaultMessage');
            if (defaultMessage) {
                defaultMessage.style.display = 'none';
            }

            // استدعاء AJAX لجلب بيانات العميل
            $.ajax({
                url: '/clients/get-client-details/' + clientId,
                method: 'GET',
                timeout: 25000, // 25 ثانية timeout
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response.success) {
                        // حفظ البيانات في الـ cache
                        clientsCache.set(clientId, {
                            data: response.client,
                            timestamp: Date.now()
                        });

                        // عرض بيانات العميل
                        displayClientDetails(response.client);
                    } else {
                        console.error('Response not successful:', response);
                        showErrorMessage('فشل تحميل بيانات العميل. الرجاء المحاولة مرة أخرى.');
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX Error:', {xhr, textStatus, errorThrown});
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }

                    let errorMsg = 'حدث خطأ أثناء تحميل بيانات العميل.';
                    if (textStatus === 'timeout') {
                        errorMsg = 'انتهت مهلة الطلب. الرجاء المحاولة مرة أخرى.';
                    } else if (xhr.status === 404) {
                        errorMsg = 'العميل غير موجود.';
                    } else if (xhr.status === 500) {
                        errorMsg = xhr.responseJSON?.error || 'خطأ في الخادم. الرجاء المحاولة لاحقاً.';
                    }

                    showErrorMessage(errorMsg);
                },
                complete: function() {
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }
                }
            });

            // تحديث زر التعديل
            const editButton = document.getElementById('editClientButton');
            if (editButton) {
                editButton.classList.remove('disabled');
                editButton.href = getClientEditUrl(clientId);
            }

            // المجموعات تبقى ظاهرة دائماً
        }

        // دالة عرض رسائل الأخطاء
        function showErrorMessage(message) {
            const clientDetailsContent = document.getElementById('clientDetailsContent');
            if (clientDetailsContent) {
                clientDetailsContent.innerHTML = `
                    <div class="rm-welcome-state rm-welcome-state--error">
                        <div class="rm-welcome-illustration">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h5>حدث خطأ</h5>
                        <p>${message}</p>
                        <button class="rm-btn rm-btn-primary mt-3" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> إعادة تحميل الصفحة
                        </button>
                    </div>
                `;
            }
        }

        // دالة لتوليد لون الأفاتار من الاسم
        function getAvatarClass(id) {
            const classes = ['avatar-c1','avatar-c2','avatar-c3','avatar-c4','avatar-c5','avatar-c6','avatar-c7','avatar-c8'];
            return classes[parseInt(id) % 8];
        }

        function displayClientDetails(clientData) {
            const clientDetailsContent = document.getElementById('clientDetailsContent');
            if (!clientDetailsContent) {
                console.error('clientDetailsContent element not found');
                return;
            }

            // إخفاء التحميل لضمان إمكانية التفاعل مع الصفحة
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }

            const clientId = clientData.id;
            const initials = (clientData.trade_name || clientData.full_name || '?').charAt(0).toUpperCase();
            const avatarClass = getAvatarClass(clientId);
            const balance = parseFloat(clientData.balance || 0);
            const balanceColor = balance >= 0 ? 'var(--success)' : 'var(--danger)';
            const invoiceCount = clientData.invoices ? clientData.invoices.length : 0;
            const notesCount = clientData.clientRelations ? clientData.clientRelations.length : 0;
            const attachCount = countAllAttachments(clientData.clientRelations || []);
            const paymentsCount = clientData.payments ? clientData.payments.length : 0;
            const visitsCount = clientData.visits ? clientData.visits.length : 0;
            const exchangesCount = clientData.productExchanges ? clientData.productExchanges.length : 0;
            const receivedCount = clientData.invoiceTransfers ? clientData.invoiceTransfers.filter(t => t.to_employee_id == currentUserId).length : 0;
            const sentCount = clientData.invoiceTransfers ? clientData.invoiceTransfers.filter(t => t.from_employee_id == currentUserId).length : 0;
            const perms = window.CRM_PERMS || {};
            // «آخر ملاحظة» في نظرة عامة تأخذ بطاقة الزيارة المدمجة (ملاحظة + فاتورة +
            // سند قبض + إشعار دائن في بطاقةٍ واحدة — نفس دمج تبويب «ملاحظات الزيارات»)
            // لا أوّل صفٍّ في clientRelations وحده، فقد يكون ملاحظةً تلقائية كتبها
            // مستندٌ صدر بعد الملاحظة اليدوية فيظهر مكرَّراً ومنفصلاً عنها.
            const leadNoteCards = (clientData.clientRelations || [])
                .filter(n => n.card)
                .sort((a, b) => a.card.order - b.card.order);
            const lastNote = leadNoteCards[0] || null;
            const lastVisit = (clientData.visits && clientData.visits[0]) || null;
            const lastInvoice = (clientData.invoices && clientData.invoices[0]) || null;
            const addNoteUrl = clientNoteCreateUrlTemplate ? clientNoteCreateUrlTemplate.replace('CLIENT_ID', clientId) : '#';

            requestAnimationFrame(() => {
                let html = `
                <div class="rm-client-details">

                    <!-- بطاقة البروفايل -->
                    <div class="rm-profile-card">
                        <div class="rm-profile-header">
                            <a href="${getClientShowUrl(clientId)}" target="_blank" title="عرض ملف العميل" class="rm-profile-avatar-lg ${avatarClass}" style="text-decoration: none; color: inherit;">${initials}</a>
                            <div class="rm-profile-info">
                                <div class="rm-profile-name-row">
                                    <span class="rm-profile-name">${clientData.trade_name || clientData.full_name || '--'}</span>
                                    ${clientData.status_name ? `<span class="rm-status-badge" style="background:${clientData.status_color || '#6c757d'}22; color:${clientData.status_color || '#6c757d'}; border:1px solid ${clientData.status_color || '#6c757d'}55;">${clientData.status_name}</span>` : ''}
                                </div>
                                <div class="rm-profile-meta">
                                    <span class="rm-profile-meta-item"><i class="fas fa-phone"></i> ${clientData.phone || '--'}</span>
                                    ${clientData.email ? `<span class="rm-profile-meta-item"><i class="fas fa-envelope"></i> ${clientData.email}</span>` : ''}
                                    ${clientData.branch_name ? `<span class="rm-profile-meta-item"><i class="fas fa-code-branch"></i> ${clientData.branch_name}</span>` : ''}
                                    ${clientData.employee_name ? `<span class="rm-profile-meta-item"><i class="fas fa-user-tie"></i> ${clientData.employee_name}</span>` : ''}
                                    ${clientData.code ? `<span class="rm-profile-code">#${clientData.code}</span>` : ''}
                                </div>
                            </div>
                            <div class="rm-profile-links">
                                <a href="${getClientShowUrl(clientId)}" id="showClientButton" class="rm-icon-btn" title="عرض العميل">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="${getClientEditUrl(clientId)}" id="editClientButton" class="rm-icon-btn" title="تعديل العميل">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>

                        <!-- الإجراءات السريعة -->
                        <div class="rm-quick-actions">
                            ${perms.canAddNote ? `
                                <a href="${addNoteUrl}" class="rm-qa-btn rm-qa-primary" title="إضافة ملاحظة متابعة">
                                    <i class="fas fa-sticky-note"></i> ملاحظة
                                </a>
                            ` : ''}
                            ${perms.canAddAppointment ? `
                                <button type="button" class="rm-qa-btn rm-qa-primary" id="addAppointmentBtn" title="إضافة موعد">
                                    <i class="fas fa-calendar-plus"></i> موعد
                                </button>
                            ` : ''}
                            ${perms.canSendNotification ? `
                                <button type="button" class="rm-qa-btn rm-qa-primary" id="sendNotificationBtn" title="إرسال إشعار">
                                    <i class="fas fa-bell"></i> إشعار
                                </button>
                            ` : ''}
                            ${perms.canAddItinerary ? `
                                <button type="button" class="rm-qa-btn rm-qa-primary" id="quickAddToItineraryBtn" title="إضافة سريعة لخط السير">
                                    <i class="fas fa-bolt"></i> خط سير
                                </button>
                            ` : ''}
                            ${perms.canAddItinerary ? `
                                <div class="rm-qa-more" id="crmMoreMenu">
                                    <button type="button" class="rm-qa-btn rm-qa-secondary" id="crmMoreToggle">
                                        <i class="fas fa-ellipsis-h"></i> المزيد
                                    </button>
                                    <ul class="rm-qa-more-menu">
                                        <li><a href="#" id="addToItineraryBtn"><i class="fas fa-route"></i> إضافة كاملة لخط السير</a></li>
                                    </ul>
                                </div>
                            ` : ''}
                        </div>

                        <!-- إحصائيات سريعة -->
                        <div class="rm-stats-row">
                            <div class="rm-stat-item">
                                <div class="rm-stat-icon rm-stat-icon-balance"><i class="fas fa-coins"></i></div>
                                <div class="rm-stat-value" style="color: ${balanceColor};">${balance.toFixed(2)}</div>
                                <div class="rm-stat-label">الرصيد (ر.س)</div>
                            </div>
                            <div class="rm-stat-item">
                                <div class="rm-stat-icon rm-stat-icon-invoice"><i class="fas fa-file-invoice"></i></div>
                                <div class="rm-stat-value text-primary">${invoiceCount}</div>
                                <div class="rm-stat-label">الفواتير</div>
                            </div>
                            <div class="rm-stat-item">
                                <div class="rm-stat-icon rm-stat-icon-payment"><i class="fas fa-credit-card"></i></div>
                                <div class="rm-stat-value">${paymentsCount}</div>
                                <div class="rm-stat-label">المدفوعات</div>
                            </div>
                            <div class="rm-stat-item">
                                <div class="rm-stat-icon rm-stat-icon-visit"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="rm-stat-value">${visitsCount}</div>
                                <div class="rm-stat-label">الزيارات</div>
                            </div>
                        </div>
                    </div>

                    <!-- بطاقة التبويبات -->
                    <div class="rm-tabs-card">
                        <!-- شريط التبويبات -->
                        <div class="rm-nav-tabs" role="tablist">
                            <button class="rm-nav-tab active" data-tab-target="overview-${clientId}">
                                <i class="fas fa-th-large"></i> نظرة عامة
                            </button>
                            <button class="rm-nav-tab" data-tab-target="activity-${clientId}">
                                <i class="fas fa-stream"></i> النشاط
                                <span class="tab-badge">${notesCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="notes-${clientId}">
                                <i class="fas fa-clipboard-list"></i> ملاحظات الزيارات
                                <span class="tab-badge">${notesCount}</span>
                            </button>
                            ${perms.canViewAppointments ? `
                                <button class="rm-nav-tab" data-tab-target="appointments-${clientId}" data-tab-appointments="1">
                                    <i class="fas fa-calendar-check"></i> المواعيد
                                    <span class="tab-badge" id="appointmentsTabBadge-${clientId}">…</span>
                                </button>
                            ` : ''}
                            <button class="rm-nav-tab" data-tab-target="invoices-${clientId}">
                                <i class="fas fa-file-invoice"></i> الفواتير
                                <span class="tab-badge">${invoiceCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="gallery-${clientId}">
                                <i class="fas fa-images"></i> المرفقات
                                <span class="tab-badge">${attachCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="payments-${clientId}">
                                <i class="fas fa-credit-card"></i> المدفوعات
                                <span class="tab-badge">${paymentsCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="visits-${clientId}">
                                <i class="fas fa-map-marker-alt"></i> الزيارات
                                <span class="tab-badge">${visitsCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="exchanges-${clientId}">
                                <i class="fas fa-exchange-alt"></i> التبديلات
                                <span class="tab-badge">${exchangesCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="received-${clientId}">
                                <i class="fas fa-inbox"></i> الاستلام
                                <span class="tab-badge">${receivedCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="delivered-${clientId}">
                                <i class="fas fa-paper-plane"></i> التسليم
                                <span class="tab-badge">${sentCount}</span>
                            </button>
                            <button class="rm-nav-tab" data-tab-target="statement-${clientId}">
                                <i class="fas fa-chart-line"></i> كشف الحساب
                            </button>
                        </div>

                        <!-- محتوى التبويبات -->
                        <div class="rm-tab-content">
                            <div class="rm-tab-pane active" id="overview-${clientId}">
                                ${generateOverviewHTML(clientData, {
                                    balance, balanceColor, invoiceCount, notesCount, visitsCount, paymentsCount,
                                    lastNote, lastVisit, lastInvoice,
                                })}
                            </div>
                            <div class="rm-tab-pane" id="activity-${clientId}">
                                ${generateActivityTimelineHTML(clientData.clientRelations || [])}
                            </div>
                            <div class="rm-tab-pane" id="notes-${clientId}">
                                ${generateNotesHTML(clientData.clientRelations || [], clientData.competitorObservations || [])}
                            </div>
                            ${perms.canViewAppointments ? `
                                <div class="rm-tab-pane" id="appointments-${clientId}" data-client-id="${clientId}">
                                    <div class="rm-empty-state" id="appointmentsPlaceholder-${clientId}">
                                        <div class="rm-empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                                        <h6>جاري التحميل...</h6>
                                    </div>
                                </div>
                            ` : ''}
                            <div class="rm-tab-pane" id="invoices-${clientId}">
                                ${generateInvoicesHTML(clientData.invoices || [])}
                            </div>
                            <div class="rm-tab-pane" id="gallery-${clientId}">
                                ${generateGalleryHTML(clientData.clientRelations || [])}
                            </div>
                            <div class="rm-tab-pane" id="payments-${clientId}">
                                ${generatePaymentsHTML(clientData.payments || [])}
                            </div>
                            <div class="rm-tab-pane" id="visits-${clientId}">
                                ${generateVisitsHTML(clientData.visits || [])}
                            </div>
                            <div class="rm-tab-pane" id="exchanges-${clientId}">
                                ${generateProductExchangesHTML(clientData.productExchanges || [])}
                            </div>
                            <div class="rm-tab-pane" id="received-${clientId}">
                                ${generateReceivedTransfersHTML(clientData.invoiceTransfers || [])}
                            </div>
                            <div class="rm-tab-pane" id="delivered-${clientId}">
                                ${generateSentTransfersHTML(clientData.invoiceTransfers || [])}
                            </div>
                            <div class="rm-tab-pane" id="statement-${clientId}">
                                <div id="statement-${clientId}-loading" class="rm-statement-loading">
                                    <div class="rm-spinner"></div>
                                    <span style="font-size:0.82rem;">جاري تحميل كشف الحساب...</span>
                                </div>
                                <div id="statement-${clientId}-content"></div>
                            </div>
                        </div>
                    </div>

                </div>
            `;

                clientDetailsContent.innerHTML = html;

                // ربط التبويبات الجديدة
                clientDetailsContent.querySelectorAll('.rm-nav-tab').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const target = this.dataset.tabTarget;
                        clientDetailsContent.querySelectorAll('.rm-tab-pane').forEach(p => p.classList.remove('active'));
                        clientDetailsContent.querySelectorAll('.rm-nav-tab').forEach(b => b.classList.remove('active'));
                        const pane = clientDetailsContent.querySelector('#' + target);
                        if (pane) pane.classList.add('active');
                        this.classList.add('active');
                        // تحميل كشف الحساب عند الطلب
                        if (target.startsWith('statement-')) {
                            const cid = target.replace('statement-', '');
                            loadClientStatement(cid);
                        }
                        // تحميل المواعيد عند فتح تبويبها لأول مرة فقط
                        if (this.dataset.tabAppointments && !this.dataset.appointmentsLoaded) {
                            this.dataset.appointmentsLoaded = '1';
                            loadAppointmentsTab(clientId);
                        }
                    });
                });

                // زر "موعد" السريع يفتح نافذة إضافة موعد
                const addAppointmentBtn = clientDetailsContent.querySelector('#addAppointmentBtn');
                if (addAppointmentBtn) {
                    addAppointmentBtn.addEventListener('click', function() {
                        if (typeof showAddAppointmentModal === 'function') {
                            showAddAppointmentModal(clientId, clientData.trade_name || clientData.full_name || '');
                        }
                    });
                }

                /* زرّا خط السير (`#addToItineraryBtn` و
                   `#quickAddToItineraryBtn`) يُعالَجان بالتفويض في
                   `crm_shared_scripts_modals.blade.php` — راجع التعليق هناك.
                   كان الربطُ هنا يتكرّر مع كل رسمٍ لبطاقة العميل. */
            });
        }

        // توليد تبويب «نظرة عامة» — من البيانات المجلوبة أصلاً، بلا طلب إضافي
        function generateOverviewHTML(clientData, stats) {
            const fmtDate = (d) => d ? formatDate(d) : '—';
            // بطاقة الزيارة المدمجة لآخر ملاحظة: نفس الملاحظة والمستندات (فاتورة/سند
            // قبض/إشعار دائن) المعروضة مجموعةً في تبويب «ملاحظات الزيارات»
            const lastNoteCard = stats.lastNote ? stats.lastNote.card : null;
            const lastNoteText = lastNoteCard
                ? [lastNoteCard.date_label, lastNoteCard.time_label].filter(Boolean).join(' ')
                    + ' — ' + (lastNoteCard.description || 'لا يوجد وصف')
                    + (lastNoteCard.documents && lastNoteCard.documents.length ? ` · ${lastNoteCard.documents.length} مستند` : '')
                : 'لا توجد ملاحظات بعد';
            const lastVisitText = stats.lastVisit ? fmtDate(stats.lastVisit.visit_date) + ' — ' + (stats.lastVisit.employee || '') : 'لا توجد زيارات بعد';
            const lastInvoiceText = stats.lastInvoice
                ? fmtDate(stats.lastInvoice.date) + ' — #' + (stats.lastInvoice.number || stats.lastInvoice.id) + ' — ' + parseFloat(stats.lastInvoice.amount || 0).toFixed(2) + ' ر.س'
                : 'لا توجد فواتير بعد';

            return `
                <div class="rm-overview-grid">
                    <div class="rm-overview-card">
                        <div class="rm-overview-card-icon" style="color:${stats.balanceColor};"><i class="fas fa-coins"></i></div>
                        <div class="rm-overview-card-body">
                            <div class="rm-overview-card-title">الرصيد الحالي</div>
                            <div class="rm-overview-card-value" style="color:${stats.balanceColor};">${stats.balance.toFixed(2)} ر.س</div>
                        </div>
                    </div>
                    <div class="rm-overview-card">
                        <div class="rm-overview-card-icon"><i class="fas fa-sticky-note"></i></div>
                        <div class="rm-overview-card-body">
                            <div class="rm-overview-card-title">آخر ملاحظة</div>
                            <div class="rm-overview-card-value rm-overview-card-value--sm">${lastNoteText}</div>
                        </div>
                    </div>
                    <div class="rm-overview-card">
                        <div class="rm-overview-card-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="rm-overview-card-body">
                            <div class="rm-overview-card-title">آخر زيارة</div>
                            <div class="rm-overview-card-value rm-overview-card-value--sm">${lastVisitText}</div>
                        </div>
                    </div>
                    <div class="rm-overview-card">
                        <div class="rm-overview-card-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="rm-overview-card-body">
                            <div class="rm-overview-card-title">آخر فاتورة</div>
                            <div class="rm-overview-card-value rm-overview-card-value--sm">${lastInvoiceText}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        // توليد الخط الزمني للنشاط — من نفس ملاحظات المتابعة (clientRelations)
        function generateActivityTimelineHTML(notes) {
            if (!notes || notes.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-stream"></i></div>
                    <h6>لا يوجد نشاط بعد</h6>
                    <p>ستظهر هنا الملاحظات والإجراءات المسجَّلة على هذا العميل</p>
                </div>`;
            }

            let html = '<div class="rm-timeline">';
            notes.forEach(note => {
                html += `
                    <div class="rm-timeline-item">
                        <div class="rm-timeline-dot"></div>
                        <div class="rm-timeline-content">
                            <div class="rm-timeline-header">
                                <strong>${note.employee || 'غير معروف'}</strong>
                                <span class="rm-timeline-date">${formatDate(note.created_at)}</span>
                            </div>
                            ${note.process ? `<span class="badge bg-primary mb-1">${note.process}</span>` : ''}
                            <p class="mb-0">${(note.description || '').substring(0, 160)}</p>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }

        // توليد HTML الفواتير
        function generateInvoicesHTML(invoices) {
            if (invoices.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-file-invoice"></i></div>
                    <h6>لا توجد فواتير</h6><p>لم يتم إصدار أي فاتورة لهذا العميل</p>
                </div>`;
            }
            let html = `<div class="rm-table-wrap"><table class="rm-table">
                <thead><tr>
                    <th>رقم الفاتورة</th><th>التاريخ</th><th>المبلغ</th><th>الحالة</th>
                </tr></thead><tbody>`;
            invoices.forEach(invoice => {
                html += `<tr>
                    <td><strong style="color:var(--primary);">#${invoice.number || invoice.id}</strong></td>
                    <td style="color:var(--text-secondary);">${invoice.date || '—'}</td>
                    <td><strong>${parseFloat(invoice.amount || 0).toFixed(2)}</strong> <small style="color:var(--text-muted);">ر.س</small></td>
                    <td><span class="rm-badge rm-badge-${invoice.status_class === 'success' ? 'success' : invoice.status_class === 'danger' ? 'danger' : 'secondary'}">${invoice.status_text || '—'}</span></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        // توليد HTML الملاحظات
        // دالة للانتقال إلى تبويبة المرفقات عند الضغط على صورة من الملاحظات
        // دالة لفتح المرفق في نافذة جديدة
        window.openAttachmentWindow = function(fileUrl, fileName) {
            // فتح النافذة الجديدة
            const windowWidth = window.innerWidth * 0.8;
            const windowHeight = window.innerHeight * 0.8;
            const left = (window.innerWidth - windowWidth) / 2;
            const top = (window.innerHeight - windowHeight) / 2;

            // محتوى النافذة
            const content = `
                <!DOCTYPE html>
                <html dir="rtl" lang="ar">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>${fileName}</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        * { margin: 0; padding: 0; }
                        body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
                        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); max-width: 90vw; max-height: 90vh; display: flex; flex-direction: column; }
                        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #dee2e6; padding-bottom: 10px; }
                        .header h4 { margin: 0; color: #333; }
                        .btn-close { cursor: pointer; font-size: 24px; color: #666; }
                        .content { flex: 1; display: flex; align-items: center; justify-content: center; overflow: auto; }
                        img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 4px; }
                        .btn-group { margin-top: 15px; text-align: center; }
                        .btn { margin: 0 5px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h4>${fileName}</h4>
                            <span class="btn-close" onclick="window.close();">&times;</span>
                        </div>
                        <div class="content">
                            <img src="${fileUrl}" alt="${fileName}" />
                        </div>
                        <div class="btn-group">
                            <a href="${fileUrl}" download="${fileName}" class="btn btn-primary btn-sm">
                                <i class="fa fa-download"></i> تحميل
                            </a>
                            <button class="btn btn-secondary btn-sm" onclick="window.close();">
                                <i class="fa fa-times"></i> إغلاق
                            </button>
                        </div>
                    </div>
                </body>
                </html>
            `;

            // فتح النافذة
            const newWindow = window.open('', '_blank', `width=${Math.round(windowWidth)},height=${Math.round(windowHeight)},left=${Math.round(left)},top=${Math.round(top)},resizable=yes,scrollbars=yes`);
            newWindow.document.write(content);
            newWindow.document.close();
        }

        function switchToGalleryTab(clientId, imageFile) {
            // إذا لم يتم تمرير clientId، ندعه فارغاً
            if (!clientId) {
                // البحث عن أقرب clientId من العنصر الذي تم النقر عليه
                const clientSection = event?.target?.closest('[id*="-client-details"]');
                if (clientSection) {
                    clientId = clientSection.id.match(/\d+/)[0];
                }
            }

            if (!clientId) return;

            // البحث عن الزر الخاص بتبويبة Gallery والنقر عليه
            const galleryTabButton = document.querySelector(`[data-bs-target="#gallery-${clientId}"]`);
            if (galleryTabButton) {
                // النقر على الزر لتبديل التبويبة
                const tab = new bootstrap.Tab(galleryTabButton);
                tab.show();

                // بعد التبديل إلى التبويبة، نبحث عن الصورة ونركز عليها
                setTimeout(() => {
                    const galleryContent = document.querySelector(`#gallery-${clientId}`);
                    if (galleryContent) {
                        const allImages = galleryContent.querySelectorAll('.gallery-image');
                        // البحث عن الصورة بناءً على اسم الملف
                        let targetImage = null;
                        allImages.forEach(img => {
                            if (img.alt.includes(imageFile) || img.alt === imageFile) {
                                targetImage = img;
                            }
                        });

                        if (targetImage) {
                            // تركيز على الصورة مع تأثير بصري (بدون auto scroll)
                            targetImage.parentElement.style.transform = 'scale(1.1)';
                            targetImage.parentElement.style.boxShadow = '0 0 20px rgba(0, 102, 204, 0.6)';

                            setTimeout(() => {
                                targetImage.parentElement.style.transform = 'scale(1)';
                                targetImage.parentElement.style.boxShadow = '';
                            }, 2000);
                        }
                    }
                }, 300);
            }
        }

        // دالة لحساب عدد المرفقات
        function countAllAttachments(notes) {
            let count = 0;
            notes.forEach(note => {
                if (note.attachments_array && note.attachments_array.length > 0) {
                    count += note.attachments_array.length;
                }
            });
            return count;
        }

        // دالة لإنشاء معرض الصور
        function generateGalleryHTML(notes) {
            let images = [];

            // جمع كل الصور من جميع الملاحظات
            notes.forEach(note => {
                if (note.attachments_array && note.attachments_array.length > 0) {
                    note.attachments_array.forEach(file => {
                        const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                        const ext = file.split('.').pop().toLowerCase();

                        if (imageExts.includes(ext)) {
                            images.push({
                                file: file,
                                noteId: note.id,
                                noteName: note.name || 'ملاحظة'
                            });
                        }
                    });
                }
            });

            if (images.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-images"></i></div>
                    <h6>لا توجد صور</h6>
                    <p>لم يتم إرفاق أي صور بالملاحظات</p>
                </div>`;
            }

            let html = '<div class="rm-gallery-grid">';
            images.forEach((img) => {
                const fileUrl = '/assets/uploads/notes/' + img.file;
                html += `
                    <div class="rm-gallery-item" onclick="openAttachmentWindow('${fileUrl}', '${img.file}')">
                        <img src="${fileUrl}" alt="${img.file}">
                        <div class="rm-gallery-overlay">
                            <i class="fas fa-expand-alt" style="font-size:1.1rem;"></i>
                            <a href="${fileUrl}" download="${img.file}" onclick="event.stopPropagation();">
                                <i class="fas fa-download" style="font-size:1rem;"></i>
                            </a>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }

        // «ملاحظات الزيارات» — الملاحظة ورصد المنافسين في تبويبٍ واحد، ثلاثُ بطاقاتٍ في
        // الصف. نظيرها في شاشة العميل `partials/tabs/visit-note-card.blade.php`
        // والتنسيق المشترك `public/assets/css/visit_note_cards.css` — غيّر الاثنين معاً.
        // بيانات كل بطاقة `note.card` من `App\Services\Clients\VisitNoteCards`.
        function generateNotesHTML(notes, observations) {
            const esc = (v) => escapeExchangeHtml(v === null || v === undefined ? '' : String(v));
            const money = (v) => parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            observations = observations || [];

            // الوضع الحالي: آخر رصدٍ لكل منافس (القائمة مرتّبة الأحدث أولاً)
            const seen = new Set();
            const competitorsNow = observations.filter(o => o.name && !seen.has(o.name) && seen.add(o.name));

            const head = `
                <div class="vn-section-head">
                    <div class="vn-section-title">
                        <span class="vn-section-icon"><i class="fas fa-clipboard-list"></i></span>
                        <div>ملاحظات الزيارات<span class="vn-section-count">${notes.length} ملاحظة · ${observations.length} رصد منافسين</span></div>
                    </div>
                </div>
                ${competitorsNow.length ? `
                    <div class="vn-comp-strip">
                        <span class="vn-comp-strip-label"><i class="fas fa-crosshairs me-1"></i>المنافسون عند العميل الآن:</span>
                        ${competitorsNow.map(o => `<span class="vn-chip">${esc(o.name)} · عهدة <b>${esc(o.asset_count ?? 0)}</b></span>`).join('')}
                    </div>` : ''}`;

            if (!notes.length) {
                return `<div class="vn-wrap">${head}
                    <div class="rm-empty-state">
                        <div class="rm-empty-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h6>لا توجد ملاحظات</h6><p>الملاحظة ورصد المنافسين يُسجَّلان من زيارة العميل</p>
                    </div>
                </div>`;
            }

            const perms = window.CRM_PERMS || {};
            const tones = ['success', 'primary', 'warning'];
            // بطاقةٌ لكل زيارة: الملاحظة القائدة وحدها تحمل `card`، وملاحظات
            // مستنداتها مدمجةٌ فيها — بترتيب البطاقات لا بترتيب الملاحظات
            notes = notes.filter(n => n.card).sort((a, b) => a.card.order - b.card.order);
            const cards = notes.map((note, i) => {
                const c = note.card || {};
                const docs = c.documents || [];
                const comps = c.competitors || [];
                const custody = c.custody || {};
                const files = c.files || [];
                const attachCount = (c.images || []).length + files.length;
                const employee = c.employee || note.employee || 'غير معروف';
                const description = c.description || note.description || '';
                const canEdit = perms.canEditAllNotes || (perms.canEditOwnNotes && note.employee_id == currentUserId);

                const docHTML = (docs.length
                        ? docs.slice(0, 3).map(doc => `
                            <div class="vn-doc">
                                <div class="vn-doc-kind"><i class="fas ${esc(doc.icon)}"></i> ${esc(doc.kind)}</div>
                                <a class="vn-doc-no" href="${esc(doc.url)}" target="_blank" rel="noopener"><bdi dir="ltr">#${esc(doc.number)}</bdi></a>
                                <div class="vn-amount vn-amount-${esc(doc.tone)}"><bdi dir="ltr">${money(doc.amount)}</bdi> <small>ر.س</small></div>
                            </div>
                        `).join('')
                        : '<div class="vn-empty">لم يتم إنشاء مستند</div>')
                    + (docs.length > 3 ? `<span class="vn-more">+${docs.length - 3} مستند آخر في الزيارة</span>` : '');

                // كلُّ منافسٍ بعهدته وصورته — لا «+N منافس» ولا صورٌ مخفيّة
                const compHTML = comps.length
                    ? comps.map(x => `
                        <div class="vn-comp-item">
                            ${x.photo_url
                                ? `<a href="${esc(x.photo_url)}" target="_blank" rel="noopener"><img class="vn-comp-thumb" src="${esc(x.photo_url)}" alt="" loading="lazy"></a>`
                                : '<span class="vn-comp-thumb vn-comp-thumb-empty"><i class="fas fa-image"></i></span>'}
                            <div class="vn-comp-body">
                                <div class="vn-line"><span title="${esc(x.name)}">${esc(x.name)}</span></div>
                                <div class="vn-count"><b>${esc(x.asset_count)}</b> ${esc(x.asset_label)}</div>
                            </div>
                        </div>
                    `).join('')
                    // «لا يوجد منافس» مع صورة إثباته إن صوّرها المندوب
                    : `<div class="vn-empty">${c.no_competitor ? 'لا يوجد منافس' : 'لم يُرصد'}</div>`
                        + (c.no_competitor_photos || []).map(url =>
                            `<a href="${esc(url)}" target="_blank" rel="noopener"><img class="vn-comp-thumb" src="${esc(url)}" alt="" loading="lazy"></a>`
                        ).join('');

                // صور الملاحظة كلُّها: الأولى كبيرة والباقي مصغّرات تحتها
                const noteImages = c.images || [];
                const custodyHTML = (custody.count !== null && custody.count !== undefined
                        ? `<div class="vn-count"><b>${esc(custody.count)}</b> ${esc(custody.label)}</div>`
                        : '<div class="vn-empty">—</div>')
                    + (noteImages.length
                        ? `<a href="${esc(noteImages[0])}" target="_blank" rel="noopener"><img class="vn-photo" src="${esc(noteImages[0])}" alt="" loading="lazy"></a>`
                        : '')
                    + (noteImages.length > 1
                        ? `<div class="vn-thumbs">${noteImages.slice(1).map(u => `<a href="${esc(u)}" target="_blank" rel="noopener"><img class="vn-thumb" src="${esc(u)}" alt="" loading="lazy"></a>`).join('')}</div>`
                        : '');

                return `
                <article class="vn-card">
                    <header class="vn-head">
                        <span class="vn-num vn-num-${tones[i % 3]}">${i + 1}</span>
                        <div class="vn-who">
                            <div class="vn-title">
                                <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                <span title="${esc(employee)}">${esc(employee)}</span>
                            </div>
                            <div class="vn-sub">
                                ${c.process ? `<span><i class="fas fa-tag"></i>${esc(c.process)}</span>` : ''}
                                ${c.site_type ? `<span><i class="fas fa-store"></i>${esc(c.site_type)}</span>` : ''}
                                ${c.visit && c.visit.number ? `<span><i class="fas fa-hashtag"></i><bdi>${esc(c.visit.number)}</bdi></span>` : ''}
                                ${c.entries > 1 ? `<span title="ملاحظة الزيارة وملاحظات مستنداتها في بطاقةٍ واحدة"><i class="fas fa-layer-group"></i>ملاحظات مدمجة: ${esc(c.entries)}</span>` : ''}
                            </div>
                        </div>
                        <div class="vn-when">
                            ${c.date_label ? `<span><i class="fas fa-calendar-alt"></i>${esc(c.date_label)}</span>` : ''}
                            ${c.time_label ? `<span><i class="fas fa-clock"></i>${esc(c.time_label)}</span>` : ''}
                            ${c.visit ? `<span class="vn-status vn-status-${esc(c.visit.tone)}"><i class="fas ${esc(c.visit.icon)}"></i>${esc(c.visit.label)}</span>` : ''}
                        </div>
                    </header>

                    <div class="vn-note" title="${esc(description)}">
                        <p><span class="vn-note-label">ملاحظة:</span> ${esc(description || 'لا يوجد وصف')}</p>
                        <span class="vn-note-icon"><i class="fas fa-file-alt"></i></span>
                    </div>

                    <div class="vn-facts">
                        <div class="vn-fact"><div class="vn-fact-label"><i class="fas fa-file-invoice vn-ic-doc"></i> المستند</div>${docHTML}</div>
                        <div class="vn-fact"><div class="vn-fact-label"><i class="fas fa-chart-bar vn-ic-comp"></i> المنافس</div>${compHTML}</div>
                        <div class="vn-fact"><div class="vn-fact-label"><i class="fas fa-cube vn-ic-custody"></i> العهدة</div>${custodyHTML}</div>
                    </div>

                    <footer class="vn-foot">
                        <span class="vn-foot-meta">${attachCount ? `<i class="fas fa-paperclip"></i> مرفقات: ${attachCount}` : ''}</span>
                        <div class="vn-actions">
                            ${files.map(f => `<a class="vn-act" href="${esc(f.url)}" target="_blank" rel="noopener" title="${esc(f.name)}"><i class="fas fa-file-download"></i></a>`).join('')}
                            <a class="vn-act" href="${clientNotePrintUrlTemplate.replace('NOTE_ID', note.id)}" target="_blank" rel="noopener" title="طباعة"><i class="fas fa-print"></i></a>
                            ${canEdit ? `
                                <a class="vn-act" href="${clientNoteEditUrlTemplate.replace('NOTE_ID', note.id)}" title="تعديل"><i class="fas fa-edit"></i></a>
                                <button type="button" class="vn-act vn-act-danger" onclick="deleteNoteConfirm(${note.id}, ${note.client_id})" title="حذف"><i class="fas fa-trash"></i></button>
                            ` : ''}
                        </div>
                    </footer>
                </article>`;
            }).join('');

            return `<div class="vn-wrap">${head}<div class="vn-grid">${cards}</div></div>`;
        }

        // حذف ملاحظة — clients.notes.destroy الحقيقي (فرع JSON من المرحلة الأولى)
        window.deleteNoteConfirm = function(noteId, clientId) {
            Swal.fire({
                title: 'حذف الملاحظة؟',
                text: 'لا يمكن التراجع عن هذا الإجراء.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: `/clients/notes/${noteId}`,
                    type: 'DELETE',
                    headers: { 'Accept': 'application/json' },
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: response.success ? 'success' : 'error',
                            title: response.message || (response.success ? 'تم حذف الملاحظة' : 'تعذّر الحذف'),
                            showConfirmButton: false, timer: 3000, timerProgressBar: true
                        });
                        if (response.success) {
                            clientsCache.delete(parseInt(clientId));
                            selectClient(clientId);
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'خطأ', text: xhr.responseJSON?.message || 'تعذّر حذف الملاحظة' });
                    }
                });
            });
        };

        // دالة لترجمة نوع الموقع
        function translateSiteType(type) {
            const types = {
                'independent_booth': 'بسطة مستقلة',
                'grocery': 'بقالة',
                'supplies': 'تموينات',
                'markets': 'أسواق',
                'station': 'محطة'
            };
            return types[type] || type;
        }

        // دالة لتنسيق التاريخ
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('ar-SA') + ' ' + date.toLocaleTimeString('ar-SA', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // توليد HTML المدفوعات
        function generatePaymentsHTML(payments) {
            if (payments.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-credit-card"></i></div>
                    <h6>لا توجد مدفوعات</h6><p>لم يتم تسجيل أي مدفوعات</p>
                </div>`;
            }
            let html = `<div class="rm-table-wrap"><table class="rm-table">
                <thead><tr><th>التاريخ</th><th>النوع</th><th>المرجع</th><th>المبلغ</th></tr></thead><tbody>`;
            payments.forEach(payment => {
                const docType = payment.document_type || payment.payment_method || '—';
                const amountColor = docType === 'سند صرف' ? 'var(--danger)' : (docType === 'إشعار دائن' ? 'var(--warning)' : 'var(--success)');
                html += `<tr>
                    <td style="color:var(--text-secondary);">${payment.payment_date || '—'}</td>
                    <td><span class="badge bg-light text-dark">${docType}</span></td>
                    <td style="color:var(--text-secondary);">${payment.reference || '—'}</td>
                    <td><strong style="color:${amountColor};">${parseFloat(payment.amount || 0).toFixed(2)}</strong> <small style="color:var(--text-muted);">ر.س</small></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        // توليد HTML الزيارات
        function generateVisitsHTML(visits) {
            if (visits.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <h6>لا توجد زيارات</h6><p>لم يتم تسجيل أي زيارات</p>
                </div>`;
            }
            let html = `<div class="rm-table-wrap"><table class="rm-table">
                <thead><tr><th>تاريخ الزيارة</th><th>الموظف</th></tr></thead><tbody>`;
            visits.forEach(visit => {
                html += `<tr>
                    <td style="color:var(--text-secondary);">${visit.visit_date || '—'}</td>
                    <td>${visit.employee || '—'}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        function escapeExchangeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // توليد HTML التبديلات (صورة + كميات المنتجات)
        function generateProductExchangesHTML(exchanges) {
            if (!exchanges || exchanges.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-exchange-alt"></i></div>
                    <h6>لا توجد تبديلات</h6><p>لم يتم تسجيل أي تبديلات</p>
                </div>`;
            }

            let html = '<div class="rm-exchanges-list">';
            exchanges.forEach(exchange => {
                const dateRaw = exchange.exchange_date || exchange.created_at;
                const dateStr = dateRaw ? new Date(dateRaw).toLocaleDateString('ar-SA') : '-';
                const statusText = exchange.status_text || 'غير معروف';
                const statusClass = exchange.status === 1 ? 'bg-success' : (exchange.status === 2 ? 'bg-danger' : 'bg-warning text-dark');
                const totalQty = exchange.total_quantity ?? (
                    (exchange.items || []).reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0)
                );

                const imageHtml = exchange.image_url
                    ? `<a href="${escapeExchangeHtml(exchange.image_url)}" target="_blank" class="rm-exchange-thumb" title="عرض الصورة">
                            <img src="${escapeExchangeHtml(exchange.image_url)}" alt="صورة التبديل">
                       </a>`
                    : `<div class="rm-exchange-thumb rm-exchange-thumb--empty" title="لا توجد صورة">
                            <i class="fas fa-image"></i>
                       </div>`;

                const items = exchange.items || [];
                let itemsHtml = '';
                if (items.length > 0) {
                    itemsHtml = '<div class="rm-exchange-items">' + items.map(item => {
                        const name = escapeExchangeHtml(item.product_name || 'منتج غير معروف');
                        const qty = parseInt(item.quantity, 10) || 0;
                        return `<span class="rm-exchange-item-chip">${name} <strong>× ${qty}</strong></span>`;
                    }).join('') + '</div>';
                } else {
                    itemsHtml = `<div class="text-muted small mt-1">عدد الأصناف: ${exchange.items_count ?? 0}</div>`;
                }

                html += `
                    <div class="rm-exchange-card">
                        ${imageHtml}
                        <div class="rm-exchange-body">
                            <div class="rm-exchange-meta">
                                <strong>${escapeExchangeHtml(exchange.reference_no || exchange.code || exchange.id)}</strong>
                                <span class="text-muted"><i class="fas fa-calendar-alt"></i> ${dateStr}</span>
                                <span class="text-muted"><i class="fas fa-warehouse"></i> ${escapeExchangeHtml(exchange.storehouse || '--')}</span>
                                <span class="badge bg-primary">إجمالي الكمية: ${totalQty}</span>
                                <span class="badge ${statusClass}">${escapeExchangeHtml(statusText)}</span>
                            </div>
                            ${itemsHtml}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }

        // توليد HTML الاستلام (وارد)
        function generateReceivedTransfersHTML(transfers) {
            const received = transfers.filter(t => t.to_employee_id == currentUserId);

            if (received.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-inbox"></i></div>
                    <h6>لا توجد استلامات واردة</h6><p>لم يتم استلام أي فواتير</p>
                </div>`;
            }
            let html = `<div class="rm-table-wrap"><table class="rm-table">
                <thead><tr><th>رقم الفاتورة</th><th>المبلغ</th><th>من الموظف</th><th>التاريخ</th><th>الحالة</th></tr></thead><tbody>`;
            received.forEach(t => {
                html += `<tr>
                    <td><strong style="color:var(--primary);">${t.invoice_code}</strong></td>
                    <td>${parseFloat(t.invoice_amount || 0).toFixed(2)} <small style="color:var(--text-muted);">ر.س</small></td>
                    <td>${t.from_employee}</td>
                    <td style="color:var(--text-secondary);">${t.created_at ? new Date(t.created_at).toLocaleDateString('ar-SA') : '—'}</td>
                    <td><span class="rm-badge rm-badge-primary">${t.status_text}</span></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        // توليد HTML التسليم (صادر)
        function generateSentTransfersHTML(transfers) {
            const sent = transfers.filter(t => t.from_employee_id == currentUserId);

            if (sent.length === 0) {
                return `<div class="rm-empty-state">
                    <div class="rm-empty-icon"><i class="fas fa-paper-plane"></i></div>
                    <h6>لا توجد تسليمات صادرة</h6><p>لم يتم تسليم أي فواتير</p>
                </div>`;
            }
            let html = `<div class="rm-table-wrap"><table class="rm-table">
                <thead><tr><th>رقم الفاتورة</th><th>المبلغ</th><th>إلى الموظف</th><th>التاريخ</th><th>الحالة</th></tr></thead><tbody>`;
            sent.forEach(t => {
                html += `<tr>
                    <td><strong style="color:var(--primary);">${t.invoice_code}</strong></td>
                    <td>${parseFloat(t.invoice_amount || 0).toFixed(2)} <small style="color:var(--text-muted);">ر.س</small></td>
                    <td>${t.to_employee}</td>
                    <td style="color:var(--text-secondary);">${t.created_at ? new Date(t.created_at).toLocaleDateString('ar-SA') : '—'}</td>
                    <td><span class="rm-badge rm-badge-info">${t.status_text}</span></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        // دالة تحميل كشف الحساب
        function loadClientStatement(clientId) {
            const loadingDiv = document.getElementById(`statement-${clientId}-loading`);
            const contentDiv = document.getElementById(`statement-${clientId}-content`);

            if (!contentDiv) return;

            // إظهار التحميل
            if (loadingDiv) loadingDiv.style.display = 'block';
            contentDiv.innerHTML = '';

            $.ajax({
                url: `{{ route('clients.statement', ':id') }}`.replace(':id', clientId),
                method: 'GET',
                timeout: 30000,
                success: function(html) {
                    if (loadingDiv) loadingDiv.style.display = 'none';

                    // استخراج جزء المحتوى الرئيسي من الصفحة الكاملة
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    // البحث عن محتوى كشف الحساب - نبحث عن الـ content-body أو print-section
                    let statementContent = null;

                    // محاولة 1: البحث عن content-body
                    const contentBody = tempDiv.querySelector('.content-body');
                    if (contentBody) {
                        statementContent = contentBody;
                    }

                    // محاولة 2: البحث عن print-section
                    const printSection = tempDiv.querySelector('#print-section');
                    if (printSection) {
                        statementContent = printSection;
                    }

                    // محاولة 3: البحث عن tab-content
                    const tabContent = tempDiv.querySelector('.tab-content');
                    if (tabContent) {
                        statementContent = tabContent;
                    }

                    // إضافة المحتوى
                    if (statementContent) {
                        contentDiv.innerHTML = statementContent.outerHTML;
                    } else {
                        // إذا لم نجد المحتوى، نضع الـ HTML كاملة
                        contentDiv.innerHTML = html;
                    }

                    console.log('Statement loaded successfully');
                },
                error: function(xhr, textStatus, errorThrown) {
                    if (loadingDiv) loadingDiv.style.display = 'none';
                    let errorMsg = 'حدث خطأ أثناء تحميل كشف الحساب';
                    if (textStatus === 'timeout') {
                        errorMsg = 'انتهت مهلة الطلب. الرجاء المحاولة مرة أخرى.';
                    } else if (xhr.status === 404) {
                        errorMsg = 'كشف الحساب غير متوفر.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'خطأ في الخادم. الرجاء التحقق من بيانات العميل.';
                    }
                    contentDiv.innerHTML = `<div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle"></i> ${errorMsg}</div>`;
                    console.error('Statement load error:', textStatus, errorThrown, xhr.responseText);
                }
            });
        }

        // تحميل تبويب المواعيد — يعيد استخدام appointments.index الحقيقي
        // (نفس الصلاحيات ونطاقات all/assigned/own المطبَّقة هناك)
        function loadAppointmentsTab(clientId) {
            const pane = document.getElementById(`appointments-${clientId}`);
            if (!pane) return;

            $.ajax({
                url: '{{ route("appointments.index") }}',
                method: 'GET',
                data: { client: clientId },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(html) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    const rows = tempDiv.querySelectorAll('tbody tr').length;
                    const badge = document.getElementById(`appointmentsTabBadge-${clientId}`);
                    if (badge) badge.textContent = rows;

                    if (rows === 0) {
                        pane.innerHTML = `<div class="rm-empty-state">
                            <div class="rm-empty-icon"><i class="fas fa-calendar-check"></i></div>
                            <h6>لا توجد مواعيد لهذا العميل</h6>
                        </div>`;
                        return;
                    }

                    pane.innerHTML = `<div class="rm-table-wrap">${html}</div>`;
                },
                error: function() {
                    pane.innerHTML = `<div class="rm-empty-state">
                        <div class="rm-empty-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <h6>حدث خطأ أثناء تحميل المواعيد</h6>
                        <button class="rm-btn rm-btn-primary mt-2" onclick="loadAppointmentsTab(${clientId})">إعادة المحاولة</button>
                    </div>`;
                }
            });
        }

        // دالة لإظهار/إخفاء تفاصيل الفاتورة
        function toggleInvoiceDetails(invoiceId) {
            const detailsRow = document.getElementById('invoice-details-' + invoiceId);
            if (!detailsRow) return;

            const button = event.target.closest('button');
            if (!button) return;

            const icon = button.querySelector('i');
            if (!icon) return;

            if (detailsRow.style.display === 'none') {
                detailsRow.style.display = 'table-row';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                detailsRow.style.display = 'none';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // دالة لإظهار/إخفاء تفاصيل الأصناف
        function toggleInvoiceItems(invoiceId) {
            const itemsDiv = document.getElementById('invoice-items-' + invoiceId);
            if (!itemsDiv) return;

            const button = event.target;
            if (!button) return;

            if (itemsDiv.style.display === 'none') {
                itemsDiv.style.display = 'block';
                button.textContent = 'إخفاء الأصناف';
            } else {
                itemsDiv.style.display = 'none';
                button.textContent = 'عرض الأصناف';
            }
        }

