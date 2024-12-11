
@php
    $lang = selectedLang();
    $cookie = App\Models\Admin\SiteSections::siteCookie();
    $seo_data = App\Models\Admin\SetupSeo::first();

    //cookies results
    $approval_status      = request()->cookie('approval_status');
    $c_user_agent         = request()->cookie('user_agent');
    $c_ip_address         = request()->cookie('ip_address');
    $c_browser            = request()->cookie('browser');
    $c_platform           = request()->cookie('platform');
    //system informations
    $s_ipAddress    = request()->ip();
    $s_location     = geoip()->getLocation($s_ipAddress);
    $s_browser      = Agent::browser();
    $s_platform     = Agent::platform();
    $s_agent        = request()->header('User-Agent');

@endphp
<!DOCTYPE html>
<html lang="{{ get_default_language_code() }}">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta property="og:title" content="{{ __(@$seo_data->title) }}">
    <meta name="keywords" content="{{ implode(',',@$seo_data->tags) }}">
    <meta property="og:description" content="{{ __(@$seo_data->desc) }}">
    <meta property="og:image" content="{{get_image(@$seo_data->image,'seo') }}">
    <meta property="og:image:width" content="140">
    <meta property="og:image:height" content="80">
    <title>{{ $basic_settings->sitename(__($page_title??'')) }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @include('partials.header-asset')

    @stack('css')
</head>
<body class="{{ selectedLangDir() ?? "ltr"}}">
<div id="body-overlay" class="body-overlay"></div>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Preloader
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
{{-- <div class="preloader">
    <div class="loader-inner">
        <div class="loader-circle">
            <img src="{{ get_fav($basic_settings) }}" alt="Preloader">
        </div>
        <div class="loader-line-mask">
        <div class="loader-line"></div>
        </div>
    </div>
</div> --}}
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Preloader
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@include('frontend.partials.header')
@include('frontend.partials.account-info')
@yield("content")


 <!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start cookie
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<div class="cookie-main-wrapper">
    <div class="cookie-content">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M21.598 11.064a1.006 1.006 0 0 0-.854-.172A2.938 2.938 0 0 1 20 11c-1.654 0-3-1.346-3.003-2.937c.005-.034.016-.136.017-.17a.998.998 0 0 0-1.254-1.006A2.963 2.963 0 0 1 15 7c-1.654 0-3-1.346-3-3c0-.217.031-.444.099-.716a1 1 0 0 0-1.067-1.236A9.956 9.956 0 0 0 2 12c0 5.514 4.486 10 10 10s10-4.486 10-10c0-.049-.003-.097-.007-.16a1.004 1.004 0 0 0-.395-.776zM12 20c-4.411 0-8-3.589-8-8a7.962 7.962 0 0 1 6.006-7.75A5.006 5.006 0 0 0 15 9l.101-.001a5.007 5.007 0 0 0 4.837 4C19.444 16.941 16.073 20 12 20z"/><circle cx="12.5" cy="11.5" r="1.5"/><circle cx="8.5" cy="8.5" r="1.5"/><circle cx="7.5" cy="12.5" r="1.5"/><circle cx="15.5" cy="15.5" r="1.5"/><circle cx="10.5" cy="16.5" r="1.5"/></svg>
        <p class="text-white">{{ __(strip_tags(@$cookie->value->desc)) }} <a href="{{ url('/').'/'.@$cookie->value->link }}">{{ __("Privacy Policy") }}</a></p>
    </div>
    <div class="cookie-btn-area">
        <button class="cookie-btn">{{__("Allow")}}</button>
        <button class="cookie-btn-cross">{{__("Decline")}}</button>
    </div>
</div>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End cookie
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@include('frontend.partials.footer')
@include('partials.footer-asset')
@include('frontend.partials.extensions.tawk-to')

@stack('script')

<script>
    var status = "{{  @$cookie->status }}";
     //cookies results
     var approval_status      = "{{ $approval_status}}";
     var c_user_agent         = "{{ $c_user_agent}}";
     var c_ip_address         = "{{ $c_ip_address}}";
     var c_browser            = "{{ $c_browser}}";
     var c_platform           = "{{ $c_platform}}";
     //system informations
    var s_ipAddress    = "{{ $s_ipAddress}}";
    var s_browser      = "{{ $s_browser}}";
    var s_platform     = "{{ $s_platform}}";
    var s_agent        = "{{ $s_agent}}";

    const pop = document.querySelector('.cookie-main-wrapper')
    if( status == 1){
        if(approval_status == 'allow' || approval_status == 'decline' || c_user_agent === s_agent || c_ip_address === s_ipAddress || c_browser === s_browser || c_platform === s_platform){
            pop.style.bottom = "-300px";
        }else{
            window.onload = function(){
            setTimeout(function(){
                pop.style.bottom = "20px";
            }, 2000)
        }
        }
    }else{
        pop.style.bottom = "-300px";
    }

    // })
</script>
<script>
    (function ($) {
        "use strict";
        //Allow
        $('.cookie-btn').on('click', function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            var postData = {
                type: "allow",
            };
            $.post('{{ route('global.set.cookie') }}', postData, function(response) {
                throwMessage('success', [response]);
                setTimeout(function() {
                    location.reload();
                }, 1000);
            });
        });
        //Decline
        $('.cookie-btn-cross').on('click', function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            var postData = {
                type: "decline",
            };
            $.post('{{ route('global.set.cookie') }}', postData, function(response) {
                throwMessage('error',[response]);
                setTimeout(function(){
                    location.reload();
                },1000);
            });
        });
    })(jQuery)
</script>
</body>
</html>
