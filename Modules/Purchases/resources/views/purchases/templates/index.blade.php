@extends('purchases::master')

@section('title')
  قوالب فواتير الشراء
@endsection

@section('css')
<style>
    .card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.125);
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.card-img-top {
    border-bottom: 1px solid rgba(0,0,0,0.125);
}
</style>
@endsection

@section('content')
<div class="content-body">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1>قوالب فواتير الشراء</h1>
            </div>
             <div class="col-md-6 text-left">
                <a href="{{ route('purchases.invoice_settings.templates.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> إضافة قالب جديد
                </a>
                <a href="{{ route('purchases.settings.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> عودة للإعدادات
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row">
            @forelse($templates as $template)
           <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-0" style="transition: all 0.3s ease;">
                    <!-- صورة القالب -->
                    <div class="card-img-top" style="height: 150px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; align-items: center; justify-content: center;">
                        @if($template->thumbnail)
                            <img src="{{ asset('storage/' . $template->thumbnail) }}" 
                                 alt="{{ $template->name }}"
                                 style="height: 100%; width: 100%; object-fit: cover; border-radius: 5px 5px 0 0;">
                        @else
                            <div class="text-center p-3">
                                <i class="fas fa-file-invoice-dollar fa-3x text-primary mb-2"></i>
                                <p class="text-muted mb-0">{{ $template->name }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="card-body d-flex flex-column bg-light">
                        <h5 class="card-title text-primary">{{ $template->name }}</h5>
                        
                        <div class="mt-auto pt-3">
                            <div class="btn-group w-100" role="group">
                                <a href="{{ route('purchases.invoice_settings.templates.edit', $template->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> تعديل
                                </a>
                                
                                @if(!$template->is_default)
                                <form action="{{ route('purchases.invoice_settings.templates.destroy', $template->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟');" style="display: inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </form>
                                @else
                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                    <i class="fas fa-lock"></i> افتراضي
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-5">
                <div class="alert alert-info">
                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                    لا توجد قوالب متاحة حالياً.
                </div>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
