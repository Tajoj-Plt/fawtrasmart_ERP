{{-- رابط حسابٍ في دليل الحسابات: $account (App\Models\Account|null) --}}
@if ($account ?? null)
    @if (hasPermission('account.chart.view') && Route::has('account.showDetails'))
        <a href="{{ route('account.showDetails', $account->id) }}"><i class="feather icon-book"></i> {{ $account->code }} — {{ $account->name }}</a>
    @else
        {{ $account->code }} — {{ $account->name }}
    @endif
@else
    —
@endif
