@extends('user.layouts.master')

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __(@$page_title)])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class=" d-flex justify-content-start">
                       <a href="{{ setRoute('user.strowallet.virtual.card.create') }}" class="btn--base"><i class="las la-arrow-left"></i></a>
                    </div>
                    <div class="dash-payment-body">
                        <div class=" mt-20 ">
                            <form class="card-form row" action="{{ route('user.strowallet.virtual.card.update.customer') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method("PUT")
                                <div class="p-3">
                                    <div class="row">
                                        <div class="col-xl-6 col-lg-6 form-group">
                                            @include('admin.components.form.input', [
                                                'label'         => __("first Name")."<span class='text--base'>*</span>"." "."<span class='text--warning'> <small>(" . __("Should match with your ID") . ")</small></span>",
                                                'placeholder'   => __("enter First Name"),
                                                'name'          => "first_name",
                                                'value'         => $user->strowallet_customer->firstName
                                            ])
                                        </div>
                                        <div class="col-xl-6 col-lg-6 form-group">
                                            @include('admin.components.form.input', [
                                                'label'         => __("Last Name")."<span class='text--base'>*</span>"." "."<span class='text--warning'> <small>(" . __("Should match with your ID") . ")</small></span>",
                                                'placeholder'   => __("enter Last Name"),
                                                'name'          => "last_name",
                                                'value'         => $user->strowallet_customer->lastName
                                            ])
                                        </div>

                                        <div class="col-xl-12 col-lg-12 form-group">
                                            @include('admin.components.form.input-file', [
                                                'label'         => __("ID Card Image (Font Side)")." "."<span class='text--warning'> (" . __("NID/Passport") . ")</span>",
                                                'name'          => "id_image_font",
                                                'class'         => "form--control input-img",
                                                'label_class'   => "mw-100"
                                            ])


                                        </div>
                                        <div class="col-xl-12 col-lg-12 form-group">
                                            @include('admin.components.form.input-file', [
                                                'label'         => __("Your Photo")." "."<span class='text--warning'> (" . __("Should show your face and must be match with your ID") . ")</span>",
                                                'name'          => "user_image",
                                                'class'         => "form--control input-img",
                                                'label_class'   => "mw-100"
                                            ])


                                        </div>
                                    </div>
                                    <ul class="kyc-preview-wrapper">
                                        <li>
                                            <span class="label">{{ __("ID Card Image") }}:</span>
                                            <div class="thumb">
                                                <img src="{{ $customer_kyc->idImageData??"" }}" alt="no-file">
                                            </div>
                                        </li>
                                        <li>
                                            <span class="label">{{ __("Your Photo") }}:</span>
                                            <div class="thumb">
                                                <img src="{{ $customer_kyc->faceImageData??"" }}" alt="no-file">
                                            </div>
                                        </li>
                                    </ul>
                                    <div class="col-xl-12 col-lg-12">
                                        <button type="submit" class="btn--base w-100 btn-loading">{{ __("Submit") }}</button>
                                    </div>
                                </div>
                        </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')

@endpush
