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
use Carbon\Carbon;

Trait ScheduleRepayments
{
    public function MarkedAsPaid($scheduleRepaymentId)
    {
        // Find the schedule repayment by ID
        $scheduleRepayment = ScheduleRepayment::find($scheduleRepaymentId);
        if (!$scheduleRepayment) {
            return 'Schedule repayment not found';
        }

        // Check if the schedule repayment is already paid
        if ($scheduleRepayment->status == ConstLoanRepaymentStatus::PAID) {
            return 'Schedule repayment is already paid';
        }

        // Update credit score if status is not LATE
        if ($scheduleRepayment->status != ConstLoanRepaymentStatus::LATE) {
            $creditScore = CreditScore::where('user_id', $scheduleRepayment->loan->user_id)->first();

            if ($creditScore) {
                if ($creditScore->score === 0) {
                    return 'Credit score is already 0';
                }
                $creditScore->score += 1; // Add 1 point for paid repayment
                $creditScore->save();
            }
        }

        // Update the schedule repayment status
        $scheduleRepayment->update([
            'paid_date' => Carbon::now(),
            'status' => ConstLoanRepaymentStatus::PAID,
        ]);

        // Check if this was the last pending repayment
        $pendingRepaymentsCount = ScheduleRepayment::where('loan_id', $scheduleRepayment->loan_id)
            ->where('status', '!=', [ConstLoanRepaymentStatus::PAID, ConstLoanRepaymentStatus::LATE])
            ->count();

        if ($pendingRepaymentsCount === 0) {
            $loan = Loan::find($scheduleRepayment->loan_id);
            if ($loan) {
                $loan->status = ConstLoanStatus::PAID;
                $loan->save();
            }
        }

        return 'Schedule repayment marked as paid successfully';
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
        $creditScore = CreditScore::where('user_id', $scheduleRepayment->loan->user_id)->first();
        if($creditScore == 0 ) {
            return 'Credit score is already 0';
        }
        if ($creditScore) {
            $creditScore->score -= 1; // Deduct 1 points for late  per one repayment
            $creditScore->save();
        }
        return 'Schedule repayment marked as late successfully';
    }


}
