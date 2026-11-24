{{-- Shared design system for attendance views --}}
<style>
    .att-hero { background: var(--app-surface, #fff); border-radius:10px; padding:18px 22px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,.04); border-right:4px solid var(--app-primary, #6366f1); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; }
    .att-hero.green  { border-right-color: var(--app-success, #10b981); }
    .att-hero.amber  { border-right-color: var(--app-warning, #f59e0b); }
    .att-hero.red    { border-right-color: var(--app-danger, #ef4444); }
    .att-hero.blue   { border-right-color: var(--app-info, #3b82f6); }
    .att-hero h2 { margin:0; font-size:1.3rem; color: var(--app-text, #2c3e50); }
    .att-hero h2 i { color:var(--app-primary, #6366f1); margin-left:8px; }
    .att-hero.green h2 i  { color: var(--app-success, #10b981); }
    .att-hero.amber h2 i  { color: var(--app-warning, #f59e0b); }
    .att-hero.red h2 i    { color: var(--app-danger, #ef4444); }
    .att-hero.blue h2 i   { color: var(--app-info, #3b82f6); }
    .att-hero .sub { color: var(--app-muted, #6b7280); font-size:.85rem; margin-top:4px; }

    .att-card { background: var(--app-surface, #fff); border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.04); margin-bottom:16px; overflow:hidden; border: 1px solid var(--app-border, #f0f0f0); }
    .att-card-head { padding:12px 18px; background: var(--app-body-bg, #f9fafb); border-bottom: 1px solid var(--app-border, #f0f0f0); font-weight:700; color: var(--app-text, #1f2937); display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .att-card-head i { color:var(--app-primary, #6366f1); margin-left:6px; }
    .att-card-body { padding:18px 22px; }

    .att-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px; }
    .att-stat { background: var(--app-surface, #fff); border-radius:10px; padding:14px 18px; box-shadow:0 1px 3px rgba(0,0,0,.04); border-right:3px solid var(--app-primary, #6366f1); }
    .att-stat.green  { border-right-color: var(--app-success, #10b981); }
    .att-stat.amber  { border-right-color: var(--app-warning, #f59e0b); }
    .att-stat.red    { border-right-color: var(--app-danger, #ef4444); }
    .att-stat.blue   { border-right-color: var(--app-info, #3b82f6); }
    .att-stat .label { font-size:.78rem; color: var(--app-muted, #6b7280); }
    .att-stat .value { font-size:1.5rem; font-weight:700; color: var(--app-text, #1f2937); margin-top:4px; }
    .att-stat.green .value  { color: var(--app-success, #065f46); }
    .att-stat.amber .value  { color: var(--app-warning, #92400e); }
    .att-stat.red .value    { color: var(--app-danger, #991b1b); }
    .att-stat.blue .value   { color: var(--app-info, #1e3a8a); }

    .att-field { margin-bottom:14px; }
    .att-field label { font-weight:600; color: var(--app-text, #374151); font-size:.875rem; margin-bottom:6px; display:block; }
    .att-field label .req { color: var(--app-danger, #ef4444); }
    .att-field .form-control, .att-field .form-select { height:42px; border: 1px solid var(--app-border, #e5e7eb); border-radius:8px; font-size:.875rem; padding:0 12px; }
    .att-field .form-control:focus, .att-field .form-select:focus { border-color:var(--app-primary, #6366f1); box-shadow:0 0 0 3px rgba(99,102,241,.1); }
    .att-field textarea.form-control { height:auto; min-height:80px; padding:8px 12px; }
    .att-field .hint { font-size:.72rem; color: var(--app-muted, #9ca3af); margin-top:4px; }
    .att-field .select2-container--default .select2-selection--single,
    .att-field .select2-container--default .select2-selection--multiple { border: 1px solid var(--app-border, #e5e7eb) !important; border-radius:8px !important; min-height:42px !important; }

    .att-table { width:100%; font-size:.875rem; margin:0; }
    .att-table thead th { background: var(--app-body-bg, #f9fafb); padding:12px 14px; font-weight:700; color: var(--app-muted, #4b5563); font-size:.78rem; text-transform:uppercase; letter-spacing:.3px; text-align:right; border-bottom: 1px solid var(--app-border, #e5e7eb); }
    .att-table tbody td { padding:12px 14px; border-bottom: 1px solid var(--app-border, #f3f4f6); vertical-align:middle; }
    .att-table tbody tr:hover { background: var(--app-body-bg, #fafbfc); }

    .att-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:10px; font-size:.72rem; font-weight:600; }
    .att-pill.success { background: var(--app-success-soft, #d1fae5); color: var(--app-success, #065f46); }
    .att-pill.danger  { background: var(--app-danger-soft, #fee2e2); color: var(--app-danger, #991b1b); }
    .att-pill.warning { background: var(--app-warning-soft, #fef3c7); color: var(--app-warning, #92400e); }
    .att-pill.info    { background: var(--app-info-soft, #dbeafe); color: var(--app-info, #1e3a8a); }
    .att-pill.secondary { background: var(--app-body-bg, #f3f4f6); color: var(--app-muted, #4b5563); }
    .att-pill.purple  { background: var(--app-primary-soft, #ede9fe); color:#5b21b6; }

    .att-btn { padding:9px 18px; border-radius:8px; font-weight:600; font-size:.85rem; border:none; display:inline-flex; align-items:center; gap:6px; cursor:pointer; text-decoration:none; }
    .att-btn-primary { background:var(--app-primary, #6366f1); color:#fff; }
    .att-btn-primary:hover { background: var(--app-primary-hover, #4f46e5); color:#fff; transform:translateY(-1px); box-shadow:0 4px 8px rgba(99,102,241,.25); }
    .att-btn-success { background: var(--app-success, #10b981); color:#fff; }
    .att-btn-success:hover { background: color-mix(in srgb, var(--app-success, #059669) 86%, #000); color:#fff; }
    .att-btn-warn { background: var(--app-warning, #f59e0b); color:#fff; }
    .att-btn-warn:hover { background: color-mix(in srgb, var(--app-warning, #d97706) 86%, #000); color:#fff; }
    .att-btn-danger { background: var(--app-danger-soft, #fee2e2); color: var(--app-danger, #991b1b); border: 1px solid var(--app-danger-soft, #fecaca); }
    .att-btn-danger:hover { background: color-mix(in srgb, var(--app-danger-soft, #fecaca) 86%, #000); }
    .att-btn-ghost { background: var(--app-surface, #fff); color: var(--app-muted, #4b5563); border: 1px solid var(--app-border, #e5e7eb); }
    .att-btn-ghost:hover { background: var(--app-body-bg, #f9fafb); }
    .att-btn-icon { width:34px; height:34px; padding:0; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; }

    /*
     * أزرارٌ مبنيّةٌ على <a>: قاعدةُ السمة العامّة
     *   a:not(.btn):not(.nav-link):not(.dropdown-item):not(.page-link){color:var(--app-link)}
     * وزنُها (0,4,1) يهزم أصناف .att-btn-* أعلاه، فكان النصّ يخرج بلون الرابط
     * البنفسجي فوق خلفيةٍ خضراء أو بنفسجية — أي زرٌّ بلا نصٍّ مقروء. نرفع
     * الوزن هنا بدل تعديل ملف السمة العام لأن العلاج يخصّ مكوّنات هذه الوحدة.
     */
    a.att-btn-primary, a.att-btn-success, a.att-btn-warn { color:#fff !important; }
    a.att-btn-primary:hover, a.att-btn-success:hover, a.att-btn-warn:hover { color:#fff !important; }
    a.att-btn-ghost  { color: var(--app-muted, #4b5563) !important; }
    a.att-btn-danger { color: var(--app-danger, #991b1b) !important; }


    .att-empty { text-align:center; padding:50px 20px; color: var(--app-muted, #9ca3af); }
    .att-empty i { font-size:3rem; color: var(--app-border, #d1d5db); margin-bottom:12px; display:block; }

    .att-toolbar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
</style>
