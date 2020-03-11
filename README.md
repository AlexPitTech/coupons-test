# test-coupons
This is a test project for employment in one company.  

### Installation

Add repository to composer.json:


    "repositories": [
      {"type": "composer", "url": "https://repo.packagist.com/alexpittech/"},
      {"packagist.org": false}
    ],

Install migrations

    php artisan migrate --path=\vendor\AlexPitTech\test-coupons\migrations\

When run console command

    composer require AlexPitTech/coupons-test

Copy configuration file to laravel configuration file. If nessasary, setup calback function as proxy provider and anower parsers.

    'proxyProvider' => function (){return 'proxy here';}

Add command to your Console Kernel 

    protected $commands = [
        ...
        '\ClickTest\Coupons\ParsingCommand'
    ];

Run console command for add parsing coupons Job to queue

    php artisan coupons:parsing {parserName}

where parser name is specified in cofiguration file 
Next starting queue

    php artisan queue:work

    
