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
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>{{ __("Notification Type") }}</th>
                        <th>{{ __("title") }}</th>
                        <th>{{ __("Details") }}</th>
                        <th>{{ __("Time") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (get_admin_notifications() ?? []  as $key => $item)
                        <tr>
                            <td>{{ $item->message->type??"" }}</td>
                            <td>{{ $item->message->title??"" }}</td>
                            <td>{{ $item->message->message??"" }}</td>
                            <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>

                        </tr>
                    @empty
                         @include('admin.components.alerts.empty',['colspan' => 4])
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ get_paginate(get_admin_notifications()) }}
    </div>
</div>
@endsection

@push('script')

@endpush
