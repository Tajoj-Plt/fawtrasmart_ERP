<div style="font-family: 'Tajawal', Tahoma, Arial, sans-serif; direction: rtl; padding: 40px; color: #333; max-width: 800px; margin: 0 auto; border: 1px solid #007bff; border-radius: 10px; background-color: #f8f9ff;">
    
    <div style="text-align: center; margin-bottom: 40px; background-color: #007bff; color: white; padding: 20px; border-radius: 8px;">
        <h2 style="text-transform: uppercase; letter-spacing: 2px; margin: 0; font-size: 28px;">فاتورة شراء</h2>
        <p style="margin-top: 10px; font-size: 16px;">{{ $company->name }}</p>
        <p style="margin: 5px 0 0; font-size: 14px;">الرقم الضريبي: {{ $company->vat_number }}</p>
    </div>

    <div style="margin-bottom: 40px; padding: 20px; background-color: white; border-radius: 8px; border: 1px solid #e9ecef;">
        <div style="float: left; width: 45%;">
            <strong style="color: #007bff; font-size: 16px;">إلى السادة:</strong><br>
            <span style="font-size: 14px;">{{ $invoice->supplier->trade_name }}</span><br>
            <span style="font-size: 14px;">{{ $invoice->supplier->address }}</span><br>
            <span style="font-size: 14px;">الهاتف: {{ $invoice->supplier->phone ?? 'غير متوفر' }}</span><br>
            <span style="font-size: 14px;">VAT: {{ $invoice->supplier->vat_number }}</span>
        </div>
        <div style="float: right; width: 45%; text-align: left;">
            <strong style="color: #28a745; font-size: 16px;">رقم الفاتورة:</strong> #{{ $invoice->invoice_number }}<br>
            <strong style="color: #28a745; font-size: 16px;">التاريخ:</strong> {{ $invoice->invoice_date->format('Y-m-d') }}<br>
            <strong style="color: #28a745; font-size: 16px;">تاريخ الاستحقاق:</strong> {{ $invoice->due_date->format('Y-m-d') ?? 'غير محدد' }}<br>
            <strong style="color: #28a745; font-size: 16px;">الرقم الضريبي:</strong> {{ $company->vat_number }}
        </div>
        <div style="clear: both;"></div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden;">
        <thead>
            <tr style="background-color: #007bff; color: white;">
                <th style="text-align: right; padding: 12px 10px; font-weight: bold;">الوصف</th>
                <th style="text-align: center; padding: 12px 10px; font-weight: bold;">الكمية</th>
                <th style="text-align: right; padding: 12px 10px; font-weight: bold;">السعر</th>
                <th style="text-align: right; padding: 12px 10px; font-weight: bold;">المجموع</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr style="border-bottom: 1px solid #e9ecef; transition: background-color 0.3s;">
                <td style="padding: 12px 10px; background-color: #fff;">{{ $item->product_name }}</td>
                <td style="text-align: center; padding: 12px 10px; background-color: #fff;">{{ $item->quantity }}</td>
                <td style="text-align: right; padding: 12px 10px; background-color: #fff;">{{ number_format($item->unit_price, 2) }}</td>
                <td style="text-align: right; padding: 12px 10px; background-color: #fff;">{{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="float: left; width: 50%;">
        <p style="font-size: 14px; margin-top: 20px; padding: 15px; background-color: #f8f9ff; border-right: 3px solid #007bff; border-radius: 5px;">
            <strong style="color: #007bff; display: block; margin-bottom: 5px;">ملاحظات:</strong>
            {{ $invoice->notes ?? 'لا توجد ملاحظات خاصة بالفاتورة' }}
        </p>
    </div>

    <div style="float: right; width: 40%;">
        <table style="width: 100%; background-color: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <tr>
                <td style="padding: 8px 0; color: #495057;"><strong>المجموع الفرعي:</strong></td>
                <td style="text-align: right; padding: 8px 0;">{{ number_format($invoice->sub_total, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #495057;"><strong>الضريبة ({{ $invoice->tax_rate ?? '15' }}%):</strong></td>
                <td style="text-align: right; padding: 8px 0;">{{ number_format($invoice->total_tax, 2) }}</td>
            </tr>
            <tr style="border-top: 2px solid #007bff;">
                <td style="padding: 12px 0; font-size: 18px; color: #007bff;"><strong>الإجمالي الكلي:</strong></td>
                <td style="text-align: right; padding: 12px 0; font-size: 18px; color: #007bff;"><strong>{{ number_format($invoice->grand_total, 2) }}</strong></td>
            </tr>
        </table>
    </div>
    <div style="clear: both;"></div>
</div>
