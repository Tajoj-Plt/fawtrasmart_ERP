<div style="font-family: 'Tajawal', sans-serif; direction: rtl; color: #444; width: 100%;">
    
    <!-- Colored Header Block -->
    <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,123,255,0.3);">
        <div>
            <h1 style="margin: 0; font-size: 32px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">فاتورة شراء</h1>
            <p style="margin: 5px 0; opacity: 0.9; font-size: 18px;">#{{ $invoice->invoice_number }}</p>
        </div>
        <div style="text-align: left;">
            <h2 style="margin: 0; font-size: 24px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">{{ $company->name }}</h2>
            <p style="margin: 5px 0; opacity: 0.8; font-size: 14px;">{{ $company->vat_number }}</p>
        </div>
    </div>

    <div style="padding: 40px;">
        
        <!-- Info Grid -->
        <div style="display: flex; gap: 40px; margin-bottom: 40px;">
            <div style="flex: 1; background-color: #f8f9ff; padding: 20px; border-radius: 10px; border-left: 4px solid #007bff;">
                <h6 style="color: #007bff; text-transform: uppercase; letter-spacing: 1px; font-size: 12px; margin-bottom: 10px; font-weight: bold;">معلومات المورد</h6>
                <h4 style="margin: 10px 0; color: #007bff;">{{ $invoice->supplier->trade_name }}</h4>
                <p style="margin: 5px 0; color: #666; display: flex; align-items: center;"><i class="fas fa-map-marker-alt" style="margin-left: 5px;"></i> {{ $invoice->supplier->address }}</p>
                <p style="margin: 5px 0; color: #666; display: flex; align-items: center;"><i class="fas fa-phone" style="margin-left: 5px;"></i> {{ $invoice->supplier->phone ?? 'غير متوفر' }}</p>
                <p style="margin: 5px 0; color: #666; display: flex; align-items: center;"><i class="fas fa-id-card" style="margin-left: 5px;"></i> الرقم الضريبي: {{ $invoice->supplier->vat_number }}</p>
            </div>
            <div style="flex: 1; background-color: #f8f9ff; padding: 20px; border-radius: 10px; border-left: 4px solid #28a745;">
                <h6 style="color: #28a745; text-transform: uppercase; letter-spacing: 1px; font-size: 12px; margin-bottom: 10px; font-weight: bold;">تفاصيل الفاتورة</h6>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #e9ecef; padding-bottom: 8px; margin-bottom: 8px;">
                    <span style="display: flex; align-items: center;"><i class="fas fa-calendar-alt" style="margin-left: 5px; color: #007bff;"></i> التاريخ:</span>
                    <strong>{{ $invoice->invoice_date->format('Y-m-d') }}</strong>
                </div>
                 <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #e9ecef; padding-bottom: 8px; margin-bottom: 8px;">
                    <span style="display: flex; align-items: center;"><i class="fas fa-calendar-check" style="margin-left: 5px; color: #28a745;"></i> تاريخ الاستحقاق:</span>
                    <strong>{{ $invoice->due_date->format('Y-m-d') ?? 'غير محدد' }}</strong>
                </div>
                 <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #e9ecef; padding-bottom: 8px; margin-bottom: 8px;">
                    <span style="display: flex; align-items: center;"><i class="fas fa-id-card" style="margin-left: 5px; color: #dc3545;"></i> الرقم الضريبي للشركة:</span>
                    <strong>{{ $company->vat_number }}</strong>
                </div>
            </div>
        </div>

        <!-- Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                    <th style="text-align: right; padding: 15px 10px; color: #007bff; text-transform: uppercase; font-size: 12px; font-weight: bold; border-bottom: 2px solid #dee2e6;">المنتج / الوصف</th>
                    <th style="text-align: center; padding: 15px 10px; color: #007bff; text-transform: uppercase; font-size: 12px; font-weight: bold; border-bottom: 2px solid #dee2e6; width: 100px;">الكمية</th>
                    <th style="text-align: center; padding: 15px 10px; color: #007bff; text-transform: uppercase; font-size: 12px; font-weight: bold; border-bottom: 2px solid #dee2e6; width: 150px;">سعر الوحدة</th>
                    <th style="text-align: center; padding: 15px 10px; color: #007bff; text-transform: uppercase; font-size: 12px; font-weight: bold; border-bottom: 2px solid #dee2e6; width: 150px;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                 @foreach($invoice->items as $item)
                <tr style="transition: background-color 0.3s;">
                    <td style="padding: 15px 10px; border-bottom: 1px solid #e9ecef; background-color: #fff;">
                        <strong style="display: block; color: #333;">{{ $item->product_name }}</strong>
                        <small style="color: #6c757d;">{{ $item->product_code ?? '' }}</small>
                    </td>
                    <td style="text-align: center; padding: 15px 10px; border-bottom: 1px solid #e9ecef; color: #666; background-color: #fff;">{{ $item->quantity }}</td>
                    <td style="text-align: center; padding: 15px 10px; border-bottom: 1px solid #e9ecef; color: #666; background-color: #fff;">{{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: center; padding: 15px 10px; border-bottom: 1px solid #e9ecef; color: #333; font-weight: bold; background-color: #fff;">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <div style="width: 320px; background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%); padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #e9ecef;">
                <div style="display: flex; justify-content: space-between; padding: 12px 0; color: #495057; border-bottom: 1px dashed #007bff;">
                    <span style="font-weight: bold; display: flex; align-items: center;"><i class="fas fa-calculator" style="margin-left: 8px; color: #007bff;"></i> المجموع الفرعي</span>
                    <span style="font-weight: bold;">{{ number_format($invoice->sub_total, 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 12px 0; color: #495057; border-bottom: 1px dashed #28a745;">
                    <span style="font-weight: bold; display: flex; align-items: center;"><i class="fas fa-percent" style="margin-left: 8px; color: #28a745;"></i> الضريبة ({{ $invoice->tax_rate ?? '15' }}%)</span>
                    <span style="font-weight: bold;">{{ number_format($invoice->total_tax, 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 15px 0; color: #007bff; font-size: 22px; font-weight: bold; background: linear-gradient(to right, #e3f2fd, #ffffff); border-radius: 5px; margin-top: 5px;">
                    <span style="display: flex; align-items: center;"><i class="fas fa-money-bill-wave" style="margin-left: 8px; color: #007bff;"></i> الإجمالي الكلي</span>
                    <span>{{ number_format($invoice->grand_total, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="margin-top: 60px; text-align: center; color: #007bff; font-size: 14px; background: linear-gradient(to right, #e3f2fd, #ffffff, #e3f2fd); padding: 20px; border-top: 2px solid #007bff; border-radius: 10px 10px 0 0;">
            <p style="font-size: 18px; margin: 10px 0; font-weight: bold;"><i class="fas fa-thumbs-up"></i> شكراً لتعاملكم معنا</p>
            <p style="font-size: 14px; color: #666; margin: 5px 0;">نأمل أن تكون خدماتنا قد نالت رضاكم الكامل</p>
            <div style="margin-top: 15px; display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
                <p style="margin: 3px 0; display: flex; align-items: center;"><i class="fas fa-phone" style="margin: 0 5px; color: #007bff;"></i> {{ $company->phone ?? 'غير متوفر' }}</p>
                <p style="margin: 3px 0; display: flex; align-items: center;"><i class="fas fa-envelope" style="margin: 0 5px; color: #28a745;"></i> {{ $company->email ?? 'غير متوفر' }}</p>
                <p style="margin: 3px 0; display: flex; align-items: center;"><i class="fas fa-globe" style="margin: 0 5px; color: #ffc107;"></i> {{ $company->website ?? 'غير متوفر' }}</p>
            </div>
        </div>
    </div>
</div>
