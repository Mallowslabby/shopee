<?php

namespace Bhavinjr\Tests\Shoppingwishlist;

use Orchestra\Testbench\TestCase;
use Bhavinjr\Shoppingwishlist\WishlistItem;
use Bhavinjr\Shoppingwishlist\ShoppingwishlistServiceProvider;

class WishlistItemTest extends TestCase
{
    /**
     * Set the package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ShoppingwishlistServiceProvider::class];
    }

    /** @test */
    public function it_can_be_cast_to_an_array()
    {
        $wishlistItem = new WishlistItem(1, 'Some item', 10.00, ['size' => 'XL', 'color' => 'red']);
        $wishlistItem->setQuantity(2);

        $this->assertEquals([
            'id' => 1,
            'name' => 'Some item',
            'price' => 10.00,
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'qty' => 2,
            'options' => [
                'size' => 'XL',
                'color' => 'red'
            ],
            'tax' => 0,
            'subtotal' => 20.00,
        ], $wishlistItem->toArray());
    }

    /** @test */
    public function it_can_be_cast_to_json()
    {
        $wishlistItem = new WishlistItem(1, 'Some item', 10.00, ['size' => 'XL', 'color' => 'red']);
        $wishlistItem->setQuantity(2);

        $this->assertJson($wishlistItem->toJson());

        $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"name":"Some item","qty":2,"price":10,"options":{"size":"XL","color":"red"},"tax":0,"subtotal":20}';

        $this->assertEquals($json, $wishlistItem->toJson());
    }
}