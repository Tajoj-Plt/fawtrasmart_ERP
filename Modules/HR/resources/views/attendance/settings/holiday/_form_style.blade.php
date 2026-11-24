{{--
    أنماطُ صفوف العطلات الديناميكية — مشتركةٌ بين «إضافة» و«تعديل» قائمة العطلات.
    الصفوف تُبنى من JavaScript (`getRowTemplate`) وتعتمد على هذه الأصناف:
    .hf-row / .row-num / .hf-type-tab / .hf-remove-btn — فلا تُحذف.
--}}
<style>
    .hf-row { border: 1px solid var(--app-border, #e5e7eb); border-radius: 8px; padding: 12px 14px 4px; margin-bottom: 10px; background: var(--app-surface, #fff); }
    .hf-row .row-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .hf-row .row-num { width: 26px; height: 26px; background: var(--app-primary-soft, #eef2ff); color: var(--app-primary, #4338ca); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .78rem; }
    .hf-field { margin-bottom: 10px; }
    .hf-field label { display: block; }

    .hf-type-tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 10px; }
    .hf-type-tab { flex: 1; min-width: 70px; padding: 6px 8px; border: 1px solid var(--app-border, #e5e7eb); border-radius: 6px; background: var(--app-surface, #fff); color: var(--app-muted, #6b7280); cursor: pointer; font-size: .75rem; font-weight: 600; text-align: center; transition: .15s; margin: 0; }
    .hf-type-tab.active { background: var(--app-primary, #6366f1); color: #fff; border-color: var(--app-primary, #6366f1); }
    .hf-type-tab i { margin-left: 3px; }

    .hf-remove-btn { background: var(--app-danger-soft, #fee2e2); color: var(--app-danger, #991b1b); border: none; width: 28px; height: 28px; border-radius: 6px; }
</style>
