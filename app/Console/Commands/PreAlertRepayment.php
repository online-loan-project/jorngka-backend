<?php

namespace App\Console\Commands;

use App\Constants\ConstLoanRepaymentStatus;
use App\Constants\ConstLoanStatus;
use App\Models\ScheduleRepayment;
use App\Models\User;
use App\Traits\TelegramNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PreAlertRepayment extends Command
{
    use TelegramNotification;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pre-alert-repayment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $notificationHours = 72;
        $now = Carbon::now(); // Get the current date and time
        $notificationTime = $now->copy()->addHours($notificationHours);

        // Get repayments due within the specified hours
        $upcomingRepayments = ScheduleRepayment::with(['loan.user'])->where('status', ConstLoanRepaymentStatus::UNPAID)->whereBetween('repayment_date', [$now, $notificationTime])->get();

        $notifiedCount = 0;

        foreach ($upcomingRepayments as $repayment) {
            $user = $repayment->loan->user;

            if ($user && $user->telegram_chat_id) {
                $this->sendRepaymentReminder($user, $repayment);
                $notifiedCount++;
            }
        }

        $this->info("Pre-schedule repayment notification completed. Notified {$notifiedCount} users.");


    }

    protected function sendRepaymentReminder(User $user, ScheduleRepayment $repayment)
    {
        $dueDate = Carbon::parse($repayment->repayment_date)->format('M j, Y H:i');
        $amount = number_format($repayment->emi_amount, 2);
        $loanId = $repayment->id;
        $currency = 'USD';

        $message = <<<MSG
⏰ Upcoming Repayment Reminder
Loan ID: #$loanId
Amount Due: $currency $amount
Due Date: $dueDate
Please ensure sufficient funds are available in your account.

This is an automated reminder.
MSG;

        $this->sendTelegram($user->telegram_chat_id, $message);
    }


}
