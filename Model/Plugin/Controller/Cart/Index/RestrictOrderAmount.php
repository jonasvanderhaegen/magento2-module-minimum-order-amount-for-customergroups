<?php

namespace Jvdh\MinOrderAmount\Model\Plugin\Controller\Cart\Index;

use Magento\Framework\Controller\Result\Redirect;
use Jvdh\MinOrderAmount\Helper\Data;
use Magento\Checkout\Controller\Index\Index;
use Magento\Customer\Model\Session;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class RestrictOrderAmount
 *
 * This around-plugin intercepts the normal flow of the Checkout Cart Index controller (i.e., the
 * display of the cart page) and checks if a minimum cart amount requirement is satisfied.
 *
 * If the requirement is not met, the plugin:
 *  - Displays an error message informing the user of the shortfall
 *  - Redirects the customer back to the cart page, preventing progression
 *
 * Configuration paths (minimum amounts, messages) are defined in the Jvdh\MinOrderAmount\Helper\Data class.
 */
class RestrictOrderAmount
{
    /**
     * Used to build URLs for redirects, assigned during the aroundExecute method.
     * 
     * @var \Magento\Framework\Url
     */
    private $urlModel;

    /**
     * Constructor
     *
     * @param UrlFactory            $urlFactory     Factory to create URL instances
     * @param Data                  $helper         Custom helper containing module configs and item total logic
     * @param Session               $customerSession Magento customer session model
     * @param RedirectFactory       $redirectFactory Factory to create redirect responses
     * @param ManagerInterface      $messageManager  Allows adding success/error messages to the session
     * @param CurrencyFactory       $currencyFactory Factory for currency conversion rates
     * @param StoreManagerInterface $storeManager   Manages store (currency, etc.) for the current scope
     */
    public function __construct(
        private UrlFactory $urlFactory,
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
     * This plugin method wraps around the normal `execute()` call in the
     * Magento\Checkout\Controller\Index\Index class. It allows us to run
     * custom logic before and (optionally) after the core controller executes.
     *
     * @param  Index    $subject  The original Cart Index controller object
     * @param  \Closure $proceed  A callback to execute the original method
     * @return mixed              The result of the core method, or a redirect if insufficient order amount
     */
    public function aroundExecute(
        Index $subject,
        \Closure $proceed
    ): mixed {
        // Only proceed with our checks if the MinOrderAmount feature is enabled
        if ($this->helper->isEnabled()) {
            // Default threshold to 0, which may be overwritten if a matching customer group is found
            $thresholdmin = 0;

            // Get the logged-in customer's group ID or default to 0 if not logged in
            $val = $this->customerSession->isLoggedIn() 
                ? $this->customerSession->getCustomer()->getGroupId() 
                : 0;

            // Current cart total (base subtotal with discount, from the helper)
            $itemAmount = $this->helper->itemTotal();

            // Retrieve the configured minimum amounts and parse as JSON
            // This structure might look like: [{"customergroup_id": "1", "active": 100}, ...]
            $minAmountData = $this->helper->minCartAmount();
            $dataDecode = json_decode($minAmountData);

            // Loop through the decoded JSON to find the matching group
            foreach ($dataDecode as $value) {
                if ($val == $value->customergroup_id) {
                    // Once found, store the "active" property as the threshold
                    $thresholdmin = $value->active;
                    break;
                }
            }

            try {
                // Retrieve the store's base and current currency codes
                $currencyCodeTo = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
                $currencyCodeFrom = $this->storeManager->getStore()->getBaseCurrency()->getCode();

                // If the customer is viewing in a different currency, convert the itemAmount accordingly
                if ($currencyCodeTo != $currencyCodeFrom) {
                    $rate = $this->currencyFactory
                        ->create()
                        ->load($currencyCodeTo)
                        ->getAnyRate($currencyCodeFrom);

                    $itemAmount *= $rate;
                }
            } catch (\Exception $exception) {
                // If something goes wrong retrieving rates, log or display the error
                $this->messageManager->addErrorMessage($exception->getMessage());
            }

            // If the threshold and item amount are both non-negative, and itemAmount is below threshold
            if ($thresholdmin >= 0 && $itemAmount >= 0 && $itemAmount < $thresholdmin) {
                // Retrieve the custom error message from config
                // e.g. "Your cart amount is -cart- but minimum required is -conf-."
                $minAmountMsg = $this->helper->minCartAmountMsg();

                // Replace placeholders with actual threshold and cart amounts
                $messages = str_replace("-conf-", $thresholdmin, $minAmountMsg);
                $message = str_replace("-cart-", (string) $itemAmount, $messages);

                // Display the error message
                $this->messageManager->addErrorMessage($message);

                // Redirect user back to the cart page
                $this->urlModel = $this->urlFactory->create();
                $defaultUrl = $this->urlModel->getUrl('*/cart/index', ['_secure' => true]);

                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->redirectFactory->create();
                return $resultRedirect->setUrl($defaultUrl);
            }
        }

        // If everything is fine or the module isn't enabled, proceed with the default controller execution
        return $proceed();
    }
}
