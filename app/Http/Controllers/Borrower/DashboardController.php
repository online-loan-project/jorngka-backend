<?php

namespace App\Http\Controllers\Borrower;

use App\Constants\ConstLoanStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditScore;
use App\Models\Loan;
use App\Models\RequestLoan;
use App\Models\ScheduleRepayment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //credit score
    public function index(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->query('per_page', env('PAGINATION_PER_PAGE', 10));

        // Get paginated pending requests
        $requestLoan = RequestLoan::query()
            ->where('user_id', $user->id)
            ->paginate($perPage);

        // Get summary statistics
        $totalRequests = RequestLoan::query()
            ->where('user_id', $user->id)
            ->count();
        $totalRequestAmount = RequestLoan::query()
            ->where('user_id', $user->id)
            ->sum('loan_amount');

        $statusCounts = RequestLoan::selectRaw('status, count(*) as count')
            ->where('user_id', $user->id)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $creditScore = CreditScore::query()
            ->where('user_id', $user->id)
            ->first();

        $currentLoan = Loan::query()
            ->where('user_id', $user->id)
            ->where('status', ConstLoanStatus::UNPAID)
            ->first();

        $currentRepayment = ScheduleRepayment::query()
            ->where('loan_id', $currentLoan?->id)
            ->get();

        //Repayment Progress
        $totalRepayment = ScheduleRepayment::query()
            ->where('loan_id', $currentLoan?->id)
            ->count();
        $totalPaid = ScheduleRepayment::query()
            ->where('loan_id', $currentLoan?->id)
            ->where('status', '!=', ConstLoanStatus::UNPAID)
            ->count();

        $progress = 0;
        if ($totalRepayment > 0) {
            $progress = ($totalPaid / $totalRepayment) * 100;
        }


        // Prepare the response data
        $response = [
            'data' => $requestLoan->items(),
            'pagination' => [
                'total' => $requestLoan->total(),
                'per_page' => $requestLoan->perPage(),
                'current_page' => $requestLoan->currentPage(),
                'last_page' => $requestLoan->lastPage(),
            ],
            'summary' => [
                'repayment_progress' => round($progress, 2),
                'current_loan' => $currentLoan,
                'current_repayment' => $currentRepayment,
                'total_requests' => $totalRequests,
                'total_request_amount' => $totalRequestAmount,
                'credit_score' => $creditScore?->score,
                'status_counts' => [
                    'pending' => $statusCounts['pending'] ?? 0,
                    'eligible' => $statusCounts['eligible'] ?? 0,
                    'approved' => $statusCounts['approved'] ?? 0,
                    'rejected' => $statusCounts['rejected'] ?? 0,
                ]
            ]
        ];

        if ($requestLoan->count() > 0) {
            return $this->success($response);
        }

        return $this->success($response,'Request Loan not found', 404);
    }
}
