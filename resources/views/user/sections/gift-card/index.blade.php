@extends('user.layouts.master')
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
    <div class="table-area mt-20">
        <div class="table-wrapper">
            <div class="dashboard-header-wrapper">
                <h4 class="title">{{ __($page_title) }}</h4>
                <div class="dashboard-btn-wrapper">
                    <div class="dashboard-btn">
                        <a href="{{ setRoute('user.gift.card.list') }}" class="btn--base"><i class="las la-plus me-1"></i> {{ __("Gift Cards") }}</a>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("TRX ID") }}</th>
                            <th>{{ __("Card Name") }}</th>
                            <th>{{ __("Card Images") }}</th>
                            <th>{{ __("receiver Email") }}</th>
                            <th>{{ __("Receiver Phone") }}</th>
                            <th>{{ __("Card Unit Price") }}</th>
                            <th>{{ __("Card Quantity") }}</th>
                            <th>{{ __("Card Total Price") }}</th>
                            <th>{{ __("Exchange Rate") }}</th>
                            <th>{{ __("Payable Unit Price") }}</th>
                            <th>{{ __("Total Charge") }}</th>
                            <th>{{ __("Payable Amount") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($giftCards ?? [] as $item)
                        <tr>
                            <td>{{ $item->trx_id}}</td>
                            <td>{{ $item->card_name}}</td>
                            <td><img style="max-width: 50px" src="{{ $item->card_image}} " alt=""></td>
                            <td>{{ $item->recipient_email}}</td>
                            <td>+{{ $item->recipient_phone}}</td>
                            <td>{{ get_amount($item->card_amount,$item->card_currency)}}</td>
                            <td>{{ $item->qty}}</td>
                            <td>{{ get_amount($item->card_total_amount,$item->card_currency)}}</td>
                            <td>{{ get_amount(1,$item->card_currency) ." = ". get_amount($item->exchange_rate,$item->user_wallet_currency)}}</td>
                            <td>{{ get_amount($item->unit_amount,$item->user_wallet_currency)}}</td>
                            <td>{{ get_amount($item->total_charge,$item->user_wallet_currency)}}</td>
                            <td>{{ get_amount($item->total_payable,$item->user_wallet_currency)}}</td>
                            <td><span class="{{ $item->stringStatus->class }}">{{ __($item->stringStatus->value) }} </span></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="13"><div class="alert alert-primary" style="margin-top: 37.5px; text-align:center">{{ __("No data found!") }}</div></td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ get_paginate($giftCards) }}
            </div>
        </div>
    </div>
</div>
@endsection
@push('script')

@endpush
