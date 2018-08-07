<?php

namespace Bhavinjr\Shoppingwishlist;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Events\Dispatcher;
use Bhavinjr\Shoppingwishlist\Contracts\Buyable;
use Bhavinjr\Shoppingwishlist\Exceptions\UnknownModelException;
use Bhavinjr\Shoppingwishlist\Exceptions\InvalidRowIDException;
use Bhavinjr\Shoppingwishlist\Exceptions\WishlistAlreadyStoredException;

class Wishlist
{
    const DEFAULT_INSTANCE = 'default';

    /**
     * Instance of the session manager.
     *
     * @var \Illuminate\Session\SessionManager
     */
    private $session;

    /**
     * Instance of the event dispatcher.
     * 
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    private $events;

    /**
     * Holds the current wishlist instance.
     *
     * @var string
     */
    private $instance;

    /**
     * Cart constructor.
     *
     * @param \Illuminate\Session\SessionManager      $session
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current wishlist instance.
     *
     * @param string|null $instance
     * @return \Bhavinjr\Shoppingcart\Cart
     */
    public function instance($instance = null)
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        $this->instance = sprintf('%s.%s', 'wishlist', $instance);

        return $this;
    }

    /**
     * Get the current wishlist instance.
     *
     * @return string
     */
    public function currentInstance()
    {
        return str_replace('wishlist.', '', $this->instance);
    }

    /**
     * Add an item to the wishlist.
     *
     * @param mixed     $id
     * @param mixed     $name
     * @param int|float $qty
     * @param float     $price
     * @param array     $options
     * @return \Bhavinjr\Shoppingcart\WishlistItem
     */
    public function add($id, $name = null, $qty = null, $price = null, array $options = [])
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        $wishlistItem = $this->createWishlistItem($id, $name, $qty, $price, $options);

        $content = $this->getContent();

        if ($content->has($wishlistItem->rowId)) {
            $wishlistItem->qty += $content->get($wishlistItem->rowId)->qty;
        }

        $content->put($wishlistItem->rowId, $wishlistItem);
        
        $this->events->fire('wishlist.added', $wishlistItem);

        $this->session->put($this->instance, $content);

        return $wishlistItem;
    }

    /**
     * Update the wishlist item with the given rowId.
     *
     * @param string $rowId
     * @param mixed  $qty
     * @return \Bhavinjr\Shoppingcart\WishlistItem
     */
    public function update($rowId, $qty)
    {
        $wishlistItem = $this->get($rowId);

        if ($qty instanceof Buyable) {
            $wishlistItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $wishlistItem->updateFromArray($qty);
        } else {
            $wishlistItem->qty = $qty;
        }

        $content = $this->getContent();

        if ($rowId !== $wishlistItem->rowId) {
            $content->pull($rowId);

            if ($content->has($wishlistItem->rowId)) {
                $existingCartItem = $this->get($wishlistItem->rowId);
                $wishlistItem->setQuantity($existingCartItem->qty + $wishlistItem->qty);
            }
        }

        if ($wishlistItem->qty <= 0) {
            $this->remove($wishlistItem->rowId);
            return;
        } else {
            $content->put($wishlistItem->rowId, $wishlistItem);
        }

        $this->events->fire('wishlist.updated', $wishlistItem);

        $this->session->put($this->instance, $content);

        return $wishlistItem;
    }

    /**
     * Remove the wishlist item with the given rowId from the wishlist.
     *
     * @param string $rowId
     * @return void
     */
    public function remove($rowId)
    {
        $wishlistItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($wishlistItem->rowId);

        $this->events->fire('wishlist.removed', $wishlistItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Get a wishlist item from the wishlist by its rowId.
     *
     * @param string $rowId
     * @return \Bhavinjr\Shoppingcart\WishlistItem
     */
    public function get($rowId)
    {
        $content = $this->getContent();

        if ( ! $content->has($rowId))
            throw new InvalidRowIDException("The wishlist does not contain rowId {$rowId}.");

        return $content->get($rowId);
    }

    /**
     * Destroy the current wishlist instance.
     *
     * @return void
     */
    public function destroy()
    {
        $this->session->remove($this->instance);
    }

    /**
     * Get the content of the wishlist.
     *
     * @return \Illuminate\Support\Collection
     */
    public function content()
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    /**
     * Get the number of items in the wishlist.
     *
     * @return int|float
     */
    public function count()
    {
        $content = $this->getContent();

        return $content->sum('qty');
    }

    /**
     * Get the total price of the items in the wishlist.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function total($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        $content = $this->getContent();

        $total = $content->reduce(function ($total, WishlistItem $wishlistItem) {
            return $total + ($wishlistItem->qty * $wishlistItem->priceTax);
        }, 0);

        return $this->numberFormat($total, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the total tax of the items in the wishlist.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return float
     */
    public function tax($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        $content = $this->getContent();

        $tax = $content->reduce(function ($tax, WishlistItem $wishlistItem) {
            return $tax + ($wishlistItem->qty * $wishlistItem->tax);
        }, 0);

        return $this->numberFormat($tax, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the wishlist.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return float
     */
    public function subtotal($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        $content = $this->getContent();

        $subTotal = $content->reduce(function ($subTotal, WishlistItem $wishlistItem) {
            return $subTotal + ($wishlistItem->qty * $wishlistItem->price);
        }, 0);

        return $this->numberFormat($subTotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Search the wishlist content for a wishlist item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function search(Closure $search)
    {
        $content = $this->getContent();

        return $content->filter($search);
    }

    /**
     * Associate the wishlist item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed  $model
     * @return void
     */
    public function associate($rowId, $model)
    {
        if(is_string($model) && ! class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $wishlistItem = $this->get($rowId);

        $wishlistItem->associate($model);

        $content = $this->getContent();

        $content->put($wishlistItem->rowId, $wishlistItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the tax rate for the wishlist item with the given rowId.
     *
     * @param string    $rowId
     * @param int|float $taxRate
     * @return void
     */
    public function setTax($rowId, $taxRate)
    {
        $wishlistItem = $this->get($rowId);

        $wishlistItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($wishlistItem->rowId, $wishlistItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Store an the current instance of the wishlist.
     *
     * @param mixed $identifier
     * @return void
     */
    public function store($identifier)
    {
        $content = $this->getContent();

        if ($this->storedCartWithIdentifierExists($identifier)) {
            throw new CartAlreadyStoredException("A wishlist with identifier {$identifier} was already stored.");
        }

        $this->getConnection()->table($this->getTableName())->insert([
            'identifier' => $identifier,
            'instance' => $this->currentInstance(),
            'content' => serialize($content)
        ]);

        $this->events->fire('wishlist.stored');
    }

    /**
     * Restore the wishlist with the given identifier.
     *
     * @param mixed $identifier
     * @return void
     */
    public function restore($identifier)
    {
        if( ! $this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->first();

        $storedContent = unserialize($stored->content);

        $currentInstance = $this->currentInstance();

        $this->instance($stored->instance);

        $content = $this->getContent();

        foreach ($storedContent as $wishlistItem) {
            $content->put($wishlistItem->rowId, $wishlistItem);
        }

        $this->events->fire('wishlist.restored');

        $this->session->put($this->instance, $content);

        $this->instance($currentInstance);

        $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->delete();
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     * @return float|null
     */
    public function __get($attribute)
    {
        if($attribute === 'total') {
            return $this->total();
        }

        if($attribute === 'tax') {
            return $this->tax();
        }

        if($attribute === 'subtotal') {
            return $this->subtotal();
        }

        return null;
    }

    /**
     * Get the carts content, if there is no wishlist content set yet, return a new empty Collection
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getContent()
    {
        $content = $this->session->has($this->instance)
            ? $this->session->get($this->instance)
            : new Collection;

        return $content;
    }

    /**
     * Create a new WishlistItem from the supplied attributes.
     *
     * @param mixed     $id
     * @param mixed     $name
     * @param int|float $qty
     * @param float     $price
     * @param array     $options
     * @return \Bhavinjr\Shoppingcart\WishlistItem
     */
    private function createWishlistItem($id, $name, $qty, $price, array $options)
    {
        if ($id instanceof Buyable) {
            $wishlistItem = WishlistItem::fromBuyable($id, $qty ?: []);
            $wishlistItem->setQuantity($name ?: 1);
            $wishlistItem->associate($id);
        } elseif (is_array($id)) {
            $wishlistItem = WishlistItem::fromArray($id);
            $wishlistItem->setQuantity($id['qty']);
        } else {
            $wishlistItem = WishlistItem::fromAttributes($id, $name, $price, $options);
            $wishlistItem->setQuantity($qty);
        }

        $wishlistItem->setTaxRate(config('wishlist.tax'));

        return $wishlistItem;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyables.
     *
     * @param mixed $item
     * @return bool
     */
    private function isMulti($item)
    {
        if ( ! is_array($item)) return false;

        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    /**
     * @param $identifier
     * @return bool
     */
    private function storedCartWithIdentifierExists($identifier)
    {
        return $this->getConnection()->table($this->getTableName())->where('identifier', $identifier)->exists();
    }

    /**
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    private function getConnection()
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    private function getTableName()
    {
        return config('wishlist.database.table', 'shoppingcart');
    }

    /**
     * Get the database connection name.
     *
     * @return string
     */
    private function getConnectionName()
    {
        $connection = config('wishlist.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }

    /**
     * Get the Formated number
     *
     * @param $value
     * @param $decimals
     * @param $decimalPoint
     * @param $thousandSeperator
     * @return string
     */
    private function numberFormat($value, $decimals, $decimalPoint, $thousandSeperator)
    {
        if(is_null($decimals)){
            $decimals = is_null(config('wishlist.format.decimals')) ? 2 : config('wishlist.format.decimals');
        }
        if(is_null($decimalPoint)){
            $decimalPoint = is_null(config('wishlist.format.decimal_point')) ? '.' : config('wishlist.format.decimal_point');
        }
        if(is_null($thousandSeperator)){
            $thousandSeperator = is_null(config('wishlist.format.thousand_seperator')) ? ',' : config('wishlist.format.thousand_seperator');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeperator);
    }
}
