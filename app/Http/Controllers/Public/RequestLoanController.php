<?php

namespace App\Http\Controllers\Public;

use App\Constants\ConstRequestLoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Borrower\RequestLoanRequest;
use App\Http\Resources\Admin\RequestLoanResource;
use App\Models\IncomeInformation;
use App\Models\NidInformation;
use App\Models\RequestLoan;
use App\Models\User;
use App\Traits\LoanEligibility;
use Illuminate\Http\Request;

class RequestLoanController extends Controller
{
    use LoanEligibility;
    //index
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', env('PAGINATION_PER_PAGE', 10));
        $search = $request->query('search');

        $requestLoan = RequestLoan::query()
            ->with(['user.borrower', 'nidInformation', 'incomeInformation'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('phone', 'like', '%' . $search . '%');
                });
            })
            ->paginate($perPage);

        // Summary statistics (consider filtering them by the same status criteria)
        $totalRequests = RequestLoan::query()
            ->count();

        $totalRequestAmount = RequestLoan::query()
            ->sum('loan_amount');

        $statusCounts = RequestLoan::selectRaw('status, count(*) as count')
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

    public function show($id)
    {
        $requestLoan = RequestLoan::query()
            ->with(['user.borrower', 'nidInformation', 'incomeInformation'])
            ->find($id);

        if (!$requestLoan) {
            return $this->failed(null, 'Request loan','Request loan not found', 404);
        }

        return $this->success(RequestLoanResource::make($requestLoan), 'Request Loan', 'Request loan details successfully');
    }

    public function store(RequestLoanRequest $request)
    {
        $user = User::query()->find(2);

        if(!$user){
            return $this->failed('User not found', 404);
        }
        //query request loan status pending and eligibility for current user
        $requestLoan = RequestLoan::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ConstRequestLoanStatus::ELIGIBLE])
            ->first();
        if($requestLoan){
            return $this->failed(null,'Already have loan', 'You already have a eligible request', 400);
        }

        $bank_statement = $request->file('bank_statement');
        $bank_statement_path = null;
        if ($bank_statement) {
            $bank_statement_path = $this->uploadImage($bank_statement, 'bank_statement', 'public');
        }

        $requestLoan = RequestLoan::query()->create([
            'loan_amount' => $request->loan_amount,
            'loan_duration' => $request->loan_duration,
            'loan_type' => $request->loan_type,
            'status' => 'pending',
            'user_id' => $user->id
        ]);

        $incomeInformation = IncomeInformation::query()->create([
            'employee_type' => $request->employee_type,
            'position' => $request->position,
            'income' => $request->income,
            'bank_statement' => $bank_statement_path,
            'request_loan_id' => $requestLoan->id
        ]);

        // Check loan eligibility
        $eligibility = $this->checkLoanEligibility($user->id, $requestLoan->id);
        logger('Public Loan Eligibility:', [
            'user_id' => $user->id,
            'eligibility' => $eligibility,
        ]);

        $data = [
            'request_loan' => $requestLoan,
            'income_information' => $incomeInformation,
            'eligibility' => $eligibility,
        ];

        return $this->success($data, 'Request Loan', 'Request Loan created successfully');
    }
}
