<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('client_settings.crm.notes.print_title') }} #{{ $note->id }}</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');

        body {
            font-family: 'Tajawal', sans-serif;
            direction: rtl;
            background-color: #f4f4f5;
            padding: 24px;
            color: #1f2937;
        }

        .note-print {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 28px;
        }

        .note-print__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 16px;
            margin-bottom: 16px;
        }

        .note-print__title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .note-print__meta {
            font-size: 13px;
            color: #6b7280;
        }

        .note-print__field {
            margin-bottom: 12px;
        }

        .note-print__field-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 2px;
        }

        .note-print__field-value {
            font-size: 15px;
            font-weight: 500;
            white-space: pre-wrap;
        }

        .note-print__grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .note-print__attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        .note-print__attachments img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .note-print__actions {
            text-align: center;
            margin-top: 20px;
        }

        .note-print__actions button {
            font-family: inherit;
            font-size: 14px;
            padding: 8px 20px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            cursor: pointer;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .note-print {
                border: none;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="note-print">
        <div class="note-print__header">
            <div>
                <p class="note-print__title">{{ __('client_settings.crm.notes.print_title') }}</p>
                <p class="note-print__meta">
                    {{ $client->trade_name ?? __('client_settings.crm.notes.unknown_client') }}
                    @if($client && $client->code)
                        — {{ $client->code }}
                    @endif
                </p>
            </div>
            <div class="note-print__meta">
                {{ $note->created_at?->format('Y-m-d H:i') }}
            </div>
        </div>

        <div class="note-print__grid">
            <div class="note-print__field">
                <div class="note-print__field-label">{{ __('client_settings.crm.notes.employee') }}</div>
                <div class="note-print__field-value">{{ $note->employee->name ?? __('client_settings.crm.notes.unknown') }}</div>
            </div>
            @if($note->process)
                <div class="note-print__field">
                    <div class="note-print__field-label">{{ __('client_settings.crm.notes.process') }}</div>
                    <div class="note-print__field-value">{{ $note->process }}</div>
                </div>
            @endif
            @if(!is_null($note->deposit_count))
                <div class="note-print__field">
                    <div class="note-print__field-label">{{ __('client_settings.crm.notes.deposit_count') }}</div>
                    <div class="note-print__field-value">{{ $note->deposit_count }}</div>
                </div>
            @endif
            @if($note->clientEmployee)
                <div class="note-print__field">
                    <div class="note-print__field-label">{{ __('client_settings.crm.notes.client_contact') }}</div>
                    <div class="note-print__field-value">{{ $note->clientEmployee->employee_name }}</div>
                </div>
            @endif
        </div>

        <div class="note-print__field">
            <div class="note-print__field-label">{{ __('client_settings.crm.notes.description') }}</div>
            <div class="note-print__field-value">{{ $note->description }}</div>
        </div>

        @php($attachments = json_decode($note->attachments, true) ?: [])
        @if(!empty($attachments))
            <div class="note-print__field">
                <div class="note-print__field-label">{{ __('client_settings.crm.notes.attachments') }}</div>
                <div class="note-print__attachments">
                    @foreach($attachments as $attachment)
                        @if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $attachment))
                            <img src="{{ asset('assets/uploads/notes/'.$attachment) }}" alt="">
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <div class="note-print__actions no-print">
            <button type="button" onclick="window.print()">{{ __('client_settings.crm.notes.print_button') }}</button>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>

</html>
