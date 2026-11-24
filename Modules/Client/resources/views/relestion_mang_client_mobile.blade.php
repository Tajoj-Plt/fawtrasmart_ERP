@extends('client::master')

@section('content')

<!-- الأصل وتجاوزات الجوال -->
<link rel="stylesheet" href="{{ asset('css/client/relestion_mang_client.css') }}?v=31">
<link rel="stylesheet" href="{{ asset('css/client/relestion_mang_client_mobile.css') }}?v=1">
{{-- تبويب «ملاحظات الزيارات» — مشتركٌ مع شاشة العميل --}}
<link rel="stylesheet" href="{{ asset('assets/css/visit_note_cards.css') }}?v=4">
{{-- تصميم شاشات خط السير: أصناف rb-* نفسها، ثمّ طبقة إدارة علاقات العملاء --}}
<link rel="stylesheet" href="{{ asset('css/client/relestion_mang_client_rb.css') }}?v=4">
@include('client::Itinerary.components.route-builder-styles')

<style>
    /* Vuexy theme overrides for mobile */
    html body .content { margin-right: 0 !important; margin-left: 0 !important; min-height: auto !important; }
    html body .content .content-wrapper { padding: 0 !important; }
    body.vertical-layout.vertical-menu-modern.menu-collapsed .app-content { margin-left: 0 !important; padding-top: 5rem !important; }
</style>

@php
    $mobileClientsCount = $regionGroups->sum(
        fn($g) => $g->total_client_count ?? 0
    );

    // عدد الفروع والمناطق والمجموعات في الشجرة المعروضة
    $countTreeNodes = function ($groups) use (&$countTreeNodes) {
        $n = 0;
        foreach ($groups ?? [] as $g) {
            $n += 1 + ($g->childGroups ? $countTreeNodes($g->childGroups) : 0);
        }

        return $n;
    };
    $treeNodesCount = $countTreeNodes($regionGroups);
@endphp

