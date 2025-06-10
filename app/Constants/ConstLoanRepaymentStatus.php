<?php
namespace App\Constants;

class ConstLoanRepaymentStatus
{
   const PAID = '1';
    const UNPAID = '0';
    const LATE = '2';

    const PAID_LATE = '3'; // Optional: if you want to track late payments that were eventually paid
}

