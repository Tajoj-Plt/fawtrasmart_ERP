{{--
    الإشعارات — على قياس «فواتير المبيعات» (`sales::invoices.index`):
    ترويسةٌ فيها الإجراءات، بطاقة فلترة ببحثٍ أساسي ومتقدّم، ثمّ بطاقة نتائج
    تُستبدَل وحدها بـAjax عبر `sales-index-filters.js`.

    ما أُصلح هنا:
      • أزرار الإجراءات كانت تنادي عناوين مكتوبةً باليد
        (`/sales/notifications/{id}/mark-read`، `…/reply`، `…/unread-count`)
        لا مسار لها — فلا «تعليم كمقروء» ولا «رد» يعمل. صارت كلُّها من `route()`.
      • الجدول كان يُرسَم مرّتين: بـBlade عند الفتح، وبنصوص JavaScript بعد كل
        فلترة. صار قالباً واحداً (`notifications.partials.table`).
      • نافذة الرد هي نافذة الترويسة (`#replyModal` في `layouts.header`) — كانت
        الشاشة تعرّف نافذةً ثانية بالمعرّفات نفسِها.
--}}

@extends('sales::master')

@section('title', 'الإشعارات')

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    {{-- بدونها تُفتح قائمة إجراءات الصفّ داخل الجدول وتُقصّ عند حافّته --}}
    @include('sales::partials.table-menu')

    @php
        $hasFilters = request()->hasAny([
            'keyword', 'type', 'read_state', 'from_date', 'to_date', 'date_from', 'date_to',
            'notification_target', 'reply_status', 'client_id', 'receiver_id', 'user_id',
        ]);

        // «بحث متقدّم» يُفتح وحده حين يكون فيه فلترٌ مضبوط
        $advancedActive = request()->filled('notification_target') || request()->filled('reply_status')
            || request()->filled('client_id') || request()->filled('receiver_id') || request()->filled('user_id');
    @endphp

    <style>
        /* غير المقروء يُميَّز بخطٍّ جانبيّ وعنوانٍ أثقل — لا بتلوين الصفّ كلِّه */
        .sl-scope tr.ntf-unread > td:first-child { box-shadow: inset 3px 0 0 var(--app-primary, #7367f0); }
        [dir="rtl"] .sl-scope tr.ntf-unread > td:first-child { box-shadow: inset -3px 0 0 var(--app-primary, #7367f0); }
        .sl-scope tr.ntf-unread .sl-media__title { font-weight: 700; }
    </style>

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.notifications.send')
                <a href="{{ route('notifications.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إرسال إشعار جديد
                </a>
            @endpermission

            <a href="{{ route('notifications.sent') }}" class="btn btn-outline-secondary">
                <i class="feather icon-send"></i> المرسلة
            </a>

            <button type="button" class="btn btn-outline-secondary" id="mark-all-read">
                <i class="feather icon-check-circle"></i> تعليم الكل كمقروء
            </button>
        @endsection

        @include('sales::components.page-head', [
            'title' => 'الإشعارات',
            'icon' => 'bell',
            'sub' => 'كل ما وصلك أو أرسلته من إشعارات، مع البحث والرد والتعليم كمقروء.',
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                'الإشعارات' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="feather icon-bell"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="ntf-total-stat">{{ number_format($notifications->total()) }}</p>
                    <p class="sl-stat__label">إشعار يطابق البحث الحالي</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="feather icon-mail"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="ntf-unread-stat">{{ number_format($unreadCount) }}</p>
                    <p class="sl-stat__label">غير مقروء منها</p>
                </div>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> {{ __('sales_settings.ui.filters') }}
                    </h6>

                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters)
                            <span class="sl-count">{{ __('sales_settings.ui.filters_active') }}</span>
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-sl-toggle="#advancedSearchForm"
                                aria-expanded="{{ $advancedActive ? 'true' : 'false' }}">
                            <i class="feather icon-sliders"></i> {{ __('sales_settings.ui.advanced') }}
                        </button>
                    </div>
                </div>

                <form id="searchForm" data-search-url="{{ route('notifications.index') }}">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="keyword">بحث</label>
                                <input type="text" id="keyword" name="keyword" class="form-control"
                                       value="{{ request('keyword') }}" placeholder="العنوان أو نص الإشعار">
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="type">نوع الإشعار</label>
                                <select id="type" name="type" class="form-control select2">
                                    <option value="">كل الأنواع</option>
                                    @foreach ($typeOptions as $typeKey => $typeName)
                                        <option value="{{ $typeKey }}" {{ request('type') === (string) $typeKey ? 'selected' : '' }}>
                                            {{ $typeName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <div class="form-group">
                                <label for="read_state">الحالة</label>
                                <select id="read_state" name="read_state" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="unread" {{ request('read_state') === 'unread' ? 'selected' : '' }}>غير مقروء</option>
                                    <option value="read" {{ request('read_state') === 'read' ? 'selected' : '' }}>مقروء</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="from_date">الفترة (من — إلى)</label>
                                <div class="input-group">
                                    <input type="date" id="from_date" name="from_date" class="form-control"
                                           value="{{ request('from_date', request('date_from')) }}">
                                    <input type="date" name="to_date" class="form-control"
                                           value="{{ request('to_date', request('date_to')) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $advancedActive ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="notification_target">موجّه إلى</label>
                                    <select id="notification_target" name="notification_target" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="clients" {{ request('notification_target') === 'clients' ? 'selected' : '' }}>العملاء</option>
                                        <option value="employees" {{ request('notification_target') === 'employees' ? 'selected' : '' }}>الموظفين</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="reply_status">حالة الرد</label>
                                    <select id="reply_status" name="reply_status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="unanswered" {{ request('reply_status') === 'unanswered' ? 'selected' : '' }}>إشعارات عملاء بلا رد</option>
                                        <option value="answered" {{ request('reply_status') === 'answered' ? 'selected' : '' }}>إشعارات عملاء تم الرد عليها</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="client_id">العميل</label>
                                    <select id="client_id" name="client_id" class="form-control select2">
                                        <option value="">كل العملاء</option>
                                        @foreach ($clientsWithNotifications as $client)
                                            <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                                {{ $client->trade_name ?: 'عميل #'.$client->id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-2 col-md-6 col-sm-6">
                                <div class="form-group">
                                    <label for="user_id">المرسل</label>
                                    <select id="user_id" name="user_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($senders as $sender)
                                            <option value="{{ $sender->id }}" {{ request('user_id') == $sender->id ? 'selected' : '' }}>
                                                {{ $sender->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="form-group">
                                    <label for="receiver_id">المستلم</label>
                                    <select id="receiver_id" name="receiver_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($receivers as $receiver)
                                            <option value="{{ $receiver->id }}" {{ request('receiver_id') == $receiver->id ? 'selected' : '' }}>
                                                {{ $receiver->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> {{ __('sales_settings.ui.filters_clear') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> {{ __('sales_settings.ui.search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-list"></i> الإشعارات
                    <span class="sl-count">{{ $notifications->total() }}</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('notifications.partials.table')
            </div>
        </div>
    </div>

    {{-- نافذة عرض الإشعار — ونافذةُ الرد هي `#replyModal` في الترويسة --}}
    <div class="modal fade" id="notificationModal" tabindex="-1" role="dialog" aria-labelledby="notificationTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="notificationTitle"></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="notificationBody"></div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @php $slFiltersVersion = @filemtime(public_path('assets/js/sales-index-filters.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/sales-index-filters.js') }}?v={{ $slFiltersVersion }}"></script>

    <script>
        $(function () {
            'use strict';

            /* كلُّ عنوانٍ من `route()`: المسارات تحت بادئة اللغة، والعناوين
               المكتوبة باليد كانت تخطئها وتخطئ ترتيب المقاطع معاً */
            const routes = {
                index: @json(route('notifications.index')),
                show: @json(route('notifications.show', ['id' => '__ID__'])),
                destroy: @json(route('notifications.destroy', ['id' => '__ID__'])),
                markRead: @json(route('notifications.markAsRead')),
                markAll: @json(route('notifications.markAllAsRead')),
            };
            const token = @json(csrf_token());
            const loadingHtml = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';
            const errorHtml = '<div class="alert alert-danger mb-0"><i class="feather icon-alert-circle"></i> تعذّر تحميل الإشعار</div>';

            const urlFor = (name, id) => routes[name].replace('__ID__', id);
            const currentPage = () => new URLSearchParams(window.location.search).get('page') || 1;
            const reload = () => window.loadData(currentPage(), false);

            function notify(icon, text) {
                Swal.fire({ icon: icon, text: text, toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
            }

            function failText(xhr, fallback) {
                return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
            }

            if ($.fn.select2) {
                $('#searchForm .select2').select2({
                    allowClear: true,
                    placeholder: 'الكل',
                    dir: @json(\App\Support\LanguageSettings::direction()),
                    width: '100%',
                });
            }

            SalesIndexFilters.bind({
                url: routes.index,
                failText: @json(__('sales_settings.ui.load_failed')),
                onData: function (response) {
                    $('#ntf-total-stat').text(Number(response.total).toLocaleString('en-US'));
                    $('#ntf-unread-stat').text(Number(response.unread).toLocaleString('en-US'));
                },
            });

            /* إجراءات الصفوف بالتفويض: الجدول يُستبدل كاملاً عند كل تحميل */
            const $results = $('#results-container');

            // ── عرض ──
            $results.on('click', '.view-notification', function (event) {
                event.preventDefault();

                const id = $(this).data('id');

                $('#notificationTitle').text('');
                $('#notificationBody').html(loadingHtml);
                $('#notificationModal').modal('show');

                $.ajax({ url: urlFor('show', id), method: 'GET', dataType: 'json' })
                    .done(function (response) {
                        if (!response.success) {
                            $('#notificationBody').html(errorHtml);
                            return;
                        }

                        $('#notificationTitle').text(response.notification.title || '');
                        $('#notificationBody').html(response.html);

                        // فتحُ الإشعار قراءةٌ له
                        if ($results.find('tr[data-notification-id="' + id + '"]').hasClass('ntf-unread')) {
                            markRead(id, true);
                        }
                    })
                    .fail(function (xhr) {
                        $('#notificationBody').html(
                            '<div class="alert alert-danger mb-0">' + $('<i>').text(failText(xhr, 'تعذّر تحميل الإشعار')).html() + '</div>'
                        );
                    });
            });

            // زرُّ «الرد» داخل نافذة العرض يفتح نافذة الرد — تُغلق الأولى قبله
            $('#notificationModal').on('click', '#replyButton', function () {
                $('#notificationModal').modal('hide');
            });

            // ── رد ── (النافذة والإرسال في `layouts.header`)
            $results.on('click', '.reply-notification', function (event) {
                event.preventDefault();

                $('#reply_notification_id').val($(this).data('id'));
                $('#originalNotificationTitle').text($(this).data('title'));
                $('#originalSender').text($(this).data('sender'));
                $('#reply_message').val('');
                $('#charCount').text('0');
                $('#replyModal').modal('show');
            });

            $(document).on('notification:replied', reload);

            // ── تعليم كمقروء ──
            function markRead(id, silent) {
                $.ajax({ url: routes.markRead, method: 'POST', data: { id: id, _token: token } })
                    .done(function () {
                        if (!silent) {
                            notify('success', 'تم تعليم الإشعار كمقروء');
                        }
                        reload();
                        $(document).trigger('notification:changed');
                    })
                    .fail(function (xhr) {
                        if (!silent) {
                            notify('error', failText(xhr, 'تعذّر تعليم الإشعار كمقروء'));
                        }
                    });
            }

            $results.on('click', '.mark-read', function (event) {
                event.preventDefault();
                markRead($(this).data('id'), false);
            });

            // ── تعليم الكل كمقروء — «الكل» هو ما تعرضه الفلاتر الحالية ──
            $('#mark-all-read').on('click', function () {
                const unread = $('#ntf-unread-stat').text();

                if (parseInt(unread.replace(/[^0-9]/g, ''), 10) === 0) {
                    notify('info', 'لا إشعارات غير مقروءة في البحث الحالي');
                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: 'تعليم الكل كمقروء',
                    text: 'سيُعلَّم ' + unread + ' إشعاراً غير مقروء يطابق البحث الحالي كمقروء.',
                    showCancelButton: true,
                    confirmButtonText: 'تعليم كمقروء',
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true,
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    const query = new URLSearchParams(window.location.search);
                    query.delete('page');

                    $.ajax({ url: routes.markAll + '?' + query.toString(), method: 'POST', data: { _token: token } })
                        .done(function () {
                            notify('success', 'تم تعليم الإشعارات كمقروءة');
                            window.loadData(1, false);
                            $(document).trigger('notification:changed');
                        })
                        .fail(xhr => notify('error', failText(xhr, 'تعذّر تعليم الإشعارات كمقروءة')));
                });
            });

            // ── حذف ──
            $results.on('click', '.delete-notification', function (event) {
                event.preventDefault();

                const id = $(this).data('id');

                Swal.fire({
                    icon: 'warning',
                    title: 'حذف الإشعار',
                    text: $(this).data('title') || '',
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_settings.ui.delete')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true,
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({ url: urlFor('destroy', id), method: 'POST', data: { _method: 'DELETE', _token: token } })
                        .done(function (response) {
                            notify('success', (response && response.message) || 'تم حذف الإشعار');
                            reload();
                            $(document).trigger('notification:changed');
                        })
                        .fail(xhr => notify('error', failText(xhr, 'تعذّر حذف الإشعار')));
                });
            });

            // رابطٌ قديم `?reply_to=ID` — يفتح نافذة الرد على ذلك الإشعار
            const replyTo = new URLSearchParams(window.location.search).get('reply_to');

            if (replyTo) {
                $.ajax({ url: urlFor('show', replyTo), method: 'GET', dataType: 'json' }).done(function (response) {
                    if (!response.success) return;

                    $('#reply_notification_id').val(replyTo);
                    $('#originalNotificationTitle').text(response.notification.title || '');
                    $('#originalSender').text(response.notification.user ? response.notification.user.name : 'النظام');
                    $('#reply_message').val('');
                    $('#replyModal').modal('show');
                });
            }
        });
    </script>
@endsection
