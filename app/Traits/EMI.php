<?php

namespace App\Traits;

use App\Models\Loan;
use App\Models\ScheduleRepayment;

trait EMI
{
    private function calculateLoanRepayment($loanAmount, $monthlyInterestRate, $loanDuration)
    {
        // Convert percentage to decimal (e.g., 1.5% → 0.015)
        $monthlyRateDecimal = $monthlyInterestRate / 100;

        // Flat Interest Calculation
        $totalInterest = $loanAmount * $monthlyRateDecimal * $loanDuration;
        $totalRepayment = $loanAmount + $totalInterest;
        $emiAmount = $totalRepayment / $loanDuration; // Equal monthly installments

        return round($emiAmount, 2); // Return monthly payment
    }

    private function createScheduleRepayment($loanId)
    {
        $loan = Loan::query()
            ->with(['creditScore', 'interestRate', 'requestLoan'])
            ->find($loanId);
        if (!$loan) {
            return 'Loan not found';
        }

        // Calculate monthly payment with flat interest
        $emiAmount = $this->calculateLoanRepayment(
            $loan->requestLoan->loan_amount,
            $loan->interestRate->rate,
            $loan->loan_duration
        );

        // Create repayment schedule
        for ($i = 1; $i <= $loan->loan_duration; $i++) {
            ScheduleRepayment::create([
                'repayment_date' => now()->addMonths($i),
                'emi_amount' => $emiAmount,
                'loan_id' => $loanId,
            ]);
        }

        return true;
    }
}