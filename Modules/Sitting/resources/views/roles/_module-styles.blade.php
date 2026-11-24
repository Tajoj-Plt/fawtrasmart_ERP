{{--
    أنماط بطاقة القسم في شاشات الصلاحيات.

    القسم إطارٌ يجمع مجموعاته، والمجموعة تبقى `stk-section` نفسها المستعملة
    في بقية الشاشات — فلا يُخترع مستوىً بصريٌّ ثالث. تعيش هنا لا في
    `stock-theme` لأنها خاصة بشاشتَي بناء الدور وعرضه، وتُستدعى من كلتيهما
    كي لا تُنسخ مرتين.
--}}
@push('css')
    <style>
        .perm-module {
            border: 1px solid var(--stk-line, rgba(127, 127, 127, .2));
            border-radius: var(--stk-r-lg, 10px);
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .perm-module:last-of-type { margin-bottom: 0; }

        .perm-module__head {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 1rem;
            background: var(--stk-tint, rgba(127, 127, 127, .05));
            border-bottom: 1px solid var(--stk-line-soft, rgba(127, 127, 127, .12));
        }

        .perm-module__toggle {
            flex: none;
            border: 0;
            background: transparent;
            color: var(--app-muted, #888);
            cursor: pointer;
            padding: .15rem;
            line-height: 1;
            transition: transform .15s ease;
        }

        .perm-module[data-collapsed="1"] .perm-module__toggle { transform: rotate(-90deg); }
        .perm-module[data-collapsed="1"] .perm-module__body { display: none; }

        .perm-module__icon {
            flex: none;
            width: 34px;
            height: 34px;
            border-radius: var(--stk-r, 8px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

        .perm-module__titles { min-width: 0; flex: 1 1 auto; }

        .perm-module__title {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-weight: 700;
            font-size: .95rem;
            color: var(--app-text, inherit);
        }

        .perm-module__sub {
            display: block;
            font-size: .75rem;
            color: var(--app-muted, #888);
            line-height: 1.5;
            margin-top: .15rem;
        }

        .perm-module__body { padding: 1rem; }
        .perm-module__body .stk-section:last-child { margin-bottom: 0; }

        /* عنوانُ قسمٍ فرعي داخل الوحدة (الموظفون، الحضور، الرواتب…) */
        .perm-section-title {
            margin: 1.25rem 0 .75rem;
            padding: .45rem .85rem;
            font-size: .95rem;
            font-weight: 700;
            color: var(--app-primary);
            background: color-mix(in srgb, var(--app-primary) 8%, transparent);
            border-inline-start: 3px solid var(--app-primary);
            border-radius: var(--app-radius, .357rem);
        }
        .perm-module__body > .perm-section-title:first-child { margin-top: 0; }

        .perm-group__sub {
            display: block;
            font-weight: 400;
            font-size: .73rem;
            color: var(--app-muted, #888);
            line-height: 1.5;
            margin-top: .1rem;
        }
    </style>
@endpush
