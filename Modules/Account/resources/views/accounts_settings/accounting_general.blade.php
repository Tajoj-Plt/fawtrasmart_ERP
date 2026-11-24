@extends('account::master')

@section('title', 'الإعدادات المحاسبية العامة')

@section('content')
    @php
        /*
            كانت الشاشة أربعة مفاتيح تبديل داخل `<form action="#">` وزرُّ
            الحفظ **خارج** النموذج — فلا يحفظ ولا يحاول. صارت تقرأ من
            `accounting_general_settings` وتكتب فيه.

            والمفاتيح موصوفة: مفتاحٌ اسمه «تعيين وسوم إلى حركات القيود» بلا
            سطرٍ يشرح أثره يُترك على حاله لأن أحداً لا يعرف ماذا يفعل.
        */
        $toggles = [
            [
                'key' => 'show_cost_center',
                'icon' => 'pie-chart',
                'title' => 'مركز التكلفة في القيود',
                'hint' => 'يُظهر عمود مركز التكلفة في سطور القيد — أخفِه إن لم تكن المنشأة تستعملها',
            ],
            [
                'key' => 'show_tax',
                'icon' => 'percent',
                'title' => 'الضريبة في القيود',
                'hint' => 'يُظهر حقل الضريبة عند تحرير القيد اليدوي',
            ],
            /*
                أُزيل ضابط «تحديث سعر الصرف».

                يَعِد بأن يقرأ السعر من إعدادات العملات عند فتح نموذج القيد.
                والعملات العشرون كلّها في `system_currencies` بسعرِ صرفٍ
                `1.000000` — أي لا أسعار أصلاً. فالضابط يَعِد بقراءةِ لا شيء،
                وتفعيلُه لا يغيّر رقماً واحداً.

                والعمود باقٍ في القاعدة: حذفه يحتاج ترحيلاً، ووجودُه بلا
                شاشةٍ لا يضرّ. فإن ضُبطت الأسعار يوماً يُعاد البند هنا وحده.
            */
            [
                'key' => 'assign_tags',
                'icon' => 'tag',
                'title' => 'وسوم على حركات القيد',
                'hint' => 'يتيح تصنيف سطور القيد بوسوم حرّة للبحث والتجميع لاحقاً',
            ],
        ];
    @endphp

    <div class="content-body">

        @include('account::components.page-head', [
            'title' => 'الإعدادات المحاسبية العامة',
            'icon' => 'sliders',
            'sub' => 'ضوابط تسري على كل قيدٍ يُحرَّر في النظام',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الحسابات' => route('accounts_settings.index'),
                'الإعدادات العامة' => null,
            ],
        ])

        <form action="{{ route('accounts_settings.store_accounting_general') }}" method="POST">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-edit-3"></i> ضوابط القيد
                    </h6>
                </div>

                <div class="card-body">
                    <div class="stk-picks">
                        @foreach ($toggles as $toggle)
                            @php($on = old($toggle['key'], $generalSettings->{$toggle['key']} ? '1' : null))
                            <label class="stk-switch" for="{{ $toggle['key'] }}">
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">
                                        <i class="feather icon-{{ $toggle['icon'] }}"></i>
                                        {{ $toggle['title'] }}
                                    </span>
                                    <span class="stk-switch__hint">{{ $toggle['hint'] }}</span>
                                </span>

                                {{-- الحقل المخفي يضمن وصول «مطفأ»: خانة الاختيار
                                     غير المحدَّدة لا تُرسَل أصلاً --}}
                                <input type="hidden" name="{{ $toggle['key'] }}" value="0">
                                <input type="checkbox" id="{{ $toggle['key'] }}" name="{{ $toggle['key'] }}"
                                    value="1" @checked($on)>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('accounts_settings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ الإعدادات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
