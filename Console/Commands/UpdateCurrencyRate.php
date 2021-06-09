<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\CurrencyExchangeController;
use App\Models\Currency;
class UpdateCurrencyRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get currency rate from foreign site and store to db';

    /**
     * Currency Exchange Service
    */

    protected $CurrencyExchanger;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(CurrencyExchangeController $currencyExchangeController)
    {
        $currencyExchangeController->updateRate();
    }
}
