<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BonusLedger;
use App\Models\Checkin;
use App\Models\Commission;
use App\Models\Deposit;
use App\Models\Fund;
use App\Models\Improvment;
use App\Models\Mining;
use App\Models\Notice;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Task;
use App\Models\User;
use App\Models\UserLedger;
use App\Models\VipSlider;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function dashboard()
    {
        //First Level
        $first_level_users = User::where('ref_by', auth()->user()->ref_id)->get();

        //Get Second Level
        $second_level_users_ids = [];
        foreach ($first_level_users as $element) {
            $users = User::where('ref_by', $element->ref_id)->get();
            foreach ($users as $user){
                array_push($second_level_users_ids, $user->id);
            }
        }
        $second_level_users = User::whereIn('id', $second_level_users_ids)->get();

        //Get Third Level
        $third_level_users_ids = [];
        foreach ($second_level_users as $element) {
            $users = User::where('ref_by', $element->ref_id)->get();
            foreach ($users as $user){
                array_push($third_level_users_ids, $user->id);
            }
        }
        $third_level_users = User::whereIn('id', $third_level_users_ids)->get();

        //Get Team Size
        $team_size = $first_level_users->count() + $second_level_users->count() + $third_level_users->count();

        //Get level wise user ids
        $first_ids = $first_level_users->pluck('id'); //first
        $second_ids = $second_level_users->pluck('id'); //Second
        $third_ids = $third_level_users->pluck('id'); //Third

        $totalDeposit1 = Deposit::whereIn('user_id', array_merge($first_ids->toArray()))->where('status', 'approved')->sum('amount');
        $totalDeposit2 = Deposit::whereIn('user_id', array_merge($second_ids->toArray()))->where('status', 'approved')->sum('amount');
        $totalDeposit3 = Deposit::whereIn('user_id', array_merge($third_ids->toArray()))->where('status', 'approved')->sum('amount');
        $teamDeposit = $totalDeposit1+$totalDeposit2+$totalDeposit3;

        return view('app.main.index', compact('teamDeposit'));
    }


    public function return_pay_number($method){
        $method = DB::table('payment_methods')->where('type', $method)->inRandomOrder()->first();
        return response()->json(['status'=> true, 'data'=> $method]);
    }


    public function vip()
    {
        return view('app.main.vip');
    }
    public function fund()
    {
        return view('app.main.fund');
    }
    public function fund_record()
    {
        return view('app.main.fund_record');
    }

    public function message()
    {
        return view('app.main.message');
    }

    public function purchase_history()
    {
        return view('app.main.purchase_history');
    }

    public function history()
    {
        return view('app.main.history');
    }

    public function history_all()
    {
        return view('app.main.history_all');
    }

    public function ordered()
    {
        return view('app.main.ordered');
    }


    public function checkin_history()
    {
        return view('app.main.checkin_history');
    }

    public function checkin()
    {
        $user = \auth()->user();
        $lastCheckin = Checkin::where('user_id', $user->id)->orderByDesc('id')->first();

        if ($lastCheckin && now()->greaterThanOrEqualTo($lastCheckin->date)){
            $checkin = new Checkin();
            $checkin->user_id = $user->id;
            $checkin->date = now()->addHours(24);
            $checkin->amount = setting('checkin');
            $checkin->save();

            $userUpdate = User::where('id', $user->id)->first();
            $userUpdate->balance = $user->balance + setting('checkin');
            $userUpdate->save();

            $ledger = new UserLedger();
            $ledger->user_id = $user->id;
            $ledger->reason = 'checkin';
            $ledger->perticulation = 'checkin commission received';
            $ledger->amount = setting('checkin');
            $ledger->debit = setting('checkin');
            $ledger->status = 'approved';
            $ledger->date = date('d-m-Y H:i');
            $ledger->save();

            return redirect()->back()->with('success', 'Check-in balance received.');
        }else{
            return redirect()->back()->with('success', 'Already checked in.');
        }
    }

    public function vip_details($id)
    {
        $package = Package::where('id', $id)->first();
        return view('app.main.vip_details', compact('package'));
    }

    public function task()
    {
        $user = Auth::user();
        //First Level Users
        $first_level_users = User::where('ref_by', $user->ref_id)->get();
        $first_level_users_ids = [];
        foreach ($first_level_users as $user) {
            array_push($first_level_users_ids, $user->id);
        }

        //Second Level Users
        $second_level_users_ids = [];
        foreach ($first_level_users as $element) {
            $users = User::where('ref_by', $element->ref_id)->get();
            foreach ($users as $user) {
                array_push($second_level_users_ids, $user->id);
            }
        }
        $second_level_users = User::whereIn('id', $second_level_users_ids)->get();

        //Third Level Users
        $third_level_users_ids = [];
        foreach ($second_level_users as $element) {
            $users = User::where('ref_by', $element->ref_id)->get();
            foreach ($users as $user) {
                array_push($third_level_users_ids, $user->id);
            }
        }
        $third_level_users = User::whereIn('id', $third_level_users_ids)->get();
        $team_size = $first_level_users->count() + $second_level_users->count() + $third_level_users->count();

        //Get level wise user ids
        $first_ids = $first_level_users->pluck('id'); //first
        $second_ids = $second_level_users->pluck('id'); //Second
        $third_ids = $third_level_users->pluck('id'); //Third

        $lv1Recharge = Deposit::whereIn('user_id', $first_ids)->where('status', 'approved')->sum('amount');
        $lv2Recharge = Deposit::whereIn('user_id', $second_ids)->where('status', 'approved')->sum('amount');
        $lv3Recharge = Deposit::whereIn('user_id', $third_ids)->where('status', 'approved')->sum('amount');
        $lvTotalDeposit = $lv1Recharge + $lv2Recharge + $lv3Recharge;

        $lv1Withdraw = Withdrawal::whereIn('user_id', $first_ids)->where('status', 'approved')->sum('amount');
        $lv2Withdraw = Withdrawal::whereIn('user_id', $second_ids)->where('status', 'approved')->sum('amount');
        $lv3Withdraw = Withdrawal::whereIn('user_id', $third_ids)->where('status', 'approved')->sum('amount');
        $lvTotalWithdraw = $lv1Withdraw + $lv2Withdraw + $lv3Withdraw;

        $activeMembers1 = Deposit::whereIn('user_id', $first_ids)->where('status', 'approved')->groupBy('user_id')->count();
        $activeMembers2 = Deposit::whereIn('user_id', $second_ids)->where('status', 'approved')->groupBy('user_id')->count();
        $activeMembers3 = Deposit::whereIn('user_id', $third_ids)->where('status', 'approved')->groupBy('user_id')->count();


        $Lv1active = 0;
        $Lv2active = 0;
        $Lv3active = 0;

        foreach ($first_level_users as $uuss) {
            $purchase = Purchase::where('user_id', $uuss->id)->first();
            if ($purchase) {
                $Lv1active = $Lv1active + 1;
            }
        }
        foreach ($second_level_users as $uuss) {
            $purchase = Purchase::where('user_id', $uuss->id)->first();
            if ($purchase) {
                $Lv2active = $Lv2active + 1;
            }
        }
        foreach ($third_level_users as $uuss) {
            $purchase = Purchase::where('user_id', $uuss->id)->first();
            if ($purchase) {
                $Lv3active = $Lv3active + 1;
            }
        }

        $teamTotalActiveMembers = $Lv1active + $Lv2active + $Lv3active;


        return view('app.main.task', compact('team_size', 'teamTotalActiveMembers', 'lv1Recharge', 'lv2Recharge', 'lv3Recharge', 'lv1Withdraw', 'lv2Withdraw', 'lv3Withdraw', 'first_level_users', 'second_level_users', 'third_level_users'));
    }

    public function task_history()
    {
        return view('app.main.task_history');
    }

    public function reword_history()
    {
        return view('app.main.reword_history');
    }

    public function recharge_history()
    {
        return view('app.main.deposit_history');
    }

    public function commission()
    {
        return view('app.main.commission');
    }

    public function amount_history()
    {
        return view('app.main.amount_history');
    }

    public function package_details($id)
    {
        $package = Package::find($id);
        return view('app.main.package_details', compact('package'));
    }

    public function profile()
    {
        return view('app.main.profile');
    }

    public function team()
    {
        return view('app.main.team.index');
    }


    public function setting()
    {
        return view('app.main.mine.setting');
    }

    public function recharge()
    {
        return view('app.main.deposit.index');
    }

    public function recharge_amount($amount)
    {
        return view('app.main.deposit.recharge_confirm', compact('amount'));
    }

    public function payment_confirm($amount, $payment_method)
    {
        $payment_method = PaymentMethod::where('id', $payment_method)->inRandomOrder()->first();
        if (!$payment_method){
            return back()->with('success', 'Method not available.');
        }

        return view('app.main.deposit.payment-confirm', compact('amount', 'payment_method'));
    }

    public function depositSubmit(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'payment_method' => 'required',
            'transaction_id' => 'required',
            'photo' => 'required',
        ]);

        if ($validate->fails()) {
            return back()->withErrors($validate->errors());
        }

        $model = new Deposit();
        $model->user_id = Auth::id();
        $path = uploadImage(false ,$request, 'photo', 'upload/payment/', null, null ,$model->photo);

        $model->photo = $path ?? $model->photo;
        $model->method_name = $request->payment_method;
        $model->order_id = rand(00000,99999);
        $model->transaction_id = $request->transaction_id;
        $model->amount = $request->amount;
        $model->final_amount = $request->amount;
        $model->date = date('d-m-Y H:i:s');
        $model->status = 'pending';
        $model->save();
        return redirect('deposit')->with('success', 'Deposit Success');
    }

    public function update_profile(Request $request)
    {
        $user = User::find(Auth::id());
        $path = uploadImage(false, $request, 'photo', 'upload/profile/', 200, 200, $user->photo);
        $user->photo = $path ?? $user->photo;

        $user->update();
        return redirect()->route('my.profile')->with('success', 'Successful');
    }

    public function personal_details()
    {
        return view('app.main.update_personal_details');
    }

    public function card()
    {
        $methods = PaymentMethod::where('status', 'active')->where('id', '!=', 4)->get();

        return view('app.main.gateway_setup', compact('methods'));
    }

    public function setupGateway(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required',
            'gateway_method' => 'required',
            'gateway_number' => 'required',
        ]);

        if ($validate->fails()) {
            return redirect()->back()->with('success', 'Please enter correct information');
        }

        User::where('id', Auth::id())->update([
            'name' => $request->name,
            'gateway_method' => $request->gateway_method,
            'gateway_number' => $request->gateway_number,
        ]);
        return redirect()->back()->with('success', 'Your withdraw account updated.');
    }

    public function invite()
    {
        return view('app.main.invite');
    }

    public function level()
    {
        return view('app.main.level');
    }


    public function service()
    {
        return view('app.main.service');
    }

    public function add_bank()
    {
        return view('app.main.gateway_setup');
    }

    public function add_bank_create()
    {
        return view('app.main.add_bank_create');
    }

    public function setting_change_password(Request $request)
    {
        //Check current password
        $user = User::find(Auth::id());
        if (Hash::check($request->old_password, $user->password)) {
            if ($request->new_password == $request->confirm_password) {
                $user->password = Hash::make($request->new_password);
                $user->update();
                return redirect()->route('login_password')->with('success', 'Password changed');
            } else {
                return redirect()->route('login_password')->with('success', 'Password not match.');
            }
        } else {
            return redirect()->route('login_password')->with('success', 'Password not match');
        }
    }

    public function confirm_submit(Request $request)
    {
        $data = $request->all();
        $model = new Deposit();
        $model->user_id = $data['ui'];
        $model->method_name = $data['pm'];
        $model->method_number = '01000000000';
        $model->order_id = $data['oid'];
        $model->transaction_id = $data['tid'];
        $model->number = $data['aca'];
        $model->amount = $data['amount'];
        $model->final_amount = $data['amount'];
        $model->usdt = $data['amount'] / setting('rate');
        $model->date = Carbon::now();
        $model->status = 'pending';
        $model->save();
        return response()->json(['status' => true, 'data' => $data]);
    }

    public function download_apk()
    {
        $file = public_path('agri.apk');
        return response()->file($file, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="agri.apk"',
        ]);
    }

}







