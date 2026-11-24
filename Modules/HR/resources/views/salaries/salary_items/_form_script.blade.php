{{-- سلوكُ نموذج بند الراتب: طريقةُ القيمة، وعلَما الخضوع بحسب النوع، وselect2 للحساب. --}}
<script src="{{ asset('js/formula-autocomplete.js') }}"></script>
<script>
    // طريقةٌ واحدة فعّالة: الحقلُ غيرُ المختار يُعطَّل فلا يُرسَل، ولا تُمسح قيمتُه
    // كي يعود كما كان إن رجع المستخدم إليه.
    function toggleFields() {
        var amountInput = document.getElementById('amount');
        var formulaInput = document.getElementById('calculation_formula');
        var byAmount = document.getElementById('amount_radio').checked;

        amountInput.disabled = !byAmount;
        amountInput.required = byAmount;
        formulaInput.disabled = byAmount;
        formulaInput.required = !byAmount;
    }

    function toggleAdditionFlags() {
        var type = document.getElementById('salary_item_type').value;
        var isDeduction = type === '2';
        var flags = document.getElementById('addition-flags');
        var note = document.getElementById('deduction-flags-note');
        flags.classList.toggle('d-none', isDeduction);
        note.classList.toggle('d-none', !isDeduction);
        flags.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.disabled = isDeduction; });
    }

    $(function () {
        toggleFields();
        toggleAdditionFlags();
        $('#salary_item_type').on('change', toggleAdditionFlags);

        if ($.fn.select2) {
            $('#account_id').select2({ width: '100%', dir: 'rtl', placeholder: 'الحساب الافتراضي', allowClear: true });
        }
    });
</script>
