@extends('errors.system-maintenance-layout')

@push("title")
    <title> {{ __("503 -") }} {{ $system_maintenance->title ?? __("Service Unavailable") }} </title>
@endpush
@section("content")

    <div class="container vh-100 w-100 d-flex flex-column justify-content-center">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <div>
                    <img src="{{ asset("public/error-images/maintenance-mode.webp") }}" alt="404" class="error">
                </div>
                <h2 class="mt-5">{{ __($system_maintenance->title??"") }}</h2>
                <p class="mb-0">
                    @php
                        echo $system_maintenance->details??"";
                    @endphp
                </p>
            </div>
        </div>
    </div>
@endsection

@push("script")

@endpush