<div class="rm-mobile-app rm-rb crm-m" id="route-builder" dir="rtl">

    <!-- ===== رأس الصفحة — rb-head ===== -->
    <header class="rb-head">
        <div class="rb-head__title">
            <span class="rb-head__icon"><i class="fas fa-handshake"></i></span>
            <div>
                <h1>علاقات العملاء</h1>
                <p>{{ number_format($mobileClientsCount) }} عميل في الشجرة</p>
            </div>
        </div>
        <div class="rb-head__actions">
            <button type="button" class="rb-btn rb-btn--success rb-btn--icon crm-m-bulk" id="bulkAddToItineraryBtn" style="display:none;" onclick="showBulkAddModal()" title="إضافة المحدَّدين لخط السير">
                <i class="fas fa-route"></i>
                <span class="rm-selection-badge crm-m-badge"><span id="selectedCount">0</span></span>
            </button>
            <button type="button" class="rb-btn rb-btn--light rb-btn--icon" id="clearSelectionBtn" style="display:none;" onclick="clearAllSelections()" title="إلغاء التحديد">
                <i class="fas fa-times"></i>
            </button>
            <button type="button" class="rb-btn rb-btn--light rb-btn--icon" onclick="document.getElementById('mobileFiltersArea').classList.toggle('show')" title="تصفية العملاء">
                <i class="fas fa-sliders-h"></i>
            </button>
            <a href="{{ route('clients.create') }}" class="rb-btn rb-btn--primary rb-btn--icon" title="عميل جديد">
                <i class="fas fa-user-plus"></i>
            </a>
        </div>
    </header>

    <!-- ===== الإحصاءات — rb-stat ===== -->
    <div class="rb-stats crm-m-stats">
        <div class="rb-stat rb-stat--navy">
            <div>
                <span class="rb-stat__label">العملاء</span>
                <strong>{{ number_format($mobileClientsCount) }}</strong>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="rb-stat rb-stat--green">
            <div>
                <span class="rb-stat__label">المحدَّدون</span>
                <strong data-crm-mirror="selected">0</strong>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-check-double"></i></span>
        </div>
        <div class="rb-stat rb-stat--amber">
            <div>
                <span class="rb-stat__label">الفروع والمجموعات</span>
                <strong>{{ number_format($treeNodesCount) }}</strong>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-sitemap"></i></span>
        </div>
        <div class="rb-stat rb-stat--blue">
            <div>
                <span class="rb-stat__label">حالات العملاء</span>
                <strong>{{ number_format($statuses->count()) }}</strong>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-tags"></i></span>
        </div>
    </div>

    <!-- ===== التصفية (تُطوى وتُفتح من زرّ الرأس) ===== -->
    <div class="rb-card rm-m-filters-area" id="mobileFiltersArea">
        <div class="rb-picker__title"><i class="fas fa-sliders-h"></i> تصفية العملاء</div>
        <div class="rb-field">
            <span class="rb-field__label">بحث</span>
            <select id="searchInputMobile" class="select2 w-100" data-placeholder="بحث: اسم، هاتف، كود...">
                <option value=""></option>
            </select>
        </div>
        <div class="rb-field">
            <span class="rb-field__label">حالة العميل</span>
            <select id="statusFilterMobile" class="select2 w-100" data-placeholder="حالة العميل">
                <option value=""></option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="rb-field crm-m-cascade">
            <span class="rb-field__label">الفروع</span>
            @include('client::partials.branch-cascade-filter')
        </div>
    </div>

    <!-- ===== شجرة العملاء ===== -->
    <div class="crm-m-section">
        <span class="crm-m-section__title"><i class="fas fa-sitemap"></i> شجرة العملاء</span>
        <span class="rb-badge">{{ number_format($mobileClientsCount) }}</span>
    </div>

    <div class="rm-m-list-container">
        <div class="rm-accordion rm-branch-tree" id="groupsAccordionMobile">
            @foreach ($regionGroups as $group)
                @include('client::partials.group_tree_item', ['group' => $group])
            @endforeach
        </div>
    </div>

    <!-- ===== شاشة التفاصيل ===== -->
    <div class="rm-m-details-view" id="mobileDetailsView">
        <div class="rm-m-details-header">
            <button type="button" class="rb-btn rb-btn--light rb-btn--icon" onclick="closeMobileDetails()" title="رجوع">
                <i class="fas fa-arrow-right"></i>
            </button>
            <div class="rm-m-details-title" id="mobileDetailsTitle">تفاصيل العميل</div>
            <div class="crm-m-dnav">
                <button type="button" class="rb-btn rb-btn--light rb-btn--icon" id="prevButton" title="العميل السابق">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button type="button" class="rb-btn rb-btn--light rb-btn--icon" id="nextButton" title="العميل التالي">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <a href="#" id="editClientButton" class="rb-btn rb-btn--light rb-btn--icon disabled" title="تعديل العميل">
                    <i class="fas fa-edit"></i>
                </a>
            </div>
        </div>

        <div class="rm-m-details-content" id="clientDetails">
            <!-- المحتوى يُحمَّل من الجافاسكربت المشترك -->
            <div id="defaultMessage" class="rm-welcome-state">
                <div class="rm-welcome-illustration">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h5>اختر عميلاً لعرض ملفه</h5>
                <p>افتح الفرع من شجرة العملاء ثم انقر على العميل.</p>
            </div>
            <div id="clientDetailsContent"></div>
        </div>

        <div class="rm-m-loader-overlay" id="loadingOverlay">
            <div class="rm-m-spinner mb-3"></div>
            <div class="rm-loading-text">جاري التحميل...</div>
        </div>
    </div>

    <!-- ===== شريط الإجراءات السفلي على الجوال ===== -->
    <div class="rm-mobile-bottom-bar" id="mobileBottomBar">
        <span class="rm-bottom-count">
            <i class="fas fa-check-circle"></i> <span id="mobileSelectedCount">0</span>
        </span>
        <button type="button" class="rm-mobile-bb-btn rm-mobile-bb-add" onclick="showBulkAddModal()">
            <i class="fas fa-route"></i> إضافة
        </button>
        <button type="button" class="rm-mobile-bb-btn rm-mobile-bb-clear" onclick="clearAllSelections()" aria-label="إلغاء التحديد">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- بطاقة «المحدَّدون» تعكس عدّاد الشاشة نفسه دون لمس منطقه --}}
    <script>
    (function () {
        function watch() {
            var bulk = document.getElementById('bulkAddToItineraryBtn');
            var selected = document.getElementById('selectedCount');

            function sync() {
                var value = bulk && bulk.style.display !== 'none' ? (selected.textContent || '0') : '0';
                document.querySelectorAll('[data-crm-mirror="selected"]').forEach(function (el) { el.textContent = value; });
            }

            if (selected) new MutationObserver(sync).observe(selected, { childList: true, characterData: true, subtree: true });
            if (bulk) new MutationObserver(sync).observe(bulk, { attributes: true, attributeFilter: ['style'] });
            sync();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', watch);
        } else {
            watch();
        }
    })();
    </script>

