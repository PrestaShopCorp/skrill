<?php
/**
* 2015 Skrill
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
*  @author    Skrill <contact@skrill.com>
*  @copyright 2015 Skrill
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of Skrill
*/

if (!class_exists('SkrillCustomMailAlert')) {
    require_once(dirname(__FILE__).'/SkrillCustomMailAlert.php');
}
require_once(dirname(__FILE__).'/core/core.php');

class Skrill extends PaymentModule
{
    private $html = '';
    public $processedStatus = '2';
    public $pendingStatus = '0';
    public $failedStatus = '-2';
    public $refundedStatus = '-4';
    public $refundFailedStatus = '-5';

    public function __construct()
    {
        $this->name = 'skrill';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.6';
        $this->author = 'Skrill';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = 'Skrill';
        $this->description = 'Accepts payments by Skrill';
        if ($this->l('BACKEND_TT_DELETE_DETAILS') == "BACKEND_TT_DELETE_DETAILS") {
            $this->confirmUninstall =  "Are you sure you want to delete your details ?";
        } else {
            $this->confirmUninstall = $this->l('BACKEND_TT_DELETE_DETAILS');
        }
    }

    public function install()
    {
        $this->warning = null;
        if (is_null($this->warning) && !function_exists('curl_init')) {
            if ($this->l('ERROR_MESSAGE_CURL_REQUIRED') == "ERROR_MESSAGE_CURL_REQUIRED") {
                $this->warning = "cURL is required to use this module. Please install the php extention cURL.";
            } else {
                $this->warning = $this->l('ERROR_MESSAGE_CURL_REQUIRED');
            }
        }
        if (is_null($this->warning)
            && !(parent::install()
            && $this->registerHook('payment')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('updateOrderStatus')
            && $this->registerHook('displayInvoice')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('displayPaymentTop'))) {
            if ($this->l('ERROR_MESSAGE_INSTALL_MODULE') == "ERROR_MESSAGE_INSTALL_MODULE") {
                $this->warning = "There was an Error installing the module.";
            } else {
                $this->warning = $this->l('ERROR_MESSAGE_INSTALL_MODULE');
            }
        }
        if (is_null($this->warning) && !$this->createOrderRefTables()) {
            if ($this->l('ERROR_MESSAGE_CREATE_TABLE') == "ERROR_MESSAGE_CREATE_TABLE") {
                $this->warning = "There was an Error creating a custom table.";
            } else {
                $this->warning = $this->l('ERROR_MESSAGE_CREATE_TABLE');
            }
        }
        //default configuration for skrill at first install

        // default skrill setting.
        Configuration::updateValue('SKRILL_GENERAL_MERCHANTID', '');
        Configuration::updateValue('SKRILL_GENERAL_MERCHANTACCOUNT', '');
        Configuration::updateValue('SKRILL_GENERAL_RECIPENT', '');
        Configuration::updateValue('SKRILL_GENERAL_LOGOURL', '');
        Configuration::updateValue('SKRILL_GENERAL_SHOPURL', '');
        Configuration::updateValue('SKRILL_GENERAL_APIPASS', '');
        Configuration::updateValue('SKRILL_GENERAL_SECRETWORD', '');
        Configuration::updateValue('SKRILL_GENERAL_DISPLAY', '');

        // default skrill flexible
        Configuration::updateValue('SKRILL_FLEXIBLE_ACTIVE', '0');
        
        $defaultSort = 1;
        foreach (array_keys(SkrillPaymentCore::getPaymentMethods()) as $paymentType) {
            //default enable payment grop
            if ($paymentType == 'VSA' || $paymentType == 'MSC' ||
             $paymentType == 'AMX' || $paymentType == 'DIN' || $paymentType == 'JCB') {
                Configuration::updateValue('SKRILL_'.$paymentType.'_ACTIVE', '0');
            } else {
                Configuration::updateValue('SKRILL_'.$paymentType.'_ACTIVE', '1');
            }
            //default payment mode
            if ($paymentType != 'FLEXIBLE') {
                Configuration::updateValue('SKRILL_'.$paymentType.'_MODE', '1');
            }
            // default sort order payment.
            Configuration::updateValue('SKRILL_'.$paymentType.'_SORT', $defaultSort);
            $defaultSort++;
        }
        
        return is_null($this->warning);
    }

    public function uninstall()
    {
        $sql= "DROP TABLE IF EXISTS skrill_order_ref";
        if (!Db::getInstance()->Execute($sql)) {
            return false;
        }
        $sql = "DELETE FROM `"._DB_PREFIX_."order_state` WHERE `module_name`='skrill'";
        if (!Db::getInstance()->Execute($sql)) {
            return false;
        }
        if (!Configuration::deleteByName('SKRILL_GENERAL_MERCHANTID')
            || !Configuration::deleteByName('SKRILL_GENERAL_MERCHANTACCOUNT')
            || !Configuration::deleteByName('SKRILL_GENERAL_RECIPENT')
            || !Configuration::deleteByName('SKRILL_GENERAL_LOGOURL')
            || !Configuration::deleteByName('SKRILL_GENERAL_SHOPURL')
            || !Configuration::deleteByName('SKRILL_GENERAL_APIPASS')
            || !Configuration::deleteByName('SKRILL_GENERAL_SECRETWORD')
            || !Configuration::deleteByName('SKRILL_GENERAL_DISPLAY')

            || !Configuration::deleteByName('SKRILL_FLEXIBLE_ACTIVE')
            || !Configuration::deleteByName('SKRILL_WLT_ACTIVE')
            || !Configuration::deleteByName('SKRILL_PSC_ACTIVE')
            || !Configuration::deleteByName('SKRILL_ACC_ACTIVE')
            || !Configuration::deleteByName('SKRILL_VSA_ACTIVE')
            || !Configuration::deleteByName('SKRILL_MSC_ACTIVE')
            || !Configuration::deleteByName('SKRILL_VSE_ACTIVE')
            || !Configuration::deleteByName('SKRILL_MAE_ACTIVE')
            || !Configuration::deleteByName('SKRILL_AMX_ACTIVE')
            || !Configuration::deleteByName('SKRILL_DIN_ACTIVE')
            || !Configuration::deleteByName('SKRILL_JCB_ACTIVE')
            || !Configuration::deleteByName('SKRILL_GCB_ACTIVE')
            || !Configuration::deleteByName('SKRILL_DNK_ACTIVE')
            || !Configuration::deleteByName('SKRILL_PSP_ACTIVE')
            || !Configuration::deleteByName('SKRILL_CSI_ACTIVE')
            || !Configuration::deleteByName('SKRILL_OBT_ACTIVE')
            || !Configuration::deleteByName('SKRILL_GIR_ACTIVE')
            || !Configuration::deleteByName('SKRILL_DID_ACTIVE')
            || !Configuration::deleteByName('SKRILL_SFT_ACTIVE')
            || !Configuration::deleteByName('SKRILL_EBT_ACTIVE')
            || !Configuration::deleteByName('SKRILL_IDL_ACTIVE')
            || !Configuration::deleteByName('SKRILL_NPY_ACTIVE')
            || !Configuration::deleteByName('SKRILL_PLI_ACTIVE')
            || !Configuration::deleteByName('SKRILL_PWY_ACTIVE')
            || !Configuration::deleteByName('SKRILL_EPY_ACTIVE')
            || !Configuration::deleteByName('SKRILL_GLU_ACTIVE')
            || !Configuration::deleteByName('SKRILL_ALI_ACTIVE')
            || !Configuration::deleteByName('SKRILL_NTL_ACTIVE')

            || !Configuration::deleteByName('SKRILL_WLT_MODE')
            || !Configuration::deleteByName('SKRILL_PSC_MODE')
            || !Configuration::deleteByName('SKRILL_ACC_MODE')
            || !Configuration::deleteByName('SKRILL_VSA_MODE')
            || !Configuration::deleteByName('SKRILL_MSC_MODE')
            || !Configuration::deleteByName('SKRILL_VSE_MODE')
            || !Configuration::deleteByName('SKRILL_MAE_MODE')
            || !Configuration::deleteByName('SKRILL_AMX_MODE')
            || !Configuration::deleteByName('SKRILL_DIN_MODE')
            || !Configuration::deleteByName('SKRILL_JCB_MODE')
            || !Configuration::deleteByName('SKRILL_GCB_MODE')
            || !Configuration::deleteByName('SKRILL_DNK_MODE')
            || !Configuration::deleteByName('SKRILL_PSP_MODE')
            || !Configuration::deleteByName('SKRILL_CSI_MODE')
            || !Configuration::deleteByName('SKRILL_OBT_MODE')
            || !Configuration::deleteByName('SKRILL_GIR_MODE')
            || !Configuration::deleteByName('SKRILL_DID_MODE')
            || !Configuration::deleteByName('SKRILL_SFT_MODE')
            || !Configuration::deleteByName('SKRILL_EBT_MODE')
            || !Configuration::deleteByName('SKRILL_IDL_MODE')
            || !Configuration::deleteByName('SKRILL_NPY_MODE')
            || !Configuration::deleteByName('SKRILL_PLI_MODE')
            || !Configuration::deleteByName('SKRILL_PWY_MODE')
            || !Configuration::deleteByName('SKRILL_EPY_MODE')
            || !Configuration::deleteByName('SKRILL_GLU_MODE')
            || !Configuration::deleteByName('SKRILL_ALI_MODE')
            || !Configuration::deleteByName('SKRILL_NTL_MODE')

            || !Configuration::deleteByName('SKRILL_FLEXIBLE_SORT')
            || !Configuration::deleteByName('SKRILL_WLT_SORT')
            || !Configuration::deleteByName('SKRILL_PSC_SORT')
            || !Configuration::deleteByName('SKRILL_ACC_SORT')
            || !Configuration::deleteByName('SKRILL_VSA_SORT')
            || !Configuration::deleteByName('SKRILL_MSC_SORT')
            || !Configuration::deleteByName('SKRILL_VSE_SORT')
            || !Configuration::deleteByName('SKRILL_MAE_SORT')
            || !Configuration::deleteByName('SKRILL_AMX_SORT')
            || !Configuration::deleteByName('SKRILL_DIN_SORT')
            || !Configuration::deleteByName('SKRILL_JCB_SORT')
            || !Configuration::deleteByName('SKRILL_GCB_SORT')
            || !Configuration::deleteByName('SKRILL_DNK_SORT')
            || !Configuration::deleteByName('SKRILL_PSP_SORT')
            || !Configuration::deleteByName('SKRILL_CSI_SORT')
            || !Configuration::deleteByName('SKRILL_OBT_SORT')
            || !Configuration::deleteByName('SKRILL_GIR_SORT')
            || !Configuration::deleteByName('SKRILL_DID_SORT')
            || !Configuration::deleteByName('SKRILL_SFT_SORT')
            || !Configuration::deleteByName('SKRILL_EBT_SORT')
            || !Configuration::deleteByName('SKRILL_IDL_SORT')
            || !Configuration::deleteByName('SKRILL_NPY_SORT')
            || !Configuration::deleteByName('SKRILL_PLI_SORT')
            || !Configuration::deleteByName('SKRILL_PWY_SORT')
            || !Configuration::deleteByName('SKRILL_EPY_SORT')
            || !Configuration::deleteByName('SKRILL_GLU_SORT')
            || !Configuration::deleteByName('SKRILL_ALI_SORT')
            || !Configuration::deleteByName('SKRILL_NTL_SORT')

            || !$this->unregisterHook('payment')
            || !$this->unregisterHook('paymentReturn')
            || !$this->unregisterHook('updateOrderStatus')
            || !$this->unregisterHook('displayInvoice')
            || !$this->unregisterHook('displayAdminOrder')
            || !$this->unregisterHook('displayPaymentTop')
            || !parent::uninstall()) {
                return false;
        }
        return true;
    }

