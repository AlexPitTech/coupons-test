<?php

namespace ClickTest\Coupons;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

class ParsingJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @inheritDoc */
    public $timeout = 1200;

    /** @inheritDoc */
    public $tries = 5;

    /** @var ParserInterface $parserName */
    protected $parserName;

    /** @var mixed $parserConfig */
    protected $parserConfig;

    /**
     * ParsingJob constructor.
     * @param $parserName
     * @param $params
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws Exception
     */
    public function __construct($parserName, array $params = [])
    {
        $parsers = config('clickcoup.parsers');
        if(isset($parsers[$parserName])){
            $this->parserName = $parserName;
            $this->parserConfig = array_merge($parsers[$parserName], $params);
            $this->parserConfig['id'] = $parserName;
        } else {
            throw new Exception('Unknown parser');
        }
    }

    /**
     * handle job
     */
    public function handle()
    {
        $parser = app()->make($this->parserConfig['class'], $this->parserConfig);
        $parser->run();
    }

}
