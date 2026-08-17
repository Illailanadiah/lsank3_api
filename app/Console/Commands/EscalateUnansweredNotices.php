<?php

namespace App\Console\Commands;

use App\Services\LegalEscalationService;
use Illuminate\Console\Command;

class EscalateUnansweredNotices extends Command
{
    protected $signature =
        'lsank:notices-escalate';

    protected $description =
        'Refer active notices with no response after 14 days to Legal.';

    public function handle(
        LegalEscalationService $service
    ): int {
        $count =
            $service
                ->escalateOverdueNotices();

        $this->info(
            "Legal escalation complete. {$count} notice(s) triggered."
        );

        return self::SUCCESS;
    }
}
