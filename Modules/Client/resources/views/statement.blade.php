@extends('client::master')

@section('title')
    كشف حساب عميل
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'كشف حساب العميل',
            'icon' => 'file-text',
            'sub' => $client->trade_name ?? null,
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'كشف الحساب' => null,
            ],
        ])

    </div>

    <div class="content-body">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
                        <strong></strong>
                        <span></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        {{-- «المطبوعات»: تُبنى من «قوالب المستندات» لنوع
                             `client_statement` — عشرة قوالب مزروعة، وللمستخدم
                             أن يصمّم غيرها من الشاشة نفسها. الزرّ القديم يبقى
                             بجانبها لطباعة الجدول كما يظهر على الشاشة. --}}
                        @include('purchases::partials.print-menu', [
                            'templates' => $documentTemplates ?? collect(),
                            'default' => $defaultTemplate ?? null,
                            'prefersPdf' => $prefersPdf ?? false,
                            'printRoute' => 'clients.statementPrint',
                            'pdfRoute' => 'clients.statementPdf',
                            'documentId' => $client->id,
                        ])

                        <button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center print-button">
                            <i class="fas fa-table me-1"></i> طباعة الجدول
                        </button>
                    </div>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════
             هيكلُ بطاقة نتائج «كشف حساب المورد»: ترويسةٌ بعنوان، ثم الجسم.
             وأُزيل غلافان: بطاقةٌ خارجية كان جسمُها صفَّ أزرارٍ فارغاً
             (تعليقان بلا زرّ)، وبطاقةٌ ثانية داخلها بلا عنوان — فكان الجدول
             داخل ثلاث بطاقاتٍ متداخلة بثلاث حواف وثلاثة ظلال. --}}
        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h6 class="cl-table-head__title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    {{ __('client_settings.statement.movements') }}
                </h6>
            </div>

            <div class="card-body">
                        <ul class="nav nav-tabs" role="tablist">

                            <li class="nav-item">
                                <a class="nav-link active" id="entry-details-tab" data-toggle="tab" href="#entry-details"
                                    role="tab" aria-controls="entry-details" aria-selected="true">تفاصيل كشف الحساب</a>

                            </li>

                        </ul>

                        <div class="tab-content mt-3">
                            <!-- القيد -->
                            <div class="tab-pane fade" id="entry" role="tabpanel" aria-labelledby="entry-tab">


                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="tab-pane fade show active"
                                            style="background: var(--app-body-bg, #f8f9fa); min-height: 100vh; padding: 20px;">
                                            <div class="card shadow" style="max-width: 600px; margin: 20px auto;">
                                                <div class="card-body bg-white p-4"
                                                    style="min-height: 400px; overflow: auto;">
                                                    <div style="transform: scale(0.8); transform-origin: top center;">

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- تفاصيل القيد -->
                            <div class="tab-pane fade show active" id="entry-details" role="tabpanel"
                                aria-labelledby="entry-details-tab">

                                <div class="pdf-view" style="background: var(--app-body-bg, #f8f9fa); min-height: 100vh; padding: 20px;">

                                    <div class="card shadow" style="max-width: 600px; margin: 20px auto;">
                                        <div class="card-body bg-white p-4">
                                            <div style="transform: scale(0.8); transform-origin: top center;">
                                                <!-- PDF Content -->
                                                <div id="print-section">
                                                    <div dir="rtl" style="font-family: 'Cairo', sans-serif;">
                                                        <!-- معلومات الشركة -->
                                                        @php
                                                            // بيانات المنشأة الحقيقية من «بيانات المنشأة» في
                                                            // الإعدادات، لا متغيّراتٍ لم يمررها المتحكّم قطّ
                                                            // فتسقط دائماً على القيمة الافتراضية المكتوبة هنا.
                                                            $orgLogo = \App\Support\OrganizationInfo::logo();
                                                            $orgName = \App\Support\OrganizationInfo::name();
                                                            $orgAddress = \App\Support\OrganizationInfo::address();
                                                            $orgPhone = \App\Support\OrganizationInfo::phone();
                                                            $orgEmail = \App\Support\OrganizationInfo::email();
                                                        @endphp
                                                        <div
                                                            style="text-align: center; margin-bottom: 25px; border-bottom: 2px solid #333; padding-bottom: 15px;">
                                                            <div style="margin-bottom: 10px;">
                                                                @if ($orgLogo)
                                                                    <img src="{{ $orgLogo }}" alt="شعار الشركة"
                                                                        style="max-height: 60px; margin-bottom: 10px;">
                                                                @endif
                                                            </div>
                                                            <h1
                                                                style="margin: 0; font-size: 24px; font-weight: bold; color: #333;">
                                                                {{ $orgName }}
                                                            </h1>
                                                            <div style="font-size: 12px; color: #555; margin-top: 8px;">
                                                                @if ($orgAddress)
                                                                    <span>{{ $orgAddress }}</span>
                                                                    @if ($orgPhone)
                                                                        |
                                                                    @endif
                                                                @endif
                                                                @if ($orgPhone)
                                                                    <span>{{ $orgPhone }}</span>
                                                                    @if ($orgEmail)
                                                                        |
                                                                    @endif
                                                                @endif
                                                                @if ($orgEmail)
                                                                    <span>{{ $orgEmail }}</span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div style="text-align: center; margin-bottom: 20px;">
                                                            <h2 style="margin: 0; font-size: 20px; color: #333;">كشف حساب
                                                                عميل</h2>
                                                            <p style="margin: 5px 0; font-size: 12px; color: #666;">
                                                                تاريخ الإصدار: {{ \Carbon\Carbon::now()->format('d/m/Y') }}
                                                            </p>
                                                        </div>

                                                        <!-- معلومات العميل -->
                                                        <div
                                                            style="margin-bottom: 20px; background-color: #f8f9fa; padding: 15px; border-radius: 5px;">
                                                            <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;">
                                                                بيانات العميل:</h3>
                                                            <p style="margin: 5px 0; font-size: 14px;"><strong>الاسم
                                                                    التجاري:</strong>
                                                                {{ $client->trade_name ?? 'غير محدد' }}</p>
                                                            <p style="margin: 5px 0; font-size: 14px;">
                                                                <strong>المنطقة:</strong>
                                                                {{ $client->region ?? 'غير محدد' }}
                                                            </p>
                                                            <p style="margin: 5px 0; font-size: 14px;"><strong>رقم
                                                                    الهاتف:</strong> {{ $client->phone ?? 'غير محدد' }}</p>
                                                        </div>

                                                        <!-- ملخص الحساب -->
                                                        <table
                                                            style="width: 100%; border-collapse: collapse; direction: rtl; margin-bottom: 20px;">
                                                            <tr>
                                                                <td colspan="2"
                                                                    style="border: 1px solid #000; padding: 10px; text-align: center; direction: rtl; background-color: #e9ecef; font-weight: bold; font-size: 16px;">
                                                                    ملخص الحساب حتى
                                                                    {{ \Carbon\Carbon::now()->format('d/m/Y') }}
                                                                </td>
                                                            </tr>
 <tr>
                                                            <td style="border: 1px solid #000; padding: 10px; font-weight: bold;">الرصيد الافتتاحي</td>
                                                            <td style="border: 1px solid #000; padding: 10px; text-align: left; font-weight: bold;">{{number_format($client->opening_balance ?? 0, 2)}} ﷼</td>
                                                        </tr>
                                                            <tr>

                                                                <td
                                                                    style="border: 1px solid #000; padding: 10px; color: #dc3545;">
                                                                    إجمالي المبيعات</td>
                                                                <td
                                                                    style="border: 1px solid #000; padding: 10px; text-align: left; color: #dc3545;">
                                                                    {{ number_format($totalDeposits, 2) }} ﷼</td>
                                                            </tr>
                                                            <tr>
                                                                <td
                                                                    style="border: 1px solid #000; padding: 10px; color: #28a745;">
                                                                    إجمالي التحصيلات</td>
                                                                <td
                                                                    style="border: 1px solid #000; padding: 10px; text-align: left; color: #28a745;">
                                                                    {{ number_format($totalWithdraws, 2) }} ﷼</td>
                                                            </tr>
                                                            <tr style="background-color: #fff3cd;">
                                                                <td
                                                                    style="border: 1px solid #000; padding: 10px; font-weight: bold; font-size: 16px;">
                                                                    الرصيد الحالي</td>
                                                                <td
                                                                    style="border: 1px solid #000; padding: 10px; text-align: left; font-weight: bold; font-size: 16px;">
                                                                    {{ number_format($operationsPaginator->last()['balance'] ?? ($client->opening_balance ?? 0), 2) }}
                                                                    ﷼</td>
                                                            </tr>
                                                            {{-- المحفظة: مالٌ للعميل عندنا، حسابٌ مستقلٌّ عن ذمّته
                                                                 فلا يُجمع مع الرصيد أعلاه. يظهر السطر متى كان له رصيد. --}}
                                                            @php
                                                                $clientWalletBalance = \App\Services\Wallets\ClientWalletService::balance($client);
                                                            @endphp
                                                            @if ($clientWalletBalance != 0)
                                                                <tr style="background-color: #d1e7dd;">
                                                                    <td style="border: 1px solid #000; padding: 10px; font-weight: bold;">
                                                                        رصيد المحفظة (له عندنا)
                                                                    </td>
                                                                    <td style="border: 1px solid #000; padding: 10px; text-align: left; font-weight: bold;">
                                                                        {{ number_format($clientWalletBalance, 2) }} ﷼
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td colspan="2" style="border: 1px solid #000; padding: 8px; font-size: 12px; color: #555;">
                                                                        المحفظة حسابٌ مستقلٌّ لا يدخل الرصيد أعلاه — يُخصَم منه سدادُ الفواتير.
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                        </table>
                                                        <!-- حركة الحساب -->
                                                        <div
                                                            style="text-align: right; font-weight: bold; font-size: 18px; margin-bottom: 15px; direction: rtl;">
                                                            تفاصيل حركة الحساب
                                                        </div>

                                                      <table style="width: 100%; border-collapse: collapse; direction: rtl;">
    <thead style="background-color: #e9ecef;">
        <tr>
            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">التاريخ</th>
            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">نوع العملية</th>
            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">الوصف</th>
            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">المبلغ</th>
            <th style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold;">الرصيد بعد العملية</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($operationsPaginator as $operation)
        <tr>
            <td style="border: 1px solid #000; padding: 8px; text-align: center;">
                {{ \Carbon\Carbon::parse($operation['date'])->format('d/m/Y') }}
            </td>
            <td style="border: 1px solid #000; padding: 8px; text-align: center;
                @if($operation['type'] == 'تحصيل')
                    color: #28a745; font-weight: bold;
                @elseif($operation['type'] == 'فاتورة')
                    color: #dc3545; font-weight: bold;
                @endif">
                {{ $operation['type'] }} {{ $operation['reference'] }}
            </td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;">
                {{ $operation['description'] }}
            </td>
            <td style="border: 1px solid #000; padding: 8px; text-align: center;
                @if($operation['is_deposit'])
                    color: #dc3545; font-weight: bold;
                @else
                    color: #28a745; font-weight: bold;
                @endif">
                @if($operation['is_deposit'])
                    +{{ number_format($operation['amount'], 2) }} ﷼
                @else
                    -{{ number_format($operation['amount'], 2) }} ﷼
                @endif
            </td>
            <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">
                {{ number_format($operation['balance'], 2) }} ﷼
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background-color: #fff3cd;">
            <td colspan="4" style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; font-size: 16px;">
                الرصيد النهائي المستحق
            </td>
            <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; font-size: 16px;">
                {{ number_format($operationsPaginator->last()['balance'] ?? ($client->opening_balance ?? 0), 2) }} ﷼
            </td>
        </tr>
    </tfoot>