</div>

<!-- Modals Containers -->
<div id="itineraryModalContainer"></div>
<div id="quickAddModalContainer"></div>
<div id="notificationModalContainer"></div>
<div id="bulkAddModalContainer"></div>
<div id="appointmentModalContainer"></div>

@endsection

@section('scripts')
<script>
    // Variables
    const currentUserId = {{ auth()->id() }};

    /* نفس صلاحيات CRM المستعملة في نسخة سطح المكتب — راجع تلك الصفحة للشرح. */
    window.CRM_PERMS = {
        canAddNote: @json(hasPermission('client_follow_up_add_notes_attachments_appointments_all') || hasPermission('client_follow_up_add_notes_attachments_appointments_assigned') || hasPermission('client_follow_up_add_notes_attachments_appointments_own')),
        canEditAllNotes: @json(hasPermission('client_follow_up_edit_delete_notes_attachments_appointments_all')),
        canEditOwnNotes: @json(hasPermission('client_follow_up_edit_delete_notes_attachments_appointments_own')),
        canViewAppointments: @json(hasPermission('client_follow_up_view_notes_attachments_appointments_all') || hasPermission('client_follow_up_view_notes_attachments_appointments_assigned') || hasPermission('client_follow_up_view_notes_attachments_appointments_own')),
        canAddAppointment: @json(hasPermission('client_follow_up_add_notes_attachments_appointments_all') || hasPermission('client_follow_up_add_notes_attachments_appointments_assigned') || hasPermission('client_follow_up_add_notes_attachments_appointments_own')),
        canAddItinerary: @json(hasPermission('clients_itinerary_add')),
        canSendNotification: @json(hasPermission('clients_crm_send_notification')),
    };

    const clientEditUrlTemplate = "{{ route('clients.edit', ['id' => 'CLIENT_ID']) }}";
    const clientShowUrlTemplate = "{{ route('clients.show', ['id' => 'CLIENT_ID']) }}";
    const clientNoteCreateUrlTemplate = "{{ route('appointment.notes.create', ['id' => 'CLIENT_ID']) }}";
    const clientNoteEditUrlTemplate = "{{ route('clients.notes.edit', ['noteId' => 'NOTE_ID']) }}";
    const clientNotePrintUrlTemplate = "{{ route('clients.notes.print', ['noteId' => 'NOTE_ID']) }}";
    
    function getClientEditUrl(id) { return clientEditUrlTemplate.replace('CLIENT_ID', id); }
    function getClientShowUrl(id) { return clientShowUrlTemplate.replace('CLIENT_ID', id); }

    // Init Select2
    $(document).ready(function() {
        if($.fn.select2) {
            $('.select2').select2({
                dir: 'rtl',
                width: '100%',
                allowClear: true
            });
            
            // Branch cascade needs to be initialized if not auto-init by its partial
            /* الدالّة التي يصدّرها `branch-cascade-filter` اسمها `bootClientBranchCascade` —
               `initCascadeFilters` لم تكن موجودة، فبقي حقل «الفروع» فارغاً على الجوال. */
            if (typeof window.bootClientBranchCascade === 'function') {
                window.bootClientBranchCascade();
                $('#branchCascadeContainer select').each(function () {
                    if (!$(this).data('select2')) {
                        $(this).select2({ dir: 'rtl', width: '100%', allowClear: true, placeholder: $(this).data('placeholder') || '' });
                    }
                });
            }

            // AJAX Search
            $('#searchInputMobile').select2({
                dir: 'rtl',
                width: '100%',
                allowClear: true,
                placeholder: 'بحث سريع...',
                minimumInputLength: 2,
                ajax: {
                    url: '{{ route("crm.search-client-node") }}',
                    dataType: 'json',
                    delay: 400,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) { return { results: data.results }; }
                }
            }).on('select2:select', function(e) {
                const data = e.params.data;
                if(data && data.id) {
                    selectClientNow(data.id, data.text);
                }
            });
        }
    });

    // Group Load Logic
    window.loadGroupClientsNow = function(groupId) {
        const collapseElement = document.getElementById(`collapseGroup${groupId}`);
        if (collapseElement && collapseElement.dataset.loaded === 'true') return;
        setTimeout(() => loadGroupClients(groupId), 150);
    };

    function loadGroupClients(groupId) {
        const collapseElement = document.getElementById(`collapseGroup${groupId}`);
        const containerElement = document.getElementById(`groupClientsContainer${groupId}`);
        
        if (!collapseElement || collapseElement.dataset.loaded === 'true') return;

        containerElement.innerHTML = '<div class="p-3 text-center text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> جاري التحميل...</div>';

        $.ajax({
            url: '{{ route("clients.get-group-clients") }}',
            method: 'GET',
            data: { group_id: groupId },
            success: function(response) {
                if (response.success && response.clients) {
                    renderGroupClientsMobile(groupId, response.clients, containerElement);
                    collapseElement.dataset.loaded = 'true';
                } else {
                    containerElement.innerHTML = '<div class="p-3 text-danger text-center">لا يوجد عملاء</div>';
                }
            },
            error: function() {
                containerElement.innerHTML = '<div class="p-3 text-danger text-center">خطأ في التحميل</div>';
            }
        });
    }

    function renderGroupClientsMobile(groupId, clients, container) {
        if (clients.length === 0) {
            container.innerHTML = '<div class="p-3 text-muted text-center"><small>لا يوجد عملاء في هذه المجموعة</small></div>';
            return;
        }

        let html = '<div class="d-flex flex-column gap-2 p-2">';
        clients.forEach(client => {
            const initials = (client.trade_name || client.name || '?').charAt(0).toUpperCase();
            html += `
                <div class="rm-m-client-card client-item" data-client-id="${client.id}" data-group-id="${groupId}" data-status="${client.status_id || ''}">
                    <!-- Checkbox for bulk actions -->
                    <div class="rm-client-check mr-2" style="position: relative; z-index: 5;">
                        <input type="checkbox" class="form-check-input rm-client-checkbox client-checkbox" 
                               data-client-id="${client.id}"
                               data-client-name="${(client.trade_name || client.name).replace(/'/g, "\\'")}"
                               data-group-id="${groupId}"
                               onchange="toggleClientSelection(${client.id}, '${(client.trade_name || client.name).replace(/'/g, "\\'")}', this.checked, ${groupId})">
                    </div>
                    
                    <div class="rm-m-avatar cursor-pointer" onclick="selectClientNow(${client.id}, '${(client.trade_name || client.name).replace(/'/g, "\\'")}')">${initials}</div>
                    
                    <div class="rm-m-info cursor-pointer" onclick="selectClientNow(${client.id}, '${(client.trade_name || client.name).replace(/'/g, "\\'")}')">
                        <h4 class="rm-m-name">${client.trade_name || client.name}</h4>
                        <div class="rm-m-meta">
                            <span><i class="fas fa-fingerprint mr-1 opacity-50"></i> ${client.code || '--'}</span>
                            <span class="rm-m-badge bg-light-${client.status_color || 'primary'} text-${client.status_color || 'primary'}">${client.status_name || 'بدون حالة'}</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-left text-muted opacity-50"></i>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // Client Details Logic
    window.selectClientNow = function(clientId, clientName) {
        // تحديث العنوان
        const titleEl = document.getElementById('mobileDetailsTitle');
        if (titleEl && clientName) titleEl.textContent = clientName;
        
        // فتح الشاشة
        const detailsView = document.getElementById('mobileDetailsView');
        detailsView.classList.add('open');
        
        // استدعاء دالة selectClient العامة من relestion_mang_client.js
        if(typeof window.selectClient === 'function') {
            window.selectClient(clientId);
        }
    };

    function closeMobileDetails() {
        document.getElementById('mobileDetailsView').classList.remove('open');
    }

    // ===== نظام تحديد العملاء المتعدد =====
    let selectedClients = []; // قائمة العملاء المحددين
    let selectedGroupId = null; // المجموعة المحددة

    // دالة تحديث/إلغاء تحديد عميل
    function toggleClientSelection(clientId, clientName, isChecked, groupId) {
        if (isChecked) {
            if (!selectedClients.some(c => c.id == clientId)) {
                selectedClients.push({ id: clientId, name: clientName, groupId: groupId });
            }
            if (groupId) selectedGroupId = groupId;
        } else {
            selectedClients = selectedClients.filter(c => c.id != clientId);
            if (selectedClients.length > 0) {
                selectedGroupId = selectedClients[0].groupId;
            } else {
                selectedGroupId = null;
            }
        }
        updateSelectionUI();
    }

    // تحديث عرض زر الإضافة وعداد التحديد
    function updateSelectionUI() {
        const bulkBtn = document.getElementById('bulkAddToItineraryBtn');
        const clearBtn = document.getElementById('clearSelectionBtn');
        const mobileBar = document.getElementById('mobileBottomBar');
        const mobileCount = document.getElementById('mobileSelectedCount');
        
        if (selectedClients.length > 0) {
            bulkBtn.style.display = 'inline-block';
            clearBtn.style.display = 'inline-block';
            
            // تحديث العدد إذا أردنا عرضه داخل الزر أو بجانبه
            const countBadge = bulkBtn.querySelector('.rm-selection-badge span');
            if (countBadge) countBadge.textContent = selectedClients.length;

            if (mobileBar) mobileBar.classList.add('show');
            if (mobileCount) mobileCount.textContent = selectedClients.length;
        } else {
            bulkBtn.style.display = 'none';
            clearBtn.style.display = 'none';
            if (mobileBar) mobileBar.classList.remove('show');
        }

        // تحديث حالة الـ checkboxes المرئية
        document.querySelectorAll('.client-checkbox').forEach(checkbox => {
            const clientId = checkbox.dataset.clientId;
            checkbox.checked = selectedClients.some(c => c.id == clientId);
        });
    }

    // إلغاء جميع التحديدات
    function clearAllSelections() {
        selectedClients = [];
        selectedGroupId = null;
        updateSelectionUI();
    }
</script>

<!-- دوال تفاصيل العميل (البروفايل، التبويبات، الملاحظات...) والنوافذ المنبثقة
     (خط السير، الإشعار) مشتركة مع سطح المكتب عبر توجيه Blade حقيقي (@@include) —
     لا file_get_contents ولا استخراج نصّي هش يعتمد على تعليقات ثابتة. -->
<script>
@include('client::partials.crm_shared_scripts_details')
</script>
<script>
@include('client::partials.crm_shared_scripts_modals')
</script>

@endsection
