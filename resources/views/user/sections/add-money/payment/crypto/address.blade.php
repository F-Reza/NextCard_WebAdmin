@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="body-wrapper">
    <div class="deposit-wrapper ptb-50">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 pb-30">
                    <div class="deposit-form">
                        <div class="form-title text-center">
                            <h4>{{ __("Pay With This Address") }} ({{ $transaction->gateway_currency->currency_code }})</h4>
                        </div>
                        @if ($transaction->status == payment_gateway_const()::STATUSWAITING)
                            <form class="row g-4 submit-form" method="POST" action="{{ setRoute('user.add.money.payment.crypto.confirm',$transaction->trx_id) }}">
                                @csrf
                                <div class="form-group">
                                    <div class="input-group">
                                        <input type="text" id="referralURL" value="{{ $transaction->details->payment_info->receiver_address ?? "" }}" class="form-control form--control ref-input text-light copiable" readonly>
                                        <div class="input-group-append">
                                            <span class="input-group-text copytext copy-button">
                                                <i class="la la-copy"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mx-auto mt-4 text-center qr-code-image">
                                    <img class="mx-auto" src="{{ $transaction->details->payment_info->receiver_qr_image ?? "" }}" alt="Qr Code">
                                </div>

                                {{-- Print Dynamic Input Filed if Have START --}}
                                @foreach ($transaction->details->payment_info->requirements ?? [] as $input)
                                    <div class="form-group col-12">
                                        <label for="">{{ $input->label }} </label>
                                        <input type="text" name="{{ $input->name }}" placeholder="{{ $input->placeholder ?? "" }}" class="form-control form--control ref-input text-light copiable" @if ($input->required)
                                            @required(true)
                                        @endif>
                                    </div>
                                @endforeach
                                {{-- Print Dynamic Input Filed if Have END --}}

                                <div class="col-12 mt-5">
                                    <button type="submit" class="btn--base w-100 text-center btn-loading ">{{ __("Proceed") }}</button>
                                </div>
                            </form>
                        @else
                            <div class="payment-received-alert">
                                <div class="text-center text--success">
                                    {{ __("Payment Received Successfully!") }}
                                </div>

                                <div class="txn-hash text-center mt-2 text--info">
                                    <strong>{{ __("Txn Hash:") }} </strong>
                                    <span>{{ $transaction->details->payment_info->txn_hash ?? "" }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-lg-6 col-md-8">
                    <div class="deposit-form">
                        <div class="d-flex justify-content-between">
                            <h4>{{ __("Request Amount") }}</h4>
                            <h4 class="enter-amount">{{ get_amount($transaction->request_amount, $transaction->creator_wallet->currency->code) }}</h4>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <h4>{{ __("Exchange Rate") }}</h4>
                            <h4 class="exchange-rate">
                                1 {{ $transaction->creator_wallet->currency->code }} =
                                {{ get_amount($transaction->details->charge->sender_cur_rate, $transaction->currency->currency_code,8) }}
                            </h4>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <h4>{{__("Fees & Charges")}}</h4>
                            <h4 class="fees">{{ get_amount($transaction->charge->total_charge, $transaction->currency->currency_code,8) }}</h4>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <h4>{{ __("Total Payable Amount") }}</h4>
                            <h4 class="payable">{{ get_amount($transaction->payable, $transaction->currency->currency_code,8) }}</h4>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <h4>{{__("Will Get")}}</h4>
                            <h4 class="will-get">{{ get_amount($transaction->details->charge->will_get, $transaction->creator_wallet->currency->code,4) }}</h4>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

@endsection

@push('script')
<script>

    $('.copytext').on('click',function(){
            var copyText = document.getElementById("referralURL");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");

            throwMessage('success',["Copied: " + copyText.value]);
        });
</script>
@endpush
