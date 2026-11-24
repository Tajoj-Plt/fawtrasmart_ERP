@if ($logs && $logs->count() > 0)
    @php
        $previousDate = null;
    @endphp

    @foreach ($logs as $log)
        @php
            $currentDate = \Carbon\Carbon::parse($log->created_at);
            $diffInDays = $previousDate ? $previousDate->diffInDays($currentDate) : 0;
        @endphp

        @if ($loop->first || $diffInDays > 0)
            @if (!$loop->first)
                </div>
            @endif
            <div class="stk-section__head">
                <i class="feather icon-calendar"></i>
                <h6 class="stk-section__title">{{ $currentDate->translatedFormat('l, d M Y') }}</h6>
            </div>
            <div class="stk-timeline mb-3">
        @endif

        @if ($log)
            <div class="stk-timeline__item">
                <span class="stk-timeline__dot stk-timeline__dot--log">
                    <i class="feather icon-activity"></i>
                </span>

                <div class="stk-timeline__body">
                    <div class="stk-timeline__text">
                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                        {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                    </div>

                    <div class="stk-timeline__meta">
                        <span class="stk-timeline__time">{{ $log->created_at->format('H:i:s') }}</span>
                        <span>{{ $log->user->branch->name ?? 'فرع غير معروف' }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if ($loop->last)
            </div>
        @endif

        @php
            $previousDate = $currentDate;
        @endphp
    @endforeach

    {{-- الصفحات --}}
    @if ($logs && is_object($logs) && method_exists($logs, 'hasPages') && $logs->hasPages())
        <nav aria-label="Activity logs pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                {{-- أول صفحة --}}
                @if ($logs->onFirstPage())
                    <li class="page-item disabled"><span class="page-link">الأول</span></li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadActivityLogs(1)">الأول</a>
                    </li>
                @endif

                {{-- السابق --}}
                @if ($logs->onFirstPage())
                    <li class="page-item disabled"><span class="page-link">السابق</span></li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadActivityLogs({{ $logs->currentPage() - 1 }})">السابق</a>
                    </li>
                @endif

                {{-- أرقام الصفحات --}}
                @foreach ($logs->getUrlRange(max($logs->currentPage() - 2, 1), min($logs->currentPage() + 2, $logs->lastPage())) as $page => $url)
                    @if ($page == $logs->currentPage())
                        <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" onclick="loadActivityLogs({{ $page }})">{{ $page }}</a>
                        </li>
                    @endif
                @endforeach

                {{-- التالي --}}
                @if ($logs->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadActivityLogs({{ $logs->currentPage() + 1 }})">التالي</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">التالي</span></li>
                @endif

                {{-- آخر صفحة --}}
                @if ($logs->currentPage() < $logs->lastPage())
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadActivityLogs({{ $logs->lastPage() }})">الأخير</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">الأخير</span></li>
                @endif
            </ul>
        </nav>
    @endif
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-list"></i></span>
        <h6 class="stk-empty__title">لا توجد سجلات نشاطات</h6>
        <p class="stk-empty__hint">لا توجد سجلات نشاطات حتى الآن.</p>
    </div>
@endif
