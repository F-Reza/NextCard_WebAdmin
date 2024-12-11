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
            'name'  => $gateway->name,
            'url'   => setRoute('admin.payment.gateway.edit',[$gateway->slug,strtolower($gateway->type),$gateway->alias]),
        ]
    ], 'active' => __("Crypto Assets")])
@endsection

@section('content')

    <div class="custom-card mb-10">
        <div class="card-header">
            <h6 class="title fw-bold">{{ __("Crypto Wallets") }}</h6>
            {{-- @if (count($wallet_not_available_coins) > 0) --}}
                <div class="table-btn-area">
                    @include('admin.components.link.custom',[
                        'href'          => setRoute('admin.crypto.assets.generate.wallet', $gateway->alias),
                        'class'         => "btn--base",
                        'text'          => __("Generate Wallets"),
                        'permission'    => "admin.crypto.assets.generate.wallet",
                    ])
                </div>
            {{-- @endif --}}
        </div>
        <div class="card-body">
            {{ __("Wallet not present in some currency/coin.") }} {{ __("Ex:") }} @foreach ($wallet_not_available_coins as $coin)
                {{ $coin }}&nbsp;
            @endforeach
            . {{ __("Click the generate wallets button to create dynamically wallets.") }}
        </div>
    </div>

    @foreach ($gateway->supported_currencies as $currency)

        <div class="custom-card mb-10">
            <div class="card-header">
                <h6 class="title fw-bold">{{ $currency }}</h6>
                <div class="table-btn-area">
                    @include('admin.components.link.add-default',[
                        'href'          => "#wallet-store",
                        'class'         => "modal-btn wallet-add-btn",
                        'text'          =>__("Add Wallet"),
                        'permission'    => "admin.crypto.assets.wallet.store",
                        'attribute'     => "data-coin=$currency data-gateway=$gateway->alias",
                    ])
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    @php
                        $wallet_assets = $gateway->cryptoAssets->where('coin', $currency)->first();
                    @endphp

                    @forelse ($wallet_assets->credentials->credentials ?? [] as $item)
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <div class="col-4 form-group switcher" data-target="{{ $wallet_assets->id }}" data-credentials="{{ $item->id }}">
                                        @include('admin.components.form.switcher',[
                                            'label'         => __('Status'),
                                            'name'          => 'type',
                                            'value'         => old('type',$item->status),
                                            'options'       => [__('Active') => 1,__('Deactive') => 0],
                                            'status'        => true,
                                            'permission'    => 'admin.crypto.assets.wallet.status.update'
                                        ])
                                    </div>

                                    <div>
                                        <a href="{{ setRoute('admin.crypto.assets.wallet.balance.update',[$wallet_assets->id,$item->id]) }}" class="btn--base bg--primary py-1 px-2">
                                            <i class="las la-sync-alt"></i>
                                        </a>

                                        @include('admin.components.link.delete-default',[
                                            'href'          => 'javascript:void(0)',
                                            'class'         => 'wallet-delete-btn py-1 px-2',
                                            'permission'    => 'admin.crypto.assets.wallet.delete',
                                            'attribute'     => "data-id=$wallet_assets->id data-credential-id=$item->id",
                                        ])

                                        @include('admin.components.link.info-default',[
                                            'href'          => setRoute('admin.crypto.assets.wallet.transactions', [$wallet_assets->id, $item->id]),
                                            'class'         => 'py-1 px-2',
                                            'permission'    => 'admin.crypto.assets.wallet.transactions'
                                        ])
                                    </div>
                                </div>
                                <div class="card-body">
                                    <ul>
                                        <li class="border px-3 py-2 rounded mb-3">
                                            <span class="fw-bold">
                                                {{ __("Mnemonic") }}:
                                            </span>

                                            {{ @$item->mnemonic }}
                                        </li>

                                        @isset($item->xpub)
                                            <li class="border px-3 py-2 rounded mb-3">
                                                <span class="fw-bold">
                                                    {{ __("Xpub") }}:
                                                </span>

                                                {{ $item->xpub }}
                                            </li>
                                        @endisset

                                        <li class="border px-3 py-2 rounded mb-3">
                                            <span class="fw-bold">
                                               {{ __("Private Key") }}:
                                            </span>

                                            {{ @$item->private_key }}
                                        </li>

                                        <li class="border px-3 py-2 rounded mb-3">
                                            <span class="fw-bold">
                                               {{ __("Public Address") }}:
                                            </span>

                                            {{ @$item->address }}
                                        </li>

                                        @if (is_numeric($item->balance))
                                            <li class="border px-3 py-2 rounded mb-3">
                                                <span class="fw-bold">
                                                    {{ __("Balance") }}:
                                                </span>

                                                {{ $item->balance }}
                                            </li>
                                        @endif

                                        @if (isset($item->balance->balance))
                                            <li class="border px-3 py-2 rounded mb-3">
                                                <span class="fw-bold">
                                                    {{ __("Balance") }}:
                                                </span>

                                                {{ $item->balance->balance }}
                                            </li>
                                        @endif

                                        @if (isset($item->balance->incoming))
                                            <li class="border px-3 py-2 rounded mb-3">
                                                <span class="fw-bold">
                                                    {{ __("Incoming Balance") }}:
                                                </span>

                                                {{ $item->balance->incoming }}
                                            </li>
                                        @endif

                                        @if (isset($item->balance->outgoing))
                                            <li class="border px-3 py-2 rounded mb-3">
                                                <span class="fw-bold">
                                                   {{ __("Outgoing Balance") }}:
                                                </span>

                                                {{ $item->balance->outgoing }}
                                            </li>
                                        @endif

                                        @if (isset($item->balance->incomingPending))
                                            <li class="border px-3 py-2 rounded mb-3">
                                                <span class="fw-bold">
                                                    {{ __("Incoming Pending Balance") }}:
                                                </span>

                                                {{ $item->balance->incomingPending }}
                                            </li>
                                        @endif

                                        @if (isset($item->balance->outgoingPending))
                                            <li class="border px-3 py-2 rounded mb-3">
                                                <span class="fw-bold">
                                                   {{ __("Outgoing Pending Balance") }}:
                                                </span>

                                                {{ $item->balance->outgoingPending }}
                                            </li>
                                        @endif

                                    </ul>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-warning col-12">
                            {{ __("This Coin is Under Maintenance Or Need to Generate Wallet. You Can Add Wallet Manually By Clicking Add Button.") }}
                        </div>
                    @endforelse

                </div>
            </div>
        </div>

    @endforeach


    {{-- Wallet Store Modal START --}}
    @if (admin_permission_by_name("admin.crypto.assets.wallet.store"))
        <div id="wallet-store" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Add New Wallet") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="modal-form" method="POST" action="{{ setRoute('admin.crypto.assets.wallet.store') }}">

                        @csrf
                        <input type="hidden" name="target" value="{{ old('target') }}">
                        <input type="hidden" name="gateway" value="{{ old('gateway') }}">

                        <div class="row mb-10-none">

                            <div class="col-12 form-group">
                                @include('admin.components.form.input',[
                                    'label'         => 'Mnemonic',
                                    'label_after'   => '<span> (Optional)</span>',
                                    'name'          => 'mnemonic',
                                    'value'         => old('mnemonic')
                                ])
                            </div>

                            <div class="col-12 form-group">
                                @include('admin.components.form.input',[
                                    'label'         => 'Extended Public Key (Xpub)',
                                    'label_after'   => '<span> (Optional)</span>',
                                    'name'          => 'xpub',
                                    'value'         => old('xpub')
                                ])
                            </div>

                            <div class="col-12 form-group">
                                @include('admin.components.form.input',[
                                    'label'         => 'Private Key',
                                    'label_after'   => '<span> (Optional)</span>',
                                    'name'          => 'private_key',
                                    'value'         => old('private_key')
                                ])
                            </div>

                            <div class="col-12 form-group">
                                @include('admin.components.form.input',[
                                    'label'         => 'Public Address',
                                    'label_after'   => '<span>*</span>',
                                    'name'          => 'public_address',
                                    'value'         => old('public_address')
                                ])
                            </div>

                            <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                                <button type="button" class="btn btn--danger modal-close">{{ __("Cancel") }}</button>
                                <button type="submit" class="btn btn--base">{{ __("Add") }}</button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    @endif
    {{-- Wallet Store Modal END --}}

    {{-- Wallet Address Delete START --}}
    @if (admin_permission_by_name("admin.crypto.assets.wallet.delete"))
        <div id="wallet-delete" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Delete Wallet") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="modal-form" method="POST" action="{{ setRoute('admin.crypto.assets.wallet.delete') }}">

                        @csrf
                        @method("DELETE")

                        <input type="hidden" name="target">
                        <input type="hidden" name="credentials_id">

                        {{ __("Are you sure to delete this wallet?") }}

                        <div class="row mb-10-none">
                            <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                                <button type="button" class="btn btn--base modal-close">{{ __("Cancel") }}</button>
                                <button type="submit" class="btn btn--danger">{{ __("Delete") }}</button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    @endif
    {{-- Wallet Address Delete END --}}


    {{-- Wallet Address Status Update START --}}
    @if (admin_permission_by_name("admin.crypto.assets.wallet.status.update"))
        <form class="modal-form d-none status-update-form" method="POST" action="{{ setRoute('admin.crypto.assets.wallet.status.update') }}">
            @csrf
            @method("PUT")
            <input type="hidden" name="target">
            <input type="hidden" name="credentials_id">
        </form>
    @endif
    {{-- Wallet Address Status Update END --}}

