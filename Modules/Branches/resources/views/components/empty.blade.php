{{-- حالة «لا يوجد شيء بعد».

     كل شاشة كانت تكتبها بصياغتها: أيقونة بحجم مختلف، ولون مختلف، وبعضها
     بلا شرح لما ينبغي فعله. الحالة الفارغة أول ما يراه مستخدم جديد، فهي
     تستحقّ شكلاً واحداً يقول: ما هذا المكان، ولماذا هو فارغ، وما الخطوة.

     والترميز `sl-empty` — نفسه المستعمل في شاشات المبيعات. والواجهة هنا
     لم تتغيّر: كل `<x-branches::empty>` في الشاشات يعمل كما كان. --}}
@props([
    'icon' => 'inbox',
    'title' => '',
    'text' => null,
])

<div class="sl-empty">
    <div class="sl-empty__icon"><i class="feather icon-{{ $icon }}"></i></div>
    @if ($title)
        <h6 class="sl-empty__title">{{ $title }}</h6>
    @endif
    @if ($text)
        <p class="sl-empty__hint">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
