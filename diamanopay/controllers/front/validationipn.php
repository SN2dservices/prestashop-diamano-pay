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
class DiamanoPayValidationIPNModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $data = file_get_contents('php://input');
        $json = json_decode($data, true);
        if ($json != null) {
            $extraData = $json['extraData'];
            if ($extraData != null && $extraData['cart_id'] != null) {
                $cart_id = $extraData['cart_id'];
                $cart = new Cart((int)$cart_id);
                if (
                    $cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
                    $cart->id_address_invoice == 0 || !$this->module->active
                )
                    die('Invalid cart');
                // Check if customer exists
                $customer = new Customer($cart->id_customer);
                if (!Validate::isLoadedObject($customer))
                    die('Invalid customer');
                $paymentStatus = $this->getPaymentStatus($_GET['token']);
                $order_id = (int)Order::getOrderByCartId($cart_id);
                $objOrder = new Order($order_id);
                if ($paymentStatus['status'] === "SUCCESS") {
                    $objOrder->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                } else if ($paymentStatus['status'] === "FAILED") {
                    $objOrder->setCurrentState(Configuration::get('PS_OS_ERROR'));
                }
                die(json_encode($paymentStatus));
            }
        }
        die(json_encode($_POST));
    }
    private function getPaymentStatus($paymentToken)
    {
        $mode = Configuration::get('DIAMANO_PAY_MODE');
        $client_id = $mode == 'sandbox' ? Configuration::get('DIAMANO_PAY_TEST_CLIENT_ID') : Configuration::get('DIAMANO_PAY_CLIENT_ID');
        $client_secret = $mode == 'sandbox' ? Configuration::get('DIAMANO_PAY_TEST_CLIENT_SECRET') : Configuration::get('DIAMANO_PAY_CLIENT_SECRET');
        $url = $mode == 'sandbox' ? 'https://sandbox-api.diamanopay.com' : 'https://api.diamanopay.com';
        $url .= '/api/payment/cms/paymentStatus?clientId=' . $client_id . '&clientSecret=' . $client_secret . '&token=' . $paymentToken;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = json_decode(curl_exec($ch), true);
        if ($response["statusCode"] != null && $response["statusCode"] != "200") {
            die($response["message"]);
        }
        return $response;
    }
}
