{{--
    سجلُّ النشاط — مجمَّعٌ باليوم (المتحكّم يجمعه).

    الوصفُ يحمل `اسم` بتنسيق Markdown، والاسمُ نصٌّ أدخله المستخدم؛
    فيُحوَّل بـ`html_input => escape` — كان يُعرض بـ`{!! Str::markdown() !!}`
    الذي يمرّر HTML الخام، فاسمُ قسمٍ فيه وسمُ `<script>` يُنفَّذ في الصفحة.
--}}
@if ($logs->isNotEmpty())
    <ul class="stk-timeline">
        @foreach ($logs as $date => $dayLogs)
            <li class="stk-timeline__day">
                <span>{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l، d F Y') }}</span>
            </li>
            @foreach ($dayLogs as $log)
                <li class="stk-timeline__item">
                    <span class="stk-timeline__dot stk-timeline__dot--log">
                        <i class="feather icon-edit-3"></i>
                    </span>
                    <div class="stk-timeline__body">
                        <p class="stk-timeline__head">
                            <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                            <span class="stk-timeline__time stk-num">
                                <i class="feather icon-clock"></i> {{ $log->created_at->format('H:i:s') }}
                            </span>
                        </p>
                        <div class="stk-timeline__text">
                            {!! \Illuminate\Support\Str::inlineMarkdown($log->description ?? 'لا يوجد وصف', ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                        </div>
                        <p class="stk-timeline__meta">
                            <span><i class="feather icon-map-pin"></i> {{ $log->user->branch->name ?? 'فرع غير معروف' }}</span>
                        </p>
                    </div>
                </li>
            @endforeach
        @endforeach
    </ul>
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
        <h5 class="stk-empty__title">لا يوجد نشاط مسجَّل</h5>
        <p class="stk-empty__hint">{{ $emptyHint ?? 'يظهر هنا كلُّ إنشاءٍ وتعديلٍ وتغييرِ حالةٍ على هذا السجل' }}</p>
    </div>
@endif
