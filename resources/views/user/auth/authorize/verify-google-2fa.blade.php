@extends('frontend.layouts.master')
@push('css')

@endpush

@section('content')

    {{-- <section class="account">
        <div class="account-area">
            <div class="account-wrapper">
                <div class="account-logo text-center">
                    <a class="site-logo" href="{{ setRoute('index') }}">
                        <img src="{{ get_logo($basic_settings) }}"  data-white_img="{{ get_logo($basic_settings,'white') }}"
                        data-dark_img="{{ get_logo($basic_settings,'dark') }}"
                            alt="site-logo">
                    </a>
                </div>
                <h3 class="title">{{ __("Two Factor Authorization") }}</h3>
                <p>{{ __("Please enter your authorization code to access dashboard") }}</p>
                <form action="{{ setRoute('user.authorize.google.2fa.submit') }}" class="account-form" method="POST">
                    @csrf
                    <div id="recaptcha-container"></div>
                    <div class="row ml-b-20">
                        <div class="col-lg-12 form-group">
                            <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(1)'
                            maxlength=1 required>
                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(2)'
                            maxlength=1 required>
                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(3)'
                            maxlength=1 required>
                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(4)'
                            maxlength=1 required>
                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(5)'
                            maxlength=1 required>
                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(6)'
                            maxlength=1 required>


                        </div>

                        <div class="col-lg-12 form-group text-start">
                            <div class="forgot-item">
                                <label><a href="" class="text--base">{{ __("Back to Login") }}</a></label>
                            </div>
                        </div>
                        <div class="col-lg-12 form-group text-center">
                            <button type="submit" class="btn--base w-100">{{ __("Authorize") }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section> --}}
    <section class="verification-otp ptb-60">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                    <div class="verification-otp-area">
                        <div class="account-wrapper otp-verification ">
                            <div class="account-logo text-center">
                                <a class="site-logo" href="{{ setRoute('index') }}">
                                    <img src="{{ get_logo($basic_settings) }}"  data-white_img="{{ get_logo($basic_settings,'white') }}"
                                    data-dark_img="{{ get_logo($basic_settings,'dark') }}"
                                        alt="site-logo">
                                </a>
                            </div>

                            <div class="verification-otp-content ptb-30 text-center">
                                <h3 class="title">{{ __($page_title) }}</h3>
                                <p>{{ __("Please enter your authorization code to access dashboard") }}</p>
                            </div>
                            <form action="{{ setRoute('user.authorize.google.2fa.submit') }}" class="account-form" method="POST">
                                @csrf
                                <div class="row ml-b-20">
                                    <div class="col-lg-12 form-group">
                                        <div class="col-lg-12 form-group">
                                            <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(1)'
                                            maxlength=1 required>
                                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(2)'
                                            maxlength=1 required>
                                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(3)'
                                            maxlength=1 required>
                                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(4)'
                                            maxlength=1 required>
                                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(5)'
                                            maxlength=1 required>
                                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(6)'
                                            maxlength=1 required>

                                    </div>

                                    <div class="col-lg-12 form-group text-center">
                                        <button type="submit" class="btn--base w-100">{{ __("Authorize") }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

@push('script')
        <script>

            let digitValidate = function (ele) {
                console.log(ele.value);
                ele.value = ele.value.replace(/[^0-9]/g, '');
            }

            let tabChange = function (val) {
                let ele = document.querySelectorAll('.otp');
                if (ele[val - 1].value != '') {
                    ele[val].focus()
                } else if (ele[val - 1].value == '') {
                    ele[val - 2].focus()
                }
            }

            $(".otp").parents("form").find("input[type=submit],button[type=submit]").click(function(e){
                // e.preventDefault();
                var otps = $(this).parents("form").find(".otp");
                var result = true;
                $.each(otps,function(index,item){
                    if($(item).val() == "" || $(item).val() == null) {
                        result = false;
                    }
                });

                if(result == false) {
                    $(this).parents("form").find(".otp").addClass("required");
                }else {
                    $(this).parents("form").find(".otp").removeClass("required");
                    $(this).parents("form").submit();
                }
            });
        </script>
@endpush
