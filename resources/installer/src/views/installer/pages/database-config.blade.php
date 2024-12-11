@extends('installer.layouts.app')

@section('content')
    <div class="doc-inner">
        <div class="doc-wrapper w-100">
            <h2 class="inner-title"><span>Configure</span> Database</h2>
            <h5 class="sub-title">Provide Database Information Correctly</h5>
            
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        <li>Please provide valid information. All field is required</li>
                    </ul>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form action="{{ route('project.install.database.config.submit') }}" class="doc-form mt-20" method="POST">
                @csrf
                <div class="form-group">
                    <label>Application name</label>
                    <input type="text" class="form--control" name="app_name" placeholder="Ex : {{ env('APP_NAME') }}">
                </div>
                <div class="form-group">
                    <label>Database Host <span class="text--warning">(If you have databse host then you can add otherwise skip this field. Default - localhost)</span></label>
                    <input type="text" class="form--control" name="host" placeholder="Ex : localhost" required value="localhost">
                </div>
                <div class="form-group">
                    <label>Database Port <span class="text--warning">(If your database port is different then you can change otherwise skip this field. Default - 3306)</span></label>
                    <input type="text" class="form--control" name="port" placeholder="Ex : 3306" required value="3306">
                </div>
                <div class="form-group">
                    <label>Database Name <span class="text--warning">(Don't include white spaces)</span> </label>
                    <input type="text" class="form--control" name="db_name" placeholder="Enter Name..." required>
                </div>
                <div class="form-group">
                    <label>Database Username <span class="text--warning">(Don't include white spaces)</span> </label>
                    <input type="text" class="form--control" name="db_user" placeholder="Enter Username..." required>
                </div>
                <div class="form-group">
                    <label>Database Password <span class="text--warning">(Don't include hash (#) or white spaces)</span> </label>
                    <input type="password" class="form--control" name="db_user_password" placeholder="Enter Password...">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn--base w-100 mt-20">Continue</button>
                </div>
            </form>
        </div>
    </div>
@endsection