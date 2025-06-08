<?php

namespace App\Constants;

class ConstCreditTransaction
{
    const TYPE_ADMIN_DEPOSIT = 'admin_deposit';
    const TYPE_ADMIN_WITHDRAWAL = 'admin_withdrawal';
    const TYPE_LOAN_DISBURSEMENT = 'loan_disbursement';
    const TYPE_LOAN_REPAYMENT = 'loan_repayment';

    const PREFIXES = [
        self::TYPE_ADMIN_DEPOSIT => 'DEP',
        self::TYPE_ADMIN_WITHDRAWAL => 'WDL',
        self::TYPE_LOAN_DISBURSEMENT => 'DIS',
        self::TYPE_LOAN_REPAYMENT => 'REP',
    ];

    const LABELS = [
        self::TYPE_ADMIN_DEPOSIT => 'Admin Deposit',
        self::TYPE_ADMIN_WITHDRAWAL => 'Admin Withdrawal',
        self::TYPE_LOAN_DISBURSEMENT => 'Loan Disbursement',
        self::TYPE_LOAN_REPAYMENT => 'Loan Repayment',
    ];
}