</table>
                                                        <!-- تذييل الكشف -->
                                                        <div
                                                            style="margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px;">
                                                            <p style="margin: 5px 0;">تم إنشاء هذا الكشف آليًا في
                                                                {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</p>
                                                            <p style="margin: 5px 0;">{{ $orgName }} -
                                                                جميع الحقوق محفوظة</p>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- سجل النشاطات -->
                            <div class="tab-pane fade" id="activity-log" role="tabpanel"
                                aria-labelledby="activity-log-tab">
                                <div class="activity-log">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        document.querySelector('.print-button').addEventListener('click', function() {
            var content = document.getElementById('print-section').innerHTML;
            var printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>كشف حساب عميل - طباعة</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: "Cairo", sans-serif; margin: 20px; direction: rtl; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }');
            printWindow.document.write('th, td { border: 1px solid #000; padding: 8px; text-align: right; }');
            printWindow.document.write('th { background-color: #f8f9fa; font-weight: bold; text-align: center; }');
            printWindow.document.write('h1, h2, h3 { color: #333; }');
            printWindow.document.write(
                '.company-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }'
            );
            printWindow.document.write(
                '.client-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }'
            );
            printWindow.document.write('.summary-row { background-color: #fff3cd; }');
            printWindow.document.write('.positive { color: #28a745; }');
            printWindow.document.write('.negative { color: #dc3545; }');
            printWindow.document.write(
                '.footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }'
            );
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        });
    </script>

@endsection
