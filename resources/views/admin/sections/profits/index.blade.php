@extends('admin.layouts.master')

@push('css')

@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
<div class="table-area">
    <div class="table-wrapper">
        <div class="table-header">
            <h5 class="title">{{ $page_title }}</h5>
            @if(count($profits) > 0)
            <div class="table-btn-area">
                <h5 class="title">{{ __("Total Profits") }}: {{ getAmount(totalAdminProfits(),3) }} {{ get_default_currency_code() }}</h5>
            </div>
        @endif
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>{{ __("TRX ID") }}</th>
                        <th>{{ __("User") }}</th>
                        <th>{{ __("Transaction Type") }}</th>
                        <th>{{ __("Profit Amount") }}</th>
                        <th>{{ __("Time") }}</th>

                    </tr>
                </thead>
                <tbody>
                    @forelse ($profits  as $key => $item)
                        <tr>
                            <td>{{ @$item->transactions->trx_id }}</td>
                            <td>
                                <a href="{{ setRoute('admin.users.details',@$item->transactions->creator->username) }}">{{ @$item->transactions->creator->fullname }}</a>
                            </td>
                            <td>{{ @$item->transactions->type }}</td>
                            <td>{{ number_format(@$item->total_charge,2) }} {{ get_default_currency_code() }}</td>
                            <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>

                        </tr>

                    @empty
                         @include('admin.components.alerts.empty',['colspan' => 6])
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ get_paginate($profits) }}
    </div>
</div>
@endsection

@push('script')

@endpush
