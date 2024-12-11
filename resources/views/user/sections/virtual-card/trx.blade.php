@extends('user.layouts.master')

@push('css')

@endpush

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
    <div class="dashboard-area mt-10">
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{ __(@$page_title) }}</h3>
        </div>
    </div>
    <div class="dashboard-list-area mt-20">
        <div class="dashboard-list-wrapper">
           
            @if(isset($card_truns) && $card_truns['data'] != null)
                @if(array_key_exists('data', $card_truns ))
                    @foreach($card_truns['data'] as $key => $value)
                    <div class="dashboard-list-item-wrapper">
                        <div class="dashboard-list-item sent">
                            <div class="dashboard-list-left">
                                <div class="dashboard-list-user-wrapper">
                                    <div class="dashboard-list-user-icon">
                                        <i class="las la-arrow-up"></i>
                                    </div>
                                    <div class="dashboard-list-user-content">
                                        <h4 class="title">TRX: {{ @$value['id'] }}</h4>
                                        <span class="sub-title text--danger"> <span class="badge badge--success ms-2">{{ @$value['status'] }}</span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dashboard-list-right">
                                <h4 class="main-money text--base">{{ @$value['amount']  }} {{ @$value['currency'] }}</h4>
                                <h6 class="exchange-money">{{ date("M-d-Y",strtotime($value['created_at'])) }}</h6>
                            </div>
                        </div>
                        <div class="preview-list-wrapper">
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-exchange-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("TRX ID") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$value['id'] }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-qrcode"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{__("Payment Details")}}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$value['product'] }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-user-tag"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$value['amount']  }} {{ @$value['currency'] }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-user-tag"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Reference") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$value['reference']  }} </span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-user-tag"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Gateway Reference") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$value['gateway_reference']  }} </span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-user-tag"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Response Message") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$value['response_message']  }} </span>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    @endforeach
                @endif
            @else
            <div class="alert alert-primary text-center">
                {{ __("No Record Found!") }}
            </div>
            @endif
            
        </div>
    </div>
   
</div>
@endsection

@push('script')

@endpush
