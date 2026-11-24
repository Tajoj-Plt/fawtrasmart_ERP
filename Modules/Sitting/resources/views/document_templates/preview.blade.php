<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('settings.doc_templates.preview_title', ['name' => $template->name]) }}</title>
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts for premium typography -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Styles for the control bar */
        .control-bar {
            background: #0f172a;
            color: white;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Arial', 'Tahoma', sans-serif;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 10000;
        }
        .control-bar h3 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }
        .control-bar p {
            margin: 3px 0 0 0;
            font-size: 11px;
            color: #94a3b8;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        .btn-print {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-print:hover {
            background: #059669;
        }
        .btn-close {
            background: #475569;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: background 0.2s;
        }
        .btn-close:hover {
            background: #334155;
        }

        /* Styling to adjust body backgrounds between preview screen and prints */
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
        }
        
        .preview-content {
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        @media print {
            .control-bar {
                display: none !important;
            }
            body {
                background-color: white !important;
            }
            .preview-content {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>

    <!-- Control bar (Hidden during printing) -->
    <div class="control-bar">
        <div>
            <h3>{{ __('settings.doc_templates.preview_heading') }}</h3>
            <p>{{ __('settings.doc_templates.preview_meta', ['name' => $template->name, 'size' => $template->paper_size]) }}</p>
        </div>
        <div class="btn-group">
            <button class="btn-print" onclick="window.print();">
                <i class="fas fa-print"></i> {{ __('settings.doc_templates.print') }}
            </button>
            <button class="btn-close" onclick="window.close();">
                {{ __('settings.doc_templates.close') }}
            </button>
        </div>
    </div>

    <!-- Rendered HTML Content -->
    <div class="preview-content">
        {!! $html !!}
    </div>

</body>
</html>
