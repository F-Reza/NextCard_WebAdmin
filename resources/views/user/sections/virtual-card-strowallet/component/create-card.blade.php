

<div class="col-lg-6 col-md-8 pb-30">
    <div class="deposit-form">
        <div class="form-title text-center">
            <h3 class="title">{{ __($page_title) }}</h3>
        </div>
        <div class="row justify-content-center">
            <form class="card-form row g-4" action="{{ route('user.strowallet.virtual.card.create') }}" method="POST">
             @csrf
            <div class="col-lg-12">
                <div class="form-group">
                    <label>{{ __("Card Holder's Name") }}<span>*</span></label>
                    <input type="text" class="form--control" placeholder="{{ __("Enter Card Holder's Name") }}" name="name_on_card" value="{{ old('name_on_card') }}" required>
                </div>
                <div class="form-group">
                    <label>{{__("Card Amount")}}<span>*</span></label>
                    <input type="number" class="form--control card_amount" id="card_amount" required placeholder="{{ __("Enter Amount") }}" name="card_amount" value="{{ old("card_amount") }}">
                    <div class="currency">
                        <p>{{ get_default_currency_code() }}</p>
                    </div>
                    <code class="d-block mt-10 text-end fw-bold balance-show">{{ __("Available Balance") }} {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                </div>
                <div class="note-area d-flex justify-content-between">
                    <div class="d-block limit-show">--</div>
                    <div class="d-block fees-show">--</div>
                </div>
                  <div class="button pt-3">
                    <button type="submit" class="btn btn--base w-100 btn-loading buyBtn">{{ __("Confirm") }}</button>
                  </div>
            </div>
            </form>
        </div>
    </div>
</div>
<div class="col-lg-6 col-md-4">
    <div class="deposit-form">
        <div class="form-title text-center pb-4">
            <h3 class="title">{{ __("preview") }}</h3>
        </div>
        <div class="preview-item d-flex justify-content-between">
            <div class="preview-content">
                <span>{{ __("Card Amount") }}</span>
            </div>
            <div class="preview-content">
                <p class="request-amount"> </p>
            </div>
        </div>
        <div class="preview-item d-flex justify-content-between">
            <div class="preview-content">
                <span>{{ __("Total Charge") }}</span>
            </div>
            <div class="preview-content">
                <p class="fees">--</p>
            </div>
        </div>


        <div class="preview-item d-flex justify-content-between">
            <div class="preview-content">
                <span>{{__("Total Payable Amount")}}</span>
            </div>
            <div class="preview-content">
                <p class="payable-total">--</p>
            </div>
        </div>

    </div>
</div>
