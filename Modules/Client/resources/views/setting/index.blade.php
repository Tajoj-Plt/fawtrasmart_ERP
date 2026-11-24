@extends('client::master')

@section('title')
    اعدادات العميل
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

    .equal-height-card .card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .hover-card {
        transition: transform 0.3s;
        height: 100%;
    }

    .hover-card:hover {
        transform: translateY(-5px);
    }

    .card-body.setting {
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
    }

    .card-body.setting a {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
        text-decoration: none;
        color: inherit;
    }

    .card-body.setting h5 {
        margin-top: 10px;
        width: 100%;
    }
</style>
@endsection

@section('content')
<div class="content-body">
    <section id="statistics-card" class="container">
        <div class="row">

            <!-- تصنيف العملاء -->
            <div class="col-lg-4 col-sm-6 col-12 mb-4">
                <div class="card hover-card equal-height-card">
                    <div class="card-content">
                        <div class="card-body setting">
                            <a href="{{ route('categoriesClient.index') }}">
                                <i class="fas fa-tags fa-6x p-3 text-primary"></i>
                                <h5><strong>تصنيف العملاء</strong></h5>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- صلاحيات العميل -->
            <div class="col-lg-4 col-sm-6 col-12 mb-4">
                <div class="card hover-card equal-height-card">
                    <div class="card-content">
                        <div class="card-body setting">
                            <a href="{{ route('clients.permission') }}">
                                <i class="fas fa-user-lock fa-6x p-3 text-primary"></i>
                                <h5><strong>صلاحيات العميل</strong></h5>
                            </a>
                        </div>
                    </div>
                </div>
            </div>




            <!-- إعدادات الأهداف -->
            <div class="col-lg-4 col-sm-6 col-12 mb-4">
                <div class="card hover-card equal-height-card">
                    <div class="card-content">
                        <div class="card-body setting">
                            <a href="{{ route('client.customer_targets.index') }}">
                                <i class="fas fa-bullseye fa-6x p-3 text-primary"></i>
                                <h5><strong>إعدادات الأهداف</strong></h5>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تقرير الأهداف -->
            <div class="col-lg-4 col-sm-6 col-12 mb-4">
                <div class="card hover-card equal-height-card">
                    <div class="card-content">
                        <div class="card-body setting">
                            <a href="{{ route('ClientReport.customerTargets') }}">
                                <i class="fas fa-chart-line fa-6x p-3 text-primary"></i>
                                <h5><strong>تقرير الأهداف</strong></h5>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
  <!-- إعدادات وحدات العملاء -->
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

            <!-- إعدادات وحدات العملاء -->
            <div class="col-lg-4 col-sm-6 col-12 mb-4">
                <div class="card hover-card equal-height-card">
                    <div class="card-content">
                        <div class="card-body setting">
                            <a href="{{ route('clients.module_settings') }}">
                                <i class="fas fa-toggle-on fa-6x p-3 text-primary"></i>
                                <h5><strong>{{ __('client_settings.module_settings.nav_title') }}</strong></h5>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
@endsection

@section('scripts')
@endsection
