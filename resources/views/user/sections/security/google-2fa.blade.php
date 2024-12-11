@extends('user.layouts.master')

@push('css')

@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
    <div class="body-wrapper">
        <div class="row mb-30-none">
            <div class="col-xl-6 mb-30">
                <div class="dash-payment-item-wrapper">
                    <div class="dash-payment-item active">

                        <div class="card-body">
                            <form class="card-form">
                                <div class="row">
                                    <div class="col-xl-12 col-lg-12 form-group">
                                        <label>{{ __($page_title) }}</label>
                                        <div class="input-group">
                                            <input type="text" class="form--control" id="referralURL" value="{{ auth()->user()->two_factor_secret }}" readonly>
                                            <div class="input-group-text copytext" id="copyBoard"><i class="las la-copy"></i></div>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 form-group">
                                        <div class="qr-code-thumb text-center">
                                            <img class="mx-auto" src="{{ $qr_code }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12">
                                @if (auth()->user()->two_factor_status)
                                    <button type="button" class="btn--base bg--warning w-100 active-deactive-btn">{{ __("Disable") }}</button>
                                    <br>
                                    <div class="text--danger mt-3">{{ __("Don't forget to add this application in your google authentication app. Otherwise you can't login in your account.") }}</div>
                                @else
                                    <button type="button" class="btn--base w-100 active-deactive-btn">{{ __("Enable") }}</button>
                                @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 mb-30">
                <div class="dash-payment-item-wrapper">
                    <div class="dash-payment-item active">
                        <div class="card-body">
                            <h4 class="mb-3">{{ __("Download Google Authenticator App") }}</h4>
                            <p>{{ __("Google Authenticator is a product based authenticator by Google that executes two-venture confirmation administrations for verifying clients of any programming applications") }}.</p>
                            <div class="play-store-thumb text-center pb-20">
                                <img class="mx-auto" src="{{ asset('public/frontend/') }}/images/element/google-authenticator.webp">
                            </div>
                            <a href="https://play.google.com/store/apps" target="_blank" class="btn--base mt-10 w-100">{{ __("Download App") }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(".active-deactive-btn").click(function(){
            var actionRoute =  "{{ setRoute('user.security.google.2fa.status.update') }}";
            var target      = 1;
            var btnText = $(this).text();
            var sureText = '{{ __("Are you sure to") }}';
            var lastText = '{{ __("2 factor authentication (Powered by google)") }}';
            var message     = `${sureText} <strong>${btnText}</strong> ${lastText}?`;
            openAlertModal(actionRoute,target,message,btnText,"POST");
        });
        $('.copytext').on('click',function(){
            var copyText = document.getElementById("referralURL");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");

            throwMessage('success',["{{ __('Copied') }}: " + copyText.value]);
        });
    </script>
@endpush
