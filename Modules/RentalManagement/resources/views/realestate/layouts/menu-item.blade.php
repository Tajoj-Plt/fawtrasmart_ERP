{{--
    بندٌ واحد من قائمة العقارات، أو مجموعةٌ بأبنائها (تتكرّر بنفسها). الوسائط:
    $item، $nested (القائمة الموحّدة)، $level (0 = مستوى القائمة، 1 = ابنٌ في مجموعة)،
    $reLocale، $rePlanAny.
--}}
@php
    use App\Support\PlanLock;
    use App\Constants\planFeatures\RealEstatePlanFeatures as RePlan;
    use App\Services\Fawtra\SubscriptionGate;

    // صفحات «طلب عميل» تشارك مسارات العروض (listings/{id}، create?kind=request) — تُميَّز بنوع السجلّ
    $onRequestPage = \Illuminate\Support\Facades\Cache::store('array')->rememberForever('re.menu.onRequestPage', function () use ($reLocale) {
        if (! request()->is("$reLocale/realestate/listings/*") || request()->is("$reLocale/realestate/listings/requests*")) {
            return false;
        }

        $id = (int) request()->segment(4);

        return request('kind') === 'request'
            || ($id > 0 && \Illuminate\Support\Facades\DB::table('re_listings')->where('id', $id)->value('listing_kind') === 'request');
    });

    $isActive = function (array $entry) use ($reLocale, $onRequestPage) {
        if ($onRequestPage) {
            return $entry['path'] === 'listings/requests';
        }

        return request()->is("$reLocale/realestate/{$entry['path']}*")
            && ! (isset($entry['except']) && request()->is("$reLocale/realestate/{$entry['except']}*"));
    };

    $topClass = ($nested && $level === 0) || (! $nested && $level === 0) ? ($nested ? '' : 'nav-item') : '';
@endphp

@if (isset($item['children']))
    @php $groupActive = collect($item['children'])->contains(fn ($child) => $isActive($child)); @endphp
    <li class="{{ $topClass }} {{ $groupActive ? 'open' : '' }}">
        <a href="#">
            <i class="feather icon-{{ $nested ? 'circle' : $item['icon'] }}"></i>
            <span class="{{ $nested ? 'menu-item' : 'menu-title' }}">{{ $item['label'] }}</span>
        </a>
        <ul class="menu-content">
            @foreach ($item['children'] as $child)
                @include('rentalmanagement::realestate.layouts.menu-item', ['item' => $child, 'nested' => true, 'level' => 1, 'reLocale' => $reLocale, 'rePlanAny' => $rePlanAny])
            @endforeach
        </ul>
    </li>
@else
    @php
        $allowed = ($item['plan'] ?? null) === null ? $rePlanAny : SubscriptionGate::permits($item['plan']);
        $active = $isActive($item);
    @endphp
    <li class="{{ $topClass }} {{ $active ? 'active' : '' }} {{ $allowed ? '' : 'stk-plan-locked' }}">
        <a href="{{ $allowed ? route($item['route']) : PlanLock::url() }}"
           @unless ($allowed) title="{{ PlanLock::reason($item['plan'] ?? RePlan::all()) }}" @endunless>
            <i class="feather icon-{{ ($nested || $level > 0) ? 'circle' : $item['icon'] }}"></i>
            <span class="{{ ($nested || $level > 0) ? 'menu-item' : 'menu-title' }}">{{ $item['label'] }}</span>
            @unless ($allowed) {!! PlanLock::menuBadge() !!} @endunless
        </a>
    </li>
@endif
