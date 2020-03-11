<?php

namespace ClickTest\Coupons;

use Illuminate\Console\Command;

class ParsingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coupons:parsing {parserName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch coupons job';

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
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function handle()
    {
        dispatch(new ParsingJob($this->argument('parserName')));
    }
}
