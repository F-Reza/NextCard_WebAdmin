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
    ], 'active' => __("Virtual Card Api")])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __("Virtual Card Api") }}</h6>
        </div>
        <div class="card-body">
            <form class="card-form" action="{{ setRoute('admin.virtual.card.api.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method("PUT")
                <div class="row mb-10-none">
                    <div class="col-xl-12   col-lg-12 form-group">
                        <label>{{ __("Name") }}*</label>
                        <select class="form--control nice-select" name="api_method">
                            <option disabled>{{ __("Select Platfrom") }}</option>
                            <option value="stripe" @if(@$api->config->name == 'stripe') selected @endif>@lang('Stripe Api')</option>
                            <option value="strowallet" @if(@$api->config->name == 'strowallet') selected @endif>@lang('Strowallet Api')</option>
                            <option value="sudo" @if(@$api->config->name == 'sudo') selected @endif>@lang('Sudo Africa')</option>
                            <option value="flutterwave" @if(@$api->config->name == 'flutterwave') selected @endif>@lang('Flutterwave')</option>
                        </select>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group configForm" id="flutterwave">
                        <div class="row" >
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                                <label>{{ __("Secret Key") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-key"></i></span>
                                    <input type="text" class="form--control" name="flutterwave_secret_key" value="{{ @$api->config->flutterwave_secret_key }}">
                                </div>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                                <label>{{ __("Secret Hash") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-hashtag"></i></span>
                                    <input type="text" class="form--control" name="flutterwave_secret_hash" value="{{ @$api->config->flutterwave_secret_hash }}">
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 form-group">
                                <label>{{ __("Base Url") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="flutterwave_url" value="{{ @$api->config->flutterwave_url }}">
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group configForm" id="sudo">
                        <div class="row" >
                            <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 form-group">
                                <label>{{ __("Api Key") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-key"></i></span>
                                    <input type="text" class="form--control" name="sudo_api_key" value="{{ @$api->config->sudo_api_key }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Vault ID Url") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="sudo_vault_id" value="{{ @$api->config->sudo_vault_id }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Base Url") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="sudo_url" value="{{ @$api->config->sudo_url }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                @include('admin.components.form.switcher', [
                                    'label'         =>__('Mode'),
                                    'value'         => old('sudo_mode',@$api->config->sudo_mode),
                                    'name'          => "sudo_mode",
                                    'options'       => [__('Live') => global_const()::LIVE, __('Sandbox') => global_const()::SANDBOX]
                                ])
                            </div>

                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group configForm" id="stripe">
                        <div class="row" >
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                                <label>{{ __("Public Key") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-key"></i></span>
                                    <input type="text" class="form--control" name="stripe_public_key" value="{{ @$api->config->stripe_public_key }}">
                                </div>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                                <label>{{ __("Secret Key") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-key"></i></span>
                                    <input type="text" class="form--control" name="stripe_secret_key" value="{{ @$api->config->stripe_secret_key }}">
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 form-group">
                                <label>{{ __("Base Url") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="stripe_url" value="{{ @$api->config->stripe_url }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group configForm" id="strowallet">
                        <div class="row" >
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Public Key") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-key"></i></span>
                                    <input type="text" class="form--control" name="strowallet_public_key" value="{{ @$api->config->strowallet_public_key }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Secret Key") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-key"></i></span>
                                    <input type="text" class="form--control" name="strowallet_secret_key" value="{{ @$api->config->strowallet_secret_key }}">
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 form-group">
                                <label>{{ __("Base Url") }}*</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" name="strowallet_url" value="{{ @$api->config->strowallet_url }}" @if($api->config->strowallet_url)
                                    readonly
                                    @endif>
                                </div>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                                @include('admin.components.form.switcher', [
                                    'label'         => __('Mode'),
                                    'value'         => old('strowallet_mode',@$api->config->strowallet_mode),
                                    'name'          => "strowallet_mode",
                                    'options'       => [__('Live') => global_const()::LIVE,__('Sandbox') => global_const()::SANDBOX]
                                ])
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                                <label>{{ __("Webhook URL") }}</label>
                                <div class="input-group append">
                                    <span class="input-group-text"><i class="las la-link"></i></span>
                                    <input type="text" class="form--control" id="referralURL" value="{{ setRoute('user.strowallet.virtual.card.webhook') }}" readonly>
                                    <div class="input-group-text copytext" id="copyBoard"><i class="las la-copy"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.form.input',[
                            'label'         =>__( 'Card Limit Within (1 to 3)'),
                            'name'          => 'card_limit',
                            'value'         => old('card_limit',@$api->card_limit),
                            'placeholder'   => "Enter 1-3 Only."
                        ])
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.form.input-text-rich',[
                            'label'         => __('Card Details').'*',
                            'name'          => 'card_details',
                            'value'         => old('card_details',@$api->card_details),
                            'placeholder'   => "Write Here...",
                        ])
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <label for="card-image">{{ __("Background Image") }}</label>
                        <div class="col-12 col-sm-6 m-auto">
                            @include('admin.components.form.input-file',[
                                'label'         => false,
                                'class'         => "file-holder m-auto",
                                'old_files_path'    => files_asset_path('card-api'),
                                'name'          => "image",
                                'old_files'         => old('image',@$api->image)
                            ])
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
        (function ($) {
            "use strict";
            var method = '{{ @$api->config->name}}';
            if (!method) {
                method = 'flutterwave';
            }

            apiMethod(method);
            $('select[name=api_method]').on('change', function() {
                var method = $(this).val();
                apiMethod(method);
            });

            function apiMethod(method){
                $('.configForm').addClass('d-none');
                if(method != 'other') {
                    $(`#${method}`).removeClass('d-none');
                }
            }

        })(jQuery);

    </script>
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
