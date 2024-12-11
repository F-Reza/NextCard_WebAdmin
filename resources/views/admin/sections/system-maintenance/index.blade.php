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
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{$page_title}}</h6>
        </div>
        <div class="card-body">
            <form class="card-form" action="{{ setRoute('admin.system.maintenance.update') }}" method="POST">
                @csrf
                @method("PUT")
                <div class="row mb-10-none">
                    <div class="col-xl-8 col-lg-8 col-md8 col-sm-8 form-group">
                        <label>{{ __("title") }}*</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form--control" name="title" value="{{ @$data->title }}">
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-4 form-group">
                        @include('admin.components.form.switcher',[
                            'label'     => __("Status")."*",
                            'name'      => "status",
                            'options'   => [__("Enable") => 1, __("Disabled") => 0],
                            'value'     => old('status',@$data->status),
                        ])
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.form.input-text-rich',[
                            'label'     => __("Description")."*",
                            'name'      => "details",
                            'value'     => __($data->details??""),
                        ])
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.button.form-btn',[
                            'class'         => "w-100 btn-loading",
                            'text'          => __("Submit"),
                            'permission'    => "admin.cookie.update"
                        ])
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
