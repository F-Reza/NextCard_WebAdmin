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
    ], 'active' => __("Transfer Money Log")])
@endsection

@section('content')
<div class="table-area">
    <div class="table-wrapper">
        <div class="table-header">
            <h5 class="title">{{ $page_title }}</h5>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>{{ __("TRX ID") }}</th>
                        <th>{{ __("Sender") }}</th>
                        <th>{{ __("Receiver") }}</th>
                        <th>{{ __("Sender Amount") }}</th>
                        <th>{{ __("Receiver Amount") }}</th>
                        <th>{{ __("Total Charge") }}</th>
                        <th>{{ __("Payable") }}</th>
                        <th>{{ __(("Status")) }}</th>
                        <th>{{ __("Time") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions ?? []  as $key => $item)
                        <tr>
                            <td>{{ $item->trx_id }}</td>
                            <td>
                                <a href="{{ setRoute('admin.users.details',$item->creator->username??"") }}">{{ $item->creator->email??"" }}</a>
                            </td>
                            <td>
                                <a href="{{ setRoute('admin.users.details',$item->details->receiver->username) }}">{{ $item->details->receiver->email }}</a>
                            </td>
                            <td>{{ get_amount($item->request_amount,get_default_currency_code(),2) }}</td>
                            <td>{{ get_amount($item->request_amount,get_default_currency_code(),2) }}</td>
                            <td>{{ get_amount($item->charge->total_charge,get_default_currency_code(),2) }}</td>
                            <td>{{ get_amount($item->payable,get_default_currency_code(),2) }}</td>
                            <td>
                                <span class="{{ $item->stringStatus->class }}">{{ __($item->stringStatus->value) }}</span>
                            </td>
                            <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>

                        </tr>
                    @empty
                         @include('admin.components.alerts.empty',['colspan' => 11])
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ get_paginate($transactions) }}
    </div>
</div>
@endsection

@push('script')

@endpush
