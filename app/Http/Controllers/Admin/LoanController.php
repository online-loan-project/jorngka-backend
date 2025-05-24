<?php

namespace App\Http\Controllers\Admin;

use App\Constants\ConstLoanRepaymentStatus;
use App\Constants\ConstLoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LoanResource;
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
        $active = $request->query('active');
        $active = $active == 'unpaid' ? ConstLoanStatus::UNPAID : ($active == 'paid' ? ConstLoanStatus::PAID : null);

        $loans = Loan::query()
            //status with active
            ->where('status', $active)
            ->with('user.borrower') //join with user
            ->with('scheduleRepayment')
            //join with user search phone
            ->whereHas('user', function ($query) use ($search) {
                $query->where('phone', 'like', "%$search%");
            })
            ->paginate($perPage);

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

        $response = [
            'data' => LoanResource::collection($loans),
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
        $loan = Loan::with('user')->find($id);
        if ($loan) {
            return $this->success($loan);
        }
        return $this->failed('Loan not found', 404);
    }
    // repayment list by loan id
    public function repaymentList($id, Request $request)
    {

        $loan = ScheduleRepayment::query()
            ->with('loan') //join with loan
            ->where('loan_id', $id)
            ->get();
        return $this->success($loan);
    }
    // repayment details by id
    public function repaymentDetails($id)
    {
        $repayment = ScheduleRepayment::with('loan')->find($id);
        if ($repayment) {
            return $this->success($repayment);
        }
        return $this->failed('Repayment not found', 404);
    }

    //repayment Mark as unpaid
    public function repaymentMarkAsUnpaid($id)
    {
       return $this->success($this->markedAsUnpaid($id));
    }
    //repayment Mark as paid
    public function repaymentMarkAsPaid($id)
    {
        return $this->success($this->markedAsPaid($id));
    }

}
