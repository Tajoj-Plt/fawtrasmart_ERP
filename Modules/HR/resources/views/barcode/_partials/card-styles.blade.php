{{--
    أنماطُ بطاقة الحضور — مشتركةٌ بين الطباعة المفردة وطباعة الدفعة.

    نسختان تتباعدان بأوّل تعديل: تُضبط البطاقةُ في شاشةٍ فتبقى الأخرى على
    مقاسها القديم، فتخرج دفعةٌ لا تُطابق ما رآه من طبعها منفردة.
--}}
<style>
        /* ══════════ الأساس ══════════ */
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --ink:      #0f172a;
            --ink-soft: #475569;
            --ink-mute: #94a3b8;
            --line:     #e2e8f0;
            --brand:    #1d4ed8;
            --brand-dk: #172554;
            --paper:    #f1f5f9;
        }

        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        body {
            font-family: 'Cairo', system-ui, sans-serif;
            margin: 0;
            padding: 28px 20px 56px;
            background: var(--paper);
            color: var(--ink);
        }

        /* ══════════ شريط الإجراءات (لا يُطبَع) ══════════ */
        .bar {
            max-width: 760px;
            margin: 0 auto 26px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: 0 1px 2px rgba(15,23,42,.04);
        }
        .bar__who   { min-width: 0; }
        .bar__name  { font-weight: 700; font-size: 15px; }
        .bar__meta  { font-size: 12.5px; color: var(--ink-mute); margin-top: 2px; }
        .bar__acts  { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn {
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            border-radius: 9px;
            padding: 9px 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink-soft);
            transition: .15s;
        }
        .btn:hover        { border-color: var(--ink-mute); color: var(--ink); }
        .btn--go          { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn--go:hover    { background: var(--brand-dk); border-color: var(--brand-dk); color: #fff; }
        .btn svg          { width: 15px; height: 15px; flex: none; }

        /* ══════════ ورقةُ البطاقات ══════════ */
        .sheet {
            max-width: 760px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
            justify-content: center;
        }

        /*
            مقاسٌ ثابتٌ بالمِلّيمتر لا بالبكسل: البطاقةُ تُقصّ بالمقصّ
            وتُوضع في حاملة، فقياسُها جزءٌ من وظيفتها لا من مظهرها.
        */
        .card {
            width: 85.6mm;
            height: 54mm;
            background: #fff;
            border-radius: 3.2mm;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 10px rgba(15,23,42,.10);
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* ── الوجه الأمامي ── */
        .card__top {
            background: var(--brand-dk);
            color: #fff;
            padding: 2.4mm 4mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3mm;
        }
        .card__org   { font-size: 3.1mm; font-weight: 700; letter-spacing: .1px; }
        .card__kind  { font-size: 2.3mm; opacity: .78; white-space: nowrap; }

        .card__body {
            flex: 1;
            display: flex;
            gap: 3.4mm;
            padding: 3mm 4mm 2mm;
            min-height: 0;
        }

        .avatar {
            position: relative;
            width: 16mm;
            height: 20mm;
            border-radius: 1.6mm;
            flex: none;
            object-fit: cover;
            background: #e8edf5;
            border: .3mm solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b6c2d4;
            overflow: hidden;
        }
        .avatar svg { width: 8mm; height: 8mm; }
        /* الصورةُ تعلو البديل؛ فإن أخفق تحميلُها أزالت نفسَها فظهر البديل */
        .avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }

        .who { min-width: 0; flex: 1; display: flex; flex-direction: column; }
        .who__name {
            font-weight: 700;
            /*
                ١٫٤ لا ١٫٢٥: الحروفُ العربية ذاتُ صعودٍ ونزولٍ أطول من
                اللاتينية، والسطرُ الضيّق يقصّ «الشرعبي» في منتصف حرفها
                حين يُقصّ الصندوقُ على سطرَين.
            */
            line-height: 1.4;
            /* سطران ثم يُقصّ — ولا يدفع الباركودَ خارج البطاقة */
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .who__role { font-size: 2.7mm; color: var(--ink-soft); margin-top: .6mm; }
        .who__rows { margin-top: auto; display: flex; gap: 4mm; font-size: 2.4mm; }
        .who__k    { color: var(--ink-mute); display: block; margin-bottom: .3mm; }
        .who__v    { font-weight: 600; letter-spacing: .2px; }

        /* ── الباركود: وظيفةُ البطاقة، فله أوسعُ مساحةٍ ممكنة ── */
        .strip {
            border-top: .25mm solid var(--line);
            padding: 1.4mm 4mm 2mm;
            text-align: center;
        }
        .strip svg { width: 100%; height: 9mm; display: block; }
        .strip__code {
            font-family: ui-monospace, 'Courier New', monospace;
            font-size: 2.5mm;
            letter-spacing: .6px;
            color: var(--ink-soft);
            margin-top: .8mm;
        }

        /* ── الوجه الخلفي ── */
        .card--back { padding: 4mm; }
        .back__head {
            font-size: 2.9mm; font-weight: 700;
            padding-bottom: 1.4mm; margin-bottom: 1.8mm;
            border-bottom: .25mm solid var(--line);
        }
        .back__grid { flex: 1; display: flex; gap: 4mm; min-height: 0; }
        .back__qr   { width: 24mm; flex: none; text-align: center; }
        .back__qr svg { width: 24mm; height: 24mm; display: block; }
        .back__qr small { font-size: 2.1mm; color: var(--ink-mute); display: block; margin-top: 1mm; }
        .back__list { margin: 0; padding-inline-start: 4mm; font-size: 2.5mm; line-height: 1.75; color: var(--ink-soft); }
        .back__foot {
            margin-top: 1.6mm; padding-top: 1.4mm;
            border-top: .25mm solid var(--line);
            font-size: 2.2mm; color: var(--ink-mute);
            display: flex; justify-content: space-between; gap: 3mm;
        }

        .card__label {
            text-align: center; font-size: 12px; color: var(--ink-mute);
            margin-top: 8px; font-weight: 600;
        }
        .card__wrap { display: flex; flex-direction: column; align-items: center; }

        /* ══════════ الطباعة ══════════ */
        @page { size: A4; margin: 12mm; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            /*
                `gap` لا هوامشُ على البطاقات: الهامشُ على كلٍّ منها يُنشئ
                فجوةً قبل الأولى في الصفّ أيضاً، فتُزاح الورقةُ كلُّها.
                وصفٌّ من بطاقتين ‎٨٥٫٦×٢ + ٦ = ١٧٧٫٢مم‏ يسع في ١٨٦مم
                (A4 بعد هوامش ١٢مم)، وأربعةُ صفوفٍ تسع في ارتفاعها.
            */
            .sheet { max-width: none; gap: 8mm 6mm; justify-content: flex-start; }

            /*
                حدٌّ رفيعٌ بدل الظلّ: الظلُّ يخرج من الطابعة رماديّاً متّسخاً،
                والحدُّ هو ما يُقصّ عليه.
            */
            .card {
                box-shadow: none;
                border: .25mm solid #cbd5e1;
            }
        }

        /* ══════════ شاشاتٌ ضيّقة ══════════ */
        @media (max-width: 480px) {
            body { padding: 14px 10px 40px; }
            .bar { flex-direction: column; align-items: stretch; }
            .bar__acts { justify-content: stretch; }
            .btn { flex: 1; justify-content: center; }
        }
</style>
