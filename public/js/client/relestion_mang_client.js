
        const currentUserId = {{ auth()->id() }};
        console.log('Script loaded successfully! User:', currentUserId);

        // فالترة حسب الفرع
        function filterByBranch(branchId) {
            const url = new URL(window.location);
            if (branchId) {
                url.searchParams.set('branch_id', branchId);
            } else {
                url.searchParams.delete('branch_id');
            }
            window.location.href = url.toString();
        }

        // Cache للعملاء لتحسين الأداء
        const clientsCache = new Map();
        const groupClientsCache = new Map(); // Cache للمجموعات
        const CACHE_DURATION = 5 * 60 * 1000; // 5 دقائق

        // دالة للتحقق من صلاحية الـ cache
        function isCacheValid(timestamp) {
            return (Date.now() - timestamp) < CACHE_DURATION;
        }

        // دالة مبسطة لتحميل العملاء
        window.loadGroupClientsNow = function(groupId) {
            console.log('loadGroupClientsNow called with groupId:', groupId);
            const collapseElement = document.getElementById(`collapseGroup${groupId}`);

            // إذا تم تحميلها سابقاً، لا تحمل مرة أخرى
            if (collapseElement && collapseElement.dataset.loaded === 'true') {
                console.log('Already loaded, skipping...');
                return;
            }

            console.log('Setting timeout to load clients...');
            setTimeout(function() {
                loadGroupClients(groupId, null);
            }, 150);
        };

        // دالة لتحميل عملاء المجموعة عند فتح Accordion
        function loadGroupClients(groupId, button) {
            console.log('loadGroupClients called with groupId:', groupId);

            const collapseElement = document.getElementById(`collapseGroup${groupId}`);
            const containerElement = document.getElementById(`groupClientsContainer${groupId}`);

            console.log('collapseElement:', collapseElement);
            console.log('containerElement:', containerElement);

            // التحقق إذا تم تحميل البيانات مسبقاً
            if (collapseElement.dataset.loaded === 'true') {
                console.log('Data already loaded, skipping...');
                return;
            }

            // التحقق من الـ cache
            const cachedData = groupClientsCache.get(groupId);
            if (cachedData && isCacheValid(cachedData.timestamp)) {
                console.log('Using cached data for group:', groupId);
                renderGroupClients(groupId, cachedData.data);
                collapseElement.dataset.loaded = 'true';
                return;
            }

            console.log('Fetching data from server...');

            // جلب البيانات عبر AJAX
            $.ajax({
                url: '{{ route("clients.get-group-clients") }}',
                method: 'GET',
                data: { group_id: groupId },
                timeout: 20000,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    console.log('AJAX Success Response:', response);

                    if (response.success && response.clients) {
                        console.log('Clients count:', response.clients.length);

                        // حفظ في الـ cache
                        groupClientsCache.set(groupId, {
                            data: response.clients,
                            timestamp: Date.now()
                        });

                        renderGroupClients(groupId, response.clients);
                        collapseElement.dataset.loaded = 'true';
                    } else {
                        console.error('Response not successful or no clients:', response);
                        containerElement.innerHTML = `
                            <div class="p-3 text-center text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p class="mb-0 small">حدث خطأ في تحميل العملاء</p>
                            </div>
                        `;
                    }
                },
                error: function(xhr, textStatus) {
                    console.error('AJAX Error:', {xhr, textStatus});

                    let errorMsg = 'حدث خطأ في تحميل العملاء';
                    if (textStatus === 'timeout') {
                        errorMsg = 'انتهت مهلة التحميل. الرجاء المحاولة مرة أخرى.';
                    }

                    containerElement.innerHTML = `
                        <div class="p-3 text-center text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p class="mb-0 small">${errorMsg}</p>
                            <button class="btn btn-sm btn-primary mt-2" onclick="retryLoadGroup(${groupId})">
                                <i class="fas fa-sync-alt"></i> إعادة المحاولة
                            </button>
                        </div>
                    `;
                }
            });
        }

        // دالة لإعادة المحاولة
        function retryLoadGroup(groupId) {
            const collapseElement = document.getElementById(`collapseGroup${groupId}`);
            const button = document.querySelector(`[data-bs-target="#collapseGroup${groupId}"]`);
            collapseElement.dataset.loaded = 'false';
            loadGroupClients(groupId, button);
        }

        // دالة لعرض عملاء المجموعة
        function renderGroupClients(groupId, clients) {
            console.log('renderGroupClients called with:', {groupId, clientsCount: clients?.length});

            const container = document.getElementById(`groupClientsContainer${groupId}`);

            if (!container) {
                console.error('Container not found:', `groupClientsContainer${groupId}`);
                return;
            }

            if (!clients || clients.length === 0) {
                console.log('No clients to display');
                container.innerHTML = `
                    <div class="p-3 text-center text-muted">
                        <i class="fas fa-users-slash"></i>
                        <p class="mb-0 small">لا يوجد عملاء في هذه المجموعة</p>
                    </div>
                `;
                return;
            }

            let html = '';
            clients.forEach(client => {
                html += `
                    <div class="client-item p-2 border-bottom cursor-pointer"
                        data-client-id="${client.id}"
                        data-group-id="${groupId}"
                        data-status="${client.status_id || ''}"
                        onclick="selectClient(${client.id})">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex: 1;">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge country-badge">
                                        ${client.country_code || 'SA'}
                                    </span>
                                    <span class="client-number text-muted small">#${client.code}</span>
                                </div>
                                <span class="client-name text-primary fw-medium d-block mt-1">${client.trade_name || client.name}</span>
                                ${client.phone ? `
                                    <div class="client-contact small text-muted mt-1">
                                        <i class="fas fa-phone-alt me-1"></i>
                                        ${client.phone}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            console.log('Rendered', clients.length, 'clients');
        }

        // دالة لاختيار الفرع وعرض تفاصيله
        function selectBranch(branchId) {
            // Store the selected branch ID for navigation
            window.currentBranchId = branchId;

            // Get the branch data from the DOM
            const branchElement = document.querySelector(`[data-branch-id="${branchId}"]`);
            const branchButton = branchElement?.querySelector('.accordion-button');

            if (!branchButton) return;

            // إخفاء الرسالة الافتراضية
            const defaultMessage = document.getElementById('defaultMessage');
            const branchDetailsSection = document.getElementById('branchDetailsSection');

            if (defaultMessage) {
                defaultMessage.style.display = 'none';
            }
            if (branchDetailsSection) {
                branchDetailsSection.style.display = 'block';
            }

            // جلب البيانات من العناصر المرئية
            const branchName = branchButton.querySelector('span')?.textContent || 'غير معروف';

            // يمكنك إضافة AJAX هنا لجلب تفاصيل الفرع من الـ Server
            // للآن سنستخدم البيانات المتاحة

            // عرض بيانات الفرع
            document.getElementById('branchName').textContent = branchName;
            document.getElementById('branchCode').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchPhone').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchMobile').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchAddress').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchCity').textContent = '--'; // يمكن إضافة من DB

            // عد الموظفين والمجموعات
            const groupsAccordion = document.getElementById(`groupsAccordion${branchId}`);
            const groupsCount = groupsAccordion ? groupsAccordion.querySelectorAll('.accordion-item').length : 0;

            document.getElementById('branchEmployees').textContent = '0'; // يمكن إضافة من DB
            document.getElementById('branchGroups').textContent = groupsCount;
        }

        // دالة wrapper للنقر على العميل
        function selectClientNow(clientId) {
            console.log('selectClientNow called:', clientId);
            selectClient(clientId);
        }

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
                    editButton.href = `/clients/${clientId}/edit`;
                }

                // إغلاق القائمة الجانبية على الأجهزة المحمولة بعد اختيار العميل
                if (window.innerWidth <= 768) {
                    const groupsList = document.getElementById('groupsListContainer');
                    groupsList.classList.remove('show');
                    groupsList.style.display = 'none';
                    const toggleBtn = document.getElementById('mobileToggleBtn');
                    if (toggleBtn) {
                        toggleBtn.classList.remove('active');
                    }
                }

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
                editButton.href = `/clients/${clientId}/edit`;
            }

            // إغلاق القائمة الجانبية على الأجهزة المحمولة بعد اختيار العميل
            if (window.innerWidth <= 768) {
                const groupsList = document.getElementById('groupsListContainer');
                groupsList.classList.remove('show');
                groupsList.style.display = 'none';
                // تحديث زر التبديل
                const toggleBtn = document.getElementById('mobileToggleBtn');
                if (toggleBtn) {
                    toggleBtn.classList.remove('active');
                }
            }
        }

        // دالة عرض رسائل الأخطاء
        function showErrorMessage(message) {
            const clientDetails = document.getElementById('clientDetails');
            if (clientDetails) {
                clientDetails.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h5>حدث خطأ</h5>
                        <p>${message}</p>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> إعادة تحميل الصفحة
                        </button>
                    </div>
                `;
            }
        }

        // دالة عرض تفاصيل العميل
        function displayClientDetails(clientData) {
            const clientDetails = document.getElementById('clientDetails');
            if (!clientDetails) {
                console.error('clientDetails element not found');
                return;
            }

            console.log('Client Data:', clientData);
            const clientId = clientData.id;

            // استخدام requestAnimationFrame لتحسين الأداء
            requestAnimationFrame(() => {
                let html = `
                <div class="client-details-section" style="display: block;">
                    <!-- معلومات العميل الأساسية -->
                    <div class="client-basic-info p-3 border-bottom">
                        <div class="row">
                            <div class="col-md-6 col-sm-6 col-12 info-item mb-3">
                                <div class="info-label">${clientData.trade_name || '--'}</div>
                                <div class="info-label">الاسم التجاري</div>
                            </div>
                            <div class="col-md-6 col-sm-6 col-12 info-item mb-3">
                                <div class="info-label">${clientData.phone || '--'}</div>
                                <div class="info-label">رقم الهاتف</div>
                            </div>
                            <div class="col-md-6 col-sm-6 col-12 info-item mb-3">
                                <div class="info-label" style="color: ${clientData.balance >= 0 ? '#28a745' : '#dc3545'}">
                                    ${parseFloat(clientData.balance).toFixed(2)} ر.س
                                </div>
                                <div class="info-label">الرصيد</div>
                            </div>
                            <div class="col-md-6 col-sm-6 col-12 info-item mb-3">
                                <div class="info-value">${clientData.invoices ? clientData.invoices.length : 0}</div>
                                <div class="info-label">إجمالي الفواتير</div>
                            </div>
                        </div>
                    </div>

                    <!-- أزرار العمليات (Mobile Operations) -->
                    <div class="client-operations p-3 border-bottom d-flex gap-2 flex-wrap justify-content-center bg-light">
                        <button type="button" class="btn btn-sm btn-success" id="quickAddToItineraryBtn" title="إضافة سريعة لخط السير">
                            <i class="fas fa-bolt"></i> إضافة سريعة
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="addToItineraryBtn" title="إضافة لخط السير">
                            <i class="fas fa-route"></i> لخط السير
                        </button>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="sendNotificationBtn" title="إرسال إشعار">
                            <i class="fas fa-bell"></i> إشعار
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" title="طباعة">
                            <i class="fas fa-print"></i> طباعة
                        </button>
                        <a href="/clients/show/${clientId}" id="showClientButton" class="btn btn-sm btn-info text-white" title="عرض العميل" target="_blank">
                            <i class="fas fa-eye"></i> عرض
                        </a>
                    </div>

                    <!-- علامات التبويب -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#invoices-${clientId}" type="button" role="tab">
                                الفواتير <span class="badge bg-primary ms-1">${clientData.invoices ? clientData.invoices.length : 0}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#notes-${clientId}" type="button" role="tab">
                                الملاحظات <span class="badge bg-secondary ms-1">${clientData.clientRelations ? clientData.clientRelations.length : 0}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#payments-${clientId}" type="button" role="tab">
                                المدفوعات <span class="badge bg-success ms-1">${clientData.payments ? clientData.payments.length : 0}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#visits-${clientId}" type="button" role="tab">
                                الزيارات <span class="badge bg-info ms-1">${clientData.visits ? clientData.visits.length : 0}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#exchanges-${clientId}" type="button" role="tab">
                                التبديلات <span class="badge bg-warning ms-1">${clientData.productExchanges ? clientData.productExchanges.length : 0}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#received-${clientId}" type="button" role="tab">
                                الاستلام <span class="badge bg-danger ms-1">${clientData.invoiceTransfers ? clientData.invoiceTransfers.filter(t => t.to_employee_id == currentUserId).length : 0}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#delivered-${clientId}" type="button" role="tab">
                                التسليم <span class="badge bg-dark ms-1">${clientData.invoiceTransfers ? clientData.invoiceTransfers.filter(t => t.from_employee_id == currentUserId).length : 0}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#statement-${clientId}" type="button" role="tab">
                                كشف الحساب <span class="badge bg-secondary ms-1"><i class="fas fa-chart-line"></i></span>
                            </button>
                        </li>
                    </ul>

                    <!-- محتوى التبويبات -->
                    <div class="tab-content p-3">
                        <!-- تبويب الفواتير -->
                        <div class="tab-pane fade show active" id="invoices-${clientId}" role="tabpanel">
                            ${generateInvoicesHTML(clientData.invoices || [])}
                        </div>

                        <!-- تبويب الملاحظات -->
                        <div class="tab-pane fade" id="notes-${clientId}" role="tabpanel">
                            ${generateNotesHTML(clientData.clientRelations || [])}
                        </div>

                        <!-- تبويب المدفوعات -->
                        <div class="tab-pane fade" id="payments-${clientId}" role="tabpanel">
                            ${generatePaymentsHTML(clientData.payments || [])}
                        </div>

                        <!-- تبويب الزيارات -->
                        <div class="tab-pane fade" id="visits-${clientId}" role="tabpanel">
                            ${generateVisitsHTML(clientData.visits || [])}
                        </div>

                        <!-- تبويب التبديلات -->
                        <div class="tab-pane fade" id="exchanges-${clientId}" role="tabpanel">
                            ${generateProductExchangesHTML(clientData.productExchanges || [])}
                        </div>

                        <!-- تبويب الاستلام -->
                        <div class="tab-pane fade" id="received-${clientId}" role="tabpanel">
                            ${generateReceivedTransfersHTML(clientData.invoiceTransfers || [])}
                        </div>

                        <!-- تبويب التسليم -->
                        <div class="tab-pane fade" id="delivered-${clientId}" role="tabpanel">
                            ${generateSentTransfersHTML(clientData.invoiceTransfers || [])}
                        </div>

                        <!-- تبويب كشف الحساب -->
                        <div class="tab-pane fade" id="statement-${clientId}" role="tabpanel">
                            <div id="statement-${clientId}-loading" class="text-center p-4">


                            </div>
                            <div id="statement-${clientId}-content"></div>
                        </div>
                    </div>
                </div>
            `;

                clientDetails.innerHTML = html;
                console.log('HTML Rendered:', html.substring(0, 500));

                // إعادة تهيئة Bootstrap tabs
                setTimeout(() => {
                    const tabButtons = clientDetails.querySelectorAll('[data-bs-toggle="tab"]');
                    console.log('Tab buttons found:', tabButtons.length);

                    tabButtons.forEach((btn, index) => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('Tab clicked:', index);

                        const tabTarget = this.getAttribute('data-bs-target');
                        console.log('Target:', tabTarget);

                        const tabPane = clientDetails.querySelector(tabTarget);
                        if (tabPane) {
                            // إخفاء جميع التبويبات
                            clientDetails.querySelectorAll('.tab-pane').forEach(pane => {
                                pane.classList.remove('active', 'show');
                            });
                            // إظهار التبويب المختار
                            tabPane.classList.add('active', 'show');

                            // تحديث أزرار التبويبات
                            clientDetails.querySelectorAll('.nav-link').forEach(link => {
                                link.classList.remove('active');
                            });
                            this.classList.add('active');

                            // تحميل كشف الحساب إذا تم النقر على تبويبة كشف الحساب
                            if (tabTarget.includes('statement')) {
                                const clientId = tabTarget.match(/statement-(\d+)/)?.[1];
                                if (clientId) {
                                    loadClientStatement(clientId);
                                }
                            }
                        } else {
                            console.error('Tab pane not found:', tabTarget);
                        }
                    });
                });

                // تحديث معالج زر إضافة إلى خط السير
                const addToItineraryBtn = clientDetails.querySelector('#addToItineraryBtn');
                const quickAddBtn = clientDetails.querySelector('#quickAddToItineraryBtn');
                
                function handleAddToItinerary() {
                    const clientItem = document.querySelector('.rm-client-item.selected') || document.querySelector('.client-item.selected');
                    if (clientItem) {
                        selectedClientId = clientItem.dataset.clientId;
                        const clientName = clientItem.querySelector('.rm-client-name')?.textContent?.trim() || clientItem.querySelector('.client-name')?.textContent?.trim() || clientItem.querySelector('.rm-m-name')?.textContent?.trim() || 'العميل';
                        if (typeof showAddToItineraryModal === 'function') {
                            showAddToItineraryModal(selectedClientId, clientName);
                        } else {
                            alert('عذراً، نافذة الإضافة إلى خط السير غير مدرجة في هذه الصفحة. يرجى التأكد من تضمين ملف المودال الخاص بها.');
                        }
                    } else if (clientId) {
                        // Fallback if no item is selected
                        selectedClientId = clientId;
                        const clientName = clientData.trade_name || clientData.full_name || clientData.name || 'العميل';
                        if (typeof showAddToItineraryModal === 'function') {
                            showAddToItineraryModal(selectedClientId, clientName);
                        }
                    }
                }

                if (addToItineraryBtn) {
                    addToItineraryBtn.addEventListener('click', handleAddToItinerary);
                }
                
                if (quickAddBtn) {
                    quickAddBtn.addEventListener('click', handleAddToItinerary);
                }

                }, 100);
            });
        }

        // توليد HTML الفواتير
        function generateInvoicesHTML(invoices) {
            if (invoices.length === 0) {
                return '<div class="empty-state"><i class="fas fa-file-invoice"></i><h6>لا توجد فواتير</h6></div>';
            }

            let html =
                '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>رقم الفاتورة</th><th>التاريخ</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody>';
            invoices.forEach(invoice => {
                html += `<tr>
                    <td><strong class="text-primary">#${invoice.number || invoice.id}</strong></td>
                    <td>${invoice.date || 'N/A'}</td>
                    <td><strong>${parseFloat(invoice.amount || 0).toFixed(2)}</strong></td>
                    <td><span class="badge bg-${invoice.status_class || 'secondary'}">${invoice.status_text || 'N/A'}</span></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        // توليد HTML الملاحظات
        function generateNotesHTML(notes) {
            if (notes.length === 0) {
                return '<div class="empty-state"><i class="fas fa-sticky-note"></i><h6>لا توجد ملاحظات</h6></div>';
            }

            let html = '<div class="row">';
            notes.forEach(note => {
                // معالجة الصور المرفقة
                let attachmentsHTML = '';
                if (note.attachments_array && note.attachments_array.length > 0) {
                    attachmentsHTML += '<div class="attachments-section mb-3"><h6 class="mb-3"><i class="fa fa-paperclip me-2" style="color: #0d6efd;"></i>المرفقات (' + note.attachments_array.length + ')</h6><div class="row g-3">';
                    note.attachments_array.forEach(file => {
                        const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                        const ext = file.split('.').pop().toLowerCase();
                        const fileUrl = '/assets/uploads/notes/' + file;

                        if (imageExts.includes(ext)) {
                            attachmentsHTML += `
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="${fileUrl}" data-fancybox="gallery-${note.id}" data-caption="${file}" class="text-decoration-none">
                                        <div class="position-relative">
                                            <img src="${fileUrl}" alt="${file}" class="img-thumbnail" style="height: 100px; object-fit: cover; border-radius: 0.25rem;">
                                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s; border-radius: 0.25rem;">
                                                <i class="fa fa-search-plus text-white fs-5"></i>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            `;
                        } else {
                            attachmentsHTML += `
                                <div class="col-12 col-sm-6 col-md-4">
                                    <a href="${fileUrl}" target="_blank" class="btn btn-outline-primary btn-sm w-100 text-truncate">
                                        <i class="fa fa-file me-2"></i>${file.substring(0, 20)}...
                                    </a>
                                </div>
                            `;
                        }
                    });
                    attachmentsHTML += '</div></div>';
                }

                // معالجة بيانات موظف العميل
                let employeeInfoHTML = '';
                if (note.clientEmployee) {
                    employeeInfoHTML = `
                        <div class="col-md-3 mb-2">
                            <small style="color: #0d6efd;">
                                <i class="fa fa-user me-1"></i>موظف:
                            </small>
                            <strong class="d-block">${note.clientEmployee.employee_name}</strong>
                            ${note.clientEmployee.is_primary ? '<span class="badge bg-success mt-1">رئيسي</span>' : ''}
                        </div>
                        ${note.clientEmployee.employee_phone ? `
                            <div class="col-md-3 mb-2">
                                <small style="color: #0d6efd;">
                                    <i class="fa fa-phone me-1"></i>الجوال:
                                </small>
                                <a href="tel:${note.clientEmployee.employee_phone}" class="text-info text-decoration-none d-block">
                                    <strong>${note.clientEmployee.employee_phone}</strong>
                                </a>
                            </div>
                        ` : ''}
                        ${note.clientEmployee.employee_position ? `
                            <div class="col-md-3 mb-2">
                                <small style="color: #0d6efd;">
                                    <i class="fa fa-briefcase me-1"></i>الوظيفة:
                                </small>
                                <strong class="d-block">${note.clientEmployee.employee_position}</strong>
                            </div>
                        ` : ''}
                    `;
                }

                html += `
                    <div class="col-12 mb-3">
                        <div class="card note-card-item shadow-sm">
                            <div class="card-body p-3">
                                <!-- رأس الملاحظة -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fa fa-user me-2" style="color: #0d6efd;"></i>
                                            ${note.employee || 'غير معروف'}
                                        </h6>
                                        ${note.clientEmployee ? `
                                            <small class="text-muted d-block mb-2">
                                                <i class="fa fa-users me-1"></i>
                                                موظف العميل: <strong>${note.clientEmployee.employee_name || 'غير معروف'}</strong>
                                                ${note.clientEmployee.is_primary ? '<span class="badge bg-success ms-2"><i class="fa fa-star me-1"></i>رئيسي</span>' : ''}
                                            </small>
                                        ` : ''}
                                        ${note.process ? `<span class="badge bg-primary"><i class="fa fa-tag me-1"></i>${note.process}</span>` : ''}
                                    </div>
                                    <small class="text-muted">
                                        <i class="fa fa-clock me-1"></i>
                                        ${formatDate(note.created_at)}
                                    </small>
                                </div>

                                <hr>

                                <!-- وصف الملاحظة -->
                                <div class="note-description mb-3">
                                    <p class="mb-0">${note.description || 'لا يوجد وصف'}</p>
                                </div>

                                <!-- معلومات إضافية -->
                                ${note.deposit_count || note.site_type || note.competitor_documents || note.clientEmployee ? `
                                    <div class="additional-info bg-light rounded p-3 mb-3">
                                        <div class="row">
                                            ${note.deposit_count ? `
                                                <div class="col-md-3 mb-2">
                                                    <small style="color: #0d6efd;">
                                                        <i class="fa fa-boxes me-1"></i>عدد العهدة:
                                                    </small>
                                                    <strong class="d-block">${note.deposit_count}</strong>
                                                </div>
                                            ` : ''}
                                            ${note.site_type ? `
                                                <div class="col-md-3 mb-2">
                                                    <small style="color: #0d6efd;">
                                                        <i class="fa fa-store me-1"></i>نوع الموقع:
                                                    </small>
                                                    <strong class="d-block">${translateSiteType(note.site_type)}</strong>
                                                </div>
                                            ` : ''}
                                            ${note.competitor_documents ? `
                                                <div class="col-md-3 mb-2">
                                                    <small style="color: #0d6efd;">
                                                        <i class="fa fa-file-contract me-1"></i>وثائق:
                                                    </small>
                                                    <strong class="d-block">${note.competitor_documents}</strong>
                                                </div>
                                            ` : ''}
                                            ${employeeInfoHTML}
                                        </div>
                                    </div>
                                ` : ''}

                                <!-- المرفقات -->
                                ${attachmentsHTML}

                                <!-- أزرار الإجراءات -->
                                <div class="note-actions pt-3 border-top">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button class="btn btn-outline-primary btn-sm">
                                            <i class="fa fa-print me-1"></i>طباعة
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }

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
                return '<div class="empty-state"><i class="fas fa-credit-card"></i><h6>لا توجد مدفوعات</h6></div>';
            }

            let html =
                '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>تاريخ الدفع</th><th>المبلغ</th><th>الطريقة</th></tr></thead><tbody>';
            payments.forEach(payment => {
                html += `<tr>
                    <td>${payment.payment_date || 'N/A'}</td>
                    <td><strong class="text-success">${parseFloat(payment.amount || 0).toFixed(2)}</strong></td>
                    <td>${payment.payment_method || 'N/A'}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        // توليد HTML الزيارات
        function generateVisitsHTML(visits) {
            if (visits.length === 0) {
                return '<div class="empty-state"><i class="fas fa-location-dot"></i><h6>لا توجد زيارات</h6></div>';
            }

            let html =
                '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>تاريخ الزيارة</th><th>الموظف</th></tr></thead><tbody>';
            visits.forEach(visit => {
                html += `<tr>
                    <td>${visit.visit_date || 'N/A'}</td>
                    <td>${visit.employee || 'N/A'}</td>
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
                return '<div class="empty-state"><i class="fas fa-exchange-alt"></i><h6>لا توجد تبديلات</h6></div>';
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
                return '<div class="empty-state"><i class="fas fa-inbox"></i><h6>لا توجد استلامات واردة</h6></div>';
            }

            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>رقم الفاتورة</th><th>المبلغ</th><th>من الموظف</th><th>التاريخ</th><th>الحالة</th></tr></thead><tbody>';
            received.forEach(t => {
                html += `<tr>
                    <td>${t.invoice_code}</td>
                    <td>${parseFloat(t.invoice_amount || 0).toFixed(2)}</td>
                    <td>${t.from_employee}</td>
                    <td>${t.created_at ? new Date(t.created_at).toLocaleDateString('ar-SA') : '-'}</td>
                    <td><span class="badge bg-${t.status_color}">${t.status_text}</span></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            return html;
        }

        // توليد HTML التسليم (صادر)
        function generateSentTransfersHTML(transfers) {
            const sent = transfers.filter(t => t.from_employee_id == currentUserId);

            if (sent.length === 0) {
                return '<div class="empty-state"><i class="fas fa-paper-plane"></i><h6>لا توجد تسليمات صادرة</h6></div>';
            }

            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>رقم الفاتورة</th><th>المبلغ</th><th>إلى الموظف</th><th>التاريخ</th><th>الحالة</th></tr></thead><tbody>';
            sent.forEach(t => {
                html += `<tr>
                    <td>${t.invoice_code}</td>
                    <td>${parseFloat(t.invoice_amount || 0).toFixed(2)}</td>
                    <td>${t.to_employee}</td>
                    <td>${t.created_at ? new Date(t.created_at).toLocaleDateString('ar-SA') : '-'}</td>
                    <td><span class="badge bg-${t.status_color}">${t.status_text}</span></td>
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
                url: '/clients/clients_management/statement/' + clientId,
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

        // دالة البحث والفلترة المحسنة
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded - Setting up event listeners...');

            // Event delegation للعملاء - لا يحتاج onclick
            document.addEventListener('click', function(e) {
                const clientItem = e.target.closest('.client-item');
                if (clientItem && clientItem.dataset.clientId) {
                    const clientId = parseInt(clientItem.dataset.clientId);
                    console.log('Client item clicked:', clientId);
                    selectClientNow(clientId);
                }
            });

            // إضافة event listeners لأزرار الفروع
            document.querySelectorAll('[data-bs-toggle="collapse"][data-branch-id]').forEach(button => {
                button.addEventListener('click', function() {
                    const branchId = this.getAttribute('data-branch-id');
                    console.log('Branch clicked:', branchId);
                    selectBranch(branchId);
                });
            });

            // إضافة event listeners لجميع المجموعات
            const groupButtons = document.querySelectorAll('[data-bs-toggle="collapse"][data-group-id]');
            console.log('Found group buttons:', groupButtons.length);

            groupButtons.forEach(button => {
                const groupId = button.getAttribute('data-group-id');
                console.log('Setting up listener for group:', groupId);

                button.addEventListener('click', function(e) {
                    console.log('Group button clicked:', groupId);
                    const collapseElement = document.getElementById(`collapseGroup${groupId}`);

                    // تحقق إذا كان الـ collapse مغلق (سيتم فتحه)
                    if (collapseElement && !collapseElement.classList.contains('show')) {
                        console.log('Collapse is closed, will load clients...');
                        setTimeout(() => {
                            loadGroupClients(groupId, button);
                        }, 100);
                    }
                });
            });

            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const groupFilter = document.getElementById('groupFilter');
            const toggleSidebar = document.getElementById('toggleSidebar');
            const mobileToggleBtn = document.getElementById('mobileToggleBtn');
            const expandAllBtn = document.getElementById('expandAllBranches');
            const collapseAllBtn = document.getElementById('collapseAllBranches');

            // دالة فتح جميع الفروع والمجموعات
            if (expandAllBtn) {
                expandAllBtn.addEventListener('click', function() {
                    // فتح جميع الفروع
                    const branchButtons = document.querySelectorAll(
                        '#branchesAccordion > .accordion-item .accordion-button');
                    branchButtons.forEach(button => {
                        if (button.classList.contains('collapsed')) {
                            button.click();
                        }
                    });

                    // فتح جميع المجموعات بعد قليل
                    setTimeout(() => {
                        const groupButtons = document.querySelectorAll(
                            '[id^="groupsAccordion"] .accordion-button');
                        groupButtons.forEach(button => {
                            if (button.classList.contains('collapsed')) {
                                button.click();
                            }
                        });
                    }, 100);
                });
            }

            // دالة طي جميع الفروع والمجموعات
            if (collapseAllBtn) {
                collapseAllBtn.addEventListener('click', function() {
                    // طي جميع المجموعات أولاً
                    const groupButtons = document.querySelectorAll(
                        '[id^="groupsAccordion"] .accordion-button');
                    groupButtons.forEach(button => {
                        if (!button.classList.contains('collapsed')) {
                            button.click();
                        }
                    });

                    // طي جميع الفروع بعد قليل
                    setTimeout(() => {
                        const branchButtons = document.querySelectorAll(
                            '#branchesAccordion > .accordion-item .accordion-button');
                        branchButtons.forEach(button => {
                            if (!button.classList.contains('collapsed')) {
                                button.click();
                            }
                        });
                    }, 100);
                });
            }

            // دالة الفلترة المركبة مع تحسين الأداء
            let filterTimeout;
            function applyFilters() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
                    const statusValue = statusFilter ? statusFilter.value : '';
                    const groupValue = groupFilter ? groupFilter.value : '';

                    console.log('Filtering with - Search:', searchTerm, 'Status:', statusValue, 'Group:', groupValue);

                    const clientItems = document.querySelectorAll('.client-item');
                    const accordionItems = document.querySelectorAll('.accordion-item');

                    console.log('Found client items:', clientItems.length);
                    console.log('Found accordion items:', accordionItems.length);

                    // استخدام DocumentFragment لتحسين الأداء
                    requestAnimationFrame(() => {
                        // فلترة العملاء
                        clientItems.forEach(item => {
                            let showItem = true;

                            // البحث في جميع النصوص
                            if (searchTerm !== '') {
                                const itemText = item.textContent.toLowerCase();
                                if (!itemText.includes(searchTerm)) {
                                    showItem = false;
                                }
                            }

                            // فلترة الحالة - التحقق من data-status
                            if (statusValue !== '' && showItem) {
                                const clientStatus = item.getAttribute('data-status') || '';
                                console.log('Item status:', clientStatus, 'Filter value:', statusValue, 'Match:', clientStatus === statusValue);
                                if (clientStatus !== statusValue) {
                                    showItem = false;
                                }
                            }

                            // فلترة المجموعة - التحقق من data-group-id
                            if (groupValue !== '' && showItem) {
                                const clientGroup = item.getAttribute('data-group-id') || '';
                                console.log('Item group:', clientGroup, 'Filter value:', groupValue, 'Match:', clientGroup === groupValue);
                                if (clientGroup !== groupValue) {
                                    showItem = false;
                                }
                            }

                            item.style.display = showItem ? 'block' : 'none';
                        });

                        // إخفاء/إظهار المجموعات التي لا تحتوي على نتائج
                        accordionItems.forEach(accordionItem => {
                            const clientsInGroup = accordionItem.querySelectorAll('.client-item');
                            let hasVisibleClients = false;

                            clientsInGroup.forEach(client => {
                                if (client.style.display !== 'none') {
                                    hasVisibleClients = true;
                                }
                            });

                            accordionItem.style.display = hasVisibleClients ? 'block' : 'none';

                            // فتح المجموعات تلقائياً إذا كانت تحتوي على نتائج بحث
                            if (hasVisibleClients && (searchTerm !== '' || statusValue !== '' || groupValue !== '')) {
                                const button = accordionItem.querySelector('.accordion-button');
                                const collapse = accordionItem.querySelector('.accordion-collapse');
                                if (button && collapse) {
                                    button.classList.remove('collapsed');
                                    collapse.classList.add('show');
                                    button.setAttribute('aria-expanded', 'true');
                                }
                            }
                        });
                    });
                }, 300); // تأخير 300ms لتقليل عدد الاستدعاءات
            }

            // ربط الفلاتر بالأحداث
            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }
            if (statusFilter) {
                statusFilter.addEventListener('change', applyFilters);
            }
            if (groupFilter) {
                groupFilter.addEventListener('change', applyFilters);
            }

            // تحسين أزرار التنقل
            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');

            if (prevButton && nextButton) {
                prevButton.addEventListener('click', function() {
                    const currentSelected = document.querySelector('.client-item.selected');
                    if (currentSelected) {
                        const allVisibleClients = Array.from(document.querySelectorAll('.client-item'))
                            .filter(item => item.style.display !== 'none');
                        const currentIndex = allVisibleClients.indexOf(currentSelected);

                        if (currentIndex > 0) {
                            const prevClient = allVisibleClients[currentIndex - 1];
                            const clientId = prevClient.dataset.clientId;
                            selectClient(clientId);
                        }
                    }
                });

                nextButton.addEventListener('click', function() {
                    const currentSelected = document.querySelector('.client-item.selected');
                    if (currentSelected) {
                        const allVisibleClients = Array.from(document.querySelectorAll('.client-item'))
                            .filter(item => item.style.display !== 'none');
                        const currentIndex = allVisibleClients.indexOf(currentSelected);

                        if (currentIndex < allVisibleClients.length - 1) {
                            const nextClient = allVisibleClients[currentIndex + 1];
                            const clientId = nextClient.dataset.clientId;
                            selectClient(clientId);
                        }
                    }
                });
            }

            // زر تبديل القائمة الجانبية
            if (toggleSidebar) {
                toggleSidebar.addEventListener('click', function() {
                    const groupsList = document.getElementById('groupsListContainer');
                    groupsList.classList.toggle('show');
                });
            }

            // تهيئة العرض بناءً على حجم الشاشة عند التحميل
            const groupsList = document.getElementById('groupsListContainer');

            // زر تبديل الجوال
            if (mobileToggleBtn) {
                mobileToggleBtn.addEventListener('click', function() {
                    const groupsList = document.getElementById('groupsListContainer');
                    groupsList.classList.toggle('show');
                    this.classList.toggle('active');
                });
            }

            if (window.innerWidth > 768) {
                // على الشاشات الكبيرة، إظهار القائمة الجانبية
                groupsList.style.position = 'relative';
                groupsList.style.display = 'block';
                // إخفاء زر التبديل على الشاشات الكبيرة
                if (mobileToggleBtn) {
                    mobileToggleBtn.style.display = 'none';
                }
            } else {
                // على الشاشات الصغيرة، إخفاء القائمة الجانبية
                groupsList.style.position = 'absolute';
                groupsList.style.display = 'none';
                if (mobileToggleBtn) {
                    mobileToggleBtn.style.display = 'flex';
                }
            }

            // تحسين الأكورديون لفتح المجموعة عند البحث
            expandGroupWithVisibleClients();

        }); // نهاية DOMContentLoaded

        // تحسين الأكورديون لفتح المجموعة عند البحث
        function expandGroupWithVisibleClients() {
            const accordionItems = document.querySelectorAll('.accordion-item');

            accordionItems.forEach(accordionItem => {
                const visibleClients = accordionItem.querySelectorAll(
                    '.client-item[style*="block"], .client-item:not([style])');
                const hasVisibleClients = Array.from(visibleClients).some(client =>
                    client.style.display !== 'none'
                );

                if (hasVisibleClients) {
                    const collapseElement = accordionItem.querySelector('.accordion-collapse');
                    const button = accordionItem.querySelector('.accordion-button');

                    if (collapseElement && button) {
                        collapseElement.classList.add('show');
                        button.classList.remove('collapsed');
                        button.setAttribute('aria-expanded', 'true');
                    }
                }
            });
        }

        // إغلاق القائمة الجانبية عند النقر خارجها على الأجهزة المحمولة
        document.addEventListener('click', function(event) {
            const groupsList = document.getElementById('groupsListContainer');
            const toggleButton = document.getElementById('toggleSidebar');
            const mobileBtn = document.getElementById('mobileToggleBtn');

            if (window.innerWidth <= 768 && groupsList && groupsList.classList.contains('show')) {
                // التحقق مما إذا كان النقر خارج القائمة الجانبية
                if (!groupsList.contains(event.target) && event.target !== toggleButton && event.target !== mobileBtn &&
                    (!toggleButton || !toggleButton.contains(event.target)) &&
                    (!mobileBtn || !mobileBtn.contains(event.target))) {
                    groupsList.classList.remove('show');
                    // تحديث زر التبديل
                    if (mobileBtn) {
                        mobileBtn.classList.remove('active');
                    }
                }
            }
        });

        // معالج تغيير حجم الشاشة
        window.addEventListener('resize', function() {
            const groupsList = document.getElementById('groupsListContainer');
            const mobileBtn = document.getElementById('mobileToggleBtn');

            // إدارة العرض بناءً على حجم الشاشة
            if (window.innerWidth <= 768) {
                // على الشاشات الصغيرة، إخفاء القائمة الجانبية
                if (groupsList) {
                    groupsList.style.position = 'absolute';
                    groupsList.style.display = 'none';
                    groupsList.classList.remove('show');
                }
                // إظهار زر التبديل على الشاشات الصغيرة
                if (mobileBtn) {
                    mobileBtn.style.display = 'flex';
                }
            } else {
                // على الشاشات الكبيرة، إظهار القائمة الجانبية
                if (groupsList) {
                    groupsList.style.position = 'relative';
                    groupsList.style.display = 'block';
                    groupsList.classList.add('show');
                }
                // إخفاء زر التبديل على الشاشات الكبيرة
                if (mobileBtn) {
                    mobileBtn.style.display = 'none';
                }
            }

            // إعادة تحميل الخريطة إذا كانت موجودة
            if (typeof mapManager !== 'undefined' && mapManager.map) {
                setTimeout(() => {
                    google.maps.event.trigger(mapManager.map, 'resize');
                }, 300);
            }
        });
