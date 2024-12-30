<?php

namespace Jvdh\MinOrderAmount\Model\Plugin\Controller\Cart\Index;

use Jvdh\MinOrderAmount\Helper\Data;
use Magento\Customer\Model\Session;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Multishipping\Controller\Checkout\Shipping;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class RestrictOrderMulti
 *
 * This around-plugin intercepts the "Multishipping Checkout Shipping" step to ensure
 * that a minimum order amount requirement is satisfied before the customer can proceed.
 * If the requirement is not met, the plugin redirects the user back to the multishipping
 * address selection page with an error message.
 */
class RestrictOrderMulti
{
    /**
     * The URL model is used to generate redirects. It's assigned dynamically in aroundExecute.
     * 
     * @var \Magento\Framework\Url|null
     */
    protected $urlModel;

    /**
     * Constructor
     *
     * @param Data                  $helper          Contains logic to retrieve configuration and the cart total
     * @param Session               $customerSession Handles customer session (logged in, group ID, etc.)
     * @param RedirectFactory       $redirectFactory Factory to create Magento redirect responses
     * @param ManagerInterface      $messageManager  Handles adding messages (errors, notices) to the session
     * @param CurrencyFactory       $currencyFactory Loads currency info for conversion
     * @param StoreManagerInterface $storeManager    Provides store-specific data, including base/current currency
     */
    public function __construct(
        private Data $helper,
        private Session $customerSession,
        private RedirectFactory $redirectFactory,
        private ManagerInterface $messageManager,
        private CurrencyFactory $currencyFactory,
        private StoreManagerInterface $storeManager
    ) {
    }

    /**
     * aroundExecute
     *
     * Wraps around the \Magento\Multishipping\Controller\Checkout\Shipping::execute() method.
     * It checks if the MinOrderAmount feature is enabled, compares the current cart total
     * against a minimum threshold determined by the customer's group, and redirects back
     * to the multishipping addresses page if the total is insufficient.
     *
     * @param  Shipping $subject  The original multishipping shipping controller
     * @param  \Closure $proceed  Callback to execute the original controller method
     * @return mixed              Redirect if minimum not met, otherwise proceeds normally
     */
    public function aroundExecute(
        Shipping $subject,
        \Closure $proceed
    ): mixed {
        // Only apply restrictions if the feature is enabled in admin settings
        if ($this->helper->isEnabled()) {
            // Default threshold set to 0, will be replaced if a matching group is found
            $thresholdmin = 0;

            // Determine customer group ID (or 0 if not logged in)
            $val = $this->customerSession->isLoggedIn()
                ? $this->customerSession->getCustomer()->getGroupId()
                : 0;

            // Get current cart subtotal (with discount) from our helper
            $itemAmount = $this->helper->itemTotal();

            // Get the minimum amounts from config, parsed from JSON.
            // Example JSON structure: [{"customer_group": "3", "active": 100}, ...]
            $minAmountData = $this->helper->minCartAmount();
            $dataDecode = json_decode($minAmountData);

            // Check if there's a matching customer group in the config data
            foreach ($dataDecode as $value) {
                if ($val == $value->customer_group) {
                    // Use the "active" property as the threshold for this group
                    $thresholdmin = $value->active;
                    break;
                }
            }

            try {
                // Retrieve the base currency and the currently selected currency codes
                $currencyCodeTo = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
                $currencyCodeFrom = $this->storeManager->getStore()->getBaseCurrency()->getCode();

                // If viewing in a different currency, convert the cart amount
                if ($currencyCodeTo != $currencyCodeFrom) {
                    $rate = $this->currencyFactory->create()
                        ->load($currencyCodeTo)
                        ->getAnyRate($currencyCodeFrom);

                    $itemAmount *= $rate;
                }
            } catch (\Exception $exception) {
                // Log/Display any currency conversion errors
                $this->messageManager->addErrorMessage($exception->getMessage());
            }

            // If item amount is below the threshold, block progression
            if ($thresholdmin >= 0 && $itemAmount >= 0 && $itemAmount < $thresholdmin) {
                // Retrieve the configured error message and substitute placeholders
                $minAmountMsg = $this->helper->minCartAmountMsg();
                $messages = str_replace("-conf-", $thresholdmin, $minAmountMsg);
                $message = str_replace("-cart-", (string) $itemAmount, $messages);

                // Display the error message
                $this->messageManager->addErrorMessage($message);

                // Redirect user back to the multishipping addresses page
                $this->urlModel = $this->urlModel->create(); // This seems a bit off; normally you'd inject a UrlFactory instance
                $defaultUrl = $this->urlModel->getUrl('*/*/addresses', ['_secure' => true]);
                $resultRedirect = $this->redirectFactory->create();
                return $resultRedirect->setUrl($defaultUrl);
            }
        }

        // If requirements are met or feature is disabled, proceed as normal
        return $proceed();
    }
}
