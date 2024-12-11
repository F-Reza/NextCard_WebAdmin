@extends('admin.auth.layouts.auth-master')

@push('css')
    <style>
        .info-block {
            background: #222329;
            padding: 8px 15px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .info-block .text--warning {
            color: #ff9f65 !important;
        }
    </style>
@endpush

@section('section')
    <div class="account-wrapper">
        <div class="account-header">
            <div class="site-logo">
                 <img src="{{ get_logo($basic_settings) }}"  data-white_img="{{ get_logo($basic_settings,'white') }}"
                    data-dark_img="{{ get_logo($basic_settings,'dark') }}"
                        alt="site-logo">
            </div>
            <span class="inner-title">👋</span>
            <h6 class="sub-title">{{ __("Welcome To") }} <span>{{ __("Admin Panel") }}</span></h6>
            <p class="mt-3">{{ __("Please enter your authorization code to access dashboard")  }}</p>
        </div>
        <form class="account-form" action="{{ setRoute('admin.authorize.google.2fa.submit') }}" method="POST">
            @csrf
            <div class="form-group">
                <input type="text" class="@error('code') is-invalid @enderror" title="Enter Authentication Code" required name="code" value="{{ old('code') }}" autofocus>
                <label>{{ __("Authentication Code") }}</label>
            </div>

            <div class="info-block">
                <p class="text--warning">
                    <i class="fas fa-info-circle"></i>
                    <i>{{ __("If something wrong with your authentication process you can reset your password to get the dashboard access") }}</i>
                </p>
            </div>

            <div class="form-group">
                <div class="forgot-item">
                    <p>
                        <a href="{{ setRoute('admin.password.forgot') }}" class="text--base">{{ __("Forgot Password") }}?</a>
                    </p>
                </div>
            </div>

            <button type="submit" class="btn--base w-100 btn-loading">{{ __("Verify") }}</button>
        </form>
    </div>
@endsection
