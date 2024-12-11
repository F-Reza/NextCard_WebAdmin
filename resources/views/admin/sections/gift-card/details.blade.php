@extends('admin.layouts.master')

@push('css')
@endpush

@section('page-title')
    @include('admin.components.page-title', ['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            [
                'name' => __('Dashboard'),
                'url' => setRoute('admin.dashboard'),
            ],
        ],
        'active' => __('Gift Card Details'),
    ])
@endsection
@section('content')
<div class="custom-card">
    <div class="card-header">
        <h6 class="title">{{ __("Gift Card Details For") }} {{ @$data->trx_id }}</h6>
    </div>
    <div class="card-body">
        <form class="card-form">
            <div class="row align-items-center mb-10-none">
                <div class="col-xl-4 col-lg-4 form-group">
                    <ul class="user-profile-list-two">
                        <li class="one">{{ __("Date")}}: <span>{{ @$data->created_at->format('d-m-y h:i:s A') }}</span></li>
                        <li class="two">{{ __("receiver Email")}}: <span>{{@$data->details->card_info->recipient_email }}</span></li>
                        <li class="three">{{ __("Receiver Phone")}}: <span>+{{@$data->details->card_info->recipient_phone }} </span></li>
                        <li class="four">{{ __("Full Name")}}: <span>
                            @if($data->user_id != null)
                                    <a href="{{ setRoute('admin.users.details',$data->user->username) }}">{{ $data->user->fullname }} ({{ __("USER") }})</a>
                            @endif
                            </span></li>

                        <li class="five">{{ __("Payable Unit Price")}}: <span>{{ get_amount(@$data->details->charge_info->sender_unit_price,@$data->details->charge_info->wallet_currency)}}</span></li>


                    </ul>
                </div>

                <div class="col-xl-4 col-lg-4 form-group">
                    <div class="user-profile-thumb">
                        <img src="{{ get_image($data->user->userImage) }}" alt="payment">
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 form-group">
                    <ul class="user-profile-list two">
                        <li class="one">{{ __("Exchange Rate")}}: <span>{{ get_amount(1,@$data->details->charge_info->card_currency) ." = ". get_amount(@$data->details->charge_info->exchange_rate,@$data->details->charge_info->wallet_currency)}}</span></li>
                        <li class="two">{{ __("Card Quantity")}}: <span>{{ @$data->details->charge_info->qty}}</span></li>
                        <li class="three">{{ __("Total Charge")}}: <span>{{ get_amount(@$data->charge->total_charge,@$data->details->charge_info->wallet_currency)}}</span></li>
                        <li class="four">{{ __("Payable Amount")}}: <span>{{ get_amount(@$data->payable,@$data->details->charge_info->wallet_currency)}}</span></li>
                        <li class="five">{{ __("Status")}}:  <span class="{{ @$data->stringStatus->class }}">{{ __(@$data->stringStatus->value) }}</span></li>
                    </ul>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="custom-card">
    <div class="card-header">
        <h6 class="title">{{ __("Gift Card Information") }} </h6>
    </div>
    <div class="card-body">
        <form class="card-form">
            <div class="row align-items-center mb-10-none">
                <div class="col-xl-4 col-lg-4 form-group">
                    <ul class="user-profile-list-two">
                        <li class="one">{{ __("Card Name")}}: <span>{{ @$data->details->card_info->card_name }}</span></li>
                        <li class="two">{{ __("Card Currency")}}: <span>{{ @$data->details->card_info->card_currency }}</span></li>
                        <li class="three">{{ __("Card Unit Price")}}: <span>{{ get_amount(@$data->details->charge_info->card_unit_price,@$data->details->charge_info->card_currency)}}</span></li>
                        <li class="four">{{ __("Card Total Price")}}: <span>{{get_amount(@$data->details->charge_info->total_receiver_amount,@$data->details->charge_info->card_currency)}} </span></li>
                        <li class="five">{{ __("Card Api Currency")}}: <span>{{@$data->details->card_info->api_currency}} </span></li>
                    </ul>
                </div>

                <div class="col-xl-4 col-lg-4 form-group">
                    <div class="user-profile-thumb">
                        <img src="{{ @$data->details->card_info->card_image }}" alt="payment">
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 form-group">
                    <ul class="user-profile-list two">
                        <li class="one">{{ __("Api Discount")}}: <span>{{get_amount(@$data->details->card_info->api_discount,@$data->details->card_info->api_currency)}}</span></li>
                        <li class="two">{{ __("Api Fee")}}: <span>{{get_amount(@$data->details->card_info->api_fee,@$data->details->card_info->api_currency)}}</span></li>
                        <li class="three">{{ __("Api Sms Fee")}}: <span>{{get_amount(@$data->details->card_info->api_sms_fee,@$data->details->card_info->api_currency)}}</span></li>
                        <li class="four">{{ __("Api Total Fee")}}: <span>{{get_amount(@$data->details->card_info->api_total_fee,@$data->details->card_info->api_currency)}}</span></li>
                        <li class="five">{{ __("Api Trx Id")}}: <span>{{ @$data->details->card_info->api_trx_id}}</span></li>
                    </ul>
                </div>
            </div>
        </form>
    </div>
</div>




@endsection


@push('script')

@endpush
