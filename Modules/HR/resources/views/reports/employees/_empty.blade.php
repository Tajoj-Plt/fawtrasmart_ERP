@if ($employees->isEmpty())
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-users"></i></div>
        <h5 class="stk-empty__title">لا موظفين مطابقين</h5>
        <p class="stk-empty__hint">عدِّل عوامل التصفية أو تأكد أن نطاق رؤيتك يشمل موظفين.</p>
    </div>
@endif
