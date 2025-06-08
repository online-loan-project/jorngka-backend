<?php

namespace App\Traits;

use App\Constants\ConstCreditTransaction;

trait CreditTransactionActivity
{
    /**
     * Reverse this transaction
     */
    public function reverse()
    {
        if ($this->related_transaction_id) {
            throw new \Exception('This transaction has already been reversed');
        }

        $reverseType = match($this->type) {
            ConstCreditTransaction::TYPE_ADMIN_DEPOSIT => ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL,
            ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL => ConstCreditTransaction::TYPE_ADMIN_DEPOSIT,
            ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT => ConstCreditTransaction::TYPE_LOAN_REPAYMENT,
            ConstCreditTransaction::TYPE_LOAN_REPAYMENT => ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT,
            default => throw new \Exception('Cannot reverse this transaction type')
        };

        $reverseTransaction = $this->credit->recordTransaction([
            'amount' => $this->amount,
            'type' => $reverseType,
            'user_id' => $this->user_id,
            'description' => "Reversal of transaction #{$this->transaction_code}",
            'related_transaction_id' => $this->id,
            'metadata' => ['reversed_from' => $this->id],
        ]);

        $this->update(['related_transaction_id' => $reverseTransaction->id]);

        return $reverseTransaction;
    }

    /**
     * Check if this transaction can be reversed
     */
    public function canBeReversed(): bool
    {
        return !$this->related_transaction_id && in_array($this->type, [
                ConstCreditTransaction::TYPE_ADMIN_DEPOSIT,
                ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL,
                ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT,
                ConstCreditTransaction::TYPE_LOAN_REPAYMENT,
            ]);
    }

    /**
     * Get the related transaction (if any)
     */
    public function getRelatedTransaction()
    {
        return $this->relatedTransaction;
    }

    /**
     * Get the previous transaction in sequence
     */
    public function getPreviousTransaction()
    {
        return $this->previousTransaction;
    }

    /**
     * Get the transaction impact on balance
     * Returns positive or negative amount based on transaction type
     */
    public function getImpactAmount(): float
    {
        return $this->isDeposit() || $this->isRepayment()
            ? (float) $this->amount
            : -1 * (float) $this->amount;
    }

    /**
     * Get the absolute value of the transaction amount
     */
    public function getAbsoluteAmount(): float
    {
        return abs((float) $this->amount);
    }
}