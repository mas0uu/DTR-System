<?php

namespace App\Console\Commands;

use App\Services\LeaveBalanceService;
use Illuminate\Console\Command;

class RefreshAnnualLeaveBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:refresh-balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh annual paid leave balances with carry-over for eligible employees.';

    /**
     * Execute the console command.
     */
    public function handle(LeaveBalanceService $leaveBalanceService): int
    {
        $refreshed = $leaveBalanceService->refreshAnnualBalances();
        $this->info("Annual leave refresh complete. {$refreshed} balance(s) updated.");

        return self::SUCCESS;
    }
}

