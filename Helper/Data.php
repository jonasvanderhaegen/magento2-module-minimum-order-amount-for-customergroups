<?php

namespace Jvdh\MinOrderAmount\Helper;

use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Data
 *
 * This helper class provides the core functionality for enforcing a minimum order amount:
 *  - Checking if the feature is enabled
 *  - Retrieving the configured minimum cart amount
 *  - Getting a custom message to display when the cart amount is too low
 *  - Determining the current cart subtotal (with discounts)
 */
class Data extends AbstractHelper
{
    /**
     * Path to the enable/disable config setting in Magento:
     * "Stores > Configuration > Min Order Amount > General > Enable/Disable"
     */
    private const ENABLE_DISABLE = 'minorderamount/general/enable_disable';

    /**
     * Path to the minimum cart amount config setting:
     * "Stores > Configuration > Min Order Amount > General > Minimum Cart Amount"
     */
    private const MINCARTAMOUNT = 'minorderamount/general/minamount';

    /**
     * Path to the custom message config setting:
     * "Stores > Configuration > Min Order Amount > General > Minimum Amount Message"
     */
    private const MINCARTAMOUNTMSG = 'minorderamount/general/minamountMsg';

    /**
     * @param Cart    $cart    Magento cart model used to access the quote and its totals
     * @param Context $context Framework helper context for configuration and logging
     */
    public function __construct(
        protected Cart $cart,
        Context $context
    ) {
        // Call parent constructor to ensure correct initialization
        parent::__construct($context);
    }

    /**
     * Determines if the Min Order Amount feature is enabled.
     *
     * NOTE: getValue() returns a string (e.g., "1" or "0").
     * If you need an actual boolean, consider using isSetFlag().
     *
     * @return bool True if enabled, otherwise false
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->getValue(
            self::ENABLE_DISABLE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Retrieves the minimum cart amount that must be reached
     * before the customer can proceed with checkout.
     *
     * @return string  e.g. "100" if the admin set 100 as the minimum amount
     */
    public function minCartAmount(): string
    {
        return $this->scopeConfig->getValue(
            self::MINCARTAMOUNT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Retrieves a custom message to display when the cart total
     * is below the configured threshold.
     *
     * @return string e.g. "Your order must be at least $100."
     */
    public function minCartAmountMsg(): string
    {
        return $this->scopeConfig->getValue(
            self::MINCARTAMOUNTMSG,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Returns the current cart subtotal with discounts applied,
     * cast to an integer. If precision is important (e.g., 100.50),
     * consider returning a float instead of int.
     *
     * @return int The cart subtotal (with discount) as an integer
     */
    public function itemTotal(): int
    {
        return (int)$this->cart->getQuote()->getBaseSubtotalWithDiscount();
    }
}
