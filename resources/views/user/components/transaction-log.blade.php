@isset($transactions)
    @forelse ($transactions as $item)
        <div class="dashboard-list-item-wrapper">
            <div class="dashboard-list-item sent">
                <div class="dashboard-list-left">
                    <div class="dashboard-list-user-wrapper">
                        <div class="dashboard-list-user-icon">
                            @if (@$item->attribute == payment_gateway_const()::SEND)
                            <i class="las la-dollar-sign"></i>
                            @else
                            <i class="las la-dollar-sign"></i>
                            @endif
                        </div>
                        <div class="dashboard-list-user-content">
                            @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                                <h4 class="title">{{ __("Add Balance via") }} <span class="text--warning">{{ @$item->currency->name }}</span></h4>
                            @elseif (@$item->type == payment_gateway_const()::WITHDRAWMONEY)
                                <h4 class="title">{{ __("Withdraw Money") }} <span class="text--warning">{{ @$item->currency->gateway->name }}</span></h4>
                            @elseif (@$item->type == payment_gateway_const()::VIRTUALCARD)
                                <h4 class="title">{{ __("Virtual Card") }} <span class="text--info">({{ __(@$item->remark) }})</span></h4>
                            @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                                <h4 class="title">{{ __("Balance Update From Admin") }} {{ "(".@$item->user_wallet->currency->code.")" }}</h4>
                            @elseif (@$item->type == payment_gateway_const()::TYPETRANSFERMONEY)
                                @if (@$item->isAuthUser())

                                    @if (@$item->attribute == payment_gateway_const()::SEND)
                                        <h4 class="title">{{__("Transfer Money to"). __(" @" . @$item->details->receiver->username." (".@$item->details->receiver->email.")") }} </h4>
                                    @elseif (@$item->attribute == payment_gateway_const()::RECEIVED)
                                        <h4 class="title">{{ __("Transfer Money from").__(" @" .@$item->details->sender->username." (".@$item->details->sender->email.")") }} </h4>
                                    @endif
                                @endif
                            @elseif ($item->type == payment_gateway_const()::GIFTCARD)
                                <h4 class="title">{{ __("Gift Card") }}</h4>
                            @endif
                            <span class="{{ @$item->stringStatus->class }}">{{__( @$item->stringStatus->value) }} </span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-list-right">
                    @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                        <h4 class="main-money text--warning">{{ get_amount(@$item->request_amount,get_default_currency_code(),4) }}</h4>
                        <h6 class="exchange-money fw-bold">{{ get_amount(@$item->payable,@$item->currency->currency_code,4) }}</h6>
                    @elseif(@$item->type == payment_gateway_const()::WITHDRAWMONEY)
                        <h6 class="exchange-money text--warning fw-bold">{{ get_amount(@$item->request_amount,get_default_currency_code()) }}</h6>
                        <h4 class="main-money ">{{ get_amount(@$item->payable,get_default_currency_code()) }}</h4>
                    @elseif(@$item->type == payment_gateway_const()::BILLPAY)
                        <h4 class="main-money text--warning">{{ get_amount(@$item->request_amount,get_default_currency_code()) }}</h4>
                        <h6 class="exchange-money fw-bold">{{ get_amount(@$item->payable,get_default_currency_code()) }}</h6>
                    @elseif(@$item->type == payment_gateway_const()::MOBILETOPUP)
                        <h4 class="main-money text--warning">{{ get_amount(@$item->request_amount,get_default_currency_code()) }}</h4>
                        <h6 class="exchange-money fw-bold">{{ get_amount(@$item->payable,get_default_currency_code()) }}</h6>
                    @elseif(@$item->type == payment_gateway_const()::VIRTUALCARD)
                        <h4 class="main-money text--warning">{{ get_amount(@$item->request_amount,get_default_currency_code()) }}</h4>
                        <h6 class="exchange-money fw-bold">{{ get_amount(@$item->payable,get_default_currency_code()) }}</h6>
                    @elseif (@$item->type == payment_gateway_const()::TYPEMONEYEXCHANGE)
                        <h4 class="main-money text--base">{{ get_amount(@$item->request_amount,@$item->user_wallet->currency->code) }}</h4>
                        <h6 class="exchange-money">{{ get_amount(@$item->available_balance,@$item->user_wallet->currency->code) }}</h6>
                    @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                        <h4 class="main-money text--base">{{ get_amount(@$item->request_amount,@$item->user_wallet->currency->code) }}</h4>
                        <h6 class="exchange-money">{{ get_amount(@$item->available_balance,@$item->user_wallet->currency->code) }}</h6>
                    @elseif (@$item->type == payment_gateway_const()::TYPETRANSFERMONEY)
                        @if (@$item->attribute == payment_gateway_const()::SEND)
                        <h6 class="exchange-money text--warning ">{{ get_amount(@$item->request_amount,get_default_currency_code()) }}</h6>
                        <h4 class="main-money fw-bold">{{ get_amount(@$item->payable,get_default_currency_code()) }}</h4>
                        @elseif (@$item->attribute == payment_gateway_const()::RECEIVED)
                        <h6 class="exchange-money fw-bold">{{ get_amount(@$item->request_amount,get_default_currency_code()) }}</h6>
                        @endif
                    @elseif($item->type == payment_gateway_const()::GIFTCARD)
                        <h4 class="main-money text--warning">{{ get_amount($item->request_amount,$item->details->charge_info->wallet_currency) }}</h4>
                        <h6 class="exchange-money fw-bold">{{ get_amount($item->payable,$item->details->charge_info->wallet_currency) }}</h6>
                    @endif
                </div>
            </div>
            <div class="preview-list-wrapper">

                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="lab la-tumblr"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Transaction ID") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ @$item->trx_id }}</span>
                    </div>
                </div>
                @if (@$item->type != payment_gateway_const()::TYPETRANSFERMONEY )
                @if (@$item->type != payment_gateway_const()::BILLPAY )
                @if (@$item->type != payment_gateway_const()::MOBILETOPUP )
                @if (@$item->type != payment_gateway_const()::VIRTUALCARD )
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
                        @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                            <span>1 {{ get_default_currency_code() }} = {{ get_amount(@$item->currency->rate,@$item->currency->currency_code,4) }}</span>
                        @elseif (@$item->type == payment_gateway_const()::WITHDRAWMONEY)
                            <span>1 {{ get_default_currency_code() }} = {{ get_amount(@$item->details->withdraw_data->gateway_rate,@$item->currency->currency_code) }}</span>
                        @elseif (@$item->type == payment_gateway_const()::TYPEMONEYEXCHANGE)
                            <span>1 {{ @$item->user_wallet->currency->code }} = {{ get_amount(@$item->details->exchange_rate,@$item->details->exchange_currency) }}</span>
                        @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                            <span>1 {{ get_default_currency_code() }} = {{ get_amount(@$item->user_wallet->currency->rate,@$item->user_wallet->currency->code) }}</span>
                        @elseif (@$item->type == payment_gateway_const()::GIFTCARD)
                        {{ get_amount(1,@$item->details->charge_info->card_currency) ." = ". get_amount(@$item->details->charge_info->exchange_rate,@$item->details->charge_info->wallet_currency)}}

                        @endif
                    </div>
                </div>
                @endif
                @endif
                @endif
                @endif

                @if (@$item->type == payment_gateway_const()::BILLPAY )
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-balance-scale"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Bill Type") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--base">{{ @$item->details->bill_type_name }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-balance-scale"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Bill Number") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--base">{{ @$item->details->bill_number }}</span>
                    </div>
                </div>
                @endif
                @if (@$item->type == payment_gateway_const()::MOBILETOPUP )
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-balance-scale"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Topup Type") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--base">{{ @$item->details->topup_type_name }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="fas fa-mobile"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Mobile Number") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--base">{{ @$item->details->mobile_number }}</span>
                    </div>
                </div>
                @endif


                @if (@$item->type == payment_gateway_const()::TYPETRANSFERMONEY)
                    @if (@$item->attribute == payment_gateway_const()::SEND)
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="las la-battery-half"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __("Fees & Charges") }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span>{{ get_amount(@$item->charge->total_charge,@$item->user_wallet->currency->code) }}</span>
                            </div>
                        </div>
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="lab la-get-pocket"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __("recipient Received") }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span>{{ get_amount(@$item->details->recipient_amount,get_default_currency_code()) }}</span>
                            </div>
                        </div>

                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="las la-balance-scale"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __("Current Balance") }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span class="text--base">{{ get_amount(@$item->available_balance,get_default_currency_code()) }}</span>
                            </div>
                        </div>
                    @else
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-balance-scale"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Current Balance") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--base">{{ get_amount(@$item->available_balance,get_default_currency_code()) }}</span>
                        </div>
                    </div>
                    @endif
                @else
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-battery-half"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Fees & Charges") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                                <span>{{ get_amount(@$item->charge->total_charge,@$item->currency->currency_code,4) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::WITHDRAWMONEY)
                                <span>{{ getAmount(@$item->charge->total_charge,4) }} {{get_default_currency_code() }}</span>
                            @elseif (@$item->type == payment_gateway_const()::BILLPAY)
                                <span>{{ get_amount(@$item->charge->total_charge,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::MOBILETOPUP)
                                <span>{{ get_amount(@$item->charge->total_charge,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::VIRTUALCARD)
                                <span>{{ get_amount(@$item->charge->total_charge,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::TYPEMONEYEXCHANGE)
                                <span>{{ get_amount(@$item->details->total_charge,@$item->user_wallet->currency->code) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                                <span>{{ get_amount(@$item->charge->total_charge,@$item->user_wallet->currency->code) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::GIFTCARD)
                                <span>{{ get_amount(@$item->charge->total_charge,@$item->details->charge_info->wallet_currency) }}</span>
                            @endif
                        </div>
                    </div>
                    @if (@$item->type != payment_gateway_const()::BILLPAY)
                    @if (@$item->type != payment_gateway_const()::MOBILETOPUP)
                    @if (@$item->type != payment_gateway_const()::GIFTCARD)
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="lab la-get-pocket"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                                        <span>{{ __("Current Balance") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::WITHDRAWMONEY)
                                        <span>{{ __("Will Get") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::BILLPAY)
                                        <span>{{ __("Payable Amount") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::MOBILETOPUP)
                                        <span>{{ __("Payable Amount") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::TYPEMONEYEXCHANGE)
                                        <span>{{ __("Total Payable") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                                        <span>{{ __("Total Received") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::VIRTUALCARD)
                                        <span>{{ __("Card Amount") }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                                <span class="text-danger">{{ get_amount(@$item->available_balance,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::WITHDRAWMONEY)

                                <span>{{ get_amount(@$item->details->withdraw_data->conversion_amount,@$item->currency->currency_code) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::BILLPAY)
                                <span class="fw-bold">{{ get_amount(@$item->payable,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::MOBILETOPUP)
                                <span class="fw-bold">{{ get_amount(@$item->payable,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::VIRTUALCARD)
                            <span class="fw-bold"> {{ get_amount(@$item->details->card_info->amount??@$item->details->card_info->balance,get_default_currency_code()) }}</span>

                            @elseif (@$item->type == payment_gateway_const()::TYPEMONEYEXCHANGE)
                                <span>{{ get_amount(@$item->payable,@$item->user_wallet->currency->code) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                                <span>{{ get_amount(@$item->payable,@$item->user_wallet->currency->code) }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                    @endif
                    @endif
                    @if (@$item->type != payment_gateway_const()::TYPEADDMONEY)
                    @if (@$item->type != payment_gateway_const()::GIFTCARD)
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-receipt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                                        <span>{{ __("Total Amount") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::WITHDRAWMONEY)
                                        <span>{{ __("Current Balance") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::BILLPAY)
                                        <span>{{ __("Current Balance") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::MOBILETOPUP)
                                        <span>{{ __("Current Balance") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::VIRTUALCARD)
                                        <span>{{ __("Card Masked") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::TYPEMONEYEXCHANGE)
                                        <span>{{ __("Exchange Amount") }}</span>
                                    @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                                        <span>{{ __("Remark") }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="preview-list-right">
                            @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                                <span class="text--warning">{{ get_amount(@$item->payable,@$item->currency->currency_code) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::WITHDRAWMONEY)
                                <span class="text--danger">{{ get_amount(@$item->available_balance,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::BILLPAY)
                                <span class="text--danger">{{ get_amount(@$item->available_balance,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::MOBILETOPUP)
                                <span class="text--danger">{{ get_amount(@$item->available_balance,get_default_currency_code()) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::VIRTUALCARD)
                            @php
                                $card_number = $item->details->card_info->card_pan?? $item->details->card_info->maskedPan ?? $item->details->card_info->card_number ?? "";
                            @endphp
                            @if ($card_number)
                                @php
                                    $card_pan = str_split($card_number, 4);
                                @endphp
                                @foreach($card_pan as $key => $value)
                                    <span class="text--base fw-bold">{{ $value }}</span>
                                @endforeach
                            @else
                                <span class="text--base fw-bold">----</span>
                                <span class="text--base fw-bold">----</span>
                                <span class="text--base fw-bold">----</span>
                                <span class="text--base fw-bold">----</span>
                            @endif

                            @elseif (@$item->type == payment_gateway_const()::TYPEMONEYEXCHANGE)
                                <span class="text--warning">{{ get_amount(@$item->details->exchange_amount,@$item->details->exchange_currency) }}</span>
                            @elseif (@$item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
                                <span class="text--warning">{{ @$item->remark }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                @endif
                @endif
                @if (@$item->type == payment_gateway_const()::VIRTUALCARD)
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-smoking"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Current Balance") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="fw-bold">{{ get_amount(@$item->available_balance,get_default_currency_code()) }}</span>
                    </div>
                </div>
                @endif


                @if (@$item->type == payment_gateway_const()::TYPEADDMONEY)
                    @if ($item->gateway_currency->gateway->isTatum($item->gateway_currency->gateway) && $item->status == payment_gateway_const()::STATUSWAITING)
                    <div class="col-12">
                        <form action="{{ setRoute('user.add.money.payment.crypto.confirm', $item->trx_id) }}" method="POST">
                            @csrf
                            @php
                                $input_fields = $item->details->payment_info->requirements ?? [];
                            @endphp

                            @foreach ($input_fields as $input)
                                <div class="p-3">
                                    <h6 class="mb-2">{{ $input->label }}</h6>
                                    <input type="text" class="form-control form--control ref-input text-light copiable" name="{{ $input->name }}" placeholder="{{ $input->placeholder ?? "" }}" required>
                                </div>
                            @endforeach

                            <div class="text-end">
                                <button type="submit" class="btn--base my-2">{{ __("Process") }}</button>
                            </div>

                        </form>
                    </div>
                    @endif
                @endif
                {{-- GIFTCARD  --}}
                @if ($item->type == payment_gateway_const()::GIFTCARD)
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-receipt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Card Name") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="fw-bold">{{ $item->details->card_info->card_name }}</span>
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
                            <span class="fw-bold">{{ $item->details->card_info->recipient_email }}</span>
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
                            <span class="fw-bold">{{ $item->details->card_info->recipient_phone }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-wallet"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Card Unit Price") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="fw-bold">{{ get_amount($item->details->card_info->card_amount,$item->details->card_info->card_currency) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-wallet"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Card Quantity") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="fw-bold">{{ $item->details->card_info->qty}}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-wallet"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Card Total Price") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="fw-bold">{{ get_amount($item->details->card_info->card_total_amount,$item->details->card_info->card_currency) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-smoking"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Current Balance") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="fw-bold">{{ get_amount($item->available_balance,@$item->details->charge_info->wallet_currency) }}</span>
                        </div>
                    </div>
                @endif
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-clock"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Time & Date") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ dateFormat('d-m-y h:i:s A', @$item->created_at) }}</span>
                    </div>
                </div>
                @if( @$item->status == 4 &&  @$item->reject_reason != null)
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-smoking"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Rejection Reason") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text-danger">{{ @$item->reject_reason }}</span>
                    </div>
                </div>
                @endif



            </div>
        </div>
    @empty
        <div class="alert alert-primary text-center">
            {{ __("No Record Found!") }}
        </div>
    @endforelse

    {{ get_paginate($transactions) }}


@endisset
