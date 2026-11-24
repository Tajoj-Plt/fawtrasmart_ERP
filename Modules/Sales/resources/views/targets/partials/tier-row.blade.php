{{--
    صفّ مستوى عمولة واحد.

    يُستعمل في موضعين: صفوفُ المستويات المحفوظة تُطبع منه في الخادم، وقالبُ
    الصفّ الجديد في المتصفّح يُقرأ منه أيضاً بعد استبدال `__I__` بالفهرس.
    فالترميز والنصوص في ملفٍّ واحد — كان قالبُ الصفّ الجديد سلسلةَ HTML
    عربيةً مكتوبةً داخل دالّة JavaScript، فلا تُترجَم ولا تتبع أنماط
    الشاشة، وتتباعد عن صفوف الخادم مع أول تعديل.

    الوسائط:
      index        فهرس الصفّ — رقمٌ، أو `__I__` حين يُقرأ قالباً
      achievement  نسبة الإنجاز المحفوظة (أو null)
      commission   نسبة العمولة المحفوظة (أو null)
--}}
<div class="sl-tier">
    <div class="form-group mb-0">
        <label>{{ __('sales_targets.tiers.achievement') }} <span class="sl-req">*</span></label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" max="999.99" required
                   name="commission_tiers[{{ $index }}][achievement_percentage]"
                   class="form-control"
                   value="{{ $achievement !== null ? $achievement : '' }}">
            <div class="input-group-append"><span class="input-group-text">%</span></div>
        </div>
    </div>

    <div class="form-group mb-0">
        <label>{{ __('sales_targets.tiers.commission') }} <span class="sl-req">*</span></label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" required
                   name="commission_tiers[{{ $index }}][commission_percentage]"
                   class="form-control"
                   value="{{ $commission !== null ? $commission : '' }}">
            <div class="input-group-append"><span class="input-group-text">%</span></div>
        </div>
    </div>

    <button type="button" class="btn btn-outline-danger sl-tier__remove"
            title="{{ __('sales_targets.tiers.remove') }}"
            aria-label="{{ __('sales_targets.tiers.remove') }}">
        <i class="feather icon-trash-2"></i>
    </button>
</div>
