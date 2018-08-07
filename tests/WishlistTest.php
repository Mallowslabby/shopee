<?php

namespace Bhavinjr\Tests\Shoppingwishlist;

use Mockery;
use PHPUnit\Framework\Assert;
use Bhavinjr\Shoppingwishlist\Wishlist;
use Orchestra\Testbench\TestCase;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Collection;
use Bhavinjr\Shoppingwishlist\WishlistItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Bhavinjr\Shoppingwishlist\ShoppingwishlistServiceProvider;
use Bhavinjr\Tests\Shoppingwishlist\Fixtures\ProductModel;
use Bhavinjr\Tests\Shoppingwishlist\Fixtures\BuyableProduct;

class WishlistTest extends TestCase
{
    use WishlistAssertions;

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

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('wishlist.database.connection', 'testing');

        $app['config']->set('session.driver', 'array');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator) {
            $migrator->path(realpath(__DIR__.'/../database/migrations'));
        });
    }

    /** @test */
    public function it_has_a_default_instance()
    {
        $wishlist = $this->getWishlist();

        $this->assertEquals(Wishlist::DEFAULT_INSTANCE, $wishlist->currentInstance());
    }

    /** @test */
    public function it_can_have_multiple_instances()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'First item'));

        $wishlist->instance('wishlist')->add(new BuyableProduct(2, 'Second item'));

        $this->assertItemsInWishlist(1, $wishlist->instance(Wishlist::DEFAULT_INSTANCE));
        $this->assertItemsInWishlist(1, $wishlist->instance('wishlist'));
    }
    
    /** @test */
    public function it_can_add_an_item()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $this->assertEquals(1, $wishlist->count());

        Event::assertDispatched('wishlist.added');
    }

    /** @test */
    public function it_will_return_the_cartitem_of_the_added_item()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlistItem = $wishlist->add(new BuyableProduct);

        $this->assertInstanceOf(WishlistItem::class, $wishlistItem);
        $this->assertEquals('027c91341fd5cf4d2579b49c4b6a90da', $wishlistItem->rowId);

        Event::assertDispatched('wishlist.added');
    }

    /** @test */
    public function it_can_add_multiple_buyable_items_at_once()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertEquals(2, $wishlist->count());

        Event::assertDispatched('wishlist.added');
    }

    /** @test */
    public function it_will_return_an_array_of_cartitems_when_you_add_multiple_items_at_once()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlistItems = $wishlist->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertTrue(is_array($wishlistItems));
        $this->assertCount(2, $wishlistItems);
        $this->assertContainsOnlyInstancesOf(WishlistItem::class, $wishlistItems);

        Event::assertDispatched('wishlist.added');
    }

    /** @test */
    public function it_can_add_an_item_from_attributes()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(1, 'Test item', 1, 10.00);

        $this->assertEquals(1, $wishlist->count());

        Event::assertDispatched('wishlist.added');
    }

    /** @test */
    public function it_can_add_an_item_from_an_array()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(['id' => 1, 'name' => 'Test item', 'qty' => 1, 'price' => 10.00]);

        $this->assertEquals(1, $wishlist->count());

        Event::assertDispatched('wishlist.added');
    }

    /** @test */
    public function it_can_add_multiple_array_items_at_once()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add([
            ['id' => 1, 'name' => 'Test item 1', 'qty' => 1, 'price' => 10.00],
            ['id' => 2, 'name' => 'Test item 2', 'qty' => 1, 'price' => 10.00]
        ]);

        $this->assertEquals(2, $wishlist->count());

        Event::assertDispatched('wishlist.added');
    }

    /** @test */
    public function it_can_add_an_item_with_options()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $options = ['size' => 'XL', 'color' => 'red'];

        $wishlist->add(new BuyableProduct, 1, $options);

        $wishlistItem = $wishlist->get('07d5da5550494c62daf9993cf954303f');

        $this->assertInstanceOf(WishlistItem::class, $wishlistItem);
        $this->assertEquals('XL', $wishlistItem->options->size);
        $this->assertEquals('red', $wishlistItem->options->color);

        Event::assertDispatched('wishlist.added');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid identifier.
     */
    public function it_will_validate_the_identifier()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(null, 'Some title', 1, 10.00);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid name.
     */
    public function it_will_validate_the_name()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(1, null, 1, 10.00);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid quantity.
     */
    public function it_will_validate_the_quantity()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(1, 'Some title', 'invalid', 10.00);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid price.
     */
    public function it_will_validate_the_price()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(1, 'Some title', 1, 'invalid');
    }

    /** @test */
    public function it_will_update_the_cart_if_the_item_already_exists_in_the_cart()
    {
        $wishlist = $this->getWishlist();

        $item = new BuyableProduct;

        $wishlist->add($item);
        $wishlist->add($item);

        $this->assertItemsInWishlist(2, $wishlist);
        $this->assertRowsInWishlist(1, $wishlist);
    }

    /** @test */
    public function it_will_keep_updating_the_quantity_when_an_item_is_added_multiple_times()
    {
        $wishlist = $this->getWishlist();

        $item = new BuyableProduct;

        $wishlist->add($item);
        $wishlist->add($item);
        $wishlist->add($item);

        $this->assertItemsInWishlist(3, $wishlist);
        $this->assertRowsInWishlist(1, $wishlist);
    }

    /** @test */
    public function it_can_update_the_quantity_of_an_existing_item_in_the_cart()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->update('027c91341fd5cf4d2579b49c4b6a90da', 2);

        $this->assertItemsInWishlist(2, $wishlist);
        $this->assertRowsInWishlist(1, $wishlist);

        Event::assertDispatched('wishlist.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_a_buyable()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->update('027c91341fd5cf4d2579b49c4b6a90da', new BuyableProduct(1, 'Different description'));

        $this->assertItemsInWishlist(1, $wishlist);
        $this->assertEquals('Different description', $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('wishlist.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_an_array()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->update('027c91341fd5cf4d2579b49c4b6a90da', ['name' => 'Different description']);

        $this->assertItemsInWishlist(1, $wishlist);
        $this->assertEquals('Different description', $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('wishlist.updated');
    }

    /**
     * @test
     * @expectedException \Bhavinjr\Shoppingcart\Exceptions\InvalidRowIDException
     */
    public function it_will_throw_an_exception_if_a_rowid_was_not_found()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->update('none-existing-rowid', new BuyableProduct(1, 'Different description'));
    }

    /** @test */
    public function it_will_regenerate_the_rowid_if_the_options_changed()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct, 1, ['color' => 'red']);

        $wishlist->update('ea65e0bdcd1967c4b3149e9e780177c0', ['options' => ['color' => 'blue']]);

        $this->assertItemsInWishlist(1, $wishlist);
        $this->assertEquals('7e70a1e9aaadd18c72921a07aae5d011', $wishlist->content()->first()->rowId);
        $this->assertEquals('blue', $wishlist->get('7e70a1e9aaadd18c72921a07aae5d011')->options->color);
    }

    /** @test */
    public function it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_rowid()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct, 1, ['color' => 'red']);
        $wishlist->add(new BuyableProduct, 1, ['color' => 'blue']);

        $wishlist->update('7e70a1e9aaadd18c72921a07aae5d011', ['options' => ['color' => 'red']]);

        $this->assertItemsInWishlist(2, $wishlist);
        $this->assertRowsInWishlist(1, $wishlist);
    }

    /** @test */
    public function it_can_remove_an_item_from_the_cart()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->remove('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertItemsInWishlist(0, $wishlist);
        $this->assertRowsInWishlist(0, $wishlist);

        Event::assertDispatched('wishlist.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_to_zero()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->update('027c91341fd5cf4d2579b49c4b6a90da', 0);

        $this->assertItemsInWishlist(0, $wishlist);
        $this->assertRowsInWishlist(0, $wishlist);

        Event::assertDispatched('wishlist.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_negative()
    {
        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->update('027c91341fd5cf4d2579b49c4b6a90da', -1);

        $this->assertItemsInWishlist(0, $wishlist);
        $this->assertRowsInWishlist(0, $wishlist);

        Event::assertDispatched('wishlist.removed');
    }

    /** @test */
    public function it_can_get_an_item_from_the_cart_by_its_rowid()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(WishlistItem::class, $wishlistItem);
    }

    /** @test */
    public function it_can_get_the_content_of_the_cart()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1));
        $wishlist->add(new BuyableProduct(2));

        $content = $wishlist->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(2, $content);
    }

    /** @test */
    public function it_will_return_an_empty_collection_if_the_cart_is_empty()
    {
        $wishlist = $this->getWishlist();

        $content = $wishlist->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(0, $content);
    }

    /** @test */
    public function it_will_include_the_tax_and_subtotal_when_converted_to_an_array()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1));
        $wishlist->add(new BuyableProduct(2));

        $content = $wishlist->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertEquals([
            '027c91341fd5cf4d2579b49c4b6a90da' => [
                'rowId' => '027c91341fd5cf4d2579b49c4b6a90da',
                'id' => 1,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'options' => [],
            ],
            '370d08585360f5c568b18d1f2e4ca1df' => [
                'rowId' => '370d08585360f5c568b18d1f2e4ca1df',
                'id' => 2,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'options' => [],
            ]
        ], $content->toArray());
    }

    /** @test */
    public function it_can_destroy_a_cart()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $this->assertItemsInWishlist(1, $wishlist);

        $wishlist->destroy();

        $this->assertItemsInWishlist(0, $wishlist);
    }

    /** @test */
    public function it_can_get_the_total_price_of_the_cart_content()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'First item', 10.00));
        $wishlist->add(new BuyableProduct(2, 'Second item', 25.00), 2);

        $this->assertItemsInWishlist(3, $wishlist);
        $this->assertEquals(60.00, $wishlist->subtotal());
    }

    /** @test */
    public function it_can_return_a_formatted_total()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'First item', 1000.00));
        $wishlist->add(new BuyableProduct(2, 'Second item', 2500.00), 2);

        $this->assertItemsInWishlist(3, $wishlist);
        $this->assertEquals('6.000,00', $wishlist->subtotal(2, ',', '.'));
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some item'));
        $wishlist->add(new BuyableProduct(2, 'Another item'));

        $wishlistItem = $wishlist->search(function ($wishlistItem, $rowId) {
            return $wishlistItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $wishlistItem);
        $this->assertCount(1, $wishlistItem);
        $this->assertInstanceOf(WishlistItem::class, $wishlistItem->first());
        $this->assertEquals(1, $wishlistItem->first()->id);
    }

    /** @test */
    public function it_can_search_the_cart_for_multiple_items()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some item'));
        $wishlist->add(new BuyableProduct(2, 'Some item'));
        $wishlist->add(new BuyableProduct(3, 'Another item'));

        $wishlistItem = $wishlist->search(function ($wishlistItem, $rowId) {
            return $wishlistItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $wishlistItem);
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item_with_options()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some item'), 1, ['color' => 'red']);
        $wishlist->add(new BuyableProduct(2, 'Another item'), 1, ['color' => 'blue']);

        $wishlistItem = $wishlist->search(function ($wishlistItem, $rowId) {
            return $wishlistItem->options->color == 'red';
        });

        $this->assertInstanceOf(Collection::class, $wishlistItem);
        $this->assertCount(1, $wishlistItem);
        $this->assertInstanceOf(WishlistItem::class, $wishlistItem->first());
        $this->assertEquals(1, $wishlistItem->first()->id);
    }

    /** @test */
    public function it_will_associate_the_cart_item_with_a_model_when_you_add_a_buyable()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertContains(BuyableProduct::class, Assert::readAttribute($wishlistItem, 'associatedModel'));
    }

    /** @test */
    public function it_can_associate_the_cart_item_with_a_model()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(1, 'Test item', 1, 10.00);

        $wishlist->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(ProductModel::class, Assert::readAttribute($wishlistItem, 'associatedModel'));
    }

    /**
     * @test
     * @expectedException \Bhavinjr\Shoppingcart\Exceptions\UnknownModelException
     * @expectedExceptionMessage The supplied model SomeModel does not exist.
     */
    public function it_will_throw_an_exception_when_a_non_existing_model_is_being_associated()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(1, 'Test item', 1, 10.00);

        $wishlist->associate('027c91341fd5cf4d2579b49c4b6a90da', 'SomeModel');
    }

    /** @test */
    public function it_can_get_the_associated_model_of_a_cart_item()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(1, 'Test item', 1, 10.00);

        $wishlist->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(ProductModel::class, $wishlistItem->model);
        $this->assertEquals('Some value', $wishlistItem->model->someValue);
    }

    /** @test */
    public function it_can_calculate_the_subtotal_of_a_cart_item()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 9.99), 3);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(29.97, $wishlistItem->subtotal);
    }

    /** @test */
    public function it_can_return_a_formatted_subtotal()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 500), 3);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('1.500,00', $wishlistItem->subtotal(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_default_tax_rate_in_the_config()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(2.10, $wishlistItem->tax);
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_specified_tax()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $wishlist->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(1.90, $wishlistItem->tax);
    }

    /** @test */
    public function it_can_return_the_calculated_tax_formatted()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 10000.00), 1);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('2.100,00', $wishlistItem->tax(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_the_total_tax_for_all_cart_items()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $wishlist->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(10.50, $wishlist->tax);
    }

    /** @test */
    public function it_can_return_formatted_total_tax()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $wishlist->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('1.050,00', $wishlist->tax(2, ',', '.'));
    }

    /** @test */
    public function it_can_return_the_subtotal()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $wishlist->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(50.00, $wishlist->subtotal);
    }

    /** @test */
    public function it_can_return_formatted_subtotal()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $wishlist->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $wishlist->subtotal(2, ',', ''));
    }

    /** @test */
    public function it_can_return_cart_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $wishlist->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $wishlist->subtotal());
        $this->assertEquals('1050,00', $wishlist->tax());
        $this->assertEquals('6050,00', $wishlist->total());

        $this->assertEquals('5000,00', $wishlist->subtotal);
        $this->assertEquals('1050,00', $wishlist->tax);
        $this->assertEquals('6050,00', $wishlist->total);
    }

    /** @test */
    public function it_can_return_cartItem_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('2000,00', $wishlistItem->price());
        $this->assertEquals('2420,00', $wishlistItem->priceTax());
        $this->assertEquals('4000,00', $wishlistItem->subtotal());
        $this->assertEquals('4840,00', $wishlistItem->total());
        $this->assertEquals('420,00', $wishlistItem->tax());
        $this->assertEquals('840,00', $wishlistItem->taxTotal());
    }

    /** @test */
    public function it_can_store_the_cart_in_a_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->store($identifier = 123);

        $serialized = serialize($wishlist->content());

        $this->assertDatabaseHas('shoppingwishlist', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

        Event::assertDispatched('wishlist.stored');
    }

    /**
     * @test
     * @expectedException \Bhavinjr\Shoppingcart\Exceptions\CartAlreadyStoredException
     * @expectedExceptionMessage A wishlist with identifier 123 was already stored.
     */
    public function it_will_throw_an_exception_when_a_cart_was_already_stored_using_the_specified_identifier()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->store($identifier = 123);

        $wishlist->store($identifier);

        Event::assertDispatched('wishlist.stored');
    }

    /** @test */
    public function it_can_restore_a_cart_from_the_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct);

        $wishlist->store($identifier = 123);

        $wishlist->destroy();

        $this->assertItemsInWishlist(0, $wishlist);

        $wishlist->restore($identifier);

        $this->assertItemsInWishlist(1, $wishlist);

        $this->assertDatabaseMissing('shoppingwishlist', ['identifier' => $identifier, 'instance' => 'default']);

        Event::assertDispatched('wishlist.restored');
    }

    /** @test */
    public function it_will_just_keep_the_current_instance_if_no_cart_with_the_given_identifier_was_stored()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        $wishlist = $this->getWishlist();

        $wishlist->restore($identifier = 123);

        $this->assertItemsInWishlist(0, $wishlist);
    }

    /** @test */
    public function it_can_calculate_all_values()
    {
        $wishlist = $this->getWishlist();

        $wishlist->add(new BuyableProduct(1, 'First item', 10.00), 2);

        $wishlistItem = $wishlist->get('027c91341fd5cf4d2579b49c4b6a90da');

        $wishlist->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $this->assertEquals(10.00, $wishlistItem->price(2));
        $this->assertEquals(11.90, $wishlistItem->priceTax(2));
        $this->assertEquals(20.00, $wishlistItem->subtotal(2));
        $this->assertEquals(23.80, $wishlistItem->total(2));
        $this->assertEquals(1.90, $wishlistItem->tax(2));
        $this->assertEquals(3.80, $wishlistItem->taxTotal(2));

        $this->assertEquals(20.00, $wishlist->subtotal(2));
        $this->assertEquals(23.80, $wishlist->total(2));
        $this->assertEquals(3.80, $wishlist->tax(2));
    }

    /** @test */
    public function it_will_destroy_the_cart_when_the_user_logs_out_and_the_config_setting_was_set_to_true()
    {
        $this->app['config']->set('wishlist.destroy_on_logout', true);

        $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock) {
            $mock->shouldReceive('forget')->once()->with('wishlist');
        }));

        $user = Mockery::mock(Authenticatable::class);

        event(new Logout($user));
    }

    /**
     * Get an instance of the wishlist.
     *
     * @return \Bhavinjr\Shoppingcart\Wishlist
     */
    private function getWishlist()
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Wishlist($session, $events);
    }

    /**
     * Set the config number format.
     * 
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     */
    private function setConfigFormat($decimals, $decimalPoint, $thousandSeperator)
    {
        $this->app['config']->set('wishlist.format.decimals', $decimals);
        $this->app['config']->set('wishlist.format.decimal_point', $decimalPoint);
        $this->app['config']->set('wishlist.format.thousand_seperator', $thousandSeperator);
    }
}
