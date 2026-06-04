<?php

namespace App\Core;

class WishList
{
    /**
     * The cart items array.
     *
     * @var array
     */
    protected $items = [];

    /**
     * The cart identifier.
     *
     * @var string|int|null
     */
    protected $cartId;

    /**
     * Cart session key for storage.
     *
     * @var string
     */
    protected $sessionKey = 'shopping_cart';

    /**
     * ShoppingCart constructor.
     *
     * @param string|int|null $cartId
     */
    public function __construct($cartId = null)
    {
        $this->cartId = $cartId ?? $this->getSessionId();
        $this->loadCart();
    }

    /**
     * Add an item to the cart.
     *
     * @param array $item
     * @param int $quantity
     * @return $this
     */
    public function add(array $item, int $quantity = 1): self
    {
        $this->validateItem($item);

        $itemId = $this->generateItemId($item);
        
        if (isset($this->items[$itemId])) {
            $quantity += $this->items[$itemId]['quantity'];
            $this->update($itemId, $quantity);
        } else {
            $item['quantity'] = $quantity;
            $item['added_at'] = time();
            $this->items[$itemId] = $item;
            $this->saveCart();
        }

        return $this;
    }

    /**
     * Increment item quantity by a specific amount.
     *
     * @param string $itemId
     * @param int $amount
     * @return $this
     * @throws \RuntimeException
     */
    public function increment(string $itemId, int $quantity = 1): self
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Increment quantity must be greater than 0.');
        }

        if (!isset($this->items[$itemId])) {
            throw new \RuntimeException("Item with ID {$itemId} not found in cart.");
        }

        $newQuantity = $this->items[$itemId]['quantity'] + $quantity;
        return $this->update($itemId, $newQuantity);
    }

    /**
     * Reduce item quantity by a specific amount.
     *
     * @param string $itemId
     * @param int $amount
     * @return $this
     * @throws \RuntimeException
     */
    public function reduce(string $itemId, int $quantity = 1): self
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Reduce quantity must be greater than 0.');
        }

        if (!isset($this->items[$itemId])) {
            throw new \RuntimeException("Item with ID {$itemId} not found in cart.");
        }

        $currentQuantity = $this->items[$itemId]['quantity'];
        $newQuantity = $currentQuantity - $quantity;
        
        if ($newQuantity <= 0) {
            return $this->remove($itemId);
        }

        return $this->update($itemId, $newQuantity);
    }

    /**
     * Update item quantity.
     *
     * @param string $itemId
     * @param int $quantity
     * @return $this
     * @throws \RuntimeException
     */
    public function update(string $itemId, int $quantity): self
    {
        if ($quantity <= 0) {
            return $this->remove($itemId);
        }

        if (!isset($this->items[$itemId])) {
            throw new \RuntimeException("Item with ID {$itemId} not found in cart.");
        }

        $this->items[$itemId]['quantity'] = $quantity;
        $this->items[$itemId]['updated_at'] = time();
        $this->saveCart();

        return $this;
    }

    /**
     * Remove an item from the cart.
     *
     * @param string $itemId
     * @return $this
     */
    public function remove(string $itemId): self
    {
        if (isset($this->items[$itemId])) {
            unset($this->items[$itemId]);
            $this->saveCart();
        }

        return $this;
    }

    /**
     * Get a cart item by ID.
     *
     * @param string $itemId
     * @return array|null
     */
    public function get(string $itemId): ?array
    {
        return $this->items[$itemId] ?? null;
    }

    /**
     * Get all cart items.
     *
     * @return array
     */
    public function all(): array
    {
        $formattedItems = [];
        foreach ($this->items as $itemId => $item) {
            $formattedItems[$itemId] = $this->formatItem($item);
        }
        return $formattedItems;
    }

    /**
     * Get the total number of items in the cart.
     *
     * @return int
     */
    public function count(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item['quantity'];
        }
        return $total;
    }

    /**
     * Get the number of distinct items in the cart.
     *
     * @return int
     */
    public function itemsCount(): int
    {
        return count($this->items);
    }

    /**
     * Calculate the cart subtotal.
     *
     * @return float
     */
    public function subtotal(): float
    {
        $subtotal = 0;
        foreach ($this->items as $item) {
            $price = $item['price'] ?? 0;
            $subtotal += $price * $item['quantity'];
        }
        return $subtotal;
    }

    /**
     * Calculate the cart total with tax.
     *
     * @param float $taxRate
     * @return float
     */
    public function total(float $taxRate = 0): float
    {
        $subtotal = $this->subtotal();
        $tax = $subtotal * ($taxRate / 100);
        
        return $subtotal + $tax;
    }
	
	public function vat(float $taxRate = 0): float
    {
        $subtotal = $this->subtotal();
        $tax = $subtotal * ($taxRate / 100);
        
        return $tax;
    }

    /**
     * Clear all items from the cart.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->items = [];
        $this->saveCart();

        return $this;
    }

    /**
     * Check if the cart is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if the cart has a specific item.
     *
     * @param string $itemId
     * @return bool
     */
    public function has(string $itemId): bool
    {
        return isset($this->items[$itemId]);
    }

    /**
     * Merge another cart into this one.
     *
     * @param ShoppingCart $cart
     * @return $this
     */
    public function merge(self $cart): self
    {
        foreach ($cart->all() as $itemId => $item) {
            if (isset($this->items[$itemId])) {
                $existingQuantity = $this->items[$itemId]['quantity'];
                $newQuantity = $existingQuantity + $item['quantity'];
                $this->update($itemId, $newQuantity);
            } else {
                $this->items[$itemId] = $item;
            }
        }

        $this->saveCart();
        return $this;
    }

    /**
     * Get the cart identifier.
     *
     * @return string|int|null
     */
    public function getCartId()
    {
        return $this->cartId;
    }

    /**
     * Load cart from storage.
     *
     * @return void
     */
    protected function loadCart(): void
    {
        $cartData = $_SESSION[$this->getSessionKey()] ?? [];
        $this->items = is_array($cartData) ? $cartData : [];
    }

    /**
     * Save cart to storage.
     *
     * @return void
     */
    protected function saveCart(): void
    {
        $_SESSION[$this->getSessionKey()] = $this->items;
    }

    /**
     * Get the session key for the cart.
     *
     * @return string
     */
    protected function getSessionKey(): string
    {
        return "{$this->sessionKey}.{$this->cartId}";
    }

    /**
     * Generate a unique ID for the cart item.
     *
     * @param array $item
     * @return string
     */
    protected function generateItemId(array $item): string
    {
        $baseId = $item['id'] ?? ($item['sku'] ?? uniqid('item_', true));
        
        // Include attributes that make the item unique
        $attributes = $item['attributes'] ?? [];
        if (!empty($attributes)) {
            ksort($attributes);
            return $baseId . '_' . md5(serialize($attributes));
        }
        
        return (string) $baseId;
    }

    /**
     * Validate item before adding to cart.
     *
     * @param array $item
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validateItem(array $item): void
    {
        if (empty($item['id'] ?? $item['sku'] ?? null)) {
            throw new \InvalidArgumentException('Cart item must have an ID or SKU.');
        }

        if (!isset($item['price'])) {
            throw new \InvalidArgumentException('Cart item must have a price.');
        }

        if (!is_numeric($item['price']) || $item['price'] < 0) {
            throw new \InvalidArgumentException('Cart item price must be a non-negative number.');
        }
    }

    /**
     * Format item for output.
     *
     * @param array $item
     * @return array
     */
    protected function formatItem(array $item): array
    {
        $item['total'] = ($item['price'] ?? 0) * $item['quantity'];
        return $item;
    }

    /**
     * Get cart summary.
     *
     * @return array
     */
    public function summary(): array
    {
        return [
            'items_count' => $this->itemsCount(),
            'quantity' => $this->count(),
            'subtotal' => $this->subtotal(),
            'total' => $this->total(),
            'items' => $this->all(),
        ];
    }

    /**
     * Convert cart to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'cart_id' => $this->cartId,
            'items' => $this->all(),
            'summary' => $this->summary(),
        ];
    }

    /**
     * Get session ID.
     *
     * @return string
     */
    protected function getSessionId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return session_id();
    }

    /**
     * Initialize session if needed.
     *
     * @return void
     */
    protected function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get item IDs for easier iteration.
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->items);
    }

    /**
     * Apply discount to cart.
     *
     * @param float $discountAmount
     * @param string $type 'fixed' or 'percentage'
     * @return float New total
     */
    public function applyDiscount(float $discountAmount, string $type = 'fixed'): float
    {
        $subtotal = $this->subtotal();
        
        if ($type === 'percentage') {
            $discountAmount = $subtotal * ($discountAmount / 100);
        }
        
        return max(0, $subtotal - $discountAmount);
    }

    /**
     * Search items in cart.
     *
     * @param string $field
     * @param mixed $value
     * @return array
     */
    public function search(string $field, $value): array
    {
        $results = [];
        foreach ($this->items as $itemId => $item) {
            if (isset($item[$field]) && $item[$field] == $value) {
                $results[$itemId] = $this->formatItem($item);
            }
        }
        return $results;
    }

    /**
     * Increment quantity of all items by a specific amount.
     *
     * @param int $amount
     * @return $this
     */
    public function incrementAll(int $amount = 1): self
    {
        foreach ($this->keys() as $itemId) {
            $this->increment($itemId, $amount);
        }
        return $this;
    }

    /**
     * Reduce quantity of all items by a specific amount.
     *
     * @param int $amount
     * @return $this
     */
    public function reduceAll(int $amount = 1): self
    {
        foreach ($this->keys() as $itemId) {
            $this->reduce($itemId, $amount);
        }
        return $this;
    }

    /**
     * Get item quantity.
     *
     * @param string $itemId
     * @return int
     * @throws \RuntimeException
     */
    public function getQuantity(string $itemId): int
    {
        if (!isset($this->items[$itemId])) {
            throw new \RuntimeException("Item with ID {$itemId} not found in cart.");
        }

        return $this->items[$itemId]['quantity'];
    }
}

