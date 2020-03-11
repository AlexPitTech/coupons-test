<?php

namespace ClickTest\Coupons;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Coupon
 * @package ClickCoup
 *
 * @property-read integer $id
 * @property-read integer $store_id
 * @property string $couponType
 * @property string $image
 * @property string $header
 * @property string $text
 * @property string $finishedAt
 * @property integer $timesCount
 * @property-read string $createdAt
 * @property-read string $updatedAt
 *
 * @property-read Store $store
 */
class Coupon extends Model
{

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'click_coup_coupons';


    protected $perPage = 100;


    protected $fillable = [
        'couponType',
        'image',
        'header',
        'text',
        'vendorId',
        'finishedAt',
        'timesCount',
    ];


    public function store()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }
}
