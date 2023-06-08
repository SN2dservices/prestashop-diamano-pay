<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class DiamanoPayExternalModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function initContent()
    {
        // Call parent init content method
        parent::initContent();
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'diamanopay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $paymentUrl = $this->getPaymentPage();
        $order_total_amount = $cart->getOrderTotal(true, Cart::BOTH);
        $currency = $this->context->currency;
        $extra_vars = array(
            '{total_to_pay}' => Tools::displayPrice($order_total_amount)
        );
        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_DIAMANO_PAY_PAYMENT'),
            $order_total_amount,
            $this->module->displayName,
            NULL,
            $extra_vars,
            (int)$currency->id,
            false,
            $cart->secure_key
        );
        Tools::redirect($paymentUrl);
        die();
    }

    private function getPaymentPage()
    {
        $mode = Configuration::get('DIAMANO_PAY_MODE');
        $paymentMethods = explode(",", Configuration::get('DIAMANO_PAY_PAYMENT_METHODS'));
        $client_id = $mode == 'sandbox' ? Configuration::get('DIAMANO_PAY_TEST_CLIENT_ID') : Configuration::get('DIAMANO_PAY_CLIENT_ID');
        $client_secret = $mode == 'sandbox' ? Configuration::get('DIAMANO_PAY_TEST_CLIENT_SECRET') : Configuration::get('DIAMANO_PAY_CLIENT_SECRET');
        $url = $mode == 'sandbox' ? 'https://sandbox-api.diamanopay.com' : 'https://api.diamanopay.com';
        $cart = $this->context->cart;
        $order_cart_id = $cart->id;
        $order_total_amount = $cart->getOrderTotal(true, Cart::BOTH);
        $url .= '/api/payment/cms/paymentToken?clientId=' . $client_id . '&clientSecret=' . $client_secret;
        $webhook = $this->context->link->getModuleLink('diamanopay', 'validationipn');
        $order_return_url = $this->context->link->getPageLink('order-confirmation', null, null, 'key=' . $cart->secure_key . '&id_cart=' . $cart->id . '&id_module=' . $this->module->id);
        $body = array(
            'amount' => (float)$order_total_amount,
            'webhook' => $webhook,
            'callbackSuccessUrl' => $order_return_url,
            'callbackCancelUrl' => Tools::getHttpHost(true) . __PS_BASE_URI__,
            'paymentMethods' => $paymentMethods,
            'description' => $this->getDescription($cart->getProducts(true)),
            'extraData' => array("cart_id" => $order_cart_id, "cart_secure_key" => $cart->secure_key, "total" => (float)$order_total_amount)
        );
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($body))
        ));

        $response = json_decode(curl_exec($ch), true);
        if ($response["statusCode"] != null && $response["statusCode"] != "200") {
            die($response["message"]);
        }

        return $response["paymentUrl"];
    }

    public function getDescription($items)
    {
        $description = "";
        foreach ($items  as $item) {
            $product_name   = $item['name'];
            $item_quantity  = $item['cart_quantity'];
            $item_total     = $item['total'];
            $description .= 'Nom du produit: ' . $product_name . ' | Quantité: ' . $item_quantity . ' | total éléments: ' . number_format($item_total, 2) . '\n';
        }
        return $description;
    }
}
