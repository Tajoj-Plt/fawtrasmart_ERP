{{--
    فروع شجرة الحسابات وقيود كل فرع — جزئية تُنادي نفسها لكل مستوًى.

    **عطلٌ كان فيها:** الاستدعاء الذاتي يشير إلى `accounts.partials.children`
    — مسارٌ لا وجود له في المشروع؛ الجزئية مسجَّلة تحت
    `account::partials.children`. فأيّ حسابٍ له أبناء يُسقط الصفحة بـ
    «View not found» عند أول نزولٍ إلى المستوى الثاني. الشجرة تعمل ما دامت
    مسطَّحة، وتنكسر فور أن تصير شجرة.

    الوسائط: `children` — مجموعة حسابات.
--}}

@foreach ($children as $child)
    <div class="stk-tree__row">
        <div class="stk-tree__cell">
            <span class="stk-tree__icon"><i class="feather icon-folder"></i></span>
            <span class="stk-tree__name">
                {{ $child->name }}
                <small class="stk-num">{{ $child->code }}</small>
            </span>
        </div>

        @if ($child->journalEntries->count() > 0)
            <div class="stk-def stk-def--inline">
                @foreach ($child->journalEntries as $entry)
                    <div class="stk-def__row">
                        <span>{{ $entry->description }}</span>
                        <strong>@money($entry->debit)</strong>
                    </div>
                @endforeach
            </div>
        @else
            <p class="stk-help mb-0">لا قيود على هذا الحساب.</p>
        @endif

        @if ($child->children->count() > 0)
            <div class="stk-tree">
                @include('account::partials.children', ['children' => $child->children])
            </div>
        @endif
    </div>
@endforeach
