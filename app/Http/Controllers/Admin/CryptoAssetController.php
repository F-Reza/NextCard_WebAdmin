<?php

namespace App\Http\Controllers\Admin;

use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Admin\CryptoAsset;
use App\Models\Admin\CryptoTransaction;
use App\Models\Admin\PaymentGateway;
use App\Traits\PaymentGateway\Tatum;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CryptoAssetController extends Controller
{
    use Tatum;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function gatewayAssets(Request $request, $alias)
    {
        $gateway = PaymentGateway::where("alias",$alias)->with(['cryptoAssets','currencies'])->firstOrFail();
        $page_title = __("Crypto Assets");

        if(!$gateway->isCrypto() && !$this->isTatum($gateway)) return abort(404);

        $gateway_available_wallets = collect($this->tatumActiveChains())->pluck('coin')->toArray();
        $gateway_currencies = $gateway->currencies->pluck('currency_code')->toArray();

        $available_address_coins = $gateway->cryptoAssets->pluck("coin")->toArray();

        $wallet_not_available_coins = array_diff($gateway_currencies, $available_address_coins);
        $gateway_action_coin = array_intersect($wallet_not_available_coins, $gateway_available_wallets);

        return view('admin.sections.crypto-assets.gateway.index', compact(
            'page_title',
            'gateway',
            'wallet_not_available_coins',
        ));
    }

    public function generateWallet(Request $request, $alias) {
        $gateway = PaymentGateway::where('alias', $alias)->firstOrFail();
        if($this->isTatum($gateway)) {
            try{
                $this->getTatumAssets($gateway);
            }catch(Exception $e) {
                return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
            }
        }

        return back()->with(['success' => [__("Wallet generated successfully!")]]);
    }

    public function walletBalanceUpdate(Request $request, $crypto_asset_id, $wallet_credentials_id) {
        $crypto_asset = CryptoAsset::where('type', PaymentGatewayConst::ASSET_TYPE_WALLET)
                                    ->with('gateway')
                                    ->findOrFail($crypto_asset_id);

        $credentials_column = json_decode(json_encode($crypto_asset->credentials), true);
        $credentials = $credentials_column['credentials'];

        $wallet_info = array_filter($credentials ?? [], function($wallet) use ($wallet_credentials_id) {
            if($wallet['id'] == $wallet_credentials_id) return $wallet;
        });

        if(count($wallet_info) == 0) return back()->with(['error' => [__("Wallet Information Not Found!")]]);

        // Update wallet balance in local database
        try{
            $wallet_balance = $this->getTatumAddressBalance($crypto_asset, array_shift($wallet_info));

            // set update data in dataset
            $update_credentials = collect($credentials)->map(function($wallet) use ($wallet_credentials_id, $wallet_balance) {
                if($wallet['id'] == $wallet_credentials_id) {
                    $wallet['balance'] = json_decode(json_encode($wallet_balance), true);
                }
                return $wallet;
            })->toArray();

            $credentials_column['credentials'] = $update_credentials;

            $crypto_asset->update([
                'credentials'   => $credentials_column,
            ]);

        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return back()->with(['success' => [__("Balance Updated Successfully!")]]);

    }

    public function walletStore(Request $request) {

        $validator = Validator::make($request->all(),[
            'mnemonic'       => 'nullable|string',
            'xpub'           => 'nullable|string',
            'private_key'    => 'nullable|string',
            'public_address' => 'required|string',
            'target'         => 'required|string',
            'gateway'        => 'required|string',
        ]);
        if($validator->fails()) return back()->withErrors($validator)->withInput()->with('modal','wallet-store');

        $validated = $validator->validate();

        $crypto_asset = CryptoAsset::whereHas('gateway', function($query) use ($validated) {
            return $query->where('alias', $validated['gateway']);
        })->where('coin', $validated['target'])->first();

        $new_wallet_info = [
            "mnemonic" => $validated['mnemonic'],
            "xpub" => $validated['xpub'],
            "private_key" => $validated['private_key'],
            "address" => $validated['public_address'],
            "id" => uniqid() . time(),
            "balance" => [
                'balance'       => 0,
            ],
            "status" => false
        ];

        if(!$crypto_asset) {
            // Create new asset this credentials
            $gateway = PaymentGateway::gateway($validated['gateway'])->first();
            $coin_info = $this->tatumRegisteredChains($validated['target']);

            $new_wallet_info['status']   = true;

            DB::beginTransaction();
            try{

                $inserted_asset_id = DB::table('crypto_assets')->insertGetId([
                    'payment_gateway_id'        => $gateway->id,
                    'type'                      => PaymentGatewayConst::ASSET_TYPE_WALLET,
                    'chain'                     => $coin_info['chain'],
                    'coin'                      => $coin_info['coin'],
                    'credentials'               => json_encode([]),
                ]);

                $crypto_asset = CryptoAsset::find($inserted_asset_id);

                $subscription = $this->tatumSubscriptionForAccountTransaction($crypto_asset, $new_wallet_info['address']);
                $new_wallet_info['subscribe_id'] = $subscription->id;

                $credentials_column['credentials']  = [
                    $new_wallet_info,
                ];

                $crypto_asset->update([
                    'credentials'   => $credentials_column,
                ]);

                DB::commit();
            }catch(Exception $e) {
                DB::rollBack();
                return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
            }
        }else {
            // Update existing crypto asset
            $credentials_column = json_decode(json_encode($crypto_asset->credentials), true);
            $credentials = $credentials_column['credentials'];

            DB::beginTransaction();
            try{

                $subscription = $this->tatumSubscriptionForAccountTransaction($crypto_asset, $new_wallet_info['address']);
                $new_wallet_info['subscribe_id']    = $subscription->id;

                array_push($credentials, $new_wallet_info);
                $credentials_column['credentials'] = $credentials;

                DB::table($crypto_asset->getTable())->where('id', $crypto_asset->id)->update([
                    'credentials'       => json_encode($credentials_column),
                ]);

                DB::commit();
            }catch(Exception $e) {
                DB::rollBack();
                return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
            }
        }

        return back()->with(['success' => [__("Wallet Added Successfully!")]]);
    }

    public function walletDelete(Request $request) {

        $validated = Validator::make($request->all(),[
            'target'            => 'required|exists:crypto_assets,id',
            'credentials_id'    => 'required|string',
        ])->validate();

        $crypto_asset = CryptoAsset::where('type', PaymentGatewayConst::ASSET_TYPE_WALLET)->findOrFail($validated['target']);

        $credentials_column = json_decode(json_encode($crypto_asset->credentials), true);
        $credentials = $credentials_column['credentials'];

        $credentials_update = array_filter($credentials, function($wallet_info) use ($validated, $crypto_asset) {
            if($wallet_info['id'] == $validated['credentials_id']) {
                $this->tatumSubscriptionCancelForAccountTransaction($wallet_info['subscribe_id'] ?? "", $crypto_asset->gateway);
                return false;
            };
            return true;
        });

        $credentials_column['credentials'] = $credentials_update;

        try{
            $crypto_asset->update([
                'credentials'   => $credentials_column,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return back()->with(['success' => [__("Wallet Address Deleted Successfully!")]]);
    }

    public function walletStatusUpdate(Request $request) {
        $validated = Validator::make($request->all(),[
            'target'            => 'required|exists:crypto_assets,id',
            'credentials_id'    => 'required|string',
        ])->validate();

        $crypto_asset = CryptoAsset::where('type', PaymentGatewayConst::ASSET_TYPE_WALLET)->findOrFail($validated['target']);

        $credentials_column = json_decode(json_encode($crypto_asset->credentials), true);
        $credentials = $credentials_column['credentials'];

        $target_credentials = array_filter($credentials, function($wallet_info) use ($validated) {
            if($wallet_info['id'] == $validated['credentials_id']) return true;
            return false;
        });

        if(count($target_credentials) == 0) return back()->with(['error' => [__("Wallet Information not found!")]]);
        $target_credentials = array_shift($target_credentials);

        if($target_credentials['status'] == true) { // make false
            $update_credentials  = collect($credentials)->map(function($wallet_info) use ($validated) {
                if($wallet_info['id'] == $validated['credentials_id']) {
                    $wallet_info['status']  = false;
                }

                return $wallet_info;
            })->toArray();
        }else { // make true
            $update_credentials  = collect($credentials)->map(function($wallet_info) use ($validated) {
                $wallet_info['status']  = false;
                if($wallet_info['id'] == $validated['credentials_id']) {
                    $wallet_info['status']  = true;
                }

                return $wallet_info;
            })->toArray();
        }

        $credentials_column['credentials'] = $update_credentials;

        try{
            $crypto_asset->update([
                'credentials'   => $credentials_column,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return back()->with(['success' => [__("Wallet Status Changed Successfully!")]]);
    }

    public function walletTransactions(Request $request, $crypto_asset_id, $wallet_credentials_id) {
        $crypto_asset = CryptoAsset::findOrFail($crypto_asset_id);
        $gateway = $crypto_asset->gateway;

        $crypto_asset_address = collect($crypto_asset->credentials->credentials ?? [])->where('id', $wallet_credentials_id)->first()->address;

        $incoming_transactions = CryptoTransaction::where('receiver_address', $crypto_asset_address)->paginate(10);

        $page_title = "Crypto Wallet Transactions";
        return view('admin.sections.crypto-assets.wallet.transactions', compact(
            'page_title',
            'gateway',
            'incoming_transactions',
            'crypto_asset',
            'wallet_credentials_id'
        ));
    }

    public function walletTransactionSearch(Request $request, $crypto_asset_id, $wallet_credentials_id)
    {
        $crypto_asset = CryptoAsset::find($crypto_asset_id);
        if(!$crypto_asset) return Response::error([__("Crypto Asset Not Found!")]);

        $gateway = $crypto_asset->gateway;

        $crypto_asset_address = collect($crypto_asset->credentials->credentials ?? [])->where('id', $wallet_credentials_id)->first()->address ?? null;

        if(!$crypto_asset_address) return Response::error([__("Crypto Asset Not Found!")]);

        $validator = Validator::make($request->all(),[
            'text'  => 'required|string',
        ]);

        if($validator->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }

        $validated = $validator->validate();

        $incoming_transactions = CryptoTransaction::where('receiver_address',$crypto_asset_address)
                                                    ->where('txn_hash','like', '%' . $validated['text'] . '%')
                                                    ->limit(10)->paginate(10);

        return view('admin.components.search.crypto-transaction-search',compact(
            'incoming_transactions',
        ));
    }

}
