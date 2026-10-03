<?php

namespace App\Console\Commands;

use App\Actions\CheckRequestRestrictions;
use Illuminate\Console\Command;

class ExpireRequestRestrictions extends Command
{
    protected $signature = 'restrictions:expire';

    protected $description = 'Expire reviewed document restrictions without deleting their history';

    public function handle(CheckRequestRestrictions $restrictions): int
    {
        $this->info('Expired restrictions: '.$restrictions->expire());

        return self::SUCCESS;
    }
}
