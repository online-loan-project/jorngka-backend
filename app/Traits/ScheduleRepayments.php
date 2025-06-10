<?php

namespace App\Traits;

use App\Constants\ConstCreditTransaction;
use App\Constants\ConstLoanRepaymentStatus;
use App\Constants\ConstLoanStatus;
use App\Constants\ConstRequestLoanStatus;
use App\Models\CreditScore;
use App\Models\InterestRate;
use App\Models\Loan;
use App\Models\RequestLoan;
use App\Models\ScheduleRepayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait ScheduleRepayments
{
    use CreditActivity;

    public function MarkedAsPaid($scheduleRepaymentId, $request)
    {
        // Find the scheduled repayment
        $scheduleRepayment = ScheduleRepayment::find($scheduleRepaymentId);

        if (!$scheduleRepayment) {
            return response()->json(['error' => 'Schedule repayment not found'], 404);
        }

        // Check if already paid
        if ($scheduleRepayment->status == ConstLoanRepaymentStatus::PAID) {
            return response()->json(['error' => 'Schedule repayment is already paid'], 400);
        }

        // Get the associated loan and user
        $loan = $scheduleRepayment->loan;
        $userId = $loan->user_id;

        // Handle credit score updates
        $this->updateCreditScoreForRepayment($scheduleRepayment, $userId);

        // Update repayment status
        $newStatus = $scheduleRepayment->status == ConstLoanRepaymentStatus::LATE
            ? ConstLoanRepaymentStatus::PAID_LATE // Consider adding this status if you want to track late payments that were eventually paid
            : ConstLoanRepaymentStatus::PAID;

        $scheduleRepayment->update([
            'paid_date' => Carbon::now(),
            'status' => $newStatus,
        ]);

        // Check if all repayments are completed
        $this->checkAndUpdateLoanCompletion($scheduleRepayment->loan_id);

        // Record transaction
        $transaction = $this->recordTransaction(
            $request,
            $userId,
            $scheduleRepayment->emi_amount,
            ConstCreditTransaction::TYPE_LOAN_REPAYMENT,
            'Loan repayment for schedule ID: ' . $scheduleRepayment->id,
            'repayment_' . $scheduleRepayment->id
        );

        // Send notification
        $this->sendRepaymentNotification($scheduleRepayment, $transaction);

        Log::channel('repayment_log')->info(
            'Schedule repayment marked as paid',
            [
                'schedule_repayment_id' => $scheduleRepayment->id,
                'loan_id' => $loan->id,
                'user_id' => $userId,
                'status' => $newStatus,
                'paid_date' => $scheduleRepayment->paid_date,
                'transaction_code' => $transaction->transaction_code,
                'amount' => $transaction->amount,
            ]
        );
        return response()->json(['message' => 'Schedule repayment marked as paid successfully']);
    }

    protected function updateCreditScoreForRepayment($scheduleRepayment, $userId)
    {
        $creditScore = CreditScore::firstOrCreate(['user_id' => $userId], ['score' => 0]);

        if ($scheduleRepayment->status == ConstLoanRepaymentStatus::LATE) {
            // Deduct point for late payment
            $creditScore->score = max(0, $creditScore->score - 1);
        } else {
            // Add point for on-time payment
            $creditScore->score += 1;
        }

        $creditScore->save();
    }

    protected function checkAndUpdateLoanCompletion($loanId)
    {
        $pendingRepayments = ScheduleRepayment::where('loan_id', $loanId)
            ->whereNotIn('status', [ConstLoanRepaymentStatus::PAID, ConstLoanRepaymentStatus::PAID_LATE])
            ->exists();

        if (!$pendingRepayments) {
            Loan::where('id', $loanId)->update(['status' => ConstLoanStatus::PAID]);
        }
    }

    protected function sendRepaymentNotification($scheduleRepayment, $transaction)
    {
        $user = User::find($scheduleRepayment->loan->user_id);

        if ($user && $user->telegram_chat_id) {
            $statusLabel = $scheduleRepayment->status == ConstLoanRepaymentStatus::PAID_LATE
                ? 'Paid (Late)'
                : 'Paid (On Time)';

            $message = <<<MSG
💰 Loan Repayment Alert

▫️ Loan ID: {$scheduleRepayment->loan_id}
▫️ Code: {$transaction->transaction_code}
▫️ Amount: {$transaction->formatted_amount}
▫️ Type: {$transaction->type_label}
▫️ Description: {$transaction->description}
▫️ Date Paid: {$scheduleRepayment->paid_date->format('Y-m-d H:i:s')}
▫️ Status: {$statusLabel}

Thank you for your payment.
MSG;

            $this->sendTelegram($user->telegram_chat_id, $message);
        }
    }

    public function MarkedAsUnpaid($scheduleRepaymentId)
    {
        // Find the schedule repayment by ID
        $scheduleRepayment = ScheduleRepayment::find($scheduleRepaymentId);
        if (!$scheduleRepayment) {
            return 'Schedule repayment not found';
        }

        // Check if the schedule repayment is already unpaid
        if ($scheduleRepayment->status == ConstLoanRepaymentStatus::UNPAID) {
            return 'Schedule repayment is already unpaid';
        }

        //update the schedule repayment status
        $scheduleRepayment->status = ConstLoanRepaymentStatus::UNPAID;
        $scheduleRepayment->save();
        return 'Schedule repayment marked as unpaid successfully';
    }

    //late payment
    public function MarkedAsLate($scheduleRepaymentId)
    {
        // Find the schedule repayment by ID
        $scheduleRepayment = ScheduleRepayment::find($scheduleRepaymentId);
        if (!$scheduleRepayment) {
            return 'Schedule repayment not found';
        }

        // Check if the schedule repayment is already late
        if ($scheduleRepayment->status == ConstLoanRepaymentStatus::LATE) {
            return 'Schedule repayment is already late';
        }

        //update the schedule repayment status
        $scheduleRepayment->status = ConstLoanRepaymentStatus::LATE;
        $scheduleRepayment->save();
        return 'Schedule repayment marked as late successfully';
    }
}
