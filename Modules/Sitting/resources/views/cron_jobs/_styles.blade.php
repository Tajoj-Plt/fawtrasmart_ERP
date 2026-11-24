{{-- أنماط مشتركة بين شاشات المهام المجدولة والنسخ الاحتياطي.
     مفصولة في ملف واحد كي لا تتكرّر في خمس شاشات فتتباعد مع التعديل. --}}
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

@include('sitting::components._stat_cards')

<style>


    /* مفتاح تشغيل أفقي — مفاتيح Bootstrap تنكسر في RTL فيهبط النص تحتها */
    .cj-switch { display: flex; align-items: center; gap: .5rem; cursor: pointer; margin: 0; }
    .cj-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .cj-switch .track {
        position: relative; flex: 0 0 auto; width: 42px; height: 22px;
        background: #d9dee5; border-radius: 999px; transition: background .18s;
    }
    .cj-switch .track::after {
        content: ''; position: absolute; top: 3px; right: 3px;
        width: 16px; height: 16px; background: #fff; border-radius: 50%;
        transition: transform .18s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
    }
    .cj-switch input:checked + .track { background: #28c76f; }
    .cj-switch input:checked + .track::after { transform: translateX(-20px); }
    .cj-switch input:disabled + .track { opacity: .5; cursor: not-allowed; }

    /* تعبير Cron وأسماء الأوامر لاتينية دائماً — بلا هذا تنقلب في RTL */
    .cj-mono {
        direction: ltr; display: inline-block; unicode-bidi: embed;
        font-family: Consolas, Monaco, 'Courier New', monospace;
        font-size: .8rem; background: #f4f5f7; color: #384250;
        padding: .12rem .4rem; border-radius: 5px; border: 1px solid #e6e9ef;
    }
    .cj-mono.lg { font-size: .95rem; padding: .35rem .7rem; }

    .cj-row-off td { opacity: .55; }
    .cj-sub { font-size: .78rem; color: #6b7280; }

    .cj-health {
        display: flex; align-items: flex-start; gap: .8rem;
        padding: 1rem 1.15rem; border-radius: 10px; margin-bottom: 1rem;
    }
    .cj-health.healthy  { background: #eafaf1; border: 1px solid #b7e8cd; color: #1b6b45; }
    .cj-health.warning  { background: #fff8e6; border: 1px solid #ffe0a3; color: #8a5b00; }
    .cj-health.failed   { background: #fdecea; border: 1px solid #f8c4be; color: #92211a; }
    .cj-health.unknown  { background: #f4f5f7; border: 1px solid #e0e3e9; color: #4b5563; }
    .cj-health .dot { width: 11px; height: 11px; border-radius: 50%; margin-top: .35rem; flex: 0 0 auto; }
    .cj-health.healthy .dot { background: #28c76f; }
    .cj-health.warning .dot { background: #ff9f43; }
    .cj-health.failed  .dot { background: #ea5455; }
    .cj-health.unknown .dot { background: #b8c0cc; }

    .cj-actions { display: inline-flex; gap: .25rem; flex-wrap: nowrap; }
    .cj-actions .btn { padding: .28rem .5rem; line-height: 1; }

    .cj-out {
        direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-word;
        background: #1e1e2d; color: #d6d9e0; padding: 1rem; border-radius: 8px;
        font-family: Consolas, Monaco, 'Courier New', monospace; font-size: .78rem;
        max-height: 420px; overflow: auto; margin: 0;
    }
    .cj-out.err { background: #2d1b1b; color: #f3b9b6; }

    .cj-def { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px dashed #e6e9ef; }
    .cj-def:last-child { border-bottom: 0; }
    .cj-def dt { color: #6b7280; font-weight: 500; margin: 0; font-size: .85rem; }
    .cj-def dd { margin: 0; font-weight: 600; text-align: left; }

    .cj-danger-zone { border: 1px solid #f8c4be; border-radius: 10px; background: #fffafa; }
    .cj-danger-zone .card-header { border-bottom: 1px solid #f8c4be; }

    /* منتقي جداول النسخة الاحتياطية */
    .bk-picker { border: 1px solid #e0e3e9; border-radius: 8px; overflow: hidden; }
    .bk-picker-bar {
        display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
        padding: .5rem; background: #f8f9fb; border-bottom: 1px solid #e6e9ef;
    }
    .bk-picker-bar .bk-picker-search { flex: 1 1 180px; min-width: 140px; }
    .bk-picker-bar .btn { padding: .2rem .55rem; }
    .bk-picker-count { margin-right: auto; white-space: nowrap; }
    .bk-picker-list { max-height: 300px; overflow-y: auto; }
    .bk-picker-item {
        display: flex; align-items: center; gap: .5rem; margin: 0;
        padding: .35rem .6rem; border-bottom: 1px solid #f1f3f6; cursor: pointer;
    }
    .bk-picker-item:last-child { border-bottom: 0; }
    .bk-picker-item:hover { background: #f8f9fb; }
    .bk-picker-item input { flex: 0 0 auto; margin: 0; }
    .bk-picker-meta { margin-right: auto; white-space: nowrap; }
    .bk-picker-item.bk-hidden { display: none; }

    /* منتقي أقسام البيانات: بطاقات لا سطوراً — للقسم عنوان ووصف وحجم،
       والسطر الواحد لا يتّسع لها فيضيع الوصف وهو أهمّ ما يميّز الأقسام */
    .bk-sec-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: .5rem; padding: .6rem; max-height: 340px;
    }
    .bk-picker-item.bk-sec {
        align-items: flex-start; border: 1px solid #e6e9ef; border-radius: 8px;
        padding: .6rem; background: #fff; transition: border-color .15s, box-shadow .15s;
    }
    .bk-picker-item.bk-sec:hover { border-color: #c6d0e0; background: #fff; }
    /* البطاقة المؤشَّرة تُقرأ من نظرة واحدة — العلامة وحدها لا تكفي في شبكة */
    .bk-picker-item.bk-sec:has(input:checked) {
        border-color: #7367f0; box-shadow: 0 0 0 1px #7367f0 inset; background: #f6f5ff;
    }
    .bk-sec input { margin-top: .2rem; }
    .bk-sec-body { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
    .bk-sec-title { font-weight: 600; font-size: .9rem; }
    .bk-sec-title i { color: #7367f0; margin-left: .25rem; }
    .bk-sec-hint { line-height: 1.45; }
    .bk-sec-meta { color: #9aa2b1; font-size: .75rem; }

    /* «خيارات متقدّمة»: مطويّة افتراضياً — ما فيها لا يحتاجه إلا من يعرف
       ما يفعله، وظهورها في الصفحة الأولى هو ما كان يُربك الشاشة */
    .bk-adv { border: 1px dashed #d8dce4; border-radius: 8px; background: #fbfbfd; }
    .bk-adv > summary {
        cursor: pointer; padding: .6rem .8rem; font-weight: 600; font-size: .88rem;
        color: #6b7280; list-style: none; display: flex; align-items: center; gap: .4rem;
    }
    .bk-adv > summary::-webkit-details-marker { display: none; }
    .bk-adv > summary::before { content: '▸'; transition: transform .15s; display: inline-block; }
    .bk-adv[open] > summary::before { transform: rotate(-90deg); }
    .bk-adv-body { padding: 0 .8rem .8rem; border-top: 1px solid #eceef3; padding-top: .8rem; }

    /* بطاقات «ما الذي يُحفَظ» — اختيار واحد من ثلاثة بصريّاً لا قائمة راديو */
    .bk-choice { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .6rem; }
    .bk-choice label {
        display: block; margin: 0; padding: .8rem; cursor: pointer;
        border: 1px solid #e6e9ef; border-radius: 10px; background: #fff;
    }
    .bk-choice label:hover { border-color: #c6d0e0; }
    .bk-choice input { position: absolute; opacity: 0; pointer-events: none; }
    .bk-choice label:has(input:checked) { border-color: #7367f0; box-shadow: 0 0 0 1px #7367f0 inset; background: #f6f5ff; }
    .bk-choice-title { font-weight: 600; display: flex; align-items: center; gap: .4rem; }
    .bk-choice-title i { color: #7367f0; }
    .bk-choice-hint { color: #7c8494; font-size: .8rem; line-height: 1.5; margin-top: .3rem; }
    .bk-choice-tag {
        display: inline-block; font-size: .7rem; padding: .05rem .4rem; border-radius: 4px;
        background: #eafaf1; color: #1b6b45; margin-right: .3rem;
    }
</style>

<script>
    // منتقي الجداول: بحث وتحديد جماعي وعدّاد. يعمل على أي عدد من النسخ
    // في الصفحة، ويُربط بالتفويض لأن النافذة المنبثقة قد تُبنى بعد التحميل
    (function () {
        function refresh(picker) {
            var boxes = picker.querySelectorAll('.bk-picker-item input');
            var checked = picker.querySelectorAll('.bk-picker-item input:checked').length;
            var counter = picker.querySelector('.bk-picker-count');

            if (counter) {
                counter.textContent = @json(__('settings.layout.selected_of')).replace(':selected', checked).replace(':total', boxes.length);
            }
        }

        function visibleBoxes(picker) {
            return Array.prototype.filter.call(
                picker.querySelectorAll('.bk-picker-item'),
                function (item) { return !item.classList.contains('bk-hidden'); }
            ).map(function (item) { return item.querySelector('input'); });
        }

        document.addEventListener('input', function (event) {
            var picker = event.target.closest('.bk-picker');

            if (!picker) return;

            if (event.target.classList.contains('bk-picker-search')) {
                var term = event.target.value.trim().toLowerCase();

                picker.querySelectorAll('.bk-picker-item').forEach(function (item) {
                    var match = item.getAttribute('data-name').toLowerCase().indexOf(term) !== -1;
                    item.classList.toggle('bk-hidden', !match);
                });
            }

            refresh(picker);
        });

        document.addEventListener('click', function (event) {
            var picker = event.target.closest('.bk-picker');

            if (!picker) return;

            // «تحديد الكل» يعمل على المعروض بعد البحث لا على القائمة كلّها —
            // وإلا فاجأ زرٌّ بعد بحثٍ بتحديد مئة جدول غير ظاهرة
            if (event.target.classList.contains('bk-picker-all')) {
                visibleBoxes(picker).forEach(function (box) { box.checked = true; });
            } else if (event.target.classList.contains('bk-picker-none')) {
                visibleBoxes(picker).forEach(function (box) { box.checked = false; });
            } else if (event.target.type !== 'checkbox') {
                return;
            }

            refresh(picker);
        });

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.bk-picker').forEach(refresh);
        });
    })();
</script>
