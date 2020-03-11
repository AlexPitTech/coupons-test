<?php

namespace ClickTest\Coupons;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Store
 * @package ClickCoup
 *
 * @property-read  integer $id
 * @property string $provider
 * @property string $name
 * @property string $uri
 * @property-read  string $createdAt
 * @property-read  string $updatedAt
 *
 * @property-read Coupon[]|Collection $coupons
 */
class Store extends Model
{

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'click_coup_stores';

    protected $perPage = 100;

    protected $fillable = [
        'provider',
        'name',
        'uri',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function coupons()
    {
        return $this->hasMany(Coupon::class, 'store_id', 'id');
    }

}
