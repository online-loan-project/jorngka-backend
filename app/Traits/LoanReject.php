<?php
namespace App\Traits;
use App\Constants\ConstLoanRepaymentStatus;
use App\Constants\ConstLoanStatus;
use App\Constants\ConstRequestLoanStatus;
use App\Models\CreditScore;
use App\Models\InterestRate;
use App\Models\Loan;
use App\Models\RequestLoan;
use App\Models\ScheduleRepayment;
use App\Models\User;

Trait LoanReject
{
    public function rejectLoan($requestLoanId, $reason = '')
    {
        // Find the loan request by ID
        $requestLoan = RequestLoan::find($requestLoanId);
        if (!$requestLoan) {
            return 'Loan request not found';
        }

        // Check if the loan is already approved
        if ($requestLoan->status == ConstRequestLoanStatus::APPROVED || $requestLoan->status == ConstRequestLoanStatus::REJECTED || $requestLoan->status == ConstRequestLoanStatus::PENDING) {
            return 'Loan request is already processed';
        }

        //update the request loan status
        $requestLoan->status = ConstRequestLoanStatus::REJECTED;
        $requestLoan->rejection_reason = $reason;
        $requestLoan->save();

        $chatId = User::query()->where('id', $requestLoan->user_id)->first();
        $this->sendTelegram(
            $chatId->telegram_chat_id,
            <<<MSG
❌ Loan Application Declined

We regret to inform you that your loan request #$requestLoanId has not been approved.

Reason: 
{$reason}

If you have any questions or would like to discuss this decision further, please contact our support team.

Thank you for considering our services.
MSG
        );
        return 'Loan rejected successfully';
    }

}
