<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'يجب قبول :attribute.',
    'active_url' => ':attribute ليس رابطاً صحيحاً.',
    'after' => 'يجب أن يكون :attribute تاريخاً بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخاً بعد أو يساوي :date.',
    'alpha' => 'يجب أن يحتوي :attribute على أحرف فقط.',
    'alpha_dash' => 'يجب أن يحتوي :attribute على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي :attribute على أحرف وأرقام فقط.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'before' => 'يجب أن يكون :attribute تاريخاً قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخاً قبل أو يساوي :date.',
    'between' => [
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'file' => 'يجب أن يكون حجم :attribute بين :min و :max كيلوبايت.',
        'string' => 'يجب أن يكون طول :attribute بين :min و :max حرفاً.',
        'array' => 'يجب أن يحتوي :attribute بين :min و :max عنصراً.',
    ],
    'boolean' => 'يجب أن تكون قيمة الحقل :attribute صحيحة أو خاطئة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'date' => ':attribute ليس تاريخاً صحيحاً.',
    'date_equals' => 'يجب أن يكون :attribute تاريخاً يساوي :date.',
    'date_format' => ':attribute غير مطابق للصيغة :format.',
    'different' => 'يجب أن يكون :attribute و :other مختلفين.',
    'digits' => 'يجب أن يتكون :attribute من :digits أرقام.',
    'digits_between' => 'يجب أن يكون عدد أرقام :attribute بين :min و :max.',
    'dimensions' => 'أبعاد الصورة :attribute غير صحيحة.',
    'distinct' => 'الحقل :attribute يحتوي على قيمة مكررة.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'ends_with' => 'يجب أن ينتهي :attribute بأحد القيم التالية: :values.',
    'exists' => ':attribute المختار غير صحيح.',
    'file' => 'يجب أن يكون :attribute ملفاً.',
    'filled' => 'الحقل :attribute مطلوب.',
    'gt' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'file' => 'يجب أن يكون حجم :attribute أكبر من :value كيلوبايت.',
        'string' => 'يجب أن يكون طول :attribute أكبر من :value حرفاً.',
        'array' => 'يجب أن يحتوي :attribute على أكثر من :value عنصراً.',
    ],
    'gte' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من أو تساوي :value.',
        'file' => 'يجب أن يكون حجم :attribute أكبر من أو يساوي :value كيلوبايت.',
        'string' => 'يجب أن يكون طول :attribute أكبر من أو يساوي :value حرفاً.',
        'array' => 'يجب أن يحتوي :attribute على :value عنصراً أو أكثر.',
    ],
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => ':attribute المختار غير صحيح.',
    'in_array' => 'الحقل :attribute غير موجود في :other.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'ip' => 'يجب أن يكون :attribute عنوان IP صحيحاً.',
    'ipv4' => 'يجب أن يكون :attribute عنوان IPv4 صحيحاً.',
    'ipv6' => 'يجب أن يكون :attribute عنوان IPv6 صحيحاً.',
    'json' => 'يجب أن يكون :attribute نص JSON صحيحاً.',
    'lt' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value.',
        'file' => 'يجب أن يكون حجم :attribute أقل من :value كيلوبايت.',
        'string' => 'يجب أن يكون طول :attribute أقل من :value حرفاً.',
        'array' => 'يجب أن يحتوي :attribute على أقل من :value عنصراً.',
    ],
    'lte' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من أو تساوي :value.',
        'file' => 'يجب أن يكون حجم :attribute أقل من أو يساوي :value كيلوبايت.',
        'string' => 'يجب أن يكون طول :attribute أقل من أو يساوي :value حرفاً.',
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :value عنصراً.',
    ],
    'max' => [
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'file' => 'يجب ألا يكون حجم :attribute أكبر من :max كيلوبايت.',
        'string' => 'يجب ألا يكون طول :attribute أكبر من :max حرفاً.',
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصراً.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'min' => [
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'string' => 'يجب ألا يقل طول :attribute عن :min حرفاً.',
        'array' => 'يجب ألا يقل عدد عناصر :attribute عن :min.',
    ],
    'not_in' => ':attribute المختار غير صحيح.',
    'not_regex' => 'صيغة :attribute غير صحيحة.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'password' => 'كلمة المرور غير صحيحة.',
    'present' => 'يجب أن يكون الحقل :attribute موجوداً.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'required' => 'الحقل :attribute مطلوب.',
    'required_if' => 'الحقل :attribute مطلوب عندما تكون قيمة :other هي :value.',
    'required_unless' => 'الحقل :attribute مطلوب إلا إذا كانت قيمة :other ضمن :values.',
    'required_with' => 'الحقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'الحقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'الحقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'الحقل :attribute مطلوب عند عدم وجود أيٍّ من :values.',
    'same' => 'يجب أن يتطابق :attribute و :other.',
    'size' => [
        'numeric' => 'يجب أن تكون قيمة :attribute مساوية لـ :size.',
        'file' => 'يجب أن يكون حجم :attribute مساوياً لـ :size كيلوبايت.',
        'string' => 'يجب أن يكون طول :attribute مساوياً لـ :size حرفاً.',
        'array' => 'يجب أن يحتوي :attribute على :size عنصراً.',
    ],
    'starts_with' => 'يجب أن يبدأ :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صحيحة.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',
    'uploaded' => 'فشل رفع :attribute.',
    'url' => 'صيغة :attribute غير صحيحة.',
    'uuid' => 'يجب أن يكون :attribute مُعرِّف UUID صحيحاً.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'expiry_date' => [
            'after_or_equal' => 'يجب أن يكون تاريخ الانتهاء بعد أو يساوي تاريخ المستند.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'username' => 'اسم المستخدم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'phone' => 'الهاتف',
        'mobile' => 'الجوال',
        'address' => 'العنوان',
        'city' => 'المدينة',
        'country' => 'الدولة',
        'description' => 'الوصف',
        'notes' => 'الملاحظات',
        'code' => 'الرقم',
        'title' => 'العنوان',
        'status' => 'الحالة',
        'amount' => 'المبلغ',
        'quantity' => 'الكمية',
        'price' => 'السعر',
        'discount' => 'الخصم',
        'discount_type' => 'نوع الخصم',
        'discount_amount' => 'قيمة الخصم',
        'tax' => 'الضريبة',
        'tax_1' => 'الضريبة',
        'tax_1_id' => 'الضريبة',
        'tax_2' => 'الضريبة الثانية',
        'tax_2_id' => 'الضريبة الثانية',
        'tax_type' => 'نوع الضريبة',
        'shipping_cost' => 'قيمة الشحن',
        'shipping_tax' => 'ضريبة الشحن',
        'currency' => 'العملة',
        'attachments' => 'المرفقات',
        'branch_id' => 'الفرع',
        'client_id' => 'العميل',
        'supplier_id' => 'المورّد',
        'product_id' => 'الصنف',
        'items' => 'البنود',
        'items.*.product_id' => 'الصنف',
        'items.*.quantity' => 'الكمية',
        'items.*.unit_price' => 'سعر الوحدة',
        'items.*.discount' => 'خصم البند',
        'items.*.description' => 'وصف البند',
        'quote_date' => 'تاريخ العرض',
        'expiry_date' => 'تاريخ الانتهاء',
        'order_date' => 'تاريخ المستند',
        'due_date' => 'تاريخ الاستحقاق',
        'invoice_date' => 'تاريخ الفاتورة',
        'received_date' => 'تاريخ الاستلام',
        'valid_days' => 'عدد أيام الصلاحية',
        'terms' => 'مهلة السداد',
        'reference_id' => 'المستند المرجعي',
        'payment_method' => 'طريقة الدفع',
        'advance_payment' => 'الدفعة المقدَّمة',
        'advance_payment_type' => 'نوع الدفعة المقدَّمة',
        'advance_payment_method' => 'طريقة الدفعة المقدَّمة',
        'advance_payment_treasury_id' => 'خزينة الدفعة المقدَّمة',
        'refund_amount' => 'مبلغ الاسترداد',
        'refund_treasury_id' => 'خزينة الاسترداد',
        'payment_status' => 'حالة السداد',

        // حقول واجهة الجوال والشاشات المشتركة — كانت تظهر بأسمائها
        // الإنجليزية داخل رسالةٍ عربية («الحقل sale price مطلوب»)
        'otp' => 'رمز التحقق',
        'avatar' => 'الصورة الشخصية',
        'current_password' => 'كلمة المرور الحالية',
        'personal_email' => 'البريد الشخصي',
        'mobile_number' => 'رقم الجوال',
        'phone_number' => 'رقم الهاتف',
        'nickname' => 'الاسم المختصر',
        'language' => 'اللغة',
        'region' => 'المنطقة',
        'postal_code' => 'الرمز البريدي',
        'emergency_contact_name' => 'اسم جهة الطوارئ',
        'emergency_contact_phone' => 'هاتف جهة الطوارئ',
        'emergency_contact_relation' => 'صلة جهة الطوارئ',
        'device_name' => 'اسم الجهاز',
        'date' => 'التاريخ',
        'invoice_id' => 'الفاتورة',
        'storehouse_id' => 'المستودع',
        'return_storehouse_id' => 'مستودع المرتجع',
        'store_houses_id' => 'المستودع',
        'from_store_houses_id' => 'المستودع المحوَّل منه',
        'to_store_houses_id' => 'المستودع المحوَّل إليه',
        'permission_type' => 'نوع الإذن',
        'permission_date' => 'تاريخ الإذن',
        'grand_total' => 'الإجمالي',
        'unit_price' => 'سعر الوحدة',
        'total' => 'الإجمالي',
        'sale_price' => 'سعر البيع',
        'exchange_date' => 'تاريخ التبديل',
        'image' => 'الصورة',
        'treasury_id' => 'الخزينة',
        'from_treasury_id' => 'الخزينة المحوَّل منها',
        'to_treasury_id' => 'الخزينة المحوَّل إليها',
        'transfer_date' => 'تاريخ التحويل',
        'from_currency' => 'عملة المصدر',
        'to_currency' => 'عملة الوجهة',
        'account_id' => 'الحساب',
        'expense_type' => 'نوع سند الصرف',
        'expenses_category_id' => 'تصنيف المصروف',
        'incomes_category_id' => 'تصنيف الإيراد',
        'cost_center_id' => 'مركز التكلفة',
        'payment_method_id' => 'طريقة الدفع',
        'rejection_reason' => 'سبب الرفض',
        'client_ids' => 'العملاء',
        'trade_name' => 'الاسم التجاري',
        'commercial_registration' => 'السجل التجاري',
        'employee_id' => 'الموظف',
        'tax1' => 'الضريبة الأولى',
        'tax2' => 'الضريبة الثانية',
        'tax1_account_id' => 'حساب الضريبة الأولى',
        'tax2_account_id' => 'حساب الضريبة الثانية',
    ],

];
