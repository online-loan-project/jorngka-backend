<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreditScoreRequest;
use App\Http\Resources\Admin\CreditScoreResource;
use App\Models\CreditScore;
use App\Models\User;
use Illuminate\Http\Request;

class CreditScoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // show list user with credit score
        $user = CreditScore::query()
            ->with(['user.borrower'])
            ->whereHas('user', function($query) {
                $query->where('role', '!=', 1);
            })
            ->get();

        if ($user->isEmpty()) {
            return $this->failed(null,'No Users', 'Users not found', 404);
        }

        if(!$user){
            return $this->failed(null,'No Users', 'Users not found', 404);
        }

        //$total_new_user is user that have credit score = 50
        $total_new_user = CreditScore::query()
            ->where('score', 50)
            ->whereHas('user', function($query) {
                $query->where('role', '!=', 1);
            })
            ->count();

        $data = [
            'data' => CreditScoreResource::collection($user),
            'summary' => [
                'total_user' => $user->count(),
                'total_new_user' => $total_new_user,
            ],
        ];

        return $this->success($data, 'Credit score list', 'Credit score list');
    }
    //reset credit score by user id
    public function resetCreditScore($id)
    {
        $creditScore = CreditScore::query()->where('user_id', $id)->first();
        if ($creditScore) {
            $creditScore->score = 50;
            $creditScore->save(); //save the updated credit score
            return $this->success($creditScore);
        }
        return $this->failed(null, 'Credit score not found', 'Credit score not found', 404);
    }
    //update credit score by user id
    public function updateCreditScore(CreditScoreRequest $request, $id)
    {
        $creditScore = CreditScore::query()->where('user_id', $id)->first();
        if ($creditScore) {
            $creditScore->score = $request->score;
            $creditScore->status = $request->status;
            $creditScore->save(); //save the updated credit score
            return $this->success($creditScore);
        }
        return $this->failed(null, 'Credit score not found', 'Credit score not found', 404);
    }

}
