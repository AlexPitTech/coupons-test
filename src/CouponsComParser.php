<?php


namespace ClickTest\Coupons;


use GuzzleHttp\Client as HttpClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class CouponsComParser implements ParserInterface
{

    /** @var string $cacheId */
    public $cacheId = 'click_coup_categories';

    /** @var int $pageLimit - limit on https://www.coupons.com/coupon-codes/stores/ */
    public $pageLimit = 15;

    /** @var string $id */
    public $id;

    /** @var integer|null $reload */
    public $reloadTimeout;

    /** @var array $types of coupons */
    public $types = [
        'Get Code' => 'code',
        'See Deal' => 'deal',
        'Click to Save' => 'sale',
    ];

    /** @var string|null */
    protected $currentCategory;

    /** @var string|null */
    protected $nextCategory;

    /** @var callable|null */
    protected $proxyProvider;

    /**
     * CouponsComParser constructor.
     * @param string $id
     * @param string|null $currentCategory
     */
    public function __construct($id, $currentCategory = null)
    {
        $this->id = $id;
        $this->proxyProvider = config('clickcoup.proxyProvider');
        $this->reloadTimeout = config('clickcoup.reloadTimeout');

        $categories = $this->ensureCategories();
        if(null === $currentCategory){
            $this->currentCategory = reset($categories);
            $this->nextCategory = next($categories);
        } else {
            $uri = reset($categories);
            do {
                if ($uri === $currentCategory) {
                    $this->currentCategory = $currentCategory;
                    $this->nextCategory = next($categories);
                    break;
                }
            } while ($uri = next($categories));
        }
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function run()
    {
        $this->parseStores($this->currentCategory);
        $this->dispatchNext();
    }

    /**
     * @return mixed
     */
    public function ensureCategories()
    {
        try {
            if (!Cache::store('file')->has($this->cacheId)){
                Cache::store('file')->set($this->cacheId, $this->parseCategories());
            }
            return Cache::store('file')->get($this->cacheId);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e){
            return false;
        }
    }

    /**
     * @return array
     */
    public function parseCategories()
    {
        $categories = [];
        $response  = $this->request($uri = 'https://www.coupons.com/coupon-codes/stores/');

        $crawler = new Crawler();
        $crawler->addHtmlContent($response,'UTF-8');
        $crawler->filter('ul.atoz')
            ->first()
            ->filter('a')
            ->each(function (Crawler $node) use (&$categories){
                $categories[] = $node->attr('href');
            });
        logs()->info(count($categories).' categories has been parsed from '. $uri);

        return $categories;
    }

    /**
     * @param $uri
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function parseStores($uri)
    {
        $stores = [];
        $response = $this->request($uri);

        $crawler = new Crawler();
        $crawler->addHtmlContent($response,'UTF-8');
        $crawler->filter('.view-all-container li.item a')
            ->each(function (Crawler $node) use (&$stores){
                $uri = trim($node->attr('href'));
                $stores[$uri] = Store::make([
                    'provider' => $this->id,
                    'name' => trim($node->text()),
                    'uri' => $uri,
                ]);
            });
        /** @var Collection $currentStores */
        $currentStores = Store::where('provider', $this->id)
            ->whereIn('uri', array_keys($stores))
            ->get()
            ->keyBy('uri');

        foreach ($currentStores as $uri => $store){
            unset($stores[$uri]);
        }
        $i = 0;
        foreach ($stores as $store){
            $store->save();
            $currentStores->add($store);
        }
        logs()->info(count($stores).' stores has been added from '. $uri);
        unset($stores);
        /** @var Store $store */
        $store = $currentStores->random();
        $this->parseCoupons($store);

        foreach ($currentStores as $store){
            $this->parseCoupons($store);
            $i++;
            if($i > 3){
                break;
            }
        }
    }

    /**
     * @param Store $store
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function parseCoupons(Store $store)
    {
        $pageNum = 0;
        $store->coupons()->delete();
        do {
            $pageNum++;
            $coupons = [];
            $uri = $store->uri.'/'.$pageNum;
            $response = $this->request($uri);
            $crawler = new Crawler();
            $crawler->addHtmlContent($response,'UTF-8');
            $totalCount = (int) $crawler->filter('.ccodes-clicks-date')->text();
            $crawler
                ->filter('#app .main-content')
                ->filterXPath('//div[contains(@id, "coupon-")]')
                ->each(function (Crawler $node) use (&$coupons, $store){
                    $coupons[] = $store->coupons()->create([
                        'store_id' => $store->id,
                        'couponType' => $this->couponType($node->filter('.coupons-btn')->text()),
                        'image' => ($_node = $node->filter('.coupons-product-image-box img'))->count() ?
                            $_node->attr('src') : '',
                        'header' => $node->filter('.couponTitle')->html(),
                        'text' => $node->filter('.coupon-description')->html(),
                        'finishedAt' => ($_node = $node->filter('span[itemprop="validThrough"]'))->count() ?
                            $_node->text() : null,
                        'timesCount' => ($_node = $node->filter('.used-times span'))->count() ?
                            $_node->text() : null,
                    ]);
                });
            logs()->info(count($coupons)." coupons has been parsed from $uri");
        } while ($totalCount > $pageNum * $this->pageLimit);
    }

    /**
     * @param $url
     * @return string
     */
    public function request($url)
    {
        $config = [];
        if($this->proxyProvider){
            $config['proxy'] = call_user_func($this->proxyProvider);
        }
        $client = new HttpClient($config);
        $request = $client->get($url);
        return (string) $request->getBody();
    }

    /**
     * @param $buttonText
     * @return mixed|string
     */
    public function couponType($buttonText)
    {
        return isset($this->types[$buttonText]) ? $this->types[$buttonText] : 'unknown';
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function dispatchNext()
    {
        if($this->nextCategory){
            dispatch(new ParsingJob($this->id, ['currentCategory' => $this->nextCategory]));
        } elseif ($this->reloadTimeout){
            dispatch(
                (new ParsingJob($this->id))
                ->delay(now()->addMinutes($this->reloadTimeout))
            );
        }
    }

}
