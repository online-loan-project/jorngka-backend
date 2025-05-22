<?php

namespace App\Http\Controllers\Admin;

use App\Constants\ConstRequestLoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectReasonRequest;
use App\Http\Requests\Borrower\RequestLoanRequest;
use App\Http\Resources\Admin\RequestLoanResource;
use App\Models\RequestLoan;
use App\Traits\LoanApproval;
use App\Traits\LoanReject;
use Illuminate\Http\Request;

class RequestLoanController extends Controller
{
    Use LoanApproval;
    Use LoanReject;

    // Request loan list
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', env('PAGINATION_PER_PAGE', 10));
        $search = $request->query('search');
        $status = $request->query('status');
        $active = $request->query('active', false); // Default to false if not provided

        // Handle status filtering
        $statusesToFilter = [];

        if ($active) {
            // If active is true, show only pending and eligible
            $statusesToFilter = [
                ConstRequestLoanStatus::NOT_ELIGIBLE,
                ConstRequestLoanStatus::ELIGIBLE
            ];
        } elseif ($status) {
            // If specific status is provided, use that
            $statusesToFilter = is_array($status) ? $status : [$status];
        } else {
            // Default case (when neither active nor status is provided)
            // You might want to show all statuses or some other default
            // Currently showing approved and rejected as per original code
            $statusesToFilter = [
                ConstRequestLoanStatus::PENDING,
                ConstRequestLoanStatus::APPROVED,
                ConstRequestLoanStatus::REJECTED,
            ];
        }

        $requestLoan = RequestLoan::query()
            ->when(!empty($statusesToFilter), function ($query) use ($statusesToFilter) {
                $query->whereIn('status', $statusesToFilter);
            })
            ->with(['user.borrower', 'nidInformation', 'incomeInformation'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('phone', 'like', '%' . $search . '%');
                });
            })
            ->paginate($perPage);

        // Summary statistics (consider filtering them by the same status criteria)
        $totalRequests = RequestLoan::when(!empty($statusesToFilter), function ($query) use ($statusesToFilter) {
            $query->whereIn('status', $statusesToFilter);
        })
            ->count();

        $totalRequestAmount = RequestLoan::when(!empty($statusesToFilter), function ($query) use ($statusesToFilter) {
            $query->whereIn('status', $statusesToFilter);
        })
            ->sum('loan_amount');

        $statusCounts = RequestLoan::selectRaw('status, count(*) as count')
            ->when(!empty($statusesToFilter), function ($query) use ($statusesToFilter) {
                $query->whereIn('status', $statusesToFilter);
            })
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $response = [
            'data' => RequestLoanResource::collection($requestLoan),
            'pagination' => [
                'total' => $requestLoan->total(),
                'per_page' => $requestLoan->perPage(),
                'current_page' => $requestLoan->currentPage(),
                'last_page' => $requestLoan->lastPage(),
            ],
            'summary' => [
                'total_requests' => $totalRequests,
                'total_request_amount' => $totalRequestAmount,
                'status_counts' => [
                    'pending' => $statusCounts[ConstRequestLoanStatus::PENDING] ?? 0,
                    'eligible' => $statusCounts[ConstRequestLoanStatus::ELIGIBLE] ?? 0,
                    'approved' => $statusCounts[ConstRequestLoanStatus::APPROVED] ?? 0,
                    'rejected' => $statusCounts[ConstRequestLoanStatus::REJECTED] ?? 0,
                ]
            ]
        ];

        return $this->success($response);
    }

    // Request loan details by id
    public function show($id)
    {
        $requestLoan = RequestLoan::find($id);
        if ($requestLoan) {
            return $this->success($requestLoan);
        }
        return $this->failed('Request loan not found', 404);
    }
    // Request loan eligibility list
    public function eligibilityList(Request $request)
    {
        $perPage = $request->query('per_page', env('PAGINATION_PER_PAGE', 10));
        $search = $request->query('search');

        $requestLoan = RequestLoan::query()
            ->where('id', 'like', "%$search%")
            ->where('status', ConstRequestLoanStatus::ELIGIBLE)
            ->paginate($perPage);
        return $this->success($requestLoan);
    }
    // Request loan approve
    public function approve($id)
    {
            return $this->success($this->approveLoan($id));
    }
    // Request loan reject
    public function reject($id, RejectReasonRequest $request)
    {
        $reason = $request->input('reason');
        return $this->success($this->rejectLoan($id, $reason));
    }
}
