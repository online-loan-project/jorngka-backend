<?php

namespace App\Traits;

use App\Constants\ConstCreditTransaction;
use App\Models\CreditTransaction;

trait CreditActivity
{
    use Security, TelegramNotification;

    /**
     * Get the current balance of the credit account
     */
    public function getCurrentBalance(): float
    {
        return (float)$this->balance;
    }

    /**
     * Record a new transaction and update the credit balance
     */
    public function recordTransaction($request, $userId, $amount, $type, $description, $reference): CreditTransaction
    {
        $attributes = [
            'transaction_code' => null, // Will be generated automatically
            'credit_id' => $this->id,
            'amount' => $amount,
            'type' => $type,
            'description' => $description, // Optional description
            'metadata' => $this->getRequestMetadata($request), // Optional metadata
            'reference' => $reference, // Optional reference
            'balance_before' => $this->balance, // Assuming this is the current balance before transaction
            'previous_transaction_id' => null, // Will be set automatically
        ];

        // Determine if this is an addition or subtraction to balance
        $isAddition = in_array($type, [
            ConstCreditTransaction::TYPE_ADMIN_DEPOSIT,
            ConstCreditTransaction::TYPE_LOAN_REPAYMENT
        ]);

        // Calculate new balance
        $newBalance = $isAddition
            ? $this->balance + $amount
            : $this->balance - $amount;

        // Create the transaction
        $transaction = $this->transactions()->create(array_merge($attributes, [
            'balance_after' => $newBalance,
            'user_id' => $userId ?? auth()->id(),
        ]));

        // Update credit balance
        $this->update([
            'balance' => $newBalance,
            'last_transaction_at' => now(),
        ]);

        $this->sendTelegram(
            env('OTP_TELEGRAM_CHAT_ID', 343413763),
            <<<MSG
💰 Credit Transaction Alert

▫️ Code: {$transaction->transaction_code}
▫️ Amount: {$transaction->formatted_amount}
▫️ Type: {$transaction->type_label}
▫️ Description: {$transaction->description}
▫️ Previous Balance: {$transaction->balance_before}
▫️ New Balance: {$newBalance}
▫️ User: {$transaction->user_id}
▫️ Reference: {$transaction->reference}
▫️ Date: {$transaction->created_at->format('Y-m-d H:i:s')}
MSG
        );

        return $transaction;
    }

    /**
     * Get the transaction history
     */
    public function getTransactionHistory(int $limit = 10)
    {
        return $this->transactions()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get the net activity (deposits - withdrawals)
     */
    public function getNetActivity(): float
    {
        return $this->getTotalDeposits() - $this->getTotalWithdrawals();
    }

    /**
     * Get the total deposits
     */
    public function getTotalDeposits(): float
    {
        return (float)$this->transactions()
            ->where('type', ConstCreditTransaction::TYPE_ADMIN_DEPOSIT)
            ->sum('amount');
    }

    /**
     * Get the total withdrawals
     */
    public function getTotalWithdrawals(): float
    {
        return (float)$this->transactions()
            ->where('type', ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL)
            ->sum('amount');
    }
}