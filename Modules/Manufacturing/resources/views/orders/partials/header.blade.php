{{-- الترويسة موحّدة الآن مع بقيّة شاشات المخزون عبر `page-head` --}}

@include('stock::components.page-head', [
    'title' => $order->name,
    'icon' => 'tool',
    'sub' => 'أمر تصنيع #' . $order->code,
    'crumbs' => [
        'الرئيسية' => url('/'),
        'أوامر التصنيع' => route('manufacturing.orders.index'),
        $order->name => null,
    ],
])
