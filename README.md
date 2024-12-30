# Jvdh Min Order Amount

This Magento 2 module enforces a **minimum order amount** requirement for both standard and multishipping checkout flows. It provides the flexibility to configure different minimum thresholds for various customer groups by storing those thresholds in JSON format in the Magento admin panel.

## Features
1. **Configurable Minimum Thresholds**  
   - Allows different minimums based on **customer group** (e.g., wholesale vs. retail).
   - Settings are retrieved from the module’s configuration paths.

2. **Error Messaging**  
   - Displays a custom error message if the customer’s cart doesn’t meet the minimum amount.  
   - Supports placeholder substitutions (`-conf-` for the threshold; `-cart-` for the cart total).

3. **Currency Conversion**  
   - Automatically converts the cart total if the store’s base currency differs from the user’s current currency.

4. **Multishipping Support**  
   - Includes an around-plugin for the **Multishipping checkout** (i.e., `Magento\Multishipping\Controller\Checkout\Shipping`) to ensure the minimum is enforced there as well.

## Components

1. **Helper/Data.php**  
   - Fetches configuration settings:
     - **isEnabled()**: Whether the module is active.  
     - **minCartAmount()**: Retrieves the JSON defining minimum thresholds by customer group.  
     - **minCartAmountMsg()**: Retrieves the error message template.  
     - **itemTotal()**: Retrieves the cart’s total (with discounts).

2. **Plugin/Controller/Cart/Index/RestrictOrderAmount.php**  
   - An *around-plugin* on `\Magento\Checkout\Controller\Index\Index::execute()`.  
   - Compares the cart total against the configured threshold; displays an error and redirects to the cart if below the minimum.

3. **Plugin/Controller/Cart/Index/RestrictOrderMulti.php**  
   - An *around-plugin* on `\Magento\Multishipping\Controller\Checkout\Shipping::execute()`.  
   - Prevents customers from proceeding with multishipping checkout if the cart total is too low, redirecting them back to the multishipping address selection page.

## Configuration

1. **Enable/Disable**  
   - In the Admin panel, navigate to:  
     `Stores > Configuration > Min Order Amount > General > Enable/Disable`  
   - Choose **Yes** to enable the module.

2. **Minimum Cart Amount (JSON)**  
   - In the same section, set the **Minimum Cart Amount** field to a JSON array.  
   - For example:  
     ```json
     [
       { "customer_group": 1, "active": 50 },
       { "customer_group": 2, "active": 75 }
     ]
     ```
     This indicates that **customer group 1** needs a minimum of **$50**, and **customer group 2** needs **$75**.

3. **Minimum Amount Message**  
   - Placeholders:
     - `-conf-` is replaced with the threshold value.  
     - `-cart-` is replaced with the current cart total.  
   - Example template:  
     ```text
     Your current cart total is -cart-, but the required minimum is -conf-.
     ```

## Usage
- Once configured, any time a customer attempts to checkout (standard or multishipping), the code will:
  1. Determine the **customer’s group**.
  2. Compare the **cart total** against the **configured threshold** for that group.
  3. **Redirect** back to the cart or multishipping addresses page if the total is too low, showing a custom error.

## Notes
- Be sure to test edge cases, such as **guest checkout** (group ID = 0) or **logged-in** users with special discounts.
- The module uses `$this->helper->itemTotal()` which relies on **`base_subtotal_with_discount`**. If your store needs to consider additional factors (tax, shipping, etc.), you may need to adjust the logic or extend it accordingly.
- If using multiple store views or currencies, the module attempts currency conversion where necessary.

## License
This module is provided under the MIT License—see the [LICENSE](./LICENSE) file for details.

## Author
Developed by me.

## Contact
For questions, improvements, or customizations, please reach out via [LinkedIn profile](https://www.linkedin.com/in/jonasvdh/).