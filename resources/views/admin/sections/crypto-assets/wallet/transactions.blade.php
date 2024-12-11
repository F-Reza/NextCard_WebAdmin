@extends('admin.layouts.master')

@push('css')
    <style>
        .fileholder {
            min-height: 200px !important;
        }

        .fileholder-files-view-wrp.accept-single-file .fileholder-single-file-view,.fileholder-files-view-wrp.fileholder-perview-single .fileholder-single-file-view{
            height: 156px !important;
        }
    </style>
@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ],
        [
            'name'  => __("Gateways"),
            'url'   => setRoute('admin.payment.gateway.view',[$gateway->slug,strtolower($gateway->type)]),
        ],
        [
            'name'  => __("Gateway Edit"),
            'url'   => setRoute('admin.payment.gateway.edit',[$gateway->slug,strtolower($gateway->type),$gateway->alias]),
        ],
        [
            'name'  => __("Crypto Assets"),
            'url'   => setRoute('admin.crypto.assets.gateway.index',$gateway->alias),
        ],
    ], 'active' => __("Incoming Transactions")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Transacitons") }}
                    ({{ __("Gateway") }}: {{ $gateway->name }}, {{ __("Coin") }}: {{ $crypto_asset->coin }})
                </h5>

                <div class="table-btn-area">
                    @include('admin.components.search-input',[
                        'name'          => 'transactioin_search',
                        'placeholder'   => __("Txn Hash"),
                    ])
                </div>
            </div>
            <div class="table-responsive">


                @include('admin.components.data-table.crypto-address-transaction-table',compact('incoming_transactions'))
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        itemSearch($("input[name=transactioin_search]"),$(".transaction-search-table"),"{{ setRoute('admin.crypto.assets.wallet.transaction.search',[$crypto_asset->id,$wallet_credentials_id]) }}");
    </script>
@endpush
