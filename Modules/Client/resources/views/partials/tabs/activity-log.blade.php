<div class="tab-pane fade" id="activity-log" role="tabpanel">
    <div class="row">
        <div class="col-12">
            <!-- قسم طلبات تغيير الحالة المعلقة (للإدارة) -->
            @php
                $hasApprovePermission = false;
                try {
                    $hasApprovePermission = hasPermission('clients_approve_status');
                } catch (\Exception $e) {
                    $hasApprovePermission = false;
                }
            @endphp
            @if(isset($statusRequests) && $statusRequests->where('status', 'pending')->count() > 0 && (auth()->user()->hasRole(['admin', 'manager']) || $hasApprovePermission))
                <div class="card border-warning mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fa fa-bell me-2"></i> طلبات تغيير حالة العميل بانتظار الاعتماد</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>تاريخ الطلب</th>
                                        <th>بواسطة</th>
                                        <th>الحالة القديمة</th>
                                        <th>الحالة الجديدة</th>
                                        <th>ملاحظات</th>
                                        <th>الإجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($statusRequests->where('status', 'pending') as $request)
                                        <tr>
                                            <td>{{ $request->created_at->format('Y-m-d H:i') }}</td>
                                            <td>{{ $request->requester->name ?? 'غير معروف' }}</td>
                                            <td>
                                                @if($request->oldStatus)
                                                    <span class="badge" style="background-color: {{ $request->oldStatus->color ?? '#6c757d' }}">{{ $request->oldStatus->name }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($request->newStatus)
                                                    <span class="badge" style="background-color: {{ $request->newStatus->color ?? '#28a745' }}">{{ $request->newStatus->name }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $request->notes ?? '-' }}</td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <form action="{{ route('clients.status-requests.approve', $request->id) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('هل أنت متأكد من اعتماد هذا الطلب؟')">
                                                            <i class="fa fa-check"></i> اعتماد
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('clients.status-requests.reject', $request->id) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟')">
                                                            <i class="fa fa-times"></i> رفض
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- سجل نشاطات العميل — بطاقاتٌ بتصميم «ملاحظات الزيارات»، ثلاثٌ في
                 الصف، الأحدث أولاً. الكنترولر يُرسل السجلّات مجمَّعةً باليوم،
                 والتاريخ صار داخل كل بطاقة فتُفرَد هنا. --}}
            @php
                $alLogs = collect();
                if (isset($activityLogs) && count($activityLogs) > 0) {
                    $alFirst = $activityLogs->first();
                    $alLogs = ($alFirst instanceof \Illuminate\Support\Collection || is_array($alFirst))
                        ? $activityLogs->collapse()
                        : collect($activityLogs);
                    $alLogs = $alLogs->filter()->values();
                }
                $alStatusIds = $alLogs->pluck('old_value')->filter(fn ($v) => is_numeric($v))->unique()->values();
                $alStatuses = $alStatusIds->isNotEmpty()
                    ? \App\Models\Statuses::whereIn('id', $alStatusIds)->get()->keyBy('id')
                    : collect();
            @endphp
            <div class="vn-wrap">
                <div class="vn-section-head">
                    <div class="vn-section-title">
                        <span class="vn-section-icon"><i class="fas fa-history"></i></span>
                        <div>
                            سجل نشاطات العميل
                            <span class="vn-section-count">{{ $alLogs->count() }} نشاط</span>
                        </div>
                    </div>
                </div>

                @if ($alLogs->isNotEmpty())
                    <div class="vn-grid" id="vn-activity-grid">
                        @foreach ($alLogs as $log)
                            @php
                                $alDesc = (string) ($log->description ?? '');
                                $alIsStatus = str_contains($alDesc, 'تغيير حالة العميل')
                                    || str_contains($alDesc, 'تم اعتماد طلب تغيير حالة')
                                    || str_contains($alDesc, 'رفع طلب تغيير حالة');
                                $alText = $alDesc !== ''
                                    ? trim(html_entity_decode(strip_tags(\Illuminate\Support\Str::markdown($alDesc))))
                                    : 'لا يوجد وصف';
                                $alOldStatus = $log->old_value ? $alStatuses->get($log->old_value) : null;
                                $alNewStatus = str_contains($alDesc, 'نشط') ? 'نشط' : (str_contains($alDesc, 'محل مسكر') ? 'محل مسكر' : 'موقوف');
                                $alUser = $log->user->name ?? 'نظام';
                            @endphp
                            <article class="vn-card" @if ($loop->iteration > 9) hidden data-vn-extra @endif>
                                <header class="vn-head">
                                    <span class="vn-num vn-num-{{ ['success', 'primary', 'warning'][$loop->index % 3] }}">{{ $loop->iteration }}</span>
                                    <div class="vn-who">
                                        <div class="vn-title">
                                            <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                            <span title="{{ $alUser }}">{{ $alUser }}</span>
                                        </div>
                                        <div class="vn-sub">
                                            <span><i class="fas fa-code-branch"></i>{{ $log->user->branch->name ?? 'فرع غير معروف' }}</span>
                                        </div>
                                    </div>
                                    <div class="vn-when">
                                        @if ($log->created_at)
                                            <span><i class="fas fa-calendar-alt"></i>{{ $log->created_at->format('Y-m-d') }}</span>
                                            <span><i class="fas fa-clock"></i>{{ $log->created_at->translatedFormat('h:i A') }}</span>
                                            <span><i class="fas fa-calendar-day"></i>{{ $log->created_at->translatedFormat('l') }}</span>
                                        @endif
                                    </div>
                                </header>

                                <div class="vn-note" title="{{ $alText }}">
                                    <p><span class="vn-note-label">{{ $alIsStatus ? 'تغيير حالة:' : 'النشاط:' }}</span> {{ $alText }}</p>
                                    <span class="vn-note-icon"><i class="fas {{ $alIsStatus ? 'fa-exchange-alt' : 'fa-file-alt' }}"></i></span>
                                </div>

                                @if ($alIsStatus && $log->old_value)
                                    <div class="vn-facts vn-facts-2">
                                        <div class="vn-fact">
                                            <div class="vn-fact-label"><i class="fas fa-arrow-circle-right vn-ic-doc"></i> من حالة</div>
                                            <span class="vn-badge" style="background-color: {{ $alOldStatus->color ?? '#6c757d' }}">{{ $alOldStatus->name ?? 'غير معروف' }}</span>
                                        </div>
                                        <div class="vn-fact">
                                            <div class="vn-fact-label"><i class="fas fa-arrow-circle-left vn-ic-comp"></i> إلى حالة</div>
                                            <span class="vn-badge" style="background-color: var(--vn-primary)">{{ $alNewStatus }}</span>
                                        </div>
                                    </div>
                                @elseif ($log->old_value)
                                    <div class="vn-facts vn-facts-1">
                                        <div class="vn-fact">
                                            <div class="vn-fact-label"><i class="fas fa-undo vn-ic-custody"></i> قيمة سابقة</div>
                                            <div class="vn-empty"><code>{{ $log->old_value }}</code></div>
                                        </div>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    @if ($alLogs->count() > 9)
                        <div class="vn-more-wrap">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-vn-more="vn-activity-grid">
                                <i class="fa fa-chevron-down me-1"></i>عرض كل النشاطات ({{ $alLogs->count() }})
                            </button>
                        </div>
                    @endif
                @else
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h5>لا توجد سجلات نشاط</h5>
                        <p>كل تعديلٍ على ملف العميل يظهر هنا</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    // «عرض الكل» في سجلّ النشاط: البطاقات بعد التاسعة مخفيّة حتى يُطلب
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-vn-more]');
        if (!btn) return;
        var grid = document.getElementById(btn.getAttribute('data-vn-more'));
        if (grid) {
            grid.querySelectorAll('[data-vn-extra]').forEach(function (el) { el.hidden = false; });
        }
        btn.parentNode.remove();
    });

    // الرجوع إلى الملف بزرّ «رجوع» في المتصفح قد يعرض نسخةً محفوظة بلا طلبٍ للخادم،
    // فلا يُسجَّل الدخول ولا تتحدّث البيانات — نُعيد التحميل في هذه الحالة وحدها
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) window.location.reload();
    });
</script>
