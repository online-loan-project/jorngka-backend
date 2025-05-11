<?php

namespace App\Http\Controllers\Borrower;

use App\Constants\ConstLoanRepaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\ScheduleRepayment;
use App\Traits\ScheduleRepayments;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    use ScheduleRepayments;
    // Loan list
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', env('PAGINATION_PER_PAGE', 10));
        $search = $request->query('search');
        $status = $request->query('status', '0');
        $userData = auth()->user();

        $loans = Loan::query()
            ->with('user')
            ->where('user_id', $userData->id)
            ->whereHas('user', function ($query) use ($search) {
                $query->where('phone', 'like', "%$search%");
            })
            ->where('status', $status)
            ->paginate($perPage);

        // Get all loan IDs for the summary calculations
        $loanIds = $loans->pluck('id')->toArray();

        $totalLoan = $loans->count();
        $totalAmount = $loans->sum('loan_repayment');
        $totalRepaymentCount = ScheduleRepayment::query()
            ->whereIn('loan_id', $loanIds)
            ->count();

        $totalLateRepaymentCount = ScheduleRepayment::query()
            ->whereIn('loan_id', $loanIds)
            ->where('status', '=', ConstLoanRepaymentStatus::LATE)
            ->count();

        $repayment = ScheduleRepayment::query()
            ->whereIn('loan_id', $loanIds)
            ->get();

        $response = [
            'data' => $loans->items(),
            'repayment' => $repayment,
            'pagination' => [
                'total' => $loans->total(),
                'per_page' => $loans->perPage(),
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
            ],
            'summary' => [
                'total_loan' => $totalLoan,
                'total_repayment_amount' => $totalAmount,
                'total_repayment_count' => $totalRepaymentCount,
                'total_late_repayment_count' => $totalLateRepaymentCount,
            ]
        ];

        return $this->success($response);
    }
    // Loan details by id
    public function show($id)
    {
        $userData = auth()->user();
        $loan = Loan::with('user')->where('user_id', $userData->id)->find($id);
        if ($loan) {
            return $this->success($loan);
        }
        return $this->failed('Loan not found', 404);
    }
    // repayment list by loan id
    public function repaymentList($id, Request $request)
    {

        $userData = auth()->user();
        $loan = ScheduleRepayment::query()
            ->with('loan') //join with loan
            ->where('loan_id', $id)
            ->where('user_id', $userData->id)
            ->get();
        return $this->success($loan);
    }

}
