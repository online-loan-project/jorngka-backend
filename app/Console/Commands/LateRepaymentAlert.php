<?php

namespace App\Console\Commands;

use App\Constants\ConstLoanRepaymentStatus;
use App\Models\ScheduleRepayment;
use App\Models\User;
use App\Traits\ScheduleRepayments;
use App\Traits\TelegramNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LateRepaymentAlert extends Command
{
    use ScheduleRepayments;
    use TelegramNotification;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'late-repayment-alert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send alerts to users with overdue repayments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now(); // Get the current date and time

        // Get repayments that are past due and still unpaid
        $lateRepayments = ScheduleRepayment::with(['loan.user'])
            ->where('status', ConstLoanRepaymentStatus::UNPAID)
            ->where('repayment_date', '<', $now)
            ->get();


        $notifiedCount = 0;

        foreach ($lateRepayments as $repayment) {
            $user = $repayment->loan->user;
            $this->MarkedAsLate($repayment->id);
            if ($user && $user->telegram_chat_id) {
                $this->sendLateRepaymentAlert($user, $repayment);
                $notifiedCount++;
            }
        }

        $this->info("Late repayment alert completed. Notified {$notifiedCount} users.");
    }

    protected function sendLateRepaymentAlert(User $user, ScheduleRepayment $repayment)
    {
        $dueDate = Carbon::parse($repayment->repayment_date)->format('M j, Y H:i');
        $amount = number_format($repayment->emi_amount, 2);
        $loanId = $repayment->id;
        $currency = 'USD';

        $message = <<<MSG
⚠️ Late Repayment Alert
Loan ID: #$loanId
Amount Due: $currency $amount
Original Due Date: $dueDate
Your repayment is overdue. Please make the payment as soon as possible to avoid penalties.

This is an automated reminder.
MSG;

        $this->sendTelegram($user->telegram_chat_id, $message);
    }
}
