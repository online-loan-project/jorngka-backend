<?php


namespace App\Traits;

use App\Constants\ConstRequestLoanStatus;
use App\Models\Borrower;
use App\Models\CreditScore;
use App\Models\IncomeInformation;
use App\Models\RequestLoan;
use App\Models\User;

trait LoanEligibility
{
    use TelegramNotification;
    use BaseApiResponse;

    public function checkLoanEligibility(int $userId, int $requestLoanId)
    {
        $user = User::query()->find($userId);
        $borrower = Borrower::where('user_id', $userId)->first();
        if (!$borrower) {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

💡 Reason : Borrower not found

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Borrower not found');
            return 'Borrower not found';
        }
        // Retrieve the requested loan details
        $requestLoan = RequestLoan::find($requestLoanId);
        if (!$requestLoan) {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

▫️ Requested Amount: {$requestLoan->loan_amount} $

💡 Reason : Loan request not found

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Loan request not found');
            return 'Loan request not found';
        }

        // Retrieve the user's income information
        $incomeInfo = IncomeInformation::where('request_loan_id', $requestLoanId)->first();
        if (!$incomeInfo) {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

▫️ Requested Amount: {$requestLoan->loan_amount} $

💡 Reason : Income information not found

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Income information not found');
            return 'Income information not found';
        }

        // Retrieve the user's credit score
        $userCredit = CreditScore::where('user_id', $userId)->first();
        if (!$userCredit) {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

▫️ Requested Amount: {$requestLoan->loan_amount} $

💡 Reason : Credit information not found

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Credit information not found');
            return 'Credit information not found';
        }

//      more logic here example :
//       1. Age (must be above a minimum threshold, e.g., 21-60 years) If age < 21 or age > 60: Not Eligible
        $borrower_age = date_diff(date_create($borrower->dob), date_create('now'))->y;
        if ($borrower_age < 21 || $borrower_age > 60) {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

▫️ Requested Amount: {$requestLoan->loan_amount} $

💡 Reason : Not Eligible (Invalid age)

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Invalid age');
            return 'Not Eligible (Invalid age)';
        }

        // 2. Employment Type
        if ($incomeInfo->employee_type == 'Unemployed') {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

▫️ Requested Amount: {$requestLoan->loan_amount} $

💡 Reason : Not Eligible (Unemployed)

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Unemployed');
            return 'Not Eligible (Unemployed)';
        }

        // 3. Determine approval percentage based on credit score
        $creditScore = $userCredit->score;
        $approvedPercentage = 0;

        if ($creditScore >= 50) {
            $approvedPercentage = 100;
        } elseif ($creditScore >= 40) {
            $approvedPercentage = 75;
        } elseif ($creditScore >= 30) {
            $approvedPercentage = 50;
        } elseif ($creditScore >= 20) {
            $approvedPercentage = 25;
        }

        // If approvedPercentage is 0, reject
        if ($approvedPercentage === 0) {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

▫️ Requested Amount: {$requestLoan->loan_amount} $

💡 Reason : Your credit score is too low (less than 20)

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Your credit score is too low (less than 20)');
            return 'Your credit score is too low (less than 20)';
        }

        // Calculate approved amount
        $approvedAmount = ($approvedPercentage / 100) * $requestLoan->loan_amount;
//      4. Income loan_amount ≤ 5 * income ( Loan should not bigger than 5 times the income )
        if ($approvedAmount > (5 * $incomeInfo->income)) {
            $this->sendTelegram(
                $user->telegram_chat_id,
                <<<MSG
🏦 Loan Not Eligibility Notification

❌  Eligibility Check Not Complete

▫️ Requested Amount: {$requestLoan->loan_amount} $

💡 Reason : Loan amount is too high

📞 Contact support if you have any questions.

This is an automated message.
MSG);
            $this->checkRequestLoanNotEligible($userId, $requestLoanId, 'Loan amount is too high');

            return 'Loan amount is too high';
        }
        // Update loan status and optionally save approved amount
        $requestLoan->update([
            'status' => ConstRequestLoanStatus::ELIGIBLE,
            'approved_amount' => $approvedAmount // Assuming this column exists
        ]);
        $this->sendTelegram(
            $user->telegram_chat_id,
            <<<MSG
🏦 Loan Eligible Notification

✅ Eligibility Check Completed

▫️ Requested Amount: {$requestLoan->loan_amount} $
▫️ Approved Amount: {$approvedAmount} $
▫️ Approval Percentage: {$approvedPercentage}%

💡 Next Steps:
- Review your loan terms
- Funds will be disbursed within 24h of acceptance

📞 Contact support if you have any questions.

This is an automated message.
MSG);

        return "Eligible. Approved amount: {$approvedAmount} ({$approvedPercentage}% of requested)";
    }

    public function checkRequestLoanNotEligible(int $userId, int $requestLoanId, $reason = null)
    {
        $user = User::query()->find($userId);
        $requestLoan = RequestLoan::find($requestLoanId);
        if (!$requestLoan) {
            return 'Loan request not found';
        }
        //set loan to not eligible
        $requestLoan->update([
            'status' => ConstRequestLoanStatus::NOT_ELIGIBLE,
            'rejection_reason' => $reason,
        ]);
        return 'Loan request not eligible';

    }


}
