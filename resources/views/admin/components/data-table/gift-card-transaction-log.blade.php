<table class="custom-table transaction-search-table">
    <thead>
        <tr>
            <th>{{ __("TRX ID") }}</th>
            <th>{{ __("User") }}</th>
            <th>{{ __("Card Name") }}</th>
            <th>{{ __("Card Images") }}</th>
            <th>{{ __("receiver Email") }}</th>
            <th>{{ __("Receiver Phone") }}</th>
            <th>{{ __("Payable Unit Price") }}</th>
            <th>{{ __("Total Charge") }}</th>
            <th>{{ __("Payable Amount") }}</th>
            <th>{{ __("Time")}}</th>
            <th>{{ __("Status") }}</th>
            <th>{{ __("Action")}}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transactions  as $key => $item)
        <tr>
            <td>{{ $item->trx_id}}</td>
            <td>
                <a href="{{ setRoute('admin.users.details',$item->user->username) }}"><span class="text-info">{{ $item->user->fullname }}</span></a>
            </td>
            <td>{{ $item->details->card_info->card_name}}</td>
            <td><img style="max-width: 50px" src="{{ $item->details->card_info->card_image}} " alt=""></td>
            <td>{{ $item->details->card_info->recipient_email}}</td>
            <td>+{{ $item->details->card_info->recipient_phone}}</td>
            <td>{{ get_amount($item->details->charge_info->sender_unit_price,$item->details->charge_info->wallet_currency)}}</td>
            <td>{{ get_amount($item->charge->total_charge,$item->details->charge_info->wallet_currency)}}</td>
            <td>{{ get_amount($item->payable,$item->details->charge_info->wallet_currency)}}</td>
            <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>
            <td><span class="{{ $item->stringStatus->class }}">{{ __($item->stringStatus->value) }} </span></td>
            <td>
                @include('admin.components.link.info-default',[
                    'href'          => setRoute('admin.gift.card.details', $item->id),
                    'permission'    => "admin.gift.card.details",
                ])
            </td>
        </tr>
        @empty

        @include('admin.components.alerts.empty',['colspan' => 15])
        @endforelse
    </tbody>
</table>
