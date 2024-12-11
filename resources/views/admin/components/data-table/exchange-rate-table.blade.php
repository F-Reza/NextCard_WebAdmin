<table class="custom-table excahnge-rate-search-table">
    <thead>
        <tr>
            <th>{{ __('Country') }}</th>
            <th>{{ __("Currency Name") }}</th>
            <th>{{ __("Currency Code") }}</th>
            <th>{{ __("Currency Symbol") }}l</th>
            <th>{{ __("Rate") }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($exchange_rates ?? [] as $key => $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td>{{ $item->currency_name }}</td>
                <td>{{ $item->currency_code }}</td>
                <td>{{ $item->currency_symbol }}</td>
                <td>
                    <div class="input-group">
                        <span class="input-group-text">1 {{ get_default_currency_code() }} =</span>
                        <input type="number" class="form--control" value="{{ $item->rate > 0 ? get_amount($item->rate, null, 4) : '' }}" name="rate[]" placeholder="0.00">
                        <span class="input-group-text">{{ $item->currency_code }}</span>
                        <input type="hidden" name="id[]" value="{{ $item->id }}">
                    </div>
                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 7])
        @endforelse
    </tbody>
</table>
