@extends('admin.layouts.master')

@push('css')
    <style>
        .switch-toggles{
            margin-left: auto;
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
        ]
    ], 'active' => __("Setup Pages")])
@endsection

@section('content')


    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __($page_title) }}</h6>
        </div>
        <div class="card-body">
                <div class="custom-card mb-10">
                    <div class="card-body">
                        <div class="row justify-content-center">
                            <div class="col-xl-12 col-lg-12 mb-10">
                                <div class="table-area custom-table-area">
                                    <div class="table-wrapper">
                                        <div class="table-responsive">
                                            <table class="custom-table">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __("Module Name") }}</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($data ?? [] as $item)

                                                        <tr>
                                                            @if($item->user_type == "USER")
                                                            <td>{{ ucwords(str_replace('-',' ',@$item->slug ))}}</td>
                                                            <td>

                                                                    @include('admin.components.form.switcher',[
                                                                        'name'          => 'status',
                                                                        'value'         => $item->status,
                                                                        'options'       => [__("Enable") => 1,__("Disable") => 0],
                                                                        'onload'        => true,
                                                                        'data_target'   => $item->slug,
                                                                        'permission'    => "admin.module.setting.status.update",
                                                                    ])

                                                            </td>
                                                            @endif
                                                        </tr>

                                                    @empty
                                                        @include('admin.components.alerts.empty',['colspan' => 2])
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

        </div>
    </div>
@endsection

@push('script')
    <script>
        switcherAjax("{{ setRoute('admin.module.setting.status.update') }}");
    </script>
@endpush
