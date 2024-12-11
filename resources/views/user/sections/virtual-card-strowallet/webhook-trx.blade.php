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
            @forelse ($data ?? [] as $item)
                @if($item->event === global_const()::TERMINATED)
                    @include('user.sections.virtual-card-strowallet.component.webhook.terminated')
                @elseif($item->event === global_const()::CROSSBORDER)
                    @include('user.sections.virtual-card-strowallet.component.webhook.crossborder')
                @elseif($item->event === global_const()::DECLINED)
                    @include('user.sections.virtual-card-strowallet.component.webhook.declined')
                @endif
            @empty
                <div class="alert alert-primary text-center">
                    {{ __("No data found!") }}
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection

@push('script')

@endpush
