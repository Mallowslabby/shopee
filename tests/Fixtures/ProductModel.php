<?php

namespace Bhavinjr\Tests\Shoppingwishlist\Fixtures;

class ProductModel
{
    public $someValue = 'Some value';

    public function find($id)
    {
        return $this;
    }
}