<?php

namespace Bhavinjr\Tests\Shoppingwishlist;

use Bhavinjr\Shoppingwishlist\Wishlist;
use PHPUnit\Framework\Assert as PHPUnit;

trait WishlistAssertions
{
    /**
     * Assert that the cart contains the given number of items.
     *
     * @param int|float                     $items
     * @param \Bhavinjr\Shoppingcart\Cart $cart
     */
    public function assertItemsInWishlist($items, Wishlist $wishlist)
    {
        $actual = $wishlist->count();

        PHPUnit::assertEquals($items, $wishlist->count(), "Expected the cart to contain {$items} items, but got {$actual}.");
    }

    /**
     * Assert that the cart contains the given number of rows.
     *
     * @param int                           $rows
     * @param \Bhavinjr\Shoppingcart\Cart $cart
     */
    public function assertRowsInCart($rows, Wishlist $wishlist)
    {
        $actual = $wishlist->content()->count();

        PHPUnit::assertCount($rows, $wishlist->content(), "Expected the cart to contain {$rows} rows, but got {$actual}.");
    }
}
