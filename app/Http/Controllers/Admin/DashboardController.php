<?php

namespace App\Http\Controllers\Admin;

use App\Constants\ConstLoanStatus;
use App\Http\Controllers\Controller;
use App\Models\RequestLoan;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // dashboard
    public function index(Request $request)
    {
        // Get total users (excluding admin users where role = 1)
        $totalUser = \App\Models\User::query()
            ->where('role', '!=', 1)
            ->count();

        // Get total loan requests for current month
        $totalRequestLoan = \App\Models\RequestLoan::query()
            ->count();

        // Get total active loans (status = 0) for current month
        $totalActiveLoan = \App\Models\Loan::query()
            ->where('status', ConstLoanStatus::UNPAID) // Assuming 0 is active status
            ->count();

        // Get total revenue for current month (excluding unpaid loans)
        $totalRevenue = \App\Models\Loan::query()
            ->sum('revenue');

        // Get total loan repayments for current month
        $totalLoanRepayment = \App\Models\Loan::query()
            ->sum('loan_repayment');

        //total loan unpaid
        $totalUnpaid = \App\Models\Loan::query()
            ->where('status', ConstLoanStatus::UNPAID) // Assuming 0 is active status
            ->count();

        $totalPaid = \App\Models\Loan::query()
            ->where('status', ConstLoanStatus::PAID) // Changed to PAID status
            ->count();

        // Get monthly loan counts from Jan to Dec for current year
        $monthlyLoans = \App\Models\Loan::query()
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Get monthly request loan counts from Jan to Dec for current year
        $monthlyRequestLoans = \App\Models\RequestLoan::query()
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Initialize arrays with 0 for all months
        $loanCounts = array_fill(1, 12, 0);
        $requestLoanCounts = array_fill(1, 12, 0);

        // Fill the actual values for months that have data
        foreach ($monthlyLoans as $loan) {
            $loanCounts[$loan->month] = $loan->count;
        }

        foreach ($monthlyRequestLoans as $requestLoan) {
            $requestLoanCounts[$requestLoan->month] = $requestLoan->count;
        }

        // Convert to simple arrays (values only)
        $monthlyLoanData = array_values($loanCounts);
        $monthlyRequestLoanData = array_values($requestLoanCounts);


        $loanAmountStats = \App\Models\Loan::query()
            ->selectRaw('COUNT(*) as count,
        CASE
            WHEN (loan_repayment - revenue) < 100 THEN "<100"
            WHEN (loan_repayment - revenue) < 200 THEN "100-200"
            WHEN (loan_repayment - revenue) < 500 THEN "200-500"
            WHEN (loan_repayment - revenue) < 1000 THEN "500-1000"
            ELSE ">1000"
        END as amount_range')
            ->groupBy('amount_range')
            ->get()
            ->pluck('count', 'amount_range');

// Initialize all ranges with 0
        $ranges = [
            '<100' => 0,
            '100-200' => 0,
            '200-500' => 0,
            '500-1000' => 0,
            '>1000' => 0
        ];

// Merge with actual data
        foreach ($loanAmountStats as $range => $count) {
            $ranges[$range] = $count;
        }

        $number_loan_stat = array_values($ranges);

        $topBorrowers = \App\Models\User::query()
            ->with(['borrower' => function ($query) {
                $query->select(['id', 'user_id', 'first_name', 'last_name']);
            }])
            ->withCount(['loans' => function ($query) {
            }])
            ->withSum(['loans' => function ($query) {
            }], 'loan_repayment') // Assuming 'amount' is the loan amount column
            ->where('role', '!=', 1)
            ->orderByDesc('loans_count') // or orderByDesc('loans_sum_amount') if sorting by total amount
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->each(function ($user, $index) {
                $user->rank = $index + 1;
                $user->name = $user->borrower?->first_name . ' ' . $user->borrower?->last_name;
                $user->total_loans_amount = $user->loans_sum_amount ?? 0; // Assign the sum
            });

        $data = [
            //card
            'total_user' => $totalUser,
            'total_request_loan' => $totalRequestLoan,
            'total_active_loan' => $totalActiveLoan,
            'total_revenue' => $totalRevenue,
            'total_loan_repayment' => $totalLoanRepayment,

            //loan status distribution
            'total_unpaid' => $totalUnpaid,
            'total_paid' => $totalPaid,

            //monthly loan trends
            'monthly_loan' => $monthlyLoanData,
            'monthly_request_loan' => $monthlyRequestLoanData,

            'number_loan_stat' => $number_loan_stat,
            'top_borrowers' => $topBorrowers
        ];

        return $this->success($data, 'Dashboard data retrieved successfully.');
    }
}
