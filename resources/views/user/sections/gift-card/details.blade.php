@extends('user.layouts.master')
@push('css')
<style>
    .modal-backdrop {
        display: none !important;
    }
</style>
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
    <div class="gift-card-item details mt-20">
        <div class="gift-card-thumb">
            <img  src="{{  $product['logoUrls'][0]??"" }}" alt="gift-cards">
        </div>
        <div class="gift-card-content">
            <h3 class="title">{{ __($product['productName']) }}</h3>
            @if($product['denominationType'] == "RANGE")
                <span class="sub-title">{{ __("Select an amount between") }}  {{ $product['minRecipientDenomination'] }} {{ $product['recipientCurrencyCode'] }} - {{ $product['maxRecipientDenomination'] }} {{ $product['recipientCurrencyCode'] }}</span>
            @endif
            <div class="gift-card-details-form">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="row">
                            @if($product['denominationType'] == "RANGE")
                                <div class="col-xxl-12 col-xl-6 col-lg-8 col-md-12 col-sm-4 form-group">
                                    <label>{{ __("Amount") }}</label>
                                    <input type="number" name="amount" class="form--control enter-amount" value="{{ old('amount') }}" placeholder="{{ __("Enter Amount") }}">
                                </div>
                            @else
                                <div class="col-xl-12 form-group">
                                    <label>{{ __("Amount") }}</label>
                                    <div class="radio-wrapper">
                                        @foreach($product['fixedRecipientDenominations'] ??[] as $key => $price)
                                        <div class="radio-item">
                                            <input type="radio" id="level-{{ $key+1 }}" name="amount" value="{{ $price }}" onclick="handleRadioClick(this)" class="radio_amount" {{ $key == 0 ? 'checked':'' }}>
                                            <label for="level-{{ $key+1 }}">{{ $price }} {{ $product['recipientCurrencyCode'] }}</label>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-6 form-group">
                        <label>{{ __("receiver Email") }}</label>
                        <input type="email" name="receiver_email" class="form--control" placeholder="{{ __("enter Receiver Email Address") }}" value="{{ old("receiver_email") }}">
                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-6 form-group">
                        <label>{{ __("Country") }}</label>
                        <select name="country" class="form--control select2-auto-tokenize country-select" >

                        </select>
                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-6 form-group">
                        <label>{{ __("phone Number") }}</label>
                        <div class="input-group">
                            <div class="input-group-text phone-code">+</div>
                            <input class="phone-code" type="hidden" name="phone_code" />
                            <input type="text" class="form--control" placeholder="{{ __("enter Phone Number") }}" name="phone" value="{{ old('phone') }}">
                        </div>

                    </div>

                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-6 form-group">
                        <label>{{ __("From Name") }}</label>
                        <input type="text" name="from_name" class="form--control" placeholder="{{ __("Your Name") }}" value="{{ old('from_name') }}">
                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-6 form-group">
                        <label>{{ __("Quantity") }}</label>
                        <div class="input-group">
                            <input type="number" class="form--control" value="1" min="1" name="quantity" value="" id="quantityInput">
                        </div>

                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-6 form-group">
                        <label>{{ __("wallet") }}</label>
                        <select class="form--control nice-select currency" name="currency">
                            @foreach ($currencies?? [] as $item)
                                <option
                                value="{{ $item->code}}"
                                data-id="{{ $item->id }}"
                                data-rate="{{ $item->rate }}"
                                data-symbol="{{ $item->symbol }}"
                                data-type="{{ $item->type }}"
                                {{ get_default_currency_code() == $item->code ? "selected": "" }}
                                >{{ $item->name." (".$item->code." )"}}</option>
                            @endforeach
                        </select>
                        <code class="d-block mt-10 text-end balance-show"></code>
                    </div>
                    <div class="col-xl-12 form-group">
                        <button type="button" class="btn--base buyBtn">{{ __("Buy Now") }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start gift card modal
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<div class="modal fade" id="BuyCardModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content overflow-hidden">
        <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">{{ __('Buy Gift Card') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="las la-times"></i></button>
        </div>
        <div class="modal-body p-0">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active mb-0 rounded-0">
                    <div class="row mt-20">
                        <form class="card-form" action="{{ setRoute('user.gift.card.order') }}" method="POST">
                            @csrf
                            <input type="hidden" name="exchange_rate">
                            <input type="hidden" name="product_id" value="{{ $product['productId'] }}">
                            <input type="hidden" name="g_qty">
                            <input type="hidden" name="g_unit_price" value="0" class="g_unit_price">
                            <input type="hidden" name="g_recipient_email">
                            <input type="hidden" name="g_receipient_country">
                            <input type="hidden" name="g_recipient_phone_code">
                            <input type="hidden" name="g_recipient_phone">
                            <input type="hidden" name="g_recipient_iso">
                            <input type="hidden" name="g_from_name">
                            <input type="hidden" name="wallet_currency">
                            <input type="hidden" name="receiver_currency">

                            <div class="col-xl-12 col-lg-12 mb-20">
                                <div class="card-body">
                                    <div class="preview-list-wrapper">
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Product Name") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success product-name">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("receiver Email") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success receiver-email">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Receiver Country") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success receiver-country">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Receiver Phone") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success receiver-phone">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("From Name") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success from-name">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Unit Price") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success unit-price">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Quantity") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success total-quantity">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-receipt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Total Price") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--success total-price">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-exchange-alt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Exchange Rate") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class=" rate-show">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-money-check-alt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Conversion Amount") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--info conversion-amount">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-battery-half"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span>{{ __("Total Fees & Charges") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--warning fees-show">--</span>
                                            </div>
                                        </div>
                                        <div class="preview-list-item">
                                            <div class="preview-list-left">
                                                <div class="preview-list-user-wrapper">
                                                    <div class="preview-list-user-icon">
                                                        <i class="las la-money-check-alt"></i>
                                                    </div>
                                                    <div class="preview-list-user-content">
                                                        <span class="last">{{ __("Total Payable Amount") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-list-right">
                                                <span class="text--info last pay-in-total">--</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <button type="submit" class="btn--base w-100 fundBtn btn-loading">{{__("Confirm")}} <i class="las la-plus-circle ms-1"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Gift card modal
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@endsection
@push('script')
<script>

        $(document).ready(function(){
            getAllCountries("{{ setRoute('global.countries') }}");
            var phoneCode = $("select[name=country] :selected").attr("data-mobile-code");
            placePhoneCode(phoneCode);
            $("select[name=country]").change(function(){
                var phoneCode = $("select[name=country] :selected").attr("data-mobile-code");
                placePhoneCode(phoneCode);
            });

            countrySelect(".country-select",$(".country-select").siblings(".select2"));

        });
        document.addEventListener("DOMContentLoaded", function() {
            const quantityInput = document.getElementById('quantityInput');
            quantityInput.addEventListener('input', function(event) {
                let inputValue = event.target.value.trim(); // Remove leading and trailing whitespace
                // Remove non-digit characters and leading zeros
                inputValue = inputValue.replace(/[^1-9]/g, '');
                // If the resulting value is empty or starts with '0', set it to '1'
                if (inputValue === '' || inputValue === '0') {
                    inputValue = '1';
                }
                // Update the input value
                event.target.value = inputValue;
            });
        });
</script>
<script>
    var amount_type = "{{ $product['denominationType'] }}";
    if(amount_type == "RANGE"){
        $(".enter-amount").focusout(function(){
            enterLimit();
        });
        $("input[name=amount]").keyup(function(){
            // getFees();
            getExchangeRate();
            senderBalance();
            getPreview();
        });
        function enterLimit(){
            var min_limit = parseFloat("{{getAmount($product['minRecipientDenomination'])}}");
            var max_limit =parseFloat("{{getAmount($product['maxRecipientDenomination'])}}");
            var senderAmount = parseFloat($(".enter-amount").val());
            senderAmount == "" ? senderAmount = 0 : senderAmount = senderAmount;
            if( senderAmount < min_limit ){
                throwMessage('error',['{{ __("Please follow the mimimum limit") }}']);
                $('.buyBtn').attr('disabled',true)
            }else if(senderAmount > max_limit){
                throwMessage('error',['{{ __("Please follow the maximum limit") }}']);
                $('.buyBtn').attr('disabled',true)
            }else{
                $('.buyBtn').attr('disabled',false)
            }
            return
        }
    }else{
        var amount = 0;
        $(document).ready(function() {
            // Get the first radio button and check it
            var firstRadio = $('input[type="radio"]:first');
            firstRadio.prop('checked', true);
            handleRadioClick(firstRadio[0]);
        });
        function handleRadioClick(radio) {
            if (radio.checked) {
                amount = radio.value;
                $('.buyBtn').attr('disabled',false);

            }
        }
    }
    $(document).ready(function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();

    });
    $(".radio_amount").click(function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
    });

    $('select[name=currency]').on('change',function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
    });
    $("input[name=receiver_email]").keyup(function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
    });
    $("input[name=phone]").keyup(function(){
        getPreview();
    });
    $("input[name=from_name]").keyup(function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
    });
    $("input[name=quantity]").keyup(function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
    });
    $("input[name=quantity]").change(function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
    });
    $(".country-select").change(function(){
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
    });
    function get_amount(){
        if(amount_type === "RANGE"){
            amount = parseFloat($(".enter-amount").val());
        }else{
            amount = amount;
        }
        return amount;
    }
    function senderBalance(){
            var senderCurrencyId = $("select[name=currency] :selected").attr("data-id");
            $.ajax({
            type:'get',
                url:"{{ route('global.user.wallet.balance') }}",
                data:{id:senderCurrencyId},
                success:function(data){
                    $('.balance-show').html("{{ __('Available balance') }}: " + $("select[name=currency] :selected").attr("data-symbol") + parseFloat(data).toFixed(2));
                }
            });
        }
    function acceptVar() {

        var country_select = $(".country-select :selected");
        var country = $(".country-select :selected").val();
        var currencyCode = $("select[name=currency] :selected").val();
        var currencyRate = $("select[name=currency] :selected").attr("data-rate");
        var currencyType = $("select[name=currency] :selected").attr("data-type");
        var currencyFixedCharge = "{{getAmount($cardCharge->fixed_charge)}}";
        var currencyPercentCharge = "{{getAmount($cardCharge->percent_charge)}}";

        return {
            country_select:country_select,
            country:country,
            currencyCode:currencyCode,
            currencyRate:currencyRate,
            currencyType:currencyType,
            currencyFixedCharge:currencyFixedCharge,
            currencyPercentCharge:currencyPercentCharge,
        };
    }
    function feesCalculation(senderAmount) {
        var currencyCode = acceptVar().currencyCode;
        var currencyRate = acceptVar().currencyRate;
        var sender_amount = senderAmount;
        sender_amount == "" ? (sender_amount = 0) : (sender_amount = sender_amount);

        var fixed_charge = acceptVar().currencyFixedCharge;
        var percent_charge = acceptVar().currencyPercentCharge;
        if ($.isNumeric(percent_charge) && $.isNumeric(fixed_charge) && $.isNumeric(sender_amount)) {
            // Process Calculation
            var fixed_charge_calc = parseFloat(currencyRate * fixed_charge);
            var percent_charge_calc = (parseFloat(sender_amount) / 100) * parseFloat(percent_charge);
            var total_charge = parseFloat(fixed_charge_calc) + parseFloat(percent_charge_calc);
            total_charge = parseFloat(total_charge).toFixed(2);
            // return total_charge;
            $(".fees-show").html( parseFloat(fixed_charge_calc).toFixed(2) + " " + currencyCode + " + " + parseFloat(percent_charge).toFixed(2) + "% = " + parseFloat(total_charge).toFixed(2) + " " + currencyCode);
            return {
                total: total_charge,
                fixed: fixed_charge_calc,
                percent: percent_charge,
            };
        } else {
            // return "--";
            return false;
        }
    }
    // function getFees() {
    //     var currencyCode = acceptVar().currencyCode;
    //     var percent = acceptVar().currencyPercentCharge;
    //     var charges = feesCalculation();
    //     if (charges == false) {
    //         return false;
    //     }
    //     $(".fees-show").html( parseFloat(charges.fixed).toFixed(2) + " " + currencyCode + " + " + parseFloat(charges.percent).toFixed(2) + "% = " + parseFloat(charges.total).toFixed(2) + " " + currencyCode);
    // }
    // calculate exchange rate
   function getExchangeRate() {
    var senderCurrencyCode = acceptVar().currencyCode;
    var senderCurrencyRate = acceptVar().currencyRate;
    var sender_amount = amount;

    sender_amount == "" ? (sender_amount = 0) : (sender_amount = sender_amount);

    var receiver_currency_code = "{{ $product['recipientCurrencyCode'] }}";
        $.ajax({
        type:'get',
            url:"{{ route('global.receiver.wallet.currency') }}",
            data:{code:receiver_currency_code},
            success:function(data){

                var receiverCurrencyCode = data.currency_code;
                var receiverCurrencyRate = data.rate;

                var exchangeRate = (senderCurrencyRate/receiverCurrencyRate);
                $('.rate-show').html("1 " +receiverCurrencyCode + " = " + parseFloat(exchangeRate).toFixed(4) + " " + senderCurrencyCode);
                $("input[name=exchange_rate]").val(exchangeRate);

            }
        });
   }

   function getPreview() {
            var qty = $("input[name=quantity]").val();
            var sender_currency = acceptVar().currencyCode;
            var sender_currency_rate = acceptVar().currencyRate;
            var unit_price = parseFloat(get_amount());
            var sender_amount = parseFloat(get_amount()) * qty;
            sender_amount   = parseFloat(sender_amount).toFixed(4);
            var receiverCurrencyCode = "{{ $product['recipientCurrencyCode'] }}";
            var exchangeRate =  parseFloat($("input[name=exchange_rate]").val());

            $(".product-name").text("{{ __($product['productName']) }}");

            var to_email = $("input[name=receiver_email]").val();
            $(".receiver-email").text(to_email);

             $(".receiver-country").text(acceptVar().country);

            var phone_code = acceptVar().country_select.data('mobile-code');
            var iso2 = acceptVar().country_select.data('iso2');
            var phone = $("input[name=phone]").val();
            var fullMobile = phone_code+phone
            $(".receiver-phone").text(fullMobile);


            var fromName = $("input[name=from_name]").val();
            $(".from-name").text(fromName);

            var consersion_amount = parseFloat(sender_amount) *  parseFloat(exchangeRate);
            var charges = feesCalculation(consersion_amount);
            var payable = parseFloat(consersion_amount) +  parseFloat(charges.total);


            // Request Amount
            $('.unit-price').html(unit_price + " " + receiverCurrencyCode);
            $('.total-quantity').html(qty);
            $('.total-price').html(sender_amount + " " + receiverCurrencyCode);
            $('.conversion-amount').html(parseFloat(consersion_amount).toFixed(4) + " " + sender_currency);
            $('.pay-in-total').html(parseFloat(payable).toFixed(4) + " " + sender_currency);

             //setup hidden input values
            $("input[name=g_qty]").val(qty);
            $("input[name=g_unit_price]").val(unit_price);
            $("input[name=g_recipient_email]").val(to_email);
            $("input[name=g_receipient_country]").val(acceptVar().country);
            $("input[name=g_recipient_phone_code]").val(phone_code);
            $("input[name=g_recipient_phone]").val(phone);
            $("input[name=g_recipient_iso]").val(iso2);
            $("input[name=g_from_name]").val(fromName);
            $("input[name=wallet_currency]").val(sender_currency);
            $("input[name=receiver_currency]").val(receiverCurrencyCode);
            //setup hidden input values
    }
    function validateInputField(){
        if( isNaN($("input[name=g_unit_price]").val()) || $("input[name=g_unit_price]").val() == 0 || $("input[name=g_unit_price]").val() == ''){
            throwMessage('error',['{{ __("Amount Field Is Required") }}']);
            return false;
        }
        var receiverEmail = $("input[name=receiver_email]").val();
        // Check if the receiver_email field is empty or contains an invalid email format
        if (receiverEmail === '') {
            throwMessage('error', ['{{ __("Receiver Email Field Is Required") }}']);
            return false;
        } else if (!isValidEmail(receiverEmail)) {
            throwMessage('error', ['{{ __("Invalid Email Format") }}']);
            return false;
        }
        console.log(acceptVar().country_select.data('iso2') )
        if( acceptVar().country_select.data('iso2') == undefined || acceptVar().country_select.data('iso2') == ''){
            throwMessage('error',['{{ __("Country Field Is Required") }}']);
            return false;
        }
        if( $("input[name=phone]").val() == ''){
            throwMessage('error',['{{ __("Receiver Phone Field Is Required") }}']);
            return false;
        }

        if( $("input[name=from_name]").val() == ''){
            throwMessage('error',['{{ __("From Name Field Is Required") }}']);
            return false;
        }
    }
    function isValidEmail(email) {
        // Regular expression pattern for email validation
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailPattern.test(email);
    }

    $('.buyBtn').on('click', function () {
        var modal = $('#BuyCardModal');
        if( validateInputField() == false){
            return false;
        }
        // getFees();
        getExchangeRate();
        senderBalance();
        getPreview();
        modal.modal('show');
    });
</script>
@endpush
