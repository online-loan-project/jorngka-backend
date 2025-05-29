<?php

namespace App\Http\Controllers\Public;

use App\Constants\ConstLoanRepaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LoanResource;
use App\Models\Loan;
use App\Models\ScheduleRepayment;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    /**
     * Display a paginated list of loans
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate per_page parameter
            $perPage = $request->validate([
                'per_page' => 'sometimes|integer|min:1|max:100'
            ])['per_page'] ?? env('PAGINATION_PER_PAGE', 10);

            // Query loans with eager loading
            $loans = Loan::query()
                ->with(['user.borrower', 'scheduleRepayment'])
                ->paginate($perPage);

            // Check if any loans exist
            if ($loans->isEmpty()) {
                return $this->success(
                    [],
                    'Loan Index',
                    'No loans found',
                    Response::HTTP_OK
                );
            }

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
                    'total_repayment_amount' => round($totalAmount, 2),
                    'total_repayment_count' => $totalRepaymentCount,
                    'total_late_repayment_count' => $totalLateRepaymentCount,
                ]
            ];

            return $this->success(
                $response,
                'Loan Index',
                'Loan list retrieved successfully.',
                Response::HTTP_OK
            );

        } catch (ValidationException $e) {
            return $this->failed(
                ['errors' => $e->errors()],
                'Loan Index',
                'Invalid request parameters',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (QueryException $e) {
            Log::error("Loan Index Query Error: " . $e->getMessage());
            return $this->failed(
                null,
                'Loan Index',
                'Database error occurred',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (Exception $e) {
            Log::error("Loan Index Error: " . $e->getMessage());
            return $this->failed(
                null,
                'Loan Index',
                'An unexpected error occurred',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Display a specific loan
     *
     * @param int $id
     * @return JsonResponse|\Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Validate ID parameter
            if (!is_numeric($id) || $id <= 0) {
                return $this->failed(
                    null,
                    'Loan Show',
                    'Invalid loan ID',
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Query the loan with eager loading
            $loan = Loan::query()
                ->with(['user.borrower', 'scheduleRepayment'])
                ->find($id);

            if (!$loan) {
                return $this->failed(
                    null,
                    'Loan Show',
                    'Loan not found',
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->success(
                LoanResource::make($loan),
                'Loan Show',
                'Loan retrieved successfully.',
                Response::HTTP_OK
            );

        } catch (QueryException $e) {
            Log::error("Loan Show Query Error - ID {$id}: " . $e->getMessage());
            return $this->failed(
                null,
                'Loan Show',
                'Database error occurred',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (Exception $e) {
            Log::error("Loan Show Error - ID {$id}: " . $e->getMessage());
            return $this->failed(
                null,
                'Loan Show',
                'An unexpected error occurred',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}