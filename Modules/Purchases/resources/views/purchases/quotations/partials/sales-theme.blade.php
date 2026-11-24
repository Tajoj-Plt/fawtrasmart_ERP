{{--
    نظام تصميم النموذج الحديث — شاشة طلبات عروض الأسعار
    نفس نسخة ordersPurchase، تم نسخه للاستقلالية
--}}

@once
    <style>
        :root {
            --sl-1: .25rem;  --sl-2: .5rem;   --sl-3: .75rem;
            --sl-4: 1rem;    --sl-5: 1.5rem;  --sl-6: 2rem;
            --sl-r: calc(var(--app-radius, 6px) + 2px);
            --sl-r-lg: calc(var(--app-radius, 6px) + 6px);
            --sl-e1: 0 1px 2px rgba(16, 24, 40, .04);
            --sl-e2: 0 1px 3px rgba(16, 24, 40, .06), 0 1px 2px rgba(16, 24, 40, .04);
            --sl-e3: 0 4px 12px rgba(16, 24, 40, .07), 0 2px 4px rgba(16, 24, 40, .04);
            --sl-e4: 0 12px 32px rgba(16, 24, 40, .10), 0 4px 8px rgba(16, 24, 40, .05);
            --sl-line: var(--app-border, #e4e7ec);
            --sl-line-soft: color-mix(in srgb, var(--app-border, #e4e7ec) 55%, transparent);
            --sl-tint: color-mix(in srgb, var(--app-primary, #7367f0) 4%, var(--app-surface, #fff));
        }

        .sl-scope { color: var(--app-text, #4b4b4b); }

        .sl-page-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-4); flex-wrap: wrap; margin-bottom: var(--sl-5);
        }

        .sl-page-head__titles { min-width: 0; display: flex; align-items: center; gap: var(--sl-3); }

        .sl-page-head__icon {
            flex: none; width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--sl-r);
            background: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            font-size: 1.15rem;
            box-shadow: 0 4px 12px var(--app-primary-glow, rgba(115, 103, 240, .3));
        }

        .sl-page-head__title {
            font-size: 1.35rem; font-weight: var(--app-heading-weight, 700); line-height: 1.25;
            color: var(--app-text, #4b4b4b); margin: 0 0 .15rem; letter-spacing: -.01em;
        }

        .sl-page-head__sub { color: var(--app-muted, #6e6b7b); font-size: .82rem; margin: 0; }

        .sl-page-head .breadcrumb {
            margin: .15rem 0 0; padding: 0; background: none; font-size: .78rem;
        }

        .sl-scope .btn {
            --btn-bg: transparent; --btn-fg: var(--app-text, #4b4b4b); --btn-bd: var(--sl-line);
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            height: 38px; padding: 0 var(--sl-4);
            font-size: .86rem; font-weight: 600; line-height: 1;
            border: 1px solid var(--btn-bd); border-radius: var(--sl-r);
            background: var(--btn-bg); color: var(--btn-fg);
            box-shadow: var(--sl-e1); transition: all .16s ease; white-space: nowrap;
        }

        .sl-scope .btn:hover { transform: translateY(-1px); box-shadow: var(--sl-e3); }

        .sl-scope .btn-primary {
            --btn-bg: var(--app-primary, #7367f0); --btn-fg: var(--app-on-primary, #fff);
            --btn-bd: var(--app-primary, #7367f0);
        }

        .sl-scope .btn-primary:hover {
            background: var(--app-primary-hover, #5e50ee) !important;
            border-color: var(--app-primary-hover, #5e50ee) !important;
            color: var(--app-on-primary, #fff) !important;
        }

        .sl-scope .card {
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r-lg);
            box-shadow: var(--sl-e2);
            margin-bottom: var(--sl-4);
        }

        .sl-stats {
            display: grid; gap: var(--sl-4);
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            margin-bottom: var(--sl-5);
        }

        .sl-stat {
            display: flex; align-items: center; gap: var(--sl-4); padding: var(--sl-5);
            background: var(--app-surface, #fff); border: 1px solid var(--sl-line);
            border-radius: var(--sl-r-lg); box-shadow: var(--sl-e2);
        }

        .sl-stat__icon {
            flex: none; width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--sl-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.25rem;
        }

        .sl-stat__icon--success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .sl-stat__icon--danger { background: var(--app-danger-soft, rgba(234, 84, 85, .12)); color: var(--app-danger, #ea5455); }
        .sl-stat__icon--warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }

        .sl-stat__body { min-width: 0; }
        .sl-stat__value { font-size: 1.55rem; font-weight: 700; line-height: 1.1; color: var(--app-text, #4b4b4b); margin: 0; }
        .sl-stat__label { font-size: .8rem; color: var(--app-muted, #6e6b7b); margin: .2rem 0 0; }

        .sl-filters .card-body { padding: var(--sl-5); }
        .sl-filters__head { display: flex; align-items: center; justify-content: space-between; gap: var(--sl-3); margin-bottom: var(--sl-4); }
        .sl-filters__title { display: flex; align-items: center; gap: var(--sl-2); font-weight: 700; font-size: .9rem; margin: 0; }

        .sl-table-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-3); flex-wrap: wrap;
            padding: var(--sl-4) var(--sl-5);
            border-bottom: 1px solid var(--sl-line-soft);
        }

        .sl-table-head__title {
            display: flex; align-items: center; gap: var(--sl-2);
            font-weight: 700; font-size: .95rem; color: var(--app-text, #4b4b4b); margin: 0;
        }

        .sl-scope .table {
            color: var(--app-text, #4b4b4b);
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .sl-scope .table thead th {
            background: var(--sl-tint);
            color: var(--app-muted, #6e6b7b);
            font-size: .74rem; font-weight: 700;
            padding: var(--sl-3) var(--sl-5);
            border: 0 !important;
            border-bottom: 1px solid var(--sl-line) !important;
        }

        .sl-scope .table tbody td {
            padding: var(--sl-4) var(--sl-5);
            border: 0 !important;
            border-bottom: 1px solid var(--sl-line-soft) !important;
            vertical-align: middle;
        }

        .sl-scope .table-hover tbody tr:hover {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12)) !important;
        }

        .sl-table-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-4); flex-wrap: wrap;
            padding: var(--sl-3) var(--sl-5);
            border-top: 1px solid var(--sl-line-soft);
            background: var(--sl-tint);
        }

        .sl-scope .pagination { gap: .3rem; margin: 0; flex-wrap: wrap; }
        .sl-scope .pagination .page-link {
            min-width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            margin: 0; padding: 0 .55rem;
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r);
            background: var(--app-surface, #fff);
            color: var(--app-text, #4b4b4b);
            font-size: .82rem;
        }

        .sl-scope .pagination .page-item.active .page-link {
            background: var(--app-primary, #7367f0);
            border-color: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
        }

        .sl-scope .form-control {
            height: 40px; padding: 0 var(--sl-3);
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r);
            color: var(--app-text, #4b4b4b);
        }

        @media print {
            .sl-page-head__actions, .sl-filters, .sl-table-foot { display: none !important; }
        }
    </style>
@endonce
