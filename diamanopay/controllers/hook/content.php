<?php

class ContentController
{
    public function __construct($module)
    {
        $this->context = Context::getContext();
        $this->module = $module;
    }

    public function handleConfiguration()
    {
        if (Tools::isSubmit('diamano_pay_form')) {
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
            array('name' => 'DIAMANO_PAY_PAYMENT_METHODS', 'label' => $this->module->l('Méthodes de paiements'), 'required' => 'true', 'type' => 'select', 'options'  => array('query' => $paymentMethods, 'id' => 'id_option', 'name' => 'name'), 'hint' => $this->module->l("Sélectionnez les méthodes de paiements à afficher pour le client.")),
            array('name' => 'DIAMANO_PAY_TEST_CLIENT_ID', 'label' => $this->module->l('Client ID en mode sandbox'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_TEST_SECRET_ID', 'label' => $this->module->l('Client Secret en mode sandbox'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_CLIENT_ID', 'label' => $this->module->l('Client ID en mode production'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_SECRET_ID', 'label' => $this->module->l('Client Secret en mode production'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'DIAMANO_PAY_MODE', 'label' => $this->module->l('Mode'), 'required' => 'true', 'type' => 'select', 'options'  => array('query' => $modes, 'id' => 'id_option', 'name' => 'name'), 'hint' => $this->module->l("Si vous êtes encore en mode test, activez cette option,sinon desactivez la")),
            array('name' => 'DIAMANO_PAY_DESCRIPTION', 'required' => 'true', 'label' => $this->module->l('Description à afficher'), 'type' => 'text'),
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->module->l('Configuration de diamano pay'),
                    'icon' => 'icon-wrench'
                ),
                'input' => $inputs,
                'submit' => array('title' => $this->module->l('Sauvegader'))
            )
        );

        $helper = new HelperForm();
        $helper->table = 'diamanopay';
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->submit_action = 'diamano_form';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->module->name . '&tab_module=' . $this->module->tab . '&module_name=' . $this->module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => array(
                'DIAMANO_PAY_PAYMENT_METHODS' => Tools::getValue('DIAMANO_PAY_PAYMENT_METHODS', Configuration::get('DIAMANO_PAY_PAYMENT_METHODS')),
                'DIAMANO_PAY_TEST_CLIENT_ID' => Tools::getValue('DIAMANO_PAY_TEST_CLIENT_ID', Configuration::get('DIAMANO_PAY_TEST_CLIENT_ID')),
                'DIAMANO_PAY_TEST_SECRET_ID' => Tools::getValue('DIAMANO_PAY_TEST_SECRET_ID', Configuration::get('DIAMANO_PAY_TEST_SECRET_ID')),
                'DIAMANO_PAY_CLIENT_ID' => Tools::getValue('DIAMANO_PAY_CLIENT_ID', Configuration::get('DIAMANO_PAY_CLIENT_ID')),
                'DIAMANO_PAY_SECRET_ID' => Tools::getValue('DIAMANO_PAY_SECRET_ID', Configuration::get('DIAMANO_PAY_SECRET_ID')),
                'DIAMANO_PAY_MODE' => Tools::getValue('DIAMANO_PAY_MODE', Configuration::get('DIAMANO_PAY_MODE')),
                'DIAMANO_PAY_DESCRIPTION' => Tools::getValue('DIAMANO_PAY_DESCRIPTION', Configuration::get('DIAMANO_PAY_DESCRIPTION'))
            ),
            'languages' => $this->context->controller->getLanguages()
        );

        $html = $helper->generateForm(array($fields_form));

        return $html;
    }

    public function run()
    {
        $this->handleConfiguration();
        $html_confirmation_message = $this->module->display(__FILE__, 'content.tpl');
        $html_form = $this->renderForm();
        return $html_confirmation_message . $html_form;
    }
}
