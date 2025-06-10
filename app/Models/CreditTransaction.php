<?php

namespace App\Models;

use App\Constants\ConstCreditTransaction;
use App\Traits\CreditTransactionActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditTransaction extends Model
{
    use HasFactory, SoftDeletes, CreditTransactionActivity;

    protected $fillable = [
        'transaction_code',
        'credit_id',
        'user_id',
        'amount',
        'type',
        'reference',
        'description',
        'balance_after',
        'balance_before',
        'previous_transaction_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->transaction_code)) {
                $model->transaction_code = $model->generateTransactionCode();
            }

            if (empty($model->previous_transaction_id)) {
                $model->previous_transaction_id = $model->getPreviousTransactionId();
            }
        });
    }

    protected function generateTransactionCode(): string
    {
        $prefix = match($this->type) {
            ConstCreditTransaction::TYPE_ADMIN_DEPOSIT => 'DEP',
            ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL => 'WDL',
            ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT => 'DIS',
            ConstCreditTransaction::TYPE_LOAN_REPAYMENT => 'REP',
            default => 'TXN'
        };

        $date = now()->format('Ymd');
        $sequence = self::whereDate('created_at', today())->count() + 1;

        return "{$prefix}-{$date}-" . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    protected function getPreviousTransactionId(): ?int
    {
        return self::where('credit_id', $this->credit_id)
            ->latest()
            ->first()
            ?->id;
    }

    public function credit()
    {
        return $this->belongsTo(Credit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function previousTransaction()
    {
        return $this->belongsTo(self::class, 'previous_transaction_id');
    }

    public function scopeAdminTransactions($query)
    {
        return $query->whereIn('type', [
            ConstCreditTransaction::TYPE_ADMIN_DEPOSIT,
            ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL
        ]);
    }

    public function scopeLoanTransactions($query)
    {
        return $query->whereIn('type', [
            ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT,
            ConstCreditTransaction::TYPE_LOAN_REPAYMENT
        ]);
    }

    public function isDeposit(): bool
    {
        return $this->type === ConstCreditTransaction::TYPE_ADMIN_DEPOSIT;
    }

    public function isWithdrawal(): bool
    {
        return $this->type === ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL;
    }

    public function isDisbursement(): bool
    {
        return $this->type === ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT;
    }

    public function isRepayment(): bool
    {
        return $this->type === ConstCreditTransaction::TYPE_LOAN_REPAYMENT;
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->isWithdrawal() || $this->isDisbursement() ? '-' : '+';
        return $sign . number_format($this->amount, 2);
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            ConstCreditTransaction::TYPE_ADMIN_DEPOSIT => 'Admin Deposit',
            ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL => 'Admin Withdrawal',
            ConstCreditTransaction::TYPE_LOAN_DISBURSEMENT => 'Loan Disbursement',
            ConstCreditTransaction::TYPE_LOAN_REPAYMENT => 'Loan Repayment',
            default => 'Transaction'
        };
    }
}