<div class="col-xl-8">
    <div class="dash-payment-item-wrapper">
        <div class="dash-payment-item active">
            <div class="dash-payment-title-area">
                <span class="dash-payment-badge">!</span>
                <h5 class="title">{{ __("Status of the customer you created") }} :  <span class="text-warning">{{ Str::upper($user->strowallet_customer->status) }}</span></h5>
            </div>
            <div class="dash-payment-body">
                <p class="fw-bold"><small>{{ __("Thank you for submitting your KYC information. Your details are currently under review. We will notify you once the verification is complete. Please note that the creation of your virtual card will proceed after your KYC is approved.") }}</small></p>
                <div class="mt-20 d-flex justify-content-center align-items-center">

                    <a href="{{ setRoute('user.strowallet.virtual.card.edit.customer') }}" class="btn--base">{{ __("Update Customer") }}</a>
                </div>

            </div>
        </div>
    </div>
</div>
