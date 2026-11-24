<div class="invoice-container" style="font-family: 'Tajawal', sans-serif; direction: rtl; padding: 30px; color: #333; max-width: 800px; margin: 0 auto; background: white;">

    <!-- Logo Section -->
    <div class="logo-section" style="text-align: center; margin-bottom: 20px;">
        @if(isset($company->logo) && $company->logo)
        <img src="{{ $company->logo }}" alt="شعار الشركة" style="max-height: 80px; max-width: 200px;">
        @endif
    </div>

    <!-- Header -->
    <div class="header-section" style="border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div class="company-info">
                <h2 style="margin: 0 0 10px 0; color: #007bff; font-size: 26px;">فاتورة شراء</h2>
                <p class="company-name" style="margin: 5px 0; font-size: 18px; font-weight: bold;">{{ $company->name }}</p>
                <p class="company-address" style="margin: 3px 0; font-size: 13px; color: #666;">{{ $company->address }}</p>
                <p class="company-phone" style="margin: 3px 0; font-size: 13px; color: #666;"><i class="fas fa-phone"></i> {{ $company->phone }}</p>
            </div>
            <div class="invoice-info" style="text-align: left; background: #f8f9ff; padding: 15px; border-radius: 8px; min-width: 220px;">
                <p class="invoice-number" style="margin: 5px 0; font-size: 14px;"><strong>رقم الفاتورة:</strong> {{ $invoice->invoice_number }}</p>
                <p class="invoice-date" style="margin: 5px 0; font-size: 14px;"><strong>التاريخ:</strong> {{ $invoice->invoice_date->format('Y-m-d') }}</p>
                <p class="due-date" style="margin: 5px 0; font-size: 14px;"><strong>تاريخ الاستحقاق:</strong> {{ $invoice->due_date->format('Y-m-d') }}</p>
                <p class="company-vat" style="margin: 5px 0; font-size: 14px;"><strong>الرقم الضريبي:</strong> {{ $company->vat_number }}</p>
            </div>
        </div>
    </div>

    <!-- Supplier & Notes Section -->
    <div style="display: flex; gap: 20px; margin-bottom: 30px;">
        <div class="supplier-section" style="flex: 1; border: 1px solid #007bff; padding: 18px; border-radius: 8px; background-color: #f8f9ff;">
            <h4 style="margin: 0 0 12px 0; color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 8px; font-size: 16px;">
                <i class="fas fa-user-tie"></i> بيانات المورد
            </h4>
            <p class="supplier-name" style="margin: 6px 0; font-size: 14px;"><strong>الاسم:</strong> {{ $invoice->supplier->trade_name }}</p>
            <p class="supplier-address" style="margin: 6px 0; font-size: 14px;"><strong>العنوان:</strong> {{ $invoice->supplier->address }}</p>
            <p class="supplier-phone" style="margin: 6px 0; font-size: 14px;"><strong>الهاتف:</strong> {{ $invoice->supplier->phone ?? 'غير متوفر' }}</p>
            <p class="supplier-vat" style="margin: 6px 0; font-size: 14px;"><strong>الرقم الضريبي:</strong> {{ $invoice->supplier->vat_number }}</p>
        </div>
        <div class="notes-section" style="flex: 1; border: 1px solid #28a745; padding: 18px; border-radius: 8px; background-color: #f8fff8;">
            <h4 style="margin: 0 0 12px 0; color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 8px; font-size: 16px;">
                <i class="fas fa-sticky-note"></i> ملاحظات الفاتورة
            </h4>
            <p class="notes" style="margin: 6px 0; font-size: 14px; line-height: 1.6;">{{ $invoice->notes ?? 'لا توجد ملاحظات' }}</p>
        </div>
    </div>

    <!-- Items Table -->
    <div class="items-table-section" style="margin-bottom: 25px;">
        <table style="width: 100%; border-collapse: collapse; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                    <th style="padding: 14px; border: 1px solid #0056b3; font-weight: bold; font-size: 14px;">#</th>
                    <th class="product-name" style="padding: 14px; border: 1px solid #0056b3; font-weight: bold; font-size: 14px;">المنتج</th>
                    <th class="quantity" style="padding: 14px; border: 1px solid #0056b3; width: 80px; font-weight: bold; font-size: 14px;">الكمية</th>
                    <th class="unit-price" style="padding: 14px; border: 1px solid #0056b3; width: 120px; font-weight: bold; font-size: 14px;">سعر الوحدة</th>
                    <th class="item-tax" style="padding: 14px; border: 1px solid #0056b3; width: 100px; font-weight: bold; font-size: 14px;">الضريبة</th>
                    <th class="item-total" style="padding: 14px; border: 1px solid #0056b3; width: 120px; font-weight: bold; font-size: 14px;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr style="border-bottom: 1px solid #eee; {{ $index % 2 == 0 ? 'background-color: #ffffff;' : 'background-color: #f9f9f9;' }}">
                    <td style="padding: 12px; border: 1px solid #eee; text-align: center; font-size: 13px;">{{ $index + 1 }}</td>
                    <td class="product-name" style="padding: 12px; border: 1px solid #eee; font-size: 13px;">
                        <strong>{{ $item->product_name }}</strong>
                        @if(isset($item->description) && $item->description)
                        <br><small style="color: #666;">{{ $item->description }}</small>
                        @endif
                    </td>
                    <td class="quantity" style="padding: 12px; border: 1px solid #eee; text-align: center; font-size: 13px;">{{ $item->quantity }}</td>
                    <td class="unit-price" style="padding: 12px; border: 1px solid #eee; text-align: center; font-size: 13px;">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="item-tax" style="padding: 12px; border: 1px solid #eee; text-align: center; font-size: 13px;">{{ number_format($item->tax, 2) }}</td>
                    <td class="item-total" style="padding: 12px; border: 1px solid #eee; text-align: center; font-size: 13px; font-weight: bold;">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Totals Section -->
    <div class="totals-section" style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
        <div style="width: 320px; background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%); padding: 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <div class="sub-total" style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #007bff; font-size: 14px;">
                <span>المجموع الفرعي:</span>
                <span style="font-weight: bold;">{{ number_format($invoice->sub_total, 2) }} {{ $currency ?? 'ريال' }}</span>
            </div>
            <div class="discount" style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #007bff; font-size: 14px; color: #dc3545;">
                <span>الخصم:</span>
                <span style="font-weight: bold;">- {{ number_format($invoice->total_discount ?? $invoice->discount ?? 0, 2) }} {{ $currency ?? 'ريال' }}</span>
            </div>
            <div class="total-tax" style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #007bff; font-size: 14px;">
                <span>الضريبة (15%):</span>
                <span style="font-weight: bold;">{{ number_format($invoice->total_tax, 2) }} {{ $currency ?? 'ريال' }}</span>
            </div>
            <div class="grand-total" style="display: flex; justify-content: space-between; padding: 15px; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; margin-top: 12px; border-radius: 8px; font-size: 18px;">
                <strong>الإجمالي الكلي:</strong>
                <strong>{{ number_format($invoice->grand_total, 2) }} {{ $currency ?? 'ريال' }}</strong>
            </div>
        </div>
    </div>

    <!-- Payment Status -->
    <div class="payment-status" style="margin-bottom: 25px; padding: 15px; border-radius: 8px;
        {{ $invoice->payment_status == 'paid' ? 'background-color: #d4edda; border: 1px solid #28a745;' :
           ($invoice->payment_status == 'partial' ? 'background-color: #fff3cd; border: 1px solid #ffc107;' :
           'background-color: #f8d7da; border: 1px solid #dc3545;') }}">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 16px; font-weight: bold;">
                حالة الدفع:
                <span style="{{ $invoice->payment_status == 'paid' ? 'color: #28a745;' : ($invoice->payment_status == 'partial' ? 'color: #856404;' : 'color: #dc3545;') }}">
                    {{ $invoice->payment_status == 'paid' ? 'مدفوعة بالكامل' : ($invoice->payment_status == 'partial' ? 'مدفوعة جزئياً' : 'غير مدفوعة') }}
                </span>
            </span>
            @if($invoice->payment_status != 'paid')
            <span style="font-size: 14px;">
                المبلغ المتبقي: <strong style="color: #dc3545;">{{ number_format($invoice->amount_due ?? ($invoice->grand_total - ($invoice->amount_paid ?? 0)), 2) }} {{ $currency ?? 'ريال' }}</strong>
            </span>
            @endif
        </div>
    </div>

    <!-- QR Code Section -->
    <div class="qr-code" style="text-align: center; margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <div style="display: inline-block; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="width: 120px; height: 120px; background: #eee; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                <i class="fas fa-qrcode" style="font-size: 80px; color: #333;"></i>
            </div>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">رمز QR للفاتورة</p>
        </div>
    </div>

    <!-- Signature Section -->
    <div class="signature" style="display: flex; justify-content: space-between; margin-bottom: 30px; padding: 20px 0; border-top: 1px dashed #ddd;">
        <div style="text-align: center; width: 45%;">
            <div style="border-bottom: 2px solid #333; padding-bottom: 60px; margin-bottom: 10px;"></div>
            <p style="margin: 0; font-size: 14px; font-weight: bold;">توقيع المستلم</p>
        </div>
        <div style="text-align: center; width: 45%;">
            <div style="border-bottom: 2px solid #333; padding-bottom: 60px; margin-bottom: 10px;"></div>
            <p style="margin: 0; font-size: 14px; font-weight: bold;">ختم الشركة</p>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer-section" style="text-align: center; color: #007bff; border-top: 3px solid #007bff; padding-top: 25px; background: linear-gradient(to right, #ffffff, #e3f2fd, #ffffff);">
        <p style="font-size: 18px; margin: 10px 0; font-weight: bold;">
            <i class="fas fa-heart" style="color: #dc3545;"></i> شكراً لتعاملكم معنا
        </p>
        <p style="font-size: 14px; color: #666; margin: 8px 0;">نأمل أن تكون خدماتنا قد نالت رضاكم</p>
        <div style="margin-top: 15px; font-size: 13px; color: #888;">
            <p style="margin: 4px 0;"><i class="fas fa-phone"></i> {{ $company->phone ?? 'غير متوفر' }} | <i class="fas fa-envelope"></i> {{ $company->email ?? 'غير متوفر' }}</p>
            <p style="margin: 4px 0;"><i class="fas fa-globe"></i> {{ $company->website ?? 'غير متوفر' }}</p>
        </div>
    </div>
</div>
