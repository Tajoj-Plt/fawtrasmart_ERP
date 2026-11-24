<div style="font-family: 'Arial', sans-serif; direction: rtl; background: white; margin: 0; padding: 0; display: flex; justify-content: center; width: 100%;">
    <style>
         @media print {
            @page { margin: 0; size: 80mm auto; }
            body { margin: 0; }
        }
    </style>
    <div style="width: 80mm; padding: 8px; text-align: center; color: #333; background-color: #f8f9ff;">
        
        <!-- Header -->
        <div style="border-bottom: 2px solid #007bff; margin-bottom: 10px; padding-bottom: 10px; background-color: white; border-radius: 5px;">
            <div style="margin-bottom: 8px; font-weight: bold; color: #007bff; font-size: 14px;">فاتورة شراء</div>
            
            <h3 style="margin: 5px 0; font-size: 16px; color: #007bff; font-weight: bold;">{{ $company->name }}</h3>
            <p style="margin: 2px 0; font-size: 11px; color: #666;">الرقم الضريبي: {{ $company->vat_number }}</p>
            <p style="margin: 2px 0; font-size: 11px; color: #666;">{{ $company->address ?? 'العنوان غير متوفر' }}</p>
            <p style="margin: 2px 0; font-size: 11px; color: #666;">{{ $company->phone ?? 'الهاتف غير متوفر' }}</p>
        </div>

        <!-- Supplier & Invoice Info -->
        <div style="text-align: right; border-bottom: 1px solid #007bff; margin-bottom: 10px; padding-bottom: 10px; font-size: 11px; background-color: white; border-radius: 5px;">
            <p style="margin: 3px 0; color: #333;"><strong style="color: #007bff;">رقم الفاتورة:</strong> {{ $invoice->invoice_number }}</p>
            <p style="margin: 3px 0; color: #333;"><strong style="color: #007bff;">التاريخ:</strong> {{ $invoice->invoice_date->format('Y-m-d') }}</p>
            <p style="margin: 3px 0; color: #333;"><strong style="color: #007bff;">المورد:</strong> {{ $invoice->supplier->trade_name }}</p>
            <p style="margin: 3px 0; color: #333;"><strong style="color: #007bff;">الهاتف:</strong> {{ $invoice->supplier->phone ?? 'غير متوفر' }}</p>
            @if($invoice->supplier->vat_number)
            <p style="margin: 3px 0; color: #333;"><strong style="color: #007bff;">ضريبة المورد:</strong> {{ $invoice->supplier->vat_number }}</p>
            @endif
        </div>

        <!-- Items Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11px; background-color: white; border-radius: 3px; overflow: hidden;">
            <thead>
                <tr style="border-bottom: 1px solid #007bff; background-color: #f8f9ff;">
                    <th style="padding: 5px; text-align: right; font-weight: bold; color: #007bff;">المنتج</th>
                    <th style="padding: 5px; text-align: center; width: 30px; font-weight: bold; color: #007bff;">ك</th>
                    <th style="padding: 5px; text-align: center; width: 40px; font-weight: bold; color: #007bff;">سعر</th>
                    <th style="padding: 5px; text-align: center; width: 40px; font-weight: bold; color: #007bff;">إجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr style="border-bottom: 1px solid #e9ecef;">
                    <td style="padding: 4px; text-align: right;">{{ $item->product_name }}</td>
                    <td style="padding: 4px; text-align: center;">{{ $item->quantity }}</td>
                    <td style="padding: 4px; text-align: center;">{{ number_format($item->unit_price, 2) }}</td>
                    <td style="padding: 4px; text-align: center;">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div style="border-top: 1px solid #007bff; padding-top: 8px; width: 100%; font-size: 11px; background-color: white; border-radius: 0 0 5px 5px; margin-top: 5px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 3px; padding: 2px 0;">
                <span style="color: #007bff; font-weight: bold;">المجموع:</span>
                <span>{{ number_format($invoice->sub_total, 2) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 3px; padding: 2px 0;">
                <span style="color: #007bff; font-weight: bold;">الضريبة ({{ $invoice->tax_rate ?? '15' }}%):</span>
                <span>{{ number_format($invoice->total_tax, 2) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; margin-top: 5px; border-top: 2px solid #007bff; padding-top: 8px; color: #007bff;">
                <span>الإجمالي:</span>
                <span>{{ number_format($invoice->grand_total, 2) }}</span>
            </div>
        </div>

        <!-- Footer -->
        <div style="margin-top: 15px; border-top: 1px solid #007bff; padding-top: 12px; font-size: 11px; background-color: white; border-radius: 5px;">
            <p style="margin-bottom: 8px; color: #007bff; font-weight: bold;"><i class="fas fa-heart"></i> شكراً لتعاملكم معنا</p>
            
            <div style="margin: 10px auto; width: 70px; height: 70px; background: white; border: 1px solid #007bff; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
             QR
            </div>
            
            <p style="font-size: 9px; color: #666; margin: 5px 0;">{{ now()->format('Y-m-d H:i:s') }}</p>
            <p style="font-size: 10px; color: #666; margin: 3px 0;">{{ $company->website ?? 'www.company.com' }}</p>
        </div>
    </div>
</div>
