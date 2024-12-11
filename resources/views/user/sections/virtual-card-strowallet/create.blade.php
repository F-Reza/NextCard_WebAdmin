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
    <div class="deposit-wrapper ptb-50">
        <div class="container">
            <div class="row justify-content-center">
        {{-- create card customer  --}}
        @if($user->strowallet_customer == null)
            @include('user.sections.virtual-card-strowallet.component.create-customer')
        @endif
        {{-- check and update for customer  --}}
        @if(isset($user->strowallet_customer) )
            @if(isset($user->strowallet_customer->status) && $user->strowallet_customer->status ==  global_const()::CARD_UNDER_STATUS || $user->strowallet_customer->status ==  global_const()::CARD_LOW_KYC_STATUS)
            @include('user.sections.virtual-card-strowallet.component.check-customer-status')
            @endif
        @endif
        {{-- Create card  --}}
        @if(isset($user->strowallet_customer))
            @if(isset($user->strowallet_customer->status) && $user->strowallet_customer->status ==  global_const()::CARD_HIGH_KYC_STATUS)
            @include('user.sections.virtual-card-strowallet.component.create-card')
            @endif
        @endif
    </div>
</div>
</div>
</div>
@endsection

@push('script')
<script>
     var defualCurrency = "{{ get_default_currency_code() }}";
     var defualCurrencyRate = "{{ get_default_currency_rate() }}";
     $(document).ready(function(){
           getLimit();
           getFees();
           getPreview();
       });
       $("input[name=card_amount]").keyup(function(){
            getFees();
            getPreview();
       });
       $("input[name=card_amount]").focusout(function(){
            enterLimit();
       });
       function getLimit() {
           var currencyCode = acceptVar().currencyCode;
           var currencyRate = acceptVar().currencyRate;

           var min_limit = acceptVar().currencyMinAmount;
           var max_limit =acceptVar().currencyMaxAmount;
           if($.isNumeric(min_limit) || $.isNumeric(max_limit)) {
               var min_limit_calc = parseFloat(min_limit/currencyRate).toFixed(2);
               var max_limit_clac = parseFloat(max_limit/currencyRate).toFixed(2);
               $('.limit-show').html("{{ __('limit') }} " + min_limit_calc + " " + currencyCode + " - " + max_limit_clac + " " + currencyCode);

               return {
                   minLimit:min_limit_calc,
                   maxLimit:max_limit_clac,
               };
           }else {
               $('.limit-show').html("--");
               return {
                   minLimit:0,
                   maxLimit:0,
               };
           }
       }
       function acceptVar() {

           var currencyCode = defualCurrency;
           var currencyRate = defualCurrencyRate;
           var currencyMinAmount ="{{getAmount($cardCharge->min_limit)}}";
           var currencyMaxAmount = "{{getAmount($cardCharge->max_limit)}}";
           var currencyFixedCharge = "{{getAmount($cardCharge->fixed_charge)}}";
           var currencyPercentCharge = "{{getAmount($cardCharge->percent_charge)}}";


           return {
               currencyCode:currencyCode,
               currencyRate:currencyRate,
               currencyMinAmount:currencyMinAmount,
               currencyMaxAmount:currencyMaxAmount,
               currencyFixedCharge:currencyFixedCharge,
               currencyPercentCharge:currencyPercentCharge,


           };
       }
       function feesCalculation() {
           var currencyCode = acceptVar().currencyCode;
           var currencyRate = acceptVar().currencyRate;
           var sender_amount = $("input[name=card_amount]").val();
           sender_amount == "" ? (sender_amount = 0) : (sender_amount = sender_amount);

           var fixed_charge = acceptVar().currencyFixedCharge;
           var percent_charge = acceptVar().currencyPercentCharge;
           if ($.isNumeric(percent_charge) && $.isNumeric(fixed_charge) && $.isNumeric(sender_amount)) {
               // Process Calculation
               var fixed_charge_calc = parseFloat(currencyRate * fixed_charge);
               var percent_charge_calc = parseFloat(currencyRate)*(parseFloat(sender_amount) / 100) * parseFloat(percent_charge);
               var total_charge = parseFloat(fixed_charge_calc) + parseFloat(percent_charge_calc);
               total_charge = parseFloat(total_charge).toFixed(2);
               // return total_charge;
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

       function getFees() {
           var currencyCode = acceptVar().currencyCode;
           var percent = acceptVar().currencyPercentCharge;
           var charges = feesCalculation();
           if (charges == false) {
               return false;
           }
           $(".fees-show").html("{{ __('Fees') }}: " + parseFloat(charges.fixed).toFixed(2) + " " + currencyCode + " + " + parseFloat(charges.percent).toFixed(2) + "% = " + parseFloat(charges.total).toFixed(2) + " " + currencyCode);
       }
       function getPreview() {
            var senderAmount = $("input[name=card_amount]").val();
            var charges = feesCalculation();
            var sender_currency = acceptVar().currencyCode;
            var sender_currency_rate = acceptVar().currencyRate;

            senderAmount == "" ? senderAmount = 0 : senderAmount = senderAmount;
            // Sending Amount
            $('.request-amount').html( senderAmount + " " + sender_currency);

            // Fees
            var charges = feesCalculation();
            var total_charge = 0;
            if(senderAmount == 0){
                total_charge = 0;
            }else{
                total_charge = charges.total;
            }
            $('.fees').html( total_charge + " " + sender_currency);
            var totalPay = parseFloat(senderAmount) * parseFloat(sender_currency_rate)
            var pay_in_total = 0;
            if(senderAmount == 0 ||  senderAmount == ''){
                pay_in_total = 0;
            }else{
                pay_in_total =  parseFloat(totalPay) + parseFloat(charges.total);
            }
            $('.payable-total').html( pay_in_total + " " + sender_currency);

       }
       function enterLimit(){
        var min_limit = parseFloat("{{getAmount($cardCharge->min_limit)}}");
        var max_limit =parseFloat("{{getAmount($cardCharge->max_limit)}}");
        var currencyRate = acceptVar().currencyRate;
        var sender_amount = parseFloat($("input[name=card_amount]").val());

        if( sender_amount < min_limit ){
            throwMessage('error',['{{ __("Please follow the mimimum limit") }}']);
            $('.buyBtn').attr('disabled',true)
        }else if(sender_amount > max_limit){
            throwMessage('error',['{{ __("Please follow the maximum limit") }}']);
            $('.buyBtn').attr('disabled',true)
        }else{
            $('.buyBtn').attr('disabled',false)
        }

       }
</script>
@endpush
