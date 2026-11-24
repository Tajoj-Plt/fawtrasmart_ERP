@if($method === 'GET')
    <a href="{{ $url }}" class="{{ $class }}" @if($confirm) onclick="return confirm('{{ $confirm }}')" @endif>
        @if($icon)
            <i class="{{ $icon }}"></i>
        @endif
        {{ $text ?? $slot }}
    </a>
@else
    <form action="{{ $url }}" method="POST" style="display: inline;" @if($confirm) onsubmit="return confirm('{{ $confirm }}')" @endif>
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif
        <button type="submit" class="{{ $class }}">
            @if($icon)
                <i class="{{ $icon }}"></i>
            @endif
            {{ $text ?? $slot }}
        </button>
    </form>
@endif
