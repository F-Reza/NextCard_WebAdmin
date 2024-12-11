@extends('frontend.layouts.master')
@php
    $lang               = selectedLang();
    $system_default     = $default_language_code;
    $banner_slug        = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::HOME_BANNER);
    $banner             = App\Models\Admin\SiteSections::getData( $banner_slug)->first();
@endphp

@section('content')

<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Banner
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="banner-section pt-60">
    <div class="baner-element">
        <img src="{{ asset('public/frontend/') }}/images/element/home-banner.png">
    </div>
    <div class="container">
        <div class="row align-items-center mb-30-none">
            <div class="col-xl-6 col-lg-6 mb-30">
                <div class="banner-content">
                    @php
                        $heading = explode(' ', $banner->value->language->$lang->heading ?? $banner->value->language->$system_default->heading);

                    @endphp
                    <h1 class="title">{{ @$heading[0] }} {{ @$heading[1] }} {{ @$heading[2] }} {{ @$heading[4] }}  <span class="text--base"> {{ @$heading[5] }}  {{ @$heading[6] }}</span>  {{ @$heading[7] }}</h1>
                    <p>{{ __($banner->value->language->$lang->sub_heading ?? $banner->value->language->$system_default->sub_heading) }}</p>
                    @php
                        $link = $banner->value->language->$lang->button_link ?? $banner->value->language->$system_default->button_link;
                    @endphp
                    <div class="banner-btn">
                        <a href="{{ url('/').'/'.$link }}" class="btn--base">{{ __($banner->value->language->$lang->button_name ?? $banner->value->language->$system_default->button_name ) }}</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-lg-6 mb-30">
                <div class="banner-thumb">
                    <img src="{{ get_image(@$banner->value->images->banner_image,'site-section') }}" alt="element">
                </div>
            </div>
        </div>
    </div>
</section>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Banner
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start About
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@include('frontend.sections.about')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End About
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<!-- app section -->
@include('frontend.sections.app-section')

<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Feature
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@include('frontend.sections.feature')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Feature
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start How it works
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@include('frontend.sections.how-work')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End How it works
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
   start Statistics section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@include('frontend.sections.statistics')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Statistics section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<!-- testimonial-section  -->
@include('frontend.sections.testimonial')
<!-- End section -->
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Call-to-action
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@include('frontend.sections.start-section')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Call-to-action
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->

@endsection

@push("script")

@endpush
