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
if (!defined('_PS_VERSION_')) {
    exit;
}
class DiamanoPay extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'diamanopay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = '2dServices';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Diamano Pay');
        $this->description = $this->l('Plateforme de paiement au Sénégal');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l("Aucune devise n'est configurée pour ce module.");
        }
    }

    public function install()
    {
        if (
            !parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')
            || !Configuration::updateValue('DIAMANO_PAY_PAYMENT_METHODS', '')
            || !Configuration::updateValue('DIAMANO_PAY_MODE', '')
            || !Configuration::updateValue('DIAMANO_PAY_TEST_CLIENT_ID', '')
            || !Configuration::updateValue('DIAMANO_PAY_TEST_CLIENT_SECRET', '')
            || !Configuration::updateValue('DIAMANO_PAY_CLIENT_ID', '')
            || !Configuration::updateValue('DIAMANO_PAY_CLIENT_SECRET', '')
            || !Configuration::updateValue('DIAMANO_PAY_DESCRIPTION', '')
        ) {
            return false;
        }
        if (!$this->installOrderState())
            return false;
        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption(),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
    public function getExternalPaymentOption()
    {
        $externalOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $externalOption->setCallToActionText($this->l('Diamano pay'))
            ->setAction($this->context->link->getModuleLink($this->name, 'external', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:diamanopay/views/templates/front/payment_infos.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.png'));

        return $externalOption;
    }

    public function postProcess()
    {
        if (Tools::isSubmit('diamano_pay_form')) {
            $_POST['DIAMANO_PAY_PAYMENT_METHODS'] = Tools::getValue('DIAMANO_PAY_PAYMENT_METHODS') != null ? implode(',', Tools::getValue('DIAMANO_PAY_PAYMENT_METHODS')) : '';
            Configuration::updateValue('DIAMANO_PAY_MODE', Tools::getValue('DIAMANO_PAY_MODE'));
            Configuration::updateValue('DIAMANO_PAY_PAYMENT_METHODS', Tools::getValue('DIAMANO_PAY_PAYMENT_METHODS'));
            Configuration::updateValue('DIAMANO_PAY_TEST_CLIENT_ID', Tools::getValue('DIAMANO_PAY_TEST_CLIENT_ID'));
            Configuration::updateValue('DIAMANO_PAY_TEST_CLIENT_SECRET', Tools::getValue('DIAMANO_PAY_TEST_CLIENT_SECRET'));
            Configuration::updateValue('DIAMANO_PAY_CLIENT_ID', Tools::getValue('DIAMANO_PAY_CLIENT_ID'));
            Configuration::updateValue('DIAMANO_PAY_CLIENT_SECRET', Tools::getValue('DIAMANO_PAY_CLIENT_SECRET'));
            Configuration::updateValue('DIAMANO_PAY_DESCRIPTION', Tools::getValue('DIAMANO_PAY_DESCRIPTION'));
            $this->context->smarty->assign('contentSubmitted', 'ok');
        }
    }
    public function renderForm()
    {
        $modes = array(
            array(
                'id_option' => 'sandbox',
                'name' => 'Sandbox'
            ),
            array(
                'id_option' => 'production',
                'name' => 'Production'
            ),
        );
        $paymentMethods = array(
            array(
                'id_option' => 'ORANGE_MONEY',
                'name' => 'Orange money'
            ),
            array(
                'id_option' => 'WAVE',
                'name' => 'Wave'
            ),
            array(
                'id_option' => 'CARD',
                'name' => 'Carte bancaire'
            ),
        );

        $inputs = array(
            array('name' => 'DIAMANO_PAY_PAYMENT_METHODS[]', 'label' => $this->l('Méthodes de paiements'), 'required' => 'true', 'type' => 'select', 'multiple' => 'true', 'options'  => array('query' => $paymentMethods, 'id' => 'id_option', 'name' => 'name'), 'hint' => $this->l("Sélectionnez les méthodes de paiements à afficher pour le client.")),
            array('name' => 'DIAMANO_PAY_TEST_CLIENT_ID', 'label' => $this->l('Client ID en mode sandbox'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_TEST_CLIENT_SECRET', 'label' => $this->l('Client Secret en mode sandbox'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_CLIENT_ID', 'label' => $this->l('Client ID en mode production'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_CLIENT_SECRET', 'label' => $this->l('Client Secret en mode production'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_MODE', 'label' => $this->l('Mode'), 'required' => 'true', 'type' => 'select', 'options'  => array('query' => $modes, 'id' => 'id_option', 'name' => 'name'), 'hint' => $this->l("Si vous êtes encore en mode test, activez cette option,sinon desactivez la")),
            array('name' => 'DIAMANO_PAY_DESCRIPTION', 'required' => 'true', 'label' => $this->l('Description à afficher'), 'type' => 'text'),
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration de diamano pay'),
                    'icon' => 'icon-wrench'
                ),
                'input' => $inputs,
                'submit' => array('title' => $this->l('Sauvegader'))
            )
        );

        $helper = new HelperForm();
        $helper->table = 'diamanopay';
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->submit_action = 'diamano_pay_form';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => array(
                'DIAMANO_PAY_PAYMENT_METHODS[]' => explode(",", Tools::getValue('DIAMANO_PAY_PAYMENT_METHODS', Configuration::get('DIAMANO_PAY_PAYMENT_METHODS'))),
                'DIAMANO_PAY_TEST_CLIENT_ID' => Tools::getValue('DIAMANO_PAY_TEST_CLIENT_ID', Configuration::get('DIAMANO_PAY_TEST_CLIENT_ID')),
                'DIAMANO_PAY_TEST_CLIENT_SECRET' => Tools::getValue('DIAMANO_PAY_TEST_CLIENT_SECRET', Configuration::get('DIAMANO_PAY_TEST_CLIENT_SECRET')),
                'DIAMANO_PAY_CLIENT_ID' => Tools::getValue('DIAMANO_PAY_CLIENT_ID', Configuration::get('DIAMANO_PAY_CLIENT_ID')),
                'DIAMANO_PAY_CLIENT_SECRET' => Tools::getValue('DIAMANO_PAY_CLIENT_SECRET', Configuration::get('DIAMANO_PAY_CLIENT_SECRET')),
                'DIAMANO_PAY_MODE' => Tools::getValue('DIAMANO_PAY_MODE', Configuration::get('DIAMANO_PAY_MODE')),
                'DIAMANO_PAY_DESCRIPTION' => Tools::getValue('DIAMANO_PAY_DESCRIPTION', Configuration::get('DIAMANO_PAY_DESCRIPTION'))
            ),
            'languages' => $this->context->controller->getLanguages()
        );

        $html = $helper->generateForm(array($fields_form));

        return $html;
    }

    public function getContent()
    {
        $this->postProcess();
        $html_confirmation_message = $this->display(__FILE__, 'content.tpl');
        $html_form = $this->renderForm();
        return $html_confirmation_message . $html_form;
    }

    public function installOrderState()
    {
        if (Configuration::get('PS_OS_DIAMANO_PAY_PAYMENT') < 1) {
            $order_state = new OrderState();
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->invoice = false;
            $order_state->color = '#4179AF';
            $order_state->logable = true;
            $order_state->shipped = false;
            $order_state->unremovable = false;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            $order_state->name = array((int)Configuration::get('PS_LANG_DEFAULT') => pSQL($this->l('Diamano pay - En attente de paiement')));
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue('PS_OS_DIAMANO_PAY_PAYMENT', $order_state->id);
                // We copy the module logo in order state logo directory
                copy(dirname(__FILE__) . '/logo.gif', dirname(__FILE__) . '/../../img/os/' . $order_state->id . '.gif');
                copy(dirname(__FILE__) . '/logo.gif', dirname(__FILE__) . '/../../img/tmp/order_state_mini_' . $order_state->id . '.gif');
            } else
                return false;
        }
        return true;
    }
}
