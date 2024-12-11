<?php
namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use App\Models\Admin\Currency;
use App\Models\GiftCard;
use App\Models\Transaction;
use App\Models\UserSupportTicket;
use App\Models\VirtualCardApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\AdminNotifications\AuthNotifications;

class DashboardController extends Controller
{
    use AuthNotifications;
    protected $api;
    public function __construct()
    {
        $cardApi = VirtualCardApi::first();
        $this->api =  $cardApi;
    }
    public function index()
    {
        $page_title = __("Dashboard");
        $user = auth()->user();
        $baseCurrency = Currency::default();
        $transactions = Transaction::auth()->latest()->take(5)->get();
        $totalAddMoney = Transaction::auth()->addMoney()->where('status',1)->sum('request_amount');
        $virtualCards = activeCardData()['active_cards'];
        $totalGiftCards = GiftCard::auth()->count();

        $active_tickets = UserSupportTicket::authTickets()->active()->count();

        return view('user.dashboard',compact(
            "page_title",
            "baseCurrency",
            "user",
            "transactions",
            'totalAddMoney',
            'virtualCards',
            'active_tickets',
            'totalGiftCards'
        ));
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('index')->with(['success' => ['Logout Successfully!']]);
    }
    public function deleteAccount(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required',
        ]);
        $validated = $validator->validate();
        $user = auth()->user();
        $user->status = false;
        $user->email_verified = false;
        $user->sms_verified = false;
        $user->kyc_verified = false;
        $user->deleted_at = now();
        $user->save();
        try{
            //admin notification
            $this->deleteUserNotificationToAdmin($user,"USER",'web');
            Auth::logout();
            return redirect()->route('index')->with(['success' => [__('User deleted successfully')]]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something Went Wrong! Please Try Again")]]);
        }


    }
}
