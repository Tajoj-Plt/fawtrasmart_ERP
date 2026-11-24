{{--
    الوجهُ الأمامي للبطاقة.

    @param $employee     الموظف
    @param $barcodeSvg   باركود CODE128 مُصيَّراً في الخادم
    @param $photoUrl     رابطُ الصورة أو null (يُفحَص وجودُ الملفّ في المتحكّم)
    @param $nameSize     مقاسُ الخطّ بحسب طول الاسم
    @param $cardId       اختياري — لتمييز بطاقةٍ بعينها في الصفحة
--}}
        <div class="card" @isset($cardId) id="{{ $cardId }}" @endisset>
            <div class="card__top">
                <span class="card__org">{{ \App\Support\OrganizationInfo::name() }}</span>
                <span class="card__kind">بطاقة حضور وانصراف</span>
            </div>

            <div class="card__body">
                {{--
                    البديلُ حاضرٌ دائماً خلف الصورة — بحجمها نفسِه، فالبطاقةُ
                    لا تتزحزح بوجود صورةٍ أو غيابها. و`$photoUrl` لا يصل إلا
                    إذا كان الملفُّ موجوداً على القرص فعلاً (انظر المتحكّم)،
                    و`onerror` احتياطٌ لما يتلف بعد الفحص.
                --}}
                <div class="avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>

                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" onerror="this.remove()">
                    @endif
                </div>

                <div class="who">
                    <div class="who__name" style="font-size: {{ $nameSize }}">{{ $employee->full_name }}</div>
                    <div class="who__role">
                        {{ $employee->job_title->name ?? $employee->department->name ?? 'موظف' }}
                    </div>

                    <div class="who__rows">
                        <div>
                            <span class="who__k">الرقم الوظيفي</span>
                            <span class="who__v">{{ $employee->employee_code ?? $employee->id }}</span>
                        </div>
                        @if ($employee->branch)
                            <div>
                                <span class="who__k">الفرع</span>
                                <span class="who__v">{{ $employee->branch->name }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="strip">
                {!! $barcodeSvg !!}
                <div class="strip__code">{{ $employee->barcode }}</div>
            </div>
        </div>
