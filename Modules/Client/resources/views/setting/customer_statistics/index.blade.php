@extends('sales::master')

@section('title')
    إعدادات إحصائيات العميل
@stop

@section('css')
<style>
    .equal-height-card {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .equal-height-card .card-content {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .equal-height-card .card-body.setting {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center; /* عمودي */
        align-items: center; /* أفقي */
        text-align: center;
        height: 100%;
        padding: 30px 10px;
    }

    .hover-card {
        transition: transform 0.3s;
        height: 100%;
        border: none;
        box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
    }

    .hover-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 6px 18px rgba(0, 123, 255, 0.3);
    }

    .card-body.setting a {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center; /* وسط الديف */
        height: 100%;
        width: 100%;
        text-decoration: none;
        color: inherit;
    }

    .card-body.setting i {
      d;
    }

    .card-body.setting h5 {
        margin-top: 15px;
        font-weight: bold;

    }
</style>
@endsection

@section('content')
<div class="content-body">
    <section id="statistics-card" class="container">
        <div class="row">

            {{-- «إضافة هدف للعميل» و«إحصائيات هدف العميل» أُزيلتا: كانتا
                 ترتكزان على رقم هدفٍ عام واحد مشترك (`Target` id=2) بلا
                 صلاحيةٍ أو اشتراكٍ يحرسهما، وتأكَّد أنهما غير مُستخدَمتين
                 فعلياً قبل الحذف. لا تخلطهما بـ«إعدادات الأهداف» في هذه
                 الشاشة الرئيسية (`client.customer_targets.*`) — نظامٌ مختلفٌ
                 تماماً (فئاتٌ لكل عميل عبر CustomerTarget)، لا بديلاً لهما. --}}

            <!-- ادارة تبرير الزيارات -->
            @permission('clients_visit_justifications_view_all')
            <div class="col-lg-4 col-sm-6 col-12 mb-4">
                <div class="card hover-card equal-height-card">
                    <div class="card-content">
                        <div class="card-body setting">
                            <a href="{{ route(name: 'admin.visit-justifications.index') }}">
                                <i class="fas fa-clipboard-list fa-6x p-3 text-primary"></i>
                                <h5>إدارة تبرير الزيارات</h5>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endpermission

        </div>
    </section>
</div>
@endsection

@section('scripts')
@endsection
