<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $storePage->meta_title ?? $storePage->title }}</title>
    <meta name="description" content="{{ $storePage->meta_description ?? '' }}">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    
    <style>
        * {
            font-family: 'Cairo', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 40px;
        }
        
        .preview-header {
            background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .preview-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .preview-actions a {
            color: white;
            margin-right: 15px;
            text-decoration: none;
        }
        
        .preview-actions a:hover {
            text-decoration: underline;
        }
        
        .content-wrapper {
            margin-top: 80px;
        }
        
        .page-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .page-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .page-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .page-content {
            padding: 40px;
            line-height: 1.9;
            font-size: 16px;
            color: #333;
        }
        
        .page-content h1,
        .page-content h2,
        .page-content h3,
        .page-content h4,
        .page-content h5,
        .page-content h6 {
            color: #2c3e50;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .page-content p {
            margin-bottom: 15px;
        }
        
        .page-content ul,
        .page-content ol {
            margin-bottom: 20px;
            padding-right: 25px;
        }
        
        .page-content li {
            margin-bottom: 8px;
        }
        
        .page-content blockquote {
            border-right: 4px solid #7367f0;
            padding: 15px 20px;
            background: #f8f9fa;
            margin: 20px 0;
            border-radius: 0 10px 10px 0;
        }
        
        .page-content table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        
        .page-content table th,
        .page-content table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: right;
        }
        
        .page-content table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .page-footer {
            border-top: 1px solid #eee;
            padding: 20px 40px;
            background: #fafafa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .type-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .type-badge.page { background: #e3e1fc; color: #7367f0; }
        .type-badge.policy { background: #d4f4e8; color: #28c76f; }
        .type-badge.faq { background: #fff3cd; color: #ff9f43; }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 30px 20px;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .page-content {
                padding: 25px;
            }
            
            .page-meta {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Preview Header -->
    <div class="preview-header">
        <div class="container">
            <div>
                <span class="preview-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    وضع المعاينة
                </span>
            </div>
            <div class="preview-actions">
                @if (hasPermission('online_store.pages.edit'))
                    <a href="{{ route('store_pages.edit', $storePage) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        تعديل
                    </a>
                @endif
                <a href="{{ route('store_pages.index') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    العودة للقائمة
                </a>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="page-card">
                        <!-- Page Header -->
                        <div class="page-header">
                            <h1>{{ $storePage->title }}</h1>
                            <div class="page-meta">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    {{ $storePage->created_at->format('Y/m/d') }}
                                </span>
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    {{ $storePage->reading_time ?? 1 }} دقيقة للقراءة
                                </span>
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                        <polyline points="10 9 9 9 8 9"></polyline>
                                    </svg>
                                    {{ $storePage->word_count ?? 0 }} كلمة
                                </span>
                            </div>
                        </div>

                        <!-- Page Content -->
                        <div class="page-content">
                            {!! $storePage->content !!}
                        </div>

                        <!-- Page Footer -->
                        <div class="page-footer">
                            <div>
                                @php
                                    $typeClass = match($storePage->type->value ?? $storePage->type) {
                                        'page' => 'page',
                                        'policy' => 'policy',
                                        'faq' => 'faq',
                                        default => 'page'
                                    };
                                    $typeText = match($storePage->type->value ?? $storePage->type) {
                                        'page' => 'صفحة عادية',
                                        'policy' => 'سياسة',
                                        'faq' => 'أسئلة شائعة',
                                        default => 'غير محدد'
                                    };
                                @endphp
                                <span class="type-badge {{ $typeClass }}">{{ $typeText }}</span>
                            </div>
                            <div class="text-muted">
                                آخر تحديث: {{ $storePage->updated_at->format('Y/m/d H:i') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
