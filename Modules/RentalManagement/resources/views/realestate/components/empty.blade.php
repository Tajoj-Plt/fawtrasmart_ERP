<div class="stk-empty">
    <div class="stk-empty__icon"><i class="feather icon-{{ $icon ?? 'inbox' }}"></i></div>
    <h5 class="stk-empty__title">{{ $title ?? 'لا سجلّات' }}</h5>
    <p class="stk-empty__hint">{{ $hint ?? 'لا شيء يطابق معايير البحث الحالية' }}</p>
    @isset($action) {!! $action !!} @endisset
</div>
