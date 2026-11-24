{{--
    Shared hero header used across TaskManager pages.

    Expected variables:
    - heroTitle (string): plain part of the title
    - heroHighlight (string, optional): colored/highlighted part appended after heroTitle
    - heroSubtitle (string, optional)
    - heroStats (array, optional): [['icon' => 'fas fa-...', 'value' => '..', 'label' => '..'], ...]
    - heroActions (array, optional): [['url' => '..', 'label' => '..', 'icon' => 'fas fa-..', 'style' => 'solid'|'ghost'], ...]
--}}
<div class="tm-hero">
    <div class="tm-hero-content">
        <h1 class="tm-hero-title">
            {{ $heroTitle }}
            @isset($heroHighlight)
                <span class="tm-hero-highlight">{{ $heroHighlight }}</span>
            @endisset
        </h1>
        @isset($heroSubtitle)
            <p class="tm-hero-subtitle">{{ $heroSubtitle }}</p>
        @endisset

        @isset($heroActions)
            <div class="tm-hero-actions">
                @foreach ($heroActions as $action)
                    <a href="{{ $action['url'] }}" class="tm-hero-btn {{ ($action['style'] ?? 'ghost') === 'solid' ? 'tm-hero-btn-solid' : 'tm-hero-btn-ghost' }}">
                        @isset($action['icon'])
                            <i class="{{ $action['icon'] }}"></i>
                        @endisset
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endisset
    </div>

    @isset($heroStats)
        <div class="tm-stat-row">
            @foreach ($heroStats as $stat)
                <div class="tm-stat-pill">
                    <i class="{{ $stat['icon'] }}"></i>
                    <div>
                        <span class="tm-stat-value">{{ $stat['value'] }}</span>
                        <span class="tm-stat-label">{{ $stat['label'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endisset
</div>
