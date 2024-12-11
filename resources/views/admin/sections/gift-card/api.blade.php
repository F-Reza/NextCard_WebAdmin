@extends('admin.layouts.master')

@push('css')

@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __($page_title) }}</h6>
        </div>
        <div class="card-body">
            <form class="card-form" action="{{ setRoute('admin.gift.card.api.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method("PUT")
                <div class="row mb-10-none">
                    <div class="col-xl-12 col-lg-12 form-group configForm">
                        <div class="row" >
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Client ID") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-key"></i></span>
                                    <input type="text" class="form--control" name="client_id" value="{{ @$api->credentials->client_id }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Secret Key") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="secret_key" value="{{ @$api->credentials->secret_key }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Production URL") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="production_base_url" value="{{ @$api->credentials->production_base_url }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Sandbox URL") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="sandbox_base_url" value="{{ @$api->credentials->sandbox_base_url }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Webhook URL") }}</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" id="referralURL" value="{{ setRoute('user.gift.card.webhook') }}" readonly>
                                    <div class="input-group-text copytext" id="copyBoard"><i class="las la-copy"></i></div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                @include('admin.components.form.switcher', [
                                    'label'         => __('Api ENV')."*",
                                    'value'         => old('    ',@$api->env),
                                    'name'          => "env",
                                    'options'       => [__('Production') => global_const()::ENV_PRODUCTION,__('Sandbox') => global_const()::ENV_SANDBOX]
                                ])
                            </div>

                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.button.form-btn',[
                            'class'         => "w-100 btn-loading",
                            'text'          => __("Update"),
                            'permission'    => "admin.virtual.card.api.update"
                        ])
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
@push('script')
    <script>
        $('.copytext').on('click',function(){
                var copyText = document.getElementById("referralURL");
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                document.execCommand("copy");

                throwMessage('success',['{{ "Copy Successfully" }}']);
            });
    </script>
@endpush
