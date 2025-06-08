<?php

namespace App\Http\Controllers\Admin;

use App\Constants\ConstCreditTransaction;
use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\User;
use App\Traits\Security;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //search transaction_code
        $search = $request->get('search', null);
        //filter by type
        $type = $request->get('type', null);
        $perPage = $request->get('per_page', 10);

        // Get the first active credit with its latest transaction
        $credit = Credit::query()
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$credit) {
            return $this->failed(
                null,
                'Credit Error',
                'No active credit account found.',
                404
            );
        }

        // Get paginated transactions with eager loading
        $transactions = $credit->transactions()
            ->with(['user' => function ($query) {
                $query->select('id', 'email', 'phone');
            }])
            ->when($search, function ($query, $search) {
                return $query->where('transaction_code', 'like', "%{$search}%");
            })
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Prepare response data
        $response = [
            'credit' => [
                'id' => $credit->id,
                'balance' => $credit->balance,
                'currency' => $credit->currency,
                'last_transaction_at' => Carbon::parse($credit->last_transaction_at)->toDateTimeString(),
                'is_active' => $credit->is_active,
            ],
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_code' => $transaction->transaction_code,
                    'amount' => $transaction->amount,
                    'formatted_amount' => $transaction->formatted_amount,
                    'type' => $transaction->type,
                    'type_label' => $transaction->type_label,
                    'description' => $transaction->description,
                    'balance_after' => $transaction->balance_after,
                    'balance_before' => $transaction->balance_before,
                    'created_at' => Carbon::parse($transaction->created_at)->toDateTimeString(),
                    'user' => $transaction->user ?? null,
                    //previous_transaction
                    'previous_transaction' => $transaction->previousTransaction ? [
                        'id' => $transaction->previousTransaction->id,
                        'transaction_code' => $transaction->previousTransaction->transaction_code
                    ] : null,
                    'metadata' => $transaction->metadata,
                ];
            }),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ]
        ];

        return $this->success(
            $response,
            'Credit Transactions',
            'Credit transactions retrieved successfully.'
        );
    }

    /**
     * Deposit amount to the credit account.
     */
    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $credit = Credit::query()
            ->where('is_active', true)
            ->first();

        if (!$credit) {
            return $this->failed(
                null,
                'Credit Error',
                'No active credit account found.',
                404
            );
        }

        $transaction = $credit->recordTransaction(
            $request,
            auth()->id(),
            $request->amount,
            ConstCreditTransaction::TYPE_ADMIN_DEPOSIT,
            $request->description,
            'Deposit by Admin'
        );

        return $this->success(
            $transaction,
            'Deposit Successful',
            'Amount deposited successfully.'
        );
    }

    /**
     * Withdraw amount from the credit account.
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $credit = Credit::query()
            ->where('is_active', true)
            ->first();

        if (!$credit) {
            return $this->failed(
                null,
                'Credit Error',
                'No active credit account found.',
                404
            );
        }

        // Check if sufficient balance exists
        if ($credit->balance < $request->amount) {
            return $this->failed(
                null,
                'Insufficient Balance',
                'Cannot withdraw more than available balance.',
                400
            );
        }

        $transaction = $credit->recordTransaction(
            $request,
            auth()->id(),
            $request->amount,
            ConstCreditTransaction::TYPE_ADMIN_WITHDRAWAL,
            $request->description,
            'Withdrawal by Admin'
        );

        return $this->success(
            $transaction,
            'Withdrawal Successful',
            'Amount withdrawn successfully.'
        );
    }
}
