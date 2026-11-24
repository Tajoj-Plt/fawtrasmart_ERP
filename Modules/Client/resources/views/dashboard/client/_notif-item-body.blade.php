{{-- جسم بند «مركز التنبيهات» — مشتركٌ بين الرابط (<a>) والحاوية العادية (<div>)
     في dashboard/client/index.blade.php، كي لا يتكرّر بين الحالتين. --}}
<div class="cd-notif-icon" style="background: color-mix(in srgb, var(--cd-{{ $notif['color'] }}) 14%, transparent);">
    <i class="fas {{ $notif['icon'] }}" style="color: var(--cd-{{ $notif['color'] }});"></i>
</div>
<div class="cd-notif-label">{{ $notif['label'] }}</div>
<div class="cd-notif-count" style="color: var(--cd-{{ $notif['color'] }});">{{ number_format($notif['count']) }}</div>
