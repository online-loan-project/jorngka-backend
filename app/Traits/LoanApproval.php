<?php

namespace App\Traits;

use App\Constants\ConstCreditTransaction;
use App\Constants\ConstRequestLoanStatus;
use App\Models\CreditScore;
use App\Models\InterestRate;
use App\Models\Loan;
use App\Models\RequestLoan;
use App\Models\User;

trait LoanApproval
{
    use TelegramNotification, CreditActivity, EMI;

    public function approveLoan($requestLoanId, $request)
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

        //credit score check by user id
        $userCredit = CreditScore::where('user_id', $requestLoan->user_id)->first();
        if (!$userCredit) {
            return 'Credit information not found';
        }

        //interest rate check the latest one
        $interestRate = InterestRate::query()->latest()->first();
        if (!$interestRate) {
            return 'Interest rate not found';
        }
        $totalLoanRepayment = $this->calculateLoanRepayment($requestLoan->loan_amount, $interestRate->rate, $requestLoan->loan_duration);
        $loan = Loan::query()->create([
            'request_loan_id' => $requestLoan->id,
            'user_id' => $requestLoan->user_id,
            'credit_score_id' => $userCredit->id,
            'loan_duration' => $requestLoan->loan_duration,
            'loan_repayment' => $totalLoanRepayment,
            'revenue' => $totalLoanRepayment - $requestLoan->loan_amount,
            'interest_rate_id' => $interestRate->id,
        ]);
        if (!$loan) {
            return 'Loan creation failed';
        }
        //create the schedule repayment
        $this->createScheduleRepayment($loan->id);
        //update the request loan status
        //approved_amount
        $requestLoan->approved_amount = $requestLoan->loan_amount;
        $requestLoan->status = ConstRequestLoanStatus::APPROVED;
        $requestLoan->save();
        $chatId = User::query()->where('id', $requestLoan->user_id)->first();
        $this->sendTelegram(
            $chatId->telegram_chat_id,
            <<<MSG
✅ Loan Approval Notification

Your loan request has been approved!

Request ID: #$requestLoan->id
Loan ID: #$loan->id
Approved Amount: {$requestLoan->loan_amount} $
Loan Duration: {$requestLoan->loan_duration} months
Total Repayment Amount: $totalLoanRepayment $

Please check your account for details. The repayment schedule has been created and will be available for review.

Thank you for choosing our service.
MSG
        );

        //log the credit activity
        $this->recordTransaction(
            $request,
            $requestLoan->user_id,
            $requestLoan->loan_amount,
            ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT,
            'Loan approved for request ID: ' . $requestLoan->id,
            'loan_' . $loan->id
        );

        return 'Loan approved successfully';
    }
}