    public function createOrderRefTables()
    {
        $sql= "CREATE TABLE IF NOT EXISTS `skrill_order_ref`(
            `id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `id_order` INT(10) NOT NULL,
            `transaction_id` VARCHAR(32) NOT NULL,
            `payment_method` VARCHAR(50) NOT NULL,
            `order_status` VARCHAR(50) NOT NULL,
            `ref_id` VARCHAR(32) NOT NULL,
            `payment_code` VARCHAR(8) NOT NULL,
            `currency` VARCHAR(3) NOT NULL,
			`amount` decimal(17,2) NOT NULL,
			`add_information` LONGTEXT NULL)";

        if (!Db::getInstance()->Execute($sql)) {
            return false;
        }
        return true;
    }

    public function getSkrillCredentials()
    {
        $credentials = array();
        $credentials['email'] = Configuration::get('SKRILL_GENERAL_MERCHANTACCOUNT');
        $credentials['password'] = Configuration::get('SKRILL_GENERAL_APIPASS');

        return $credentials;
    }

    public function getVersionData()
    {
        $versionData = array();
        $versionData['transaction_mode'] = 'LIVE';
        $versionData['ip_address'] = $_SERVER['SERVER_ADDR'];
        $versionData['shop_version'] = _PS_VERSION_;
        $versionData['plugin_version'] = $this->version;
        $versionData['client'] = 'Skrill';
        $versionData['email'] = Configuration::get('SKRILL_GENERAL_MERCHANTACCOUNT');
        $versionData['merchant_id'] = Configuration::get('SKRILL_GENERAL_MERCHANTID');
        $versionData['shop_system'] = 'Prestashop';
        $versionData['shop_url'] = Configuration::get('SKRILL_GENERAL_SHOPURL');
        return $versionData;
    }

    public function hookdisplayAdminOrder()
    {
        $orderId =Tools::getValue('id_order');
        $sql = "SELECT * FROM skrill_order_ref WHERE id_order ='".(int)$orderId."'";
        $row = Db::getInstance()->getRow($sql);
        if ($row) {
            $paymentInfo = array();
            $paymentInfo['name'] = $this->getFrontendPaymentLocale($row['payment_method']);
            $isSkrill = strpos($paymentInfo['name'], 'Skrill');
            if ($isSkrill === false) {
                $paymentInfo['name'] = 'Skrill '.$paymentInfo['name'];
            }
            $trnStatus = SkrillPaymentCore::getTrnStatus($row['order_status']);
            $paymentInfo['status'] = $this->getTrnStatusLocale($trnStatus);
            $paymentInfo['method'] = $this->getFrontendPaymentLocale('SKRILL_FRONTEND_PM_'.$row['payment_code']);
            $paymentInfo['currency'] = $row['currency'];

            $additionalInformation = $this->getAdditionalInformation($row['add_information']);
            $langId = Context::getContext()->language->id;
            $orderOriginId = $this->getCountryIdByIso($additionalInformation['SKRILL_BACKEND_ORDER_ORIGIN']);
            $paymentInfo['order_origin'] = Country::getNameById($langId, $orderOriginId);
            $getCountryIso2 = SkrillPaymentCore::getCountryIso2($additionalInformation['SKRILL_BACKEND_ORDER_COUNTRY']);
            $orderCountryId = $this->getCountryIdByIso($getCountryIso2);
            $paymentInfo['order_country'] = Country::getNameById($langId, $orderCountryId);

            $buttonUpdateOrder = ($row['order_status'] == $this->refundedStatus) ? false : true;

            $this->context->smarty->assign(array(
                'orderId'		=> (int)$orderId,
                'paymentInfo' => $paymentInfo,
                'buttonUpdateOrder'	=> $buttonUpdateOrder
            ));

            return $this->display(__FILE__, 'views/templates/hook/displayAdminOrder.tpl');
        }
        return '';
    }

    private function getAdditionalInformation($serializeAdditionalInfo)
    {
        $additionalInformation = false;
        if ($serializeAdditionalInfo) {
            $unserializeAdditionalInfo = unserialize($serializeAdditionalInfo);
            foreach ($unserializeAdditionalInfo as $addInfo) {
                $explodeAddInfo = explode('=>', $addInfo);
                $additionalInformation[$explodeAddInfo[0]] = $explodeAddInfo[1];
            }
        }

        return $additionalInformation;
    }

    private function getCountryIdByIso($countryIso2)
    {
        $sql = "SELECT `id_country` FROM `"._DB_PREFIX_."country` WHERE `iso_code` = '".pSQL($countryIso2)."'";
        $result = Db::getInstance()->getRow($sql);
        return (int)$result['id_country'];
    }

    public function hookdisplayInvoice()
    {
        $orderId =Tools::getValue('id_order');
        $order = new Order((int)$orderId);
        $successMessage = '';
        $errorMessage = '';

        if (isset($this->context->cookie->skrill_status_refund)) {
            if ($this->context->cookie->skrill_status_refund) {
                $successMessage = "refund";
            } else {
                $errorMessage = "refund";
            }
            unset ($this->context->cookie->skrill_status_refund);
        }
        $sql = "SELECT * FROM skrill_order_ref WHERE id_order ='".(int)$orderId."'";
        $row = Db::getInstance()->getRow($sql);
        
        if ($row) {
            if (Tools::isSubmit('skrillUpdateOrder') && $row['order_status'] != $this->refundedStatus) {
                $fieldParams = $this->getSkrillCredentials();
                $fieldParams['type'] = 'mb_trn_id';
                $fieldParams['id'] = $row['ref_id'];
                $paymentResult = '';
                $isPaymentAccepted = SkrillPaymentCore::isPaymentAccepted($fieldParams, $paymentResult);

                if ($isPaymentAccepted) {
                    $responseUpdateOrder = SkrillPaymentCore::getResponseArray($paymentResult);
                    $this->updateTransLogStatus($row['ref_id'], $responseUpdateOrder['status']);
                    $successMessage = "updateOrder";

                    $sql = "SELECT * FROM skrill_order_ref WHERE id_order ='".(int)$orderId."'";
                    $row = Db::getInstance()->getRow($sql);
                } else {
                    $errorMessage = "updateOrder";
                }
            }
        }
        $this->context->smarty->assign(array(
           'module' => $order->module,
           'successMessage' => $successMessage,
           'errorMessage' => $errorMessage
        ));

        return $this->display(__FILE__, 'views/templates/hook/displayStatusOrder.tpl');
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return ;
        }

        foreach ($params['cart']->getProducts() as $product) {
            $pd = ProductDownload::getIdFromIdProduct((int)($product['id_product']));
            if ($pd and Validate::isUnsignedInt($pd)) {
                return false;
            }
        }

        $address = new Address((int)$this->context->cart->id_address_delivery);
        $country = new Country($address->id_country);
        $countryCode = $country->iso_code;
    
        $this->context->smarty->assign(array(
           'payments'			=> $this->getEnabledPayment($countryCode),
           'this_path' 		=> $this->_path,
           'this_path_ssl' 	=> Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    private function getEnabledPayment($countryCode)
    {
        $countryCode3Digit = SkrillPaymentCore::getCountryIso3($countryCode);
        $notSupportCountries = SkrillPaymentCore::isCountryNotSupport($countryCode3Digit);
        $supportedPayments = SkrillPaymentCore::getSupportedPayments($countryCode3Digit);

        $paymentsConfig = array();
        if (Configuration::get('SKRILL_FLEXIBLE_ACTIVE')) {
            $paymentsConfig[0] = array(
                'name' => 'flexible',
                'enabled' => '1'
            );
        }

        $defaultPaymentSort = 1000;
        foreach (array_keys(SkrillPaymentCore::getPaymentMethods()) as $paymentType) {
            if ($paymentType == 'FLEXIBLE') {
                continue;
            }
            $paymentActive = Configuration::get('SKRILL_'.$paymentType.'_ACTIVE');
            $paymentShowSeparatly = Configuration::get('SKRILL_'.$paymentType.'_MODE');
            $supportedPayment = in_array($paymentType, $supportedPayments);
            $paymentSort = (int)Configuration::get('SKRILL_'.$paymentType.'_SORT');
            if (!$paymentSort || $paymentSort == 0 || array_key_exists($paymentSort, $paymentsConfig)) {
                $paymentSort = $defaultPaymentSort;
            }
            if ($paymentActive && $paymentShowSeparatly && $notSupportCountries == false && $supportedPayment) {
                $paymentsConfig[$paymentSort] = array(
                   'name' => Tools::strtolower($paymentType),
                   'enabled' => $paymentActive
                );
            }
            $defaultPaymentSort++;
        }
        ksort($paymentsConfig);

        return $paymentsConfig;
    }

    /**
	 * @return string
	 */
    public function hookdisplayPaymentTop()
    {
        if (!$this->active || !Tools::getValue('skrillerror')) {
            return;
        }

        $errorMessage = $this->getLocaleErrorMapping(Tools::getValue('skrillerror'));

        $this->context->smarty->assign(array(
           'errorMessage' => $errorMessage,
           'module' => "skrill"
        ));

        return $this->display(__FILE__, 'error.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['objOrder']->getCurrentState();
        $template = '';
        if ($state == Configuration::get('PS_OS_PAYMENT')) {
            $this->smarty->assign(array(
               'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
               'status' => 'ok',
               'id_order' => $params['objOrder']->id
            ));
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                 $this->smarty->assign('reference', $params['objOrder']->reference);
            }
            $status='SUCCESFUL';
            $template='order_successful';

            $this->mailAlert($params['objOrder'], $this->context->cookie->skrill_paymentName, $status, $template);

        }

        unset($this->context->cookie->skrill_paymentName);
        
        return $this->display(__FILE__, 'payment_return.tpl');

    }

    public function refundOrder($params, &$refId, $refundType = 'refund')
    {
        if ($refundType != "fraud") {
            $sql = "SELECT * FROM skrill_order_ref WHERE id_order ='".(int)$params['id_order']."'";
            $row = Db::getInstance()->getRow($sql);
        }

        $refId = $row['ref_id'] ? $row['ref_id'] : $params['mb_transaction_id'];
        $fieldParams = $this->getSkrillCredentials();
        $fieldParams['mb_transaction_id'] = $row['ref_id']? $row['ref_id'] : $params['mb_transaction_id'];
        $fieldParams['amount'] = $row['amount']? $row['amount'] : $params['amount'];

        $refundResult = SkrillPaymentCore::doRefund('prepare', $fieldParams);
        $sid = (string) $refundResult->sid;

        $refundResult = SkrillPaymentCore::doRefund('refund', $sid);

        if (!$refundResult) {
            $refundResult['status'] = "error";
        }
        
        return $refundResult;
    }

    public function hookUpdateOrderStatus($params)
    {
        $order = new Order((int)($params['id_order']));

        if ($order->module == "skrill" && $order->current_state == Configuration::get('PS_OS_PAYMENT')
            && $params['newOrderStatus']->id == Configuration::get('PS_OS_REFUND')) {
            $refId = '';
            $refundResult = $this->refundOrder($params, $refId, $order);

            $refundStatus = (string) $refundResult->status;

            if ($refundStatus == $this->processedStatus) {
                $status = 'REFUND';
                $template = 'order_refund';
                $this->updateTransLogStatus($refId, $this->refundedStatus);
                $this->mailAlert($order, $order->payment, $status, $template);
                $this->context->cookie->skrill_status_refund = true;
            } else {
                $this->updateTransLogStatus($refId, $this->refundFailedStatus);
                $this->context->cookie->skrill_status_refund = false;
                $getAdminLink = $this->context->link->getAdminLink('AdminOrders');
                $getViewOrder = $getAdminLink.'&vieworder&id_order='.$params['id_order'];
                Tools::redirectAdmin($getViewOrder);
            }
        }
    }

    public function updateTransLogStatus($refId, $orderStatus)
    {
        $sql = "UPDATE skrill_order_ref SET order_status = '".pSQL($orderStatus)."' where ref_id = '".pSQL($refId)."'";
        if (!Db::getInstance()->execute($sql)) {
            die('Erreur etc.');
        }
    }

    private function getFrontendPaymentLocale($paymentMethod)
    {
        switch ($paymentMethod) {
            case 'SKRILL_FRONTEND_PM_FLEXIBLE':
                if ($this->l('SKRILL_FRONTEND_PM_FLEXIBLE') == "SKRILL_FRONTEND_PM_FLEXIBLE") {
                    $paymentLocale = "Pay By Skrill";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_FLEXIBLE');
                }
                break;
            case 'SKRILL_FRONTEND_PM_WLT':
                if ($this->l('SKRILL_FRONTEND_PM_WLT') == "SKRILL_FRONTEND_PM_WLT") {
                    $paymentLocale = "Skrill Wallet";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_WLT');
                }
                break;
            case 'SKRILL_FRONTEND_PM_PSC':
                if ($this->l('SKRILL_FRONTEND_PM_PSC') == "SKRILL_FRONTEND_PM_PSC") {
                    $paymentLocale = "Paysafecard";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_PSC');
                }
                break;
            case 'SKRILL_FRONTEND_PM_ACC':
                if ($this->l('SKRILL_FRONTEND_PM_ACC') == "SKRILL_FRONTEND_PM_ACC") {
                    $paymentLocale = "Credit Card / Visa, Mastercard, AMEX, JCB, Diners";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_ACC');
                }
                break;
            case 'SKRILL_FRONTEND_PM_VSA':
                if ($this->l('SKRILL_FRONTEND_PM_VSA') == "SKRILL_FRONTEND_PM_VSA") {
                    $paymentLocale = "Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_VSA');
                }
                break;
            case 'SKRILL_FRONTEND_PM_MSC':
                if ($this->l('SKRILL_FRONTEND_PM_MSC') == "SKRILL_FRONTEND_PM_MSC") {
                    $paymentLocale = "MasterCard";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_MSC');
                }
                break;
            case 'SKRILL_FRONTEND_PM_VSE':
                if ($this->l('SKRILL_FRONTEND_PM_VSE') == "SKRILL_FRONTEND_PM_VSE") {
                    $paymentLocale = "Visa Electron";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_VSE');
                }
                break;
            case 'SKRILL_FRONTEND_PM_MAE':
                if ($this->l('SKRILL_FRONTEND_PM_MAE') == "SKRILL_FRONTEND_PM_MAE") {
                    $paymentLocale = "Maestro";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_MAE');
                }
                break;
            case 'SKRILL_FRONTEND_PM_AMX':
                if ($this->l('SKRILL_FRONTEND_PM_AMX') == "SKRILL_FRONTEND_PM_AMX") {
                    $paymentLocale = "American Express";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_AMX');
                }
                break;
            case 'SKRILL_FRONTEND_PM_DIN':
                if ($this->l('SKRILL_FRONTEND_PM_DIN') == "SKRILL_FRONTEND_PM_DIN") {
                    $paymentLocale = "Diners";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_DIN');
                }
                break;
            case 'SKRILL_FRONTEND_PM_JCB':
                if ($this->l('SKRILL_FRONTEND_PM_JCB') == "SKRILL_FRONTEND_PM_JCB") {
                    $paymentLocale = "JCB";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_JCB');
                }
                break;
            case 'SKRILL_FRONTEND_PM_GCB':
                if ($this->l('SKRILL_FRONTEND_PM_GCB') == "SKRILL_FRONTEND_PM_GCB") {
                    $paymentLocale = "Carte Bleue by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_GCB');
                }
                break;
            case 'SKRILL_FRONTEND_PM_DNK':
                if ($this->l('SKRILL_FRONTEND_PM_DNK') == "SKRILL_FRONTEND_PM_DNK") {
                    $paymentLocale = "Dankort by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_DNK');
                }
                break;
            case 'SKRILL_FRONTEND_PM_PSP':
                if ($this->l('SKRILL_FRONTEND_PM_PSP') == "SKRILL_FRONTEND_PM_PSP") {
                    $paymentLocale = "PostePay by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_PSP');
                }
                break;
            case 'SKRILL_FRONTEND_PM_CSI':
                if ($this->l('SKRILL_FRONTEND_PM_CSI') == "SKRILL_FRONTEND_PM_CSI") {
                    $paymentLocale = "CartaSi by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_CSI');
                }
                break;
            case 'SKRILL_FRONTEND_PM_OBT':
                if ($this->l('SKRILL_FRONTEND_PM_OBT') == "SKRILL_FRONTEND_PM_OBT") {
                    $paymentLocale = "Skrill Direct (Online Bank Transfer)";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_OBT');
                }
                break;
            case 'SKRILL_FRONTEND_PM_GIR':
                if ($this->l('SKRILL_FRONTEND_PM_GIR') == "SKRILL_FRONTEND_PM_GIR") {
                    $paymentLocale = "Giropay";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_GIR');
                }
                break;
            case 'SKRILL_FRONTEND_PM_DID':
                if ($this->l('SKRILL_FRONTEND_PM_DID') == "SKRILL_FRONTEND_PM_DID") {
                    $paymentLocale = "Direct Debit / ELV";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_DID');
                }
                break;
            case 'SKRILL_FRONTEND_PM_SFT':
                if ($this->l('SKRILL_FRONTEND_PM_SFT') == "SKRILL_FRONTEND_PM_SFT") {
                    $paymentLocale = "Sofortueberweisung";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_SFT');
                }
                break;
            case 'SKRILL_FRONTEND_PM_EBT':
                if ($this->l('SKRILL_FRONTEND_PM_EBT') == "SKRILL_FRONTEND_PM_EBT") {
                    $paymentLocale = "Nordea Solo";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_EBT');
                }
                break;
            case 'SKRILL_FRONTEND_PM_IDL':
                if ($this->l('SKRILL_FRONTEND_PM_IDL') == "SKRILL_FRONTEND_PM_IDL") {
                    $paymentLocale = "iDEAL";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_IDL');
                }
                break;
            case 'SKRILL_FRONTEND_PM_NPY':
                if ($this->l('SKRILL_FRONTEND_PM_NPY') == "SKRILL_FRONTEND_PM_NPY") {
                    $paymentLocale = "EPS (Netpay)";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_NPY');
                }
                break;
            case 'SKRILL_FRONTEND_PM_PLI':
                if ($this->l('SKRILL_FRONTEND_PM_PLI') == "SKRILL_FRONTEND_PM_PLI") {
                    $paymentLocale = "POLi";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_PLI');
                }
                break;
            case 'SKRILL_FRONTEND_PM_PWY':
                if ($this->l('SKRILL_FRONTEND_PM_PWY') == "SKRILL_FRONTEND_PM_PWY") {
                    $paymentLocale = "Przelewy24";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_PWY');
                }
                break;
            case 'SKRILL_FRONTEND_PM_EPY':
                if ($this->l('SKRILL_FRONTEND_PM_EPY') == "SKRILL_FRONTEND_PM_EPY") {
                    $paymentLocale = "ePay.bg";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_EPY');
                }
                break;
            case 'SKRILL_FRONTEND_PM_GLU':
                if ($this->l('SKRILL_FRONTEND_PM_GLU') == "SKRILL_FRONTEND_PM_GLU") {
                    $paymentLocale = "Trustly";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_GLU');
                }
                break;
            case 'SKRILL_FRONTEND_PM_ALI':
                if ($this->l('SKRILL_FRONTEND_PM_ALI') == "SKRILL_FRONTEND_PM_ALI") {
                    $paymentLocale = "Alipay";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_ALI');
                }
                break;
            case 'SKRILL_FRONTEND_PM_NTL':
                if ($this->l('SKRILL_FRONTEND_PM_NTL') == "SKRILL_FRONTEND_PM_NTL") {
                    $paymentLocale = "Neteller";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_NTL');
                }
                break;
            default:
                if ($this->l('SKRILL_FRONTEND_PM_FLEXIBLE') == "SKRILL_FRONTEND_PM_FLEXIBLE") {
                    $paymentLocale = "All Cards and Alternative Payment Methods";
                } else {
                    $paymentLocale = $this->l('SKRILL_FRONTEND_PM_FLEXIBLE');
                }
                break;
        }
        
        return $paymentLocale;
    }

    private function getBackendPaymentLocale($paymentMethod)
    {
        switch ($paymentMethod) {
            case 'SKRILL_BACKEND_PM_FLEXIBLE':
                if ($this->l('SKRILL_BACKEND_PM_FLEXIBLE') == "SKRILL_BACKEND_PM_FLEXIBLE") {
                    $paymentLocale = "All Cards and Alternative Payment Methods";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_FLEXIBLE');
                }
                break;
            case 'SKRILL_BACKEND_PM_WLT':
                if ($this->l('SKRILL_BACKEND_PM_WLT') == "SKRILL_BACKEND_PM_WLT") {
                    $paymentLocale = "Skrill Wallet";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_WLT');
                }
                break;
            case 'SKRILL_BACKEND_PM_PSC':
                if ($this->l('SKRILL_BACKEND_PM_PSC') == "SKRILL_BACKEND_PM_PSC") {
                    $paymentLocale = "Paysafecard";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_PSC');
                }
                break;
            case 'SKRILL_BACKEND_PM_ACC':
                if ($this->l('SKRILL_BACKEND_PM_ACC') == "SKRILL_BACKEND_PM_ACC") {
                    $paymentLocale = "Credit Card / Visa, Mastercard, AMEX, JCB, Diners";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_ACC');
                }
                break;
            case 'SKRILL_BACKEND_PM_VSA':
                if ($this->l('SKRILL_BACKEND_PM_VSA') == "SKRILL_BACKEND_PM_VSA") {
                    $paymentLocale = "Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_VSA');
                }
                break;
            case 'SKRILL_BACKEND_PM_MSC':
                if ($this->l('SKRILL_BACKEND_PM_MSC') == "SKRILL_BACKEND_PM_MSC") {
                    $paymentLocale = "MasterCard";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_MSC');
                }
                break;
            case 'SKRILL_BACKEND_PM_VSE':
                if ($this->l('SKRILL_BACKEND_PM_VSE') == "SKRILL_BACKEND_PM_VSE") {
                    $paymentLocale = "Visa Electron";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_VSE');
                }
                break;
            case 'SKRILL_BACKEND_PM_MAE':
                if ($this->l('SKRILL_BACKEND_PM_MAE') == "SKRILL_BACKEND_PM_MAE") {
                    $paymentLocale = "Maestro";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_MAE');
                }
                break;
            case 'SKRILL_BACKEND_PM_AMX':
                if ($this->l('SKRILL_BACKEND_PM_AMX') == "SKRILL_BACKEND_PM_AMX") {
                    $paymentLocale = "American Express";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_AMX');
                }
                break;
            case 'SKRILL_BACKEND_PM_DIN':
                if ($this->l('SKRILL_BACKEND_PM_DIN') == "SKRILL_BACKEND_PM_DIN") {
                    $paymentLocale = "Diners";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_DIN');
                }
                break;
            case 'SKRILL_BACKEND_PM_JCB':
                if ($this->l('SKRILL_BACKEND_PM_JCB') == "SKRILL_BACKEND_PM_JCB") {
                    $paymentLocale = "JCB";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_JCB');
                }
                break;
            case 'SKRILL_BACKEND_PM_GCB':
                if ($this->l('SKRILL_BACKEND_PM_GCB') == "SKRILL_BACKEND_PM_GCB") {
                    $paymentLocale = "Carte Bleue by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_GCB');
                }
                break;
            case 'SKRILL_BACKEND_PM_DNK':
                if ($this->l('SKRILL_BACKEND_PM_DNK') == "SKRILL_BACKEND_PM_DNK") {
                    $paymentLocale = "Dankort by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_DNK');
                }
                break;
            case 'SKRILL_BACKEND_PM_PSP':
                if ($this->l('SKRILL_BACKEND_PM_PSP') == "SKRILL_BACKEND_PM_PSP") {
                    $paymentLocale = "PostePay by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_PSP');
                }
                break;
            case 'SKRILL_BACKEND_PM_CSI':
                if ($this->l('SKRILL_BACKEND_PM_CSI') == "SKRILL_BACKEND_PM_CSI") {
                    $paymentLocale = "CartaSi by Visa";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_CSI');
                }
                break;
            case 'SKRILL_BACKEND_PM_OBT':
                if ($this->l('SKRILL_BACKEND_PM_OBT') == "SKRILL_BACKEND_PM_OBT") {
                    $paymentLocale = "Skrill Direct (Online Bank Transfer)";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_OBT');
                }
                break;
            case 'SKRILL_BACKEND_PM_GIR':
                if ($this->l('SKRILL_BACKEND_PM_GIR') == "SKRILL_BACKEND_PM_GIR") {
                    $paymentLocale = "Giropay";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_GIR');
                }
                break;
            case 'SKRILL_BACKEND_PM_DID':
                if ($this->l('SKRILL_BACKEND_PM_DID') == "SKRILL_BACKEND_PM_DID") {
                    $paymentLocale = "Direct Debit / ELV";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_DID');
                }
                break;
            case 'SKRILL_BACKEND_PM_SFT':
                if ($this->l('SKRILL_BACKEND_PM_SFT') == "SKRILL_BACKEND_PM_SFT") {
                    $paymentLocale = "Sofortueberweisung";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_SFT');
                }
                break;
            case 'SKRILL_BACKEND_PM_EBT':
                if ($this->l('SKRILL_BACKEND_PM_EBT') == "SKRILL_BACKEND_PM_EBT") {
                    $paymentLocale = "Nordea Solo";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_EBT');
                }
                break;
            case 'SKRILL_BACKEND_PM_IDL':
                if ($this->l('SKRILL_BACKEND_PM_IDL') == "SKRILL_BACKEND_PM_IDL") {
                    $paymentLocale = "iDEAL";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_IDL');
                }
                break;
            case 'SKRILL_BACKEND_PM_NPY':
                if ($this->l('SKRILL_BACKEND_PM_NPY') == "SKRILL_BACKEND_PM_NPY") {
                    $paymentLocale = "EPS (Netpay)";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_NPY');
                }
                break;
            case 'SKRILL_BACKEND_PM_PLI':
                if ($this->l('SKRILL_BACKEND_PM_PLI') == "SKRILL_BACKEND_PM_PLI") {
                    $paymentLocale = "POLi";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_PLI');
                }
                break;
            case 'SKRILL_BACKEND_PM_PWY':
                if ($this->l('SKRILL_BACKEND_PM_PWY') == "SKRILL_BACKEND_PM_PWY") {
                    $paymentLocale = "Przelewy24";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_PWY');
                }
                break;
            case 'SKRILL_BACKEND_PM_EPY':
                if ($this->l('SKRILL_BACKEND_PM_EPY') == "SKRILL_BACKEND_PM_EPY") {
                    $paymentLocale = "ePay.bg";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_EPY');
                }
                break;
            case 'SKRILL_BACKEND_PM_GLU':
                if ($this->l('SKRILL_BACKEND_PM_GLU') == "SKRILL_BACKEND_PM_GLU") {
                    $paymentLocale = "Trustly";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_GLU');
                }
                break;
            case 'SKRILL_BACKEND_PM_ALI':
                if ($this->l('SKRILL_BACKEND_PM_ALI') == "SKRILL_BACKEND_PM_ALI") {
                    $paymentLocale = "Alipay";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_ALI');
                }
                break;
            case 'SKRILL_BACKEND_PM_NTL':
                if ($this->l('SKRILL_BACKEND_PM_NTL') == "SKRILL_BACKEND_PM_NTL") {
                    $paymentLocale = "Neteller";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_NTL');
                }
                break;
            default:
                if ($this->l('SKRILL_BACKEND_PM_FLEXIBLE') == "SKRILL_BACKEND_PM_FLEXIBLE") {
                    $paymentLocale = "All Cards and Alternative Payment Methods";
                } else {
                    $paymentLocale = $this->l('SKRILL_BACKEND_PM_FLEXIBLE');
                }
                break;
        }
        
        return $paymentLocale;
    }

    private function getTrnStatusLocale($status)
    {
        switch ($status) {
            case 'BACKEND_TT_PROCESSED':
                if ($this->l('BACKEND_TT_PROCESSED') == "BACKEND_TT_PROCESSED") {
                    $trnStatus = "Processed";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_PROCESSED');
                }
                break;
            case 'BACKEND_TT_PENDING':
                if ($this->l('BACKEND_TT_PENDING') == "BACKEND_TT_PENDING") {
                    $trnStatus = "Pending";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_PENDING');
                }
                break;
            case 'BACKEND_TT_CANCELLED':
                if ($this->l('BACKEND_TT_CANCELLED') == "BACKEND_TT_CANCELLED") {
                    $trnStatus = "Cancelled";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_CANCELLED');
                }
                break;
            case 'BACKEND_TT_FAILED':
                if ($this->l('BACKEND_TT_FAILED') == "BACKEND_TT_FAILED") {
                    $trnStatus = "Failed";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_FAILED');
                }
                break;
            case 'BACKEND_TT_CHARGEBACK':
                if ($this->l('BACKEND_TT_CHARGEBACK') == "BACKEND_TT_CHARGEBACK") {
                    $trnStatus = "Chargeback";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_CHARGEBACK');
                }
                break;
            case 'BACKEND_TT_REFUNDED':
                if ($this->l('BACKEND_TT_REFUNDED') == "BACKEND_TT_REFUNDED") {
                    $trnStatus = "Refund successfull";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_REFUNDED');
                }
                break;
            case 'BACKEND_TT_REFUNDED_FAILED':
                if ($this->l('BACKEND_TT_REFUNDED_FAILED') == "BACKEND_TT_REFUNDED_FAILED") {
                    $trnStatus = "Refund failed";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_REFUNDED_FAILED');
                }
                break;
            case 'BACKEND_TT_FRAUD':
                if ($this->l('BACKEND_TT_FRAUD') == "BACKEND_TT_FRAUD") {
                    $trnStatus = "was considered fraudulent";
                } else {
                    $trnStatus = $this->l('BACKEND_TT_FRAUD');
                }
                break;
            case 'ERROR_GENERAL_ABANDONED_BYUSER':
                if ($this->l('ERROR_GENERAL_ABANDONED_BYUSER') == "ERROR_GENERAL_ABANDONED_BYUSER") {
                    $trnStatus = "Abandoned by user";
                } else {
                    $trnStatus = $this->l('ERROR_GENERAL_ABANDONED_BYUSER');
                }
                break;
            default:
                if ($this->l('ERROR_GENERAL_ABANDONED_BYUSER') == "ERROR_GENERAL_ABANDONED_BYUSER") {
                    $trnStatus = "Abandoned by user";
                } else {
                    $trnStatus = $this->l('ERROR_GENERAL_ABANDONED_BYUSER');
                }
                break;
        }

        return $trnStatus;
    }

    public function getLocaleErrorMapping($errorIdentifier)
    {
        //$error_identifier = SkrillPaymentCore::getSkrillErrorMapping($code);

        switch ($errorIdentifier) {
            case 'ERROR_GENERAL_NORESPONSE':
                if ($this->l('ERROR_GENERAL_NORESPONSE') == "ERROR_GENERAL_NORESPONSE") {
                    $returnMessage = "Unfortunately, the confirmation of your payment failed.
                    Please contact your merchant for clarification.";
                } else {
                    $returnMessage = $this->l('ERROR_GENERAL_NORESPONSE');
                }
                break;
            case 'ERROR_GENERAL_FRAUD_DETECTION':
                if ($this->l('ERROR_GENERAL_FRAUD_DETECTION') == "ERROR_GENERAL_FRAUD_DETECTION") {
                    $returnMessage = "Unfortunately, there was an error while processing your order.
                    In case a payment has been made, it will be automatically refunded.";
                } else {
                    $returnMessage = $this->l('ERROR_GENERAL_FRAUD_DETECTION');
                }
                break;
            case 'SKRILL_ERROR_01':
                if ($this->l('SKRILL_ERROR_01') == "SKRILL_ERROR_01") {
                    $returnMessage = "Referred by card issuer";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_01');
                }
                break;
            case 'SKRILL_ERROR_02':
                if ($this->l('SKRILL_ERROR_02') == "SKRILL_ERROR_02") {
                    $returnMessage = "Invalid Merchant";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_02');
                }
                break;
            case 'SKRILL_ERROR_03':
                if ($this->l('SKRILL_ERROR_03') == "SKRILL_ERROR_03") {
                    $returnMessage = "Stolen card";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_03');
                }
                break;
            case 'SKRILL_ERROR_04':
                if ($this->l('SKRILL_ERROR_04') == "SKRILL_ERROR_04") {
                    $returnMessage = "Declined by customer's Card Issuer";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_04');
                }
                break;
            case 'SKRILL_ERROR_05':
                if ($this->l('SKRILL_ERROR_05') == "SKRILL_ERROR_05") {
                    $returnMessage = "Insufficient funds";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_05');
                }
                break;
            case 'SKRILL_ERROR_08':
                if ($this->l('SKRILL_ERROR_08') == "SKRILL_ERROR_08") {
                    $returnMessage = "PIN tries exceeded - card blocked";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_08');
                }
                break;
            case 'SKRILL_ERROR_09':
                if ($this->l('SKRILL_ERROR_09') == "SKRILL_ERROR_09") {
                    $returnMessage = "Invalid Transaction";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_09');
                }
                break;
            case 'SKRILL_ERROR_10':
                if ($this->l('SKRILL_ERROR_10') == "SKRILL_ERROR_10") {
                    $returnMessage = "Transaction frequency limit exceeded";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_10');
                }
                break;
            case 'SKRILL_ERROR_12':
                if ($this->l('SKRILL_ERROR_12') == "SKRILL_ERROR_12") {
                    $returnMessage = "Invalid credit card or bank account";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_12');
                }
                break;
            case 'SKRILL_ERROR_15':
                if ($this->l('SKRILL_ERROR_15') == "SKRILL_ERROR_15") {
                    $returnMessage = "Duplicate transaction";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_15');
                }
                break;
            case 'SKRILL_ERROR_19':
                if ($this->l('SKRILL_ERROR_19') == "SKRILL_ERROR_19") {
                    $returnMessage = "Unknown failure reason. Try again";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_19');
                }
                break;
            case 'SKRILL_ERROR_24':
                if ($this->l('SKRILL_ERROR_24') == "SKRILL_ERROR_24") {
                    $returnMessage = "Card expired";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_24');
                }
                break;
            case 'SKRILL_ERROR_28':
                if ($this->l('SKRILL_ERROR_28') == "SKRILL_ERROR_28") {
                    $returnMessage = "Lost/Stolen card";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_28');
                }
                break;
            case 'SKRILL_ERROR_32':
                if ($this->l('SKRILL_ERROR_32') == "SKRILL_ERROR_32") {
                    $returnMessage = "Card Security Code check failed";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_32');
                }
                break;
            case 'SKRILL_ERROR_37':
                if ($this->l('SKRILL_ERROR_37') == "SKRILL_ERROR_37") {
                    $returnMessage = "Card restricted by card issuer";
                } else {
                    $returnMessage = $this->l('SKRILL_ERROR_37');
                }
                break;
            case 'SKRILL_ERROR_38':
                if ($this->l('SKRILL_ERROR_38') == "SKRILL_ERROR_38") {
                    $returnMessage = "Security violation";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_38');
                }
                break;
            case 'SKRILL_ERROR_42':
                if ($this->l('SKRILL_ERROR_42') == "SKRILL_ERROR_42") {
                    $returnMessage = "Card blocked by card issuer";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_42');
                }
                break;
            case 'SKRILL_ERROR_44':
                if ($this->l('SKRILL_ERROR_44') == "SKRILL_ERROR_44") {
                    $returnMessage = "Customer's issuing bank not available";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_44');
                }
                break;
            case 'SKRILL_ERROR_51':
                if ($this->l('SKRILL_ERROR_51') == "SKRILL_ERROR_51") {
                    $returnMessage = "Processing system error";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_51');
                }
                break;
            case 'SKRILL_ERROR_63':
                if ($this->l('SKRILL_ERROR_63') == "SKRILL_ERROR_63") {
                    $returnMessage = "Transaction not permitted to cardholder ";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_63');
                }
                break;
            case 'SKRILL_ERROR_70':
                if ($this->l('SKRILL_ERROR_70') == "SKRILL_ERROR_70") {
                    $returnMessage = "Customer failed to complete 3DS";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_70');
                }
                break;
            case 'SKRILL_ERROR_71':
                if ($this->l('SKRILL_ERROR_71') == "SKRILL_ERROR_71") {
                    $returnMessage = "Customer failed SMS verification";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_71');
                }
                break;
            case 'SKRILL_ERROR_80':
                if ($this->l('SKRILL_ERROR_80') == "SKRILL_ERROR_80") {
                    $returnMessage = "Fraud engine declined";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_80');
                }
                break;
            case 'SKRILL_ERROR_98':
                if ($this->l('SKRILL_ERROR_98') == "SKRILL_ERROR_98") {
                    $returnMessage = "Error in communication with provider";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_98');
                }
                break;
            case 'SKRILL_ERROR_99_GENERAL':
                if ($this->l('SKRILL_ERROR_99_GENERAL') == "SKRILL_ERROR_99_GENERAL") {
                    $returnMessage = "Failure reason not specified";
                } else {
                    $returnMessage =  $this->l('SKRILL_ERROR_99_GENERAL');
                }
                break;
            default:
                if ($this->l('ERROR_GENERAL_REDIRECT') == "ERROR_GENERAL_REDIRECT") {
                    $returnMessage = "Error before redirect";
                } else {
                    $returnMessage =  $this->l('ERROR_GENERAL_REDIRECT');
                }
                break;
        }

        return $returnMessage;
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->validateConfigurationPost();
        } else {
            $this->html .= '<br />';
        }

        $getShopDomainSsl = Tools::getShopDomainSsl(true, true);

        $configJsUrl = $getShopDomainSsl.__PS_BASE_URI__.'modules/'.$this->name.'/views/js/backend_config.js';
        
        $this->html .= "<script type='text/javascript' src='$configJsUrl'></script>";
        $this->html .= $this->renderForm();

        return $this->html;
    }

    private function validateConfigurationPost()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $locale = $this->getConfigLocale();
            $isRequired = false;
            $fieldsRequired = array();

            if (trim(Tools::getValue('SKRILL_GENERAL_MERCHANTID')) == '') {
                $fieldsRequired[] = $locale['mid']['label'];
                $isRequired = true;
            }
            if (trim(Tools::getValue('SKRILL_GENERAL_MERCHANTACCOUNT')) == '') {
                $fieldsRequired[] = $locale['merchant']['label'];
                $isRequired = true;
            }
            if (trim(Tools::getValue('SKRILL_GENERAL_RECIPENT')) == '') {
                $fieldsRequired[] = $locale['recepient']['label'];
                $isRequired = true;
            }
            if (trim(Tools::getValue('SKRILL_GENERAL_LOGOURL')) == '') {
                $fieldsRequired[] = $locale['logourl']['label'];
                $isRequired = true;
            }
            if (trim(Tools::getValue('SKRILL_GENERAL_APIPASS')) == ''
            && trim(Configuration::get('SKRILL_GENERAL_APIPASS')) == '') {
                $fieldsRequired[] =  $locale['apipass']['label'];
                $isRequired = true;
            }
            if (trim(Tools::getValue('SKRILL_GENERAL_SECRETWORD')) == ''
            && trim(Configuration::get('SKRILL_GENERAL_SECRETWORD')) == '') {
                $fieldsRequired[] =  $locale['secretword']['label'];
                $isRequired = true;
            }

            if ($isRequired) {
                $warning = implode(', ', $fieldsRequired) . ' ';
                if ($this->l('ERROR_MANDATORY') == "ERROR_MANDATORY") {
                    $warning .= "is required. please fill out this field";
                } else {
                    $warning .= $this->l('ERROR_MANDATORY');
                }
                $this->html .= $this->displayWarning($warning);
            } else {
                $this->configurationPost();
            }
        }
    }

    private function configurationPost()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $apiPass = Tools::getValue('SKRILL_GENERAL_APIPASS');
            $secretWord = Tools::getValue('SKRILL_GENERAL_SECRETWORD');
            $merchantAccount = Tools::getValue('SKRILL_GENERAL_MERCHANTACCOUNT');

            Configuration::updateValue('SKRILL_GENERAL_MERCHANTID', Tools::getValue('SKRILL_GENERAL_MERCHANTID'));
            Configuration::updateValue('SKRILL_GENERAL_MERCHANTACCOUNT', $merchantAccount);
            Configuration::updateValue('SKRILL_GENERAL_RECIPENT', Tools::getValue('SKRILL_GENERAL_RECIPENT'));
            Configuration::updateValue('SKRILL_GENERAL_LOGOURL', Tools::getValue('SKRILL_GENERAL_LOGOURL'));
            Configuration::updateValue('SKRILL_GENERAL_SHOPURL', Tools::getValue('SKRILL_GENERAL_SHOPURL'));
            if (trim($apiPass) != '') {
                Configuration::updateValue('SKRILL_GENERAL_APIPASS', md5($apiPass));
            }
            if (trim($secretWord) != '') {
                Configuration::updateValue('SKRILL_GENERAL_SECRETWORD', md5($secretWord));
            }
            Configuration::updateValue('SKRILL_GENERAL_DISPLAY', Tools::getValue('SKRILL_GENERAL_DISPLAY'));

            Configuration::updateValue('SKRILL_FLEXIBLE_ACTIVE', Tools::getValue('SKRILL_FLEXIBLE_ACTIVE'));

            foreach (array_keys(SkrillPaymentCore::getPaymentMethods()) as $paymentType) {
                $active = Tools::getValue('SKRILL_'.$paymentType.'_ACTIVE');
                $mode = Tools::getValue('SKRILL_'.$paymentType.'_MODE');
                $sort = Tools::getValue('SKRILL_'.$paymentType.'_SORT');
                Configuration::updateValue('SKRILL_'.$paymentType.'_ACTIVE', $active);
                if ($paymentType != 'FLEXIBLE') {
                    Configuration::updateValue('SKRILL_'.$paymentType.'_MODE', $mode);
                }
                Configuration::updateValue('SKRILL_'.$paymentType.'_SORT', $sort);
            }
        }
        if ($this->l('BACKEND_CH_UPDATED') == "BACKEND_CH_UPDATED") {
            $chUpdated = "Configurations Updated";
        } else {
            $chUpdated = $this->l('BACKEND_CH_UPDATED');
        }
        $this->html .= $this->displayConfirmation($chUpdated);
    }

    private function renderForm()
    {
        $locale = $this->getConfigLocale();

        $generalForm = $this->getGeneralForm($locale);
        $paymentForm = $this->getPaymentForm($locale);
        $getAdminLink = $this->context->link->getAdminLink('AdminModules', false);
        $module = '&tab_module='.$this->tab.'&module_name='.$this->name;
        $fieldsForm = array_merge($generalForm, $paymentForm);

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        if (Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')) {
            $helper->allow_employee_form_lang =  Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        } else {
            $helper->allow_employee_form_lang =  0;
        }
        $this->fields_form = array();
        $this->fields_form = $fieldsForm;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $getAdminLink.'&configure='.$this->name.$module;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm($this->fields_form);
    }

    private function getConfigFieldsValues()
    {
        $getMerchantID = Configuration::get('SKRILL_GENERAL_MERCHANTID');
        $getMerchantAccount = Configuration::get('SKRILL_GENERAL_MERCHANTACCOUNT');
        $getRecipent = Configuration::get('SKRILL_GENERAL_RECIPENT');
        $getLogourl = Configuration::get('SKRILL_GENERAL_LOGOURL');
        $getShopurl = Configuration::get('SKRILL_GENERAL_SHOPURL');
        $getApipass = Configuration::get('SKRILL_GENERAL_APIPASS');
        $getSecretWord = Configuration::get('SKRILL_GENERAL_SECRETWORD');
        $getDisplay = Configuration::get('SKRILL_GENERAL_DISPLAY');
        $getActive = Configuration::get('SKRILL_FLEXIBLE_ACTIVE');
        $saveConfig = array();
        $saveConfig['SKRILL_GENERAL_MERCHANTID'] =
            Tools::getValue('SKRILL_GENERAL_MERCHANTID', $getMerchantID);
        $saveConfig['SKRILL_GENERAL_MERCHANTACCOUNT'] =
            Tools::getValue('SKRILL_GENERAL_MERCHANTACCOUNT', $getMerchantAccount);
        $saveConfig['SKRILL_GENERAL_RECIPENT'] =
            Tools::getValue('SKRILL_GENERAL_RECIPENT', $getRecipent);
        $saveConfig['SKRILL_GENERAL_LOGOURL'] =
            Tools::getValue('SKRILL_GENERAL_LOGOURL', $getLogourl);
        $saveConfig['SKRILL_GENERAL_SHOPURL'] =
            Tools::getValue('SKRILL_GENERAL_SHOPURL', $getShopurl);
        $saveConfig['SKRILL_GENERAL_APIPASS'] =
            Tools::getValue('SKRILL_GENERAL_APIPASS', $getApipass);
        $saveConfig['SKRILL_GENERAL_SECRETWORD'] =
            Tools::getValue('SKRILL_GENERAL_SECRETWORD', $getSecretWord);
        $saveConfig['SKRILL_GENERAL_DISPLAY'] =
            Tools::getValue('SKRILL_GENERAL_DISPLAY', $getDisplay);
        $saveConfig['SKRILL_FLEXIBLE_ACTIVE'] =
            Tools::getValue('SKRILL_FLEXIBLE_ACTIVE', $getActive);

        foreach (array_keys(SkrillPaymentCore::getPaymentMethods()) as $paymentType) {
            $getActive = Configuration::get('SKRILL_'.$paymentType.'_ACTIVE');
            $getMode = Configuration::get('SKRILL_'.$paymentType.'_MODE');
            $getSort =Configuration::get('SKRILL_'.$paymentType.'_SORT');
            $saveConfig['SKRILL_'.$paymentType.'_ACTIVE'] =
                Tools::getValue('SKRILL_'.$paymentType.'_ACTIVE', $getActive);
            if ($paymentType != 'FLEXIBLE') {
                $saveConfig['SKRILL_'.$paymentType.'_MODE'] = Tools::getValue('SKRILL_'.$paymentType.'_MODE', $getMode);
            }
            $saveConfig['SKRILL_'.$paymentType.'_SORT'] = Tools::getValue('SKRILL_'.$paymentType.'_SORT', $getSort);
        }

        return $saveConfig;
    }

    private function getConfigLocale()
    {
        $locale = array();
        foreach (array_keys(SkrillPaymentCore::getPaymentMethods()) as $paymentType) {
            $paymentTitle = Tools::strtolower($paymentType);
            $locale[$paymentTitle] = $this->getBackendPaymentLocale('SKRILL_BACKEND_PM_'.$paymentType);
        }

        if ($this->l('SKRILL_BACKEND_PM_SETTINGS') == "SKRILL_BACKEND_PM_SETTINGS") {
            $locale['setting']['label'] = "Skrill Settings";
        } else {
            $locale['setting']['label'] = $this->l('SKRILL_BACKEND_PM_SETTINGS');
        }
        if ($this->l('SKRILL_BACKEND_MID') == "SKRILL_BACKEND_MID") {
            $locale['mid']['label'] = "Merchant ID";
        } else {
            $locale['mid']['label'] = $this->l('SKRILL_BACKEND_MID');
        }
        if ($this->l('SKRILL_BACKEND_MERCHANT') == "SKRILL_BACKEND_MERCHANT") {
            $locale['merchant']['label'] = "Merchant Account (email)";
        } else {
            $locale['merchant']['label'] = $this->l('SKRILL_BACKEND_MERCHANT');
        }
        if ($this->l('SKRILL_BACKEND_RECIPIENT') == "SKRILL_BACKEND_RECIPIENT") {
            $locale['recepient']['label'] = "Recipient";
        } else {
            $locale['recepient']['label'] = $this->l('SKRILL_BACKEND_RECIPIENT');
        }
        if ($this->l('SKRILL_BACKEND_LOGO') == "SKRILL_BACKEND_LOGO") {
            $locale['logourl']['label'] = "Logo Url";
        } else {
            $locale['logourl']['label'] = $this->l('SKRILL_BACKEND_LOGO');
        }
        if ($this->l('SKRILL_BACKEND_SHOPURL') == "SKRILL_BACKEND_SHOPURL") {
            $locale['shopurl']['label'] = "Shop Url";
        } else {
            $locale['shopurl']['label'] = $this->l('SKRILL_BACKEND_SHOPURL');
        }
        if ($this->l('SKRILL_BACKEND_SECRETWORD') == "SKRILL_BACKEND_SECRETWORD") {
            $locale['secretword']['label'] = "Secret word";
        } else {
            $locale['secretword']['label'] = $this->l('SKRILL_BACKEND_SECRETWORD');
        }

        if ($this->l('SKRILL_BACKEND_APIPASS') == "SKRILL_BACKEND_APIPASS") {
            $locale['apipass']['label'] = "API Password";
        } else {
            $locale['apipass']['label'] = $this->l('SKRILL_BACKEND_APIPASS');
        }
        if ($this->l('SKRILL_BACKEND_DISPLAY') == "SKRILL_BACKEND_DISPLAY") {
            $locale['display']['label'] = "Display";
        } else {
            $locale['display']['label'] = $this->l('SKRILL_BACKEND_DISPLAY');
        }
        if ($this->l('SKRILL_BACKEND_IFRAME') == "SKRILL_BACKEND_IFRAME") {
            $locale['display']['iframe'] = "iFrame";
        } else {
            $locale['display']['iframe'] = $this->l('SKRILL_BACKEND_IFRAME');
        }
        if ($this->l('SKRILL_BACKEND_REDIRECT') == "SKRILL_BACKEND_REDIRECT") {
            $locale['display']['redirect'] = "Redirect";
        } else {
            $locale['display']['redirect'] = $this->l('SKRILL_BACKEND_REDIRECT');
        }
        if ($this->l('SKRILL_BACKEND_TT_MID') == "SKRILL_BACKEND_TT_MID") {
            $locale['mid']['desc'] = "Your Skrill customer ID. 
                    It is displayed in the upper-right corner of your Skrill account.";
        } else {
            $locale['mid']['desc'] = $this->l('SKRILL_BACKEND_TT_MID');
        }
        if ($this->l('SKRILL_BACKEND_TT_MEMAIL') == "SKRILL_BACKEND_TT_MEMAIL") {
            $locale['merchant']['desc'] = "Your Skrill account email address.";
        } else {
            $locale['merchant']['desc'] = $this->l('SKRILL_BACKEND_TT_MEMAIL');
        }
        if ($this->l('SKRILL_BACKEND_TT_RECIPIENT') == "SKRILL_BACKEND_TT_RECIPIENT") {
            $locale['recepient']['desc'] = "A description to be shown on Quick Checkout.
                    This can be your company name (max 30 characters).";
        } else {
            $locale['recepient']['desc'] = $this->l('SKRILL_BACKEND_TT_RECIPIENT');
        }
        if ($this->l('SKRILL_BACKEND_TT_LOGO') == "SKRILL_BACKEND_TT_LOGO") {
            $locale['logourl']['desc'] =
                    "The URL of the logo which you would like to appear at the top right of the Skrill page.
                    The logo must be accessible via HTTPS or it will not be shown.
                    For best results use logos with dimensions up to 200px in width and 50px in height.";
        } else {
            $locale['logourl']['desc'] = $this->l('SKRILL_BACKEND_TT_LOGO');
        }
        if ($this->l('SKRILL_BACKEND_TT_APIPW') == "SKRILL_BACKEND_TT_APIPW") {
            $locale['apipass']['desc'] =
                    "When enabled, this feature allows you to issue refunds and check transaction statuses.
                    To set it up, you need to login to your Skrill account and go to Settings -> then,
                    Developer Settings.";
        } else {
            $locale['apipass']['desc'] = $this->l('SKRILL_BACKEND_TT_APIPW');
        }
        if ($this->l('SKRILL_BACKEND_TT_SECRET') == "SKRILL_BACKEND_TT_SECRET") {
            $locale['secretword']['desc'] =
                    "This feature is mandatory and ensures the integrity of the data posted back to your servers.
                    To set it up, you need to login to your Skrill account and go to Settings -> then,
                    Developer Settings.";
        } else {
            $locale['secretword']['desc'] = $this->l('SKRILL_BACKEND_TT_SECRET');
        }
        if ($this->l('SKRILL_BACKEND_TT_DISPLAY') == "SKRILL_BACKEND_TT_DISPLAY") {
            $locale['display']['desc'] =
                    "iFrame – when this option is enabled the Quick
                    Checkout payment form  is embedded on your website,
                    Redirect – when this option is enabled the customer is redirected
                    to the Quick Checkout payment form .
                    This option is recommended for payment options which redirect the user to an external website.";
        } else {
            $locale['display']['desc'] = $this->l('SKRILL_BACKEND_TT_DISPLAY');
        }
        if ($this->l('BACKEND_CH_ACTIVE') == "BACKEND_CH_ACTIVE") {
            $locale['active']['label'] = "Enabled";
        } else {
            $locale['active']['label'] = $this->l('BACKEND_CH_ACTIVE');
        }
        if ($this->l('SKRILL_BACKEND_PM_MODE') == "SKRILL_BACKEND_PM_MODE") {
            $locale['mode']['label'] = "Show separately";
        } else {
            $locale['mode']['label'] = $this->l('SKRILL_BACKEND_PM_MODE');
        }
        if ($this->l('BACKEND_CH_ORDER') == "BACKEND_CH_ORDER") {
            $locale['order']['label'] = "Sort Order";
        } else {
            $locale['order']['label'] = $this->l('BACKEND_CH_ORDER');
        }
        if ($this->l('SKRILL_BACKEND_TT_APM') == "SKRILL_BACKEND_TT_APM") {
            $locale['active']['flexible']['desc'] =
                    "When enabled to show separately all other payment methods
                    will automatically default to 'disabled'.";
            $locale['active']['acc']['desc'] =
                    "When enabled to show separately all other payment methods
                    will automatically default to 'disabled'.";
            $locale['mode']['acc']['desc'] =
                    "When enabled to show separately all other payment methods
                    will automatically default to 'disabled'.";
        } else {
            $locale['active']['flexible']['desc'] = $this->l('SKRILL_BACKEND_TT_APM');
            $locale['active']['acc']['desc'] = $this->l('SKRILL_BACKEND_TT_APM');
            $locale['mode']['acc']['desc'] = $this->l('SKRILL_BACKEND_TT_APM');
        }

        $locale['save'] = $this->l('BACKEND_CH_SAVE') == "BACKEND_CH_SAVE" ? "Save" : $this->l('BACKEND_CH_SAVE');
        $locale['active']['psc']['desc'] = 'American Samoa, Austria, Belgium, Canada, Croatia,
                Cyprus, Czech Republic, Denmark, Finland, France, Germany, Greece, Guam,
                Hungary, Ireland, Italy, Latvia, Luxembourg, Malta, Mexico, Netherlands,
                Northern Mariana Islands, Norway, Poland, Portugal, Puerto Rico, Romania,
                Slovakia, Slovenia, Spain, Sweden, Switzerland, Turkey, United Kingdom,
                United States Of America and US Virgin Islands';
        $locale['active']['vse']['desc'] = 'All Countries (excluding United States Of America)';
        $locale['active']['mae']['desc'] = 'United Kingdom, Spain, Ireland and Austria';
        $locale['active']['gcb']['desc'] = 'France';
        $locale['active']['dnk']['desc'] = 'Denmark';
        $locale['active']['psp']['desc'] = 'Italy';
        $locale['active']['csi']['desc'] = 'Italy';
        $locale['active']['obt']['desc'] = 'Germany, United Kingdom, France, Italy, Spain, Hungary and Austria';
        $locale['active']['gir']['desc'] = 'Germany';
        $locale['active']['did']['desc'] = 'Germany';
        $locale['active']['sft']['desc'] = 'Germany, Austria, Belgium, Netherlands,
        Italy, France, Poland and United Kingdom';
        $locale['active']['ebt']['desc'] = 'Sweden';
        $locale['active']['idl']['desc'] = 'Netherlands';
        $locale['active']['npy']['desc'] = 'Austria';
        $locale['active']['pli']['desc'] = 'Australia';
        $locale['active']['pwy']['desc'] = 'Poland';
        $locale['active']['epy']['desc'] = 'Bulgaria';
        $locale['active']['glu']['desc'] = 'Sweden, Finland, Estonia, Denmark, Spain, Poland, Italy,
                France, Germany, Portugal, Austria, Latvia, Lithuania, Netherlands';
        
        if ($this->l('SKRILL_BACKEND_TT_ALI') == "SKRILL_BACKEND_TT_ALI") {
            $locale['active']['ali']['desc'] = 'Consumer location: China only.
                Merchant location: This is available for merchants in all countries except China.';
        } else {
            $locale['active']['ali']['desc'] = $this->l('SKRILL_BACKEND_TT_ALI');
        }

        $locale['active']['ntl']['desc'] = "All except for
            Afghanistan, Armenia, Bhutan, Bouvet Island,
            Myanmar, China, (Keeling) Islands, Democratic
            Republic of Congo, Cook Islands, Cuba, Eritrea,
            South Georgia and the South Sandwich Islands,
            Guam, Guinea, Territory of Heard Island and
            McDonald Islands, Iran, Iraq, Cote d'Ivoire,
            Kazakhstan, North Korea, Kyrgyzstan, Liberia,
            Libya, Mongolia, Northern Mariana Islands,
            Federated States of Micronesia, Marshall
            Islands, Palau, Pakistan, East Timor, Puerto Rico,
            Sierra Leone, Somalia, Zimbabwe, Sudan, Syria,
            Tajikistan, Turkmenistan, Uganda, United
            States, US Virgin Islands, Uzbekistan, and Yemen";
            
        return $locale;
    }

    private function getGeneralForm($locale)
    {
        $getDisplayList = $this->getDisplayList($locale['display']);
        $generalForm = array();
        $generalForm[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $locale['setting']['label']
                ),
                'input' => array(
                    $this->getTextForm('GENERAL_MERCHANTID', $locale['mid']),
                    $this->getTextForm('GENERAL_MERCHANTACCOUNT', $locale['merchant']),
                    $this->getTextForm('GENERAL_RECIPENT', $locale['recepient']),
                    $this->getTextForm('GENERAL_LOGOURL', $locale['logourl']),
                    $this->getTextForm('GENERAL_SHOPURL', $locale['shopurl']),
                    $this->getPasswordForm('GENERAL_APIPASS', $locale['apipass']),
                    $this->getPasswordForm('GENERAL_SECRETWORD', $locale['secretword'], true),
                    $this->getSelectForm('GENERAL_DISPLAY', $locale['display'], $getDisplayList),
                ),
                'submit' => array(
                    'title' => $locale['save']
                )
            )
        );

        return $generalForm;
    }

    private function getPaymentForm($locale)
    {
        $paymentForm = array();
        $i = 0;
        foreach (array_keys(SkrillPaymentCore::getPaymentMethods()) as $paymentType) {
            $paymentTitle = Tools::strtolower($paymentType);
            $activeTooltips = @$locale['active'][$paymentTitle]['desc'];
            $modeTooltips = @$locale['mode'][$paymentTitle]['desc'];
            $activeLabel = $locale['active']['label'];
            $getSwitchList = $this->getSwitchList('active');

            if (!$activeTooltips) {
                $activeTooltips = 'All Countries';
            }

            $paymentForm[$i] = array(
                'form' => array(
                    'legend'=> array(
                       'title' => $locale[$paymentTitle]
                    ),
                    'input' => array(
                       $this->getSwitchForm($paymentType.'_ACTIVE', $activeLabel, $getSwitchList, $activeTooltips),
                       $this->getTextForm($paymentType.'_SORT', $locale['order']),
                    ),
                    'submit' => array(
                       'title' => $locale['save']
                    )
                )
            );

            if ($paymentType != "FLEXIBLE") {
                $addInputMode = array (
                    $this->getSwitchForm($paymentType.'_MODE', $locale['mode']['label'], $getSwitchList, $modeTooltips)
                );
                array_splice($paymentForm[$i]['form']['input'], 1, 0, $addInputMode);
            }

            $i++;

        }

        return $paymentForm;
    }

    private function getTextForm($pm, $locale, $requirement = false)
    {
        $textForm =
            array(
               'type' => 'text',
               'label' => @$locale['label'],
               'name' => 'SKRILL_'.$pm,
               'required' => $requirement,
               'desc' => @$locale['desc']
            );

        return $textForm;
    }

    private function getPasswordForm($pm, $locale, $requirement = false)
    {
        $passwordForm =
            array(
               'type' => 'password',
               'label' => $locale['label'],
               'name' => 'SKRILL_'.$pm,
               'required' => $requirement,
               'desc' => $locale['desc']
            );

        return $passwordForm;
    }

    private function getSelectForm($pm, $locale, $selectList)
    {
        $selectForm =
            array(
                'type'		=> 'select',
                'label'		=> $locale['label'],
                'name'		=> 'SKRILL_'.$pm,
                'desc'		=> $locale['desc'],
                'options'	=>
                   array(
                   'query' => $selectList,
                   'id'	=> 'id',
                   'name'	=> 'name'
        )
            );

        return $selectForm;
    }

    private function getSwitchForm($pm, $label, $switchList, $desc = false)
    {
        $switchForm =
            array(
               'type'		=> 'switch',
               'label' 	=> $label,
               'name' 		=> 'SKRILL_'.$pm,
               'is_bool' 	=> true,
               'values' 	=> $switchList,
               'desc' 		=> $desc
            );

        return $switchForm;
    }

    //Display
    private function getDisplayList($display)
    {
        $displayList = array (
            array(
               'id'	=> 'IFRAME',
               'name' 	=> $display['iframe']
            ),
            array(
               'id' 	=> "REDIRECT",
               'name' 	=> $display['redirect']
            )
        );

        return $displayList;
    }

    //Enabled
    private function getSwitchList($field)
    {
        $switchList = array(
            array(
               'id' 	=> $field.'_on',
               'value' => 1,
            ),
            array(
               'id' 	=> $field.'_off',
               'value' => 0,
            )
        );

        return $switchList;
    }

    public function mailAlert($order, $paymentBrand, $status, $template)
    {
        if (!Module::isInstalled('mailalerts')) {
            $id_lang = (int)Context::getContext()->language->id;

            $customer = $this->context->customer;

            $delivery = new Address((int)$order->id_address_delivery);
            $invoice = new Address((int)$order->id_address_invoice);
            $order_date_text = Tools::displayDate($order->date_add, (int)$id_lang);
            $carrier = new Carrier((int)$order->id_carrier);
            $products = $order->getProducts();
            $customized_datas = Product::getAllCustomizedDatas((int)$this->context->cart->id);
            $currency = $this->context->currency;
            $emails = array();
            $message = $order->getFirstMessage();
            if (!$message || empty($message)) {
                if ($this->l('ERROR_GENERAL_NOMESSAGE') == "ERROR_GENERAL_NOMESSAGE") {
                    $message =  "Without Message";
                } else {
                    $message =  $this->l('ERROR_GENERAL_NOMESSAGE');
                }
            }

            $items_table = '';
            $sql = 'SELECT * FROM '._DB_PREFIX_.'employee where id_profile = 1 and active = 1';
            $results = Db::getInstance()->ExecuteS($sql);
            if ($results) {
                foreach ($results as $row) {
                    array_push($emails, $row['email']);
                }
            }
            
            foreach ($products as $key => $product) {
                $unit_price = $product['product_price_wt'];

                $customization_text = '';
                $customized_datas = $customized_datas[$product['product_id']][$product['product_attribute_id']];
                if (isset($customized_datas)) {

                    foreach ($customized_datas as $customization) {
                        if (isset($customization['datas'][_CUSTOMIZE_TEXTFIELD_])) {
                            foreach ($customization['datas'][_CUSTOMIZE_TEXTFIELD_] as $text) {
                                $customization_text .= $text['name'].': '.$text['value'].'<br />';
                            }
                        }

                        $customize_file = $customization['datas'][_CUSTOMIZE_FILE_];

                        if (isset($customize_file)) {
                            if (count($customize_file).' '.$this->l('BACKEND_TEXT_IMAGE') == "BACKEND_TEXT_IMAGE") {
                                $customization_text .= "image(s)" ;
                            } else {
                                $customization_text .=  $this->l('BACKEND_TEXT_IMAGE').'<br />';
                            }
                        }

                        $customization_text .= '---<br />';
                    }

                    $customization_text = rtrim($customization_text, '---<br />');
                }
                if (isset($product['attributes_small'])) {
                    $attributes_small = ' '.$product['attributes_small'];
                } else {
                    $attributes_small = '';
                }
                if (!empty($customization_text)) {
                    $custom_text = '<br />'.$customization_text;
                } else {
                    $custom_text = '';
                }
                $items_table .=
                    '<tr style="background-color:'.($key % 2 ? '#DDE2E6' : '#EBECEE').';">
                        <td style="padding:0.6em 0.4em;">
                            '.$product['product_reference'].'
                        </td>
                        <td style="padding:0.6em 0.4em;">
                            <strong>'
                                .$product['product_name'].$attributes_small.$custom_text.
                            '</strong>
                        </td>
                        <td style="padding:0.6em 0.4em; text-align:right;">
                            '.Tools::displayPrice($unit_price, $currency, false).'
                        </td>
                        <td style="padding:0.6em 0.4em; text-align:center;">
                            '.(int)$product['product_quantity'].'
                        </td>
                        <td style="padding:0.6em 0.4em; text-align:right;">
                            '.Tools::displayPrice(($unit_price * $product['product_quantity']), $currency, false).'
                        </td>
                    </tr>';
            }
            foreach ($order->getCartRules() as $discount) {
                if ($this->l('BACKEND_TEXT_VOUCHER_CODE') == "BACKEND_TEXT_VOUCHER_CODE") {
                    $voucherCode = "Voucher code :";
                } else {
                    $voucherCode = $this->l('BACKEND_TEXT_VOUCHER_CODE').' '.$discount['name'];
                }

                $items_table .=
                '<tr style="background-color:#EBECEE;">
                        <td colspan="4" style="padding:0.6em 0.4em; text-align:right;">
                        '.$voucherCode.'
                        </td>
                        <td style="padding:0.6em 0.4em; text-align:right;">-
                           '.Tools::displayPrice($discount['value'], $currency, false).'
                        </td>
                </tr>';
            }

            if ($delivery->id_state) {
                $delivery_state = new State((int)$delivery->id_state);
            }
            if ($invoice->id_state) {
                $invoice_state = new State((int)$invoice->id_state);
            }

            $template_vars = array(
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{email}' => $customer->email,
                '{delivery_block_txt}' => SkrillCustomMailAlert::getFormatedAddress($delivery, "\n"),
                '{invoice_block_txt}' => SkrillCustomMailAlert::getFormatedAddress($invoice, "\n"),
                '{delivery_block_html}' => SkrillCustomMailAlert::getFormatedAddress($delivery, '<br />', array(
                'firstname' => '<span style="color:blue; font-weight:bold;">%s</span>',
                'lastname' => '<span style="color:blue; font-weight:bold;">%s</span>')),
                '{invoice_block_html}' => SkrillCustomMailAlert::getFormatedAddress($invoice, '<br />', array(
                'firstname' => '<span style="color:blue; font-weight:bold;">%s</span>',
                'lastname' => '<span style="color:blue; font-weight:bold;">%s</span>')),
                '{delivery_company}' => $delivery->company,
                '{delivery_firstname}' => $delivery->firstname,
                '{delivery_lastname}' => $delivery->lastname,
                '{delivery_address1}' => $delivery->address1,
                '{delivery_address2}' => $delivery->address2,
                '{delivery_city}' => $delivery->city,
                '{delivery_postal_code}' => $delivery->postcode,
                '{delivery_country}' => $delivery->country,
                '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
                '{delivery_phone}' => $delivery->phone ? $delivery->phone : $delivery->phone_mobile,
                '{delivery_other}' => $delivery->other,
                '{invoice_company}' => $invoice->company,
                '{invoice_firstname}' => $invoice->firstname,
                '{invoice_lastname}' => $invoice->lastname,
                '{invoice_address2}' => $invoice->address2,
                '{invoice_address1}' => $invoice->address1,
                '{invoice_city}' => $invoice->city,
                '{invoice_postal_code}' => $invoice->postcode,
                '{invoice_country}' => $invoice->country,
                '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
                '{invoice_phone}' => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
                '{invoice_other}' => $invoice->other,
                '{order_name}' => sprintf('%06d', $order->id),
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{date}' => $order_date_text,
                '{carrier}' => (($carrier->name == '0') ? Configuration::get('PS_SHOP_NAME') : $carrier->name),
                '{payment}' => $order->payment,
                '{items}' => $items_table,
                '{total_paid}' => Tools::displayPrice($order->total_paid, $currency),
                '{total_products}' => Tools::displayPrice($order->getTotalProductsWithTaxes(), $currency),
                '{total_discounts}' => Tools::displayPrice($order->total_discounts, $currency),
                '{total_shipping}' => Tools::displayPrice($order->total_shipping, $currency),
                '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $currency),
                '{currency}' => $currency->sign,
                '{message}' => $message
            );
            
            $mail_order = Mail::l('Order #%06d - '.$this->displayName.' ('.$paymentBrand.' - '.$status.')', $id_lang);

            Mail::Send(
                $id_lang,
                $template,
                sprintf($mail_order, $order->id),
                $template_vars,
                $emails,
                null,
                Configuration::get('PS_SHOP_EMAIL'),
                Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                dirname(__FILE__).'/mails/'
            );
        }
    }
}
