{{--
    جدول الإشعارات — يُستبدل وحده عند البحث والتنقّل بـAjax.

    نظير `sales::invoices.partials.table`. يُرسَم في الخادم للصفحة ولطلب Ajax
    معاً: كانت الصفحةُ ترسمه هنا وطلبُ Ajax يعيد بناءه بنصوص JavaScript،
    فتباعدت النسختان.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $notifications
    @param bool $canReply
    @param bool $canDelete
--}}

@php
    $canReply = isset($canReply) && $canReply === true;
    $canDelete = isset($canDelete) && $canDelete === true;
    $me = auth()->id();

    // أنواعٌ تُميَّز بلونٍ؛ غيرُها محايد
    $typeTones = [
        'urgent' => 'danger',
        'reply' => 'info',
        'reminder' => 'warning',
        'cron_job_failed' => 'danger',
    ];
@endphp

@if ($notifications->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('sales::components.per-page', ['onchange' => 'loadData(1)', 'sizes' => [10, 25, 50, 100], 'current' => $notifications->perPage()])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:38%">الإشعار</th>
                    <th style="width:14%">المرسل</th>
                    <th style="width:16%">المستلم</th>
                    <th style="width:14%">التاريخ</th>
                    <th class="text-center" style="width:10%">الحالة</th>
                    <th class="text-center" style="width:8%">الإجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($notifications as $notification)
                    @php
                        $pending = $notification->is_scheduled && ! $notification->is_sent
                            && $notification->scheduled_at && $notification->scheduled_at->isFuture();
                        $unread = ! $notification->read;

                        [$stateLabel, $stateTone] = $pending
                            ? ['مجدول', 'info']
                            : ($unread ? ['جديد', 'warning'] : ['مقروء', 'secondary']);

                        // الاسم من الإعدادات ثم ملفّ اللغة — لا مفتاحَ إنجليزياً في الشاشة
                        $typeLabel = \App\Models\notifications::typeLabel($notification->type);

                        $senderName = $notification->user->name ?? 'النظام';

                        $clientName = $notification->client
                            ? ($notification->client->trade_name ?: trim($notification->client->first_name.' '.$notification->client->last_name))
                            : null;

                        // يردّ المستلمُ على المرسل، والمرسلُ على المستلم، وعلى العامّ من ليس مرسلَه
                        $otherParty = $notification->user_id == $me ? $notification->receiver_id : $notification->user_id;
                        $mayReply = $canReply && $otherParty && $otherParty != $me
                            && ($notification->user_id == $me || $notification->receiver_id == $me || is_null($notification->receiver_id));

                        $when = ($notification->is_scheduled && $notification->scheduled_at)
                            ? $notification->scheduled_at
                            : $notification->created_at;
                    @endphp

                    <tr data-notification-id="{{ $notification->id }}" class="{{ $unread && ! $pending ? 'ntf-unread' : '' }}">
                        <td>
                            <div class="sl-media">
                                <span class="sl-media__thumb text-{{ $unread ? 'primary' : 'muted' }}"><i class="feather icon-bell"></i></span>
                                <div class="sl-media__body">
                                    <a href="#" class="sl-media__title view-notification" data-id="{{ $notification->id }}">
                                        {{ $notification->title ?: 'بدون عنوان' }}
                                    </a>
                                    <span class="sl-media__sub">{{ \Illuminate\Support\Str::limit((string) $notification->description, 90) }}</span>
                                    @if ($typeLabel)
                                        <span class="sl-media__sub d-block">
                                            <span class="badge badge-light-{{ $typeTones[$notification->type] ?? 'secondary' }}">{{ $typeLabel }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td><span class="sl-media__sub">{{ $senderName }}</span></td>

                        <td>
                            @if ($clientName)
                                <span class="sl-media__sub">{{ $clientName }}</span>
                                <span class="sl-media__sub d-block"><span class="badge badge-light-info">عميل</span></span>
                            @elseif ($notification->receiver)
                                <span class="sl-media__sub">{{ $notification->receiver->name }}</span>
                            @else
                                <span class="sl-media__sub">الجميع</span>
                            @endif
                        </td>

                        <td>
                            <span class="sl-num d-block">{{ $when ? \App\Support\LanguageSettings::formatDate($when, true) : '—' }}</span>
                            @if ($when)
                                <span class="sl-media__sub">{{ $when->diffForHumans() }}</span>
                            @endif
                        </td>

                        <td class="text-center">
                            <span class="badge badge-light-{{ $stateTone }} ntf-state">{{ $stateLabel }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                {{-- `data-bs-toggle` وحده: القالب يحمّل Bootstrap 4 و5،
                                     والوسمان معاً يجعلان الاثنين يعالجان النقرة نفسها --}}
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="الإجراءات — {{ $notification->title }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item view-notification" href="#" data-id="{{ $notification->id }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    @if ($mayReply)
                                        <a class="dropdown-item reply-notification" href="#"
                                           data-id="{{ $notification->id }}"
                                           data-title="{{ $notification->title }}"
                                           data-sender="{{ $senderName }}">
                                            <i class="feather icon-corner-up-left"></i> رد
                                        </a>
                                    @endif

                                    @if ($unread && ! $pending)
                                        <a class="dropdown-item mark-read" href="#" data-id="{{ $notification->id }}">
                                            <i class="feather icon-check"></i> تعليم كمقروء
                                        </a>
                                    @endif

                                    @if ($canDelete)
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-notification" href="#"
                                           data-id="{{ $notification->id }}" data-title="{{ $notification->title }}">
                                            <i class="feather icon-trash-2"></i> حذف
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('sales::invoices.partials.pagination', ['invoices' => $notifications])
@else
    @include('sales::components.empty-state', [
        'icon' => 'bell-off',
        'title' => 'لا توجد إشعارات',
        'hint' => 'لا إشعار يطابق البحث الحالي. جرّب مسح الفلاتر أو توسيع نطاق التاريخ.',
    ])
@endif