@endsection

@push('script')

    <script>
        $(document).ready(function(){
            openModalWhenError("wallet-store","#wallet-store");
        });

        $(".wallet-add-btn").click(function() {
            let coin = $(this).data('coin');
            let gateway = $(this).data('gateway');
            let storeModal = $("#wallet-store");
            storeModal.find("input[name=target]").val(coin);
            storeModal.find("input[name=gateway]").val(gateway);
        });

        $(".wallet-delete-btn").click(function() {
            let cryptoAssetId = $(this).data("id");
            let credentialsId = $(this).data("credential-id");

            let deleteModal = $("#wallet-delete");

            deleteModal.find('input[name=target]').val(cryptoAssetId);
            deleteModal.find('input[name=credentials_id]').val(credentialsId);

            openModalBySelector("#wallet-delete");
        });


        let timeOutTwo;
        $(".switcher .switch").bind("click", function() {

            let cryptoAssetId = $(this).parents(".switcher").data("target");
            let credentialsId = $(this).parents(".switcher").data("credentials");

            clearTimeout(timeOutTwo);
            timeOutTwo = setTimeout(() => {
                $(".status-update-form").find("input[name=target]").val(cryptoAssetId);
                $(".status-update-form").find("input[name=credentials_id]").val(credentialsId);
                $(".status-update-form").submit();
            }, 500);
        });

    </script>

@endpush
