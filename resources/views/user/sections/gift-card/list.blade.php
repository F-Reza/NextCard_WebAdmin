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
    <div class="dashboard-header-wrapper mt-20">
        <h4 class="title">{{ __($page_title) }}</h4>
    </div>
    <form action="{{ setRoute('user.gift.card.search') }}" method="GET">
        <div class="row mb-10-none">
            <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 mb-20">
                <div class="gift-card-form">
                    <div class="form-group">
                        <div class="input-group">
                            <select name="country" class="form--control select2-auto-tokenize" data-placeholder="{{ __('Select Country') }}" data-old="">
                                <option selected disabled>{{ __('Select Country') }}</option>
                                @foreach (get_all_countries() ?? [] as $country)
                                <option value="{{ $country->iso2 }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                            <button class="input-group-text" type="submit">
                                <i class="las la-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="row mb-20-none">
        @forelse($products ??[] as $key => $card)
            @php
                $image = $card['logoUrls'][0];
            @endphp
        <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-4 col-sm-6 mb-20">
            <div class="gift-card-item">
                <div class="gift-card-thumb">
                    <a href="{{ setRoute('user.gift.card.details',$card['productId']) }}"><img src="{{ $image??'' }}" alt="gift-cards"></a>
                </div>
                <div class="gift-card-content">
                    <h5 class="title"><a href=" {{ setRoute('user.gift.card.details',$card['productId']) }}">{{ $card['productName'] }}</a></h5>

                </div>
            </div>
        </div>

        @empty
        <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-4 col-sm-6 mb-20">
            <div class="gift-card-item">
                <div class="gift-card-content">
                    <h5 class="title">{{ __("No data found!") }}</h5>
                </div>
            </div>
        </div>
        @endforelse
        @if (count($products??[]) > 0)
            {{ $products->withQueryString()->setPath(url()->current())->links('pagination::bootstrap-5') }}
        @endif
       <div>

       </div>


    </div>
</div>
@endsection
@push('script')

@endpush
