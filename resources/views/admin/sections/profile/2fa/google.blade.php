@extends('admin.layouts.master')

@push('css')
    <style>
        .fileholder {
            min-height: 280px !important;
        }

        .fileholder-files-view-wrp.accept-single-file .fileholder-single-file-view,.fileholder-files-view-wrp.fileholder-perview-single .fileholder-single-file-view{
            height: 246px !important;
        }
    </style>
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
    ], 'active' => __("Admin Profile")])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __("2FA Settings") }}</h6>
        </div>

        <div class="card-body">

            <div class="text-center">
                <div class="img-box twofa-qr-code">
                    <img src="{{ $qr_code }}" alt="qr-code">
                </div>

                <div class="secret-key mt-3">
                    <span class="fw-bold"  id="referralURL">{{ auth()->user()->two_factor_secret }}</span>
                    <div class="copy-text copy-btn copytext" data-copy-text="{{ auth()->user()->two_factor_secret }}"><i class="las la-copy"></i></div>
                </div>
            </div>

            @if (auth()->user()->two_factor_status)
                <button type="button" class="mt-3 btn--base bg-warning w-100 active-deactive-btn" data-confirm-btn-text="{{ __("Disable") }}">{{ __("Disable Two Factor Authenticator") }}</button>
                <br>
                <div class="text--danger mt-3 text-center fs-6">{{ __("Don't forget to add this application in your google authentication app. Otherwise you can't login in your account.") }}</div>
            @else
                <button type="button" class="mt-3 btn--base w-100 active-deactive-btn" data-confirm-btn-text="{{ __("Enable") }}">{{ __("Enable Two Factor Authenticator") }}</button>
            @endif

        </div>
    </div>
@endsection

@push('script')
    <script>
        $(".active-deactive-btn").click(function(){
            var actionRoute =  "{{ setRoute('admin.profile.google.2fa.status.update') }}";
            var target      = "{{ auth()->user()->id }}";
            var btnText     = $(this).attr("data-confirm-btn-text");
            var sureText = '{{ __("Are you sure to") }}';
            var lastText = '{{ __("2 factor authentication (Powered by google)") }}';
            var message     = `${sureText} <strong>${btnText}</strong> ${lastText}?`;
            openDeleteModal(actionRoute,target,message,btnText,"POST");
        });
        $('.copytext').on('click',function(){
            var copyText = document.getElementById("referralURL");
            var range = document.createRange();
            range.selectNode(copyText);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            document.execCommand("copy");
            window.getSelection().removeAllRanges();

            throwMessage('success',["{{ __('Copied') }}: " + copyText.textContent]);
        });
    </script>
@endpush
