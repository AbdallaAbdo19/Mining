<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Rebate;
use App\Models\Task;
use App\Models\TaskRequest;
use App\Models\User;
use App\Models\Purchase;
use App\Models\UserLedger;
use Illuminate\Support\Facades\Auth;

class MiningController extends Controller
{
    public function received_amount()
    {
        $user = Auth::user();
        if ($user->receive_able_amount > 0){
            $uu = User::where('id', $user->id)->first();
            $uu->balance = $user->balance + $user->receive_able_amount;

            $ledger = new UserLedger();
            $ledger->user_id = $user->id;
            $ledger->reason = 'daily_income';
            $ledger->perticulation = 'Package commission Received.';
            $ledger->amount = $user->receive_able_amount;
            $ledger->credit = $user->receive_able_amount;
            $ledger->status = 'approved';
            $ledger->date = date("Y-m-d H:i:s");
            $ledger->save();

            $uu->receive_able_amount = 0;
            $uu->save();

            return response()->json(['status'=> true, 'message'=> 'Commission Received.'.price($uu->receive_able_amount), 'balance'=> price($uu->receive_able_amount)]);
        }else{
            return response()->json(['status'=> true, 'message'=> 'Waiting for commission added', 'balance'=> price($user->receive_able_amount)]);
        }
    }

    public function received_reward(){
        $user = Auth::user();
        $refer = User::where('ref_by', auth()->user()->ref_id)->count();

        if ($user->reward > 0 && $refer >= setting('total_member_register_reword')){
            $user->balance = $user->balance + $user->reward;

            $ledger = new UserLedger();
            $ledger->user_id = $user->id;
            $ledger->reason = 'reword';
            $ledger->perticulation = 'Team reward received.';
            $ledger->amount = $user->reward;
            $ledger->credit = $user->reward;
            $ledger->status = 'approved';
            $ledger->date = date("Y-m-d H:i:s");
            $ledger->save();

            $user->reward = 0;
            $user->reward_received = 'true';
            $user->save();

            return redirect()->back()->with('success', 'Success');
        }else{
            return redirect()->back()->with('success', 'Not Success');
        }
    }


    public function apply_task_commission($task_id){
        $task = Task::where('id', $task_id)->first();

        if ($task){
            //check task submit
            $taskSubmitCheck = TaskRequest::where('user_id', \auth()->id())->where('task_id', $task_id)->where('status', 'pending')->count();
            if ($taskSubmitCheck > 0){
                return redirect('home')->with('success', 'Already Submitted.');
            }

            $referUser = User::where('ref_by', auth()->user()->ref_id)->get();
            if ($referUser->count() >= $task->team_size){
                $amount = Deposit::whereIn('user_id', $referUser->pluck('id')->toArray())->where('status', 'approved')->sum('amount');
                if ($amount >= $task->invest){
                    $model = new TaskRequest();
                    $model->task_id = $task->id;
                    $model->user_id = \auth()->id();
                    $model->team_invest = $task->invest;
                    $model->team_size = $task->team_size;
                    $model->save();

                    $ledger = new UserLedger();
                    $ledger->user_id = \auth()->id();
                    $ledger->reason = 'task';
                    $ledger->perticulation = 'Task request submitted.';
                    $ledger->amount = $task->bonus;
                    $ledger->debit = $task->bonus;
                    $ledger->status = 'approved';
                    $ledger->date = date('d-m-Y H:i');
                    $ledger->save();


                    return redirect('home')->with('success', 'Your application has been sent to the owner.');
                }else{
                    return redirect('home')->with('error', 'Need More ['.$task->team_size - $referUser->count(). '] Members');
                }
            }else{
                return redirect('home')->with('error', 'You need more team members.');
            }
        }
        return back();
    }
}








