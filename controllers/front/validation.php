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

require_once(dirname(__FILE__).'/../../core/core.php');
require_once(dirname(__FILE__).'/../../core/versiontracker.php');

class SkrillValidationModuleFrontController extends ModuleFrontController
{
    private $checkoutUrl = 'index.php?controller=order&step=1';
    private $refundType = 'fraud';
    private $orderConfirmationUrl = 'index.php?controller=order-confirmation&id_cart=';
    
    public function postProcess()
    {
        $transactionId = Tools::getValue('transaction_id');

        if ($transactionId) {
            $this->processPayment($transactionId);
        }
        $this->redirectError('SKRILL_ERROR_99_GENERAL');
    }

    private function processPayment($transactionId)
    {
        VersionTracker::sendVersionTracker($this->module->getVersionData());
        $fieldParams = $this->module->getSkrillCredentials();
        $fieldParams['type'] = 'trn_id';
        $fieldParams['id'] = $transactionId;
        $paymentResult = '';
        $isPaymentAccepted = SkrillPaymentCore::isPaymentAccepted($fieldParams, $paymentResult);
        if ($isPaymentAccepted) {
            $this->validatePayment($transactionId, $paymentResult);
        }
        $this->redirectError('ERROR_GENERAL_NORESPONSE');

    }

    private function validatePayment($transactionId, $paymentResult)
    {
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0 || !$this->module->active
            || !Validate::isLoadedObject($customer)) {
            Tools::redirect($this->checkoutUrl);
        }
        
        $currency = $this->context->currency;
        $orderTotal = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $responseCheckout = SkrillPaymentCore::getResponseArray($paymentResult);

        $transactionLog = $this->setTransactionLog($transactionId, $currency, $orderTotal, $responseCheckout);

        $this->saveTransactionLog($transactionLog, $responseCheckout);
        $isFraud = $this->isFraud($orderTotal, $responseCheckout);
        $isAuthorized = $this->isAuthorized();
        if ($isFraud or !$isAuthorized) {
            $this->processFraudPayment($responseCheckout);
        }
        $this->processSuccessPayment($cart, $customer, $currency, $orderTotal, $responseCheckout, $transactionLog);
    }
    
    private function processSuccessPayment($cart, $customer, $currency, $orderTotal, $responseCheckout, $transactionLog)
    {
        if ($responseCheckout['status'] == $this->module->processedStatus) {
            $cartId = (int)$cart->id;
            $secureKey = $customer->secure_key;
            $paymentName = $transactionLog['payment_name'];
            $currencyId = (int) $currency->id;
            $paymentStatus = Configuration::get('PS_OS_PAYMENT');
            $this->module->validateOrder(
                $cartId,
                $paymentStatus,
                $orderTotal,
                $paymentName,
                null,
                array(),
                $currencyId,
                false,
                $secureKey
            );
            $this->updateTransLog($this->module->currentOrder, $responseCheckout);
            $this->context->cookie->skrill_paymentName = $transactionLog['payment_name'];
            Tools::redirect(
                $this->orderConfirmationUrl.
                $cartId.
                '&id_module='.(int)$this->module->id.
                '&id_order='.$this->module->currentOrder.
                '&key='.$secureKey
            );
        } else {
            $this->module->updateTransLogStatus($responseCheckout['mb_transaction_id'], $responseCheckout['status']);
            if ($responseCheckout['status'] == $this->module->failedStatus) {
                $errorStatus = SkrillPaymentCore::getSkrillErrorMapping($responseCheckout['failed_reason_code']);
                $this->redirectError($errorStatus);
            }
            $this->redirectError('SKRILL_ERROR_99_GENERAL');
        }

    }

    private function processFraudPayment($responseCheckout)
    {
        $refundedStatus =  $this->module->refundedStatus;
        $refundFailedStatus =  $this->module->refundFailedStatus;
        $refId = '';
        $refundResult = $this->module->refundOrder($responseCheckout, $refId, $this->refundType);
        $refundStatus = (string) $refundResult->status;
        if ($refundStatus == $this->module->processedStatus) {
            $this->module->updateTransLogStatus($responseCheckout['mb_transaction_id'], $refundedStatus);
        } else {
            $this->module->updateTransLogStatus($responseCheckout['mb_transaction_id'], $refundFailedStatus);
        }
        $this->redirectError('ERROR_GENERAL_FRAUD_DETECTION');
    }

    private function setTransactionLog($transactionId, $currency, $orderTotal, $responseCheckout)
    {
        $transactionLog = array();
        $transactionLog['transaction_id'] = $transactionId;
        $transactionLog['payment_type'] = $this->getPaymentType($responseCheckout);
        $transactionLog['payment_method'] = 'SKRILL_FRONTEND_PM_'.Tools::getValue('payment_method');
        $transactionLog['payment_name'] = $this->getPaymentName($transactionLog['payment_type']);
        $transactionLog['status'] = SkrillPaymentCore::getTrnStatus($responseCheckout['status']);
        $transactionLog['currency'] = $this->getPaymentCurrency($currency, $responseCheckout);
        $transactionLog['amount'] = $this->getPaymentAmount($orderTotal, $responseCheckout);
        return $transactionLog;
    }

    private function getPaymentCurrency($currency, $responseCheckout)
    {
        if (!empty($responseCheckout['currency'])) {
            return $responseCheckout['currency'];
        }
        return $currency->iso_code;
    }

    private function getPaymentAmount($orderTotal, $responseCheckout)
    {
        if (!empty($responseCheckout['amount'])) {
            return $responseCheckout['amount'];
        }
        return $orderTotal;
    }
     
    private function getPaymentType($responseCheckout)
    {
        if (!empty($responseCheckout['payment_type'])) {
            if ($responseCheckout['payment_type'] == 'NGP') {
                return 'OBT';
            } else {
                return $responseCheckout['payment_type'];
            }
        }
        return Tools::getValue('payment_method');
        
    }

    private function getPaymentName($paymentType)
    {
        $paymentMethod = SkrillPaymentCore::getPaymentMethods($paymentType);
        if ($this->module->l('SKRILL_FRONTEND_PM_'.$paymentType) == 'SKRILL_FRONTEND_PM_'.$paymentType) {
            $paymentName = $paymentMethod['name'];
        } else {
            $paymentName = $this->module->l('SKRILL_FRONTEND_PM_'.$paymentType);
        }
        
        $isSkrill = strpos($paymentName, 'Skrill');
        if ($isSkrill === false) {
            $paymentName = 'Skrill '.$paymentName;
        }

        return $paymentName;
    }

    private function redirectError($returnMessage)
    {
        Tools::redirect($this->context->link->getPageLink('order', true, null, array(
            'step' => '3', 'skrillerror' => $returnMessage)));
    }

    private function isFraud($orderTotal, $responseCheckout)
    {
        $amount = (float) $responseCheckout['amount'];
        if ($responseCheckout['amount']) {
            return !( ($orderTotal == $amount) &&
                ($responseCheckout['md5sig'] == $this->generateMd5sig($responseCheckout)) );
        } else {
            return false;
        }
    }

    // Check that this payment option is still available in case the customer
    // changed his address just before the end of the checkout process
    private function isAuthorized()
    {
        $isAuthorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'skrill') {
                $isAuthorized = true;
                break;
            }
        }

        return $isAuthorized;
    }

    private function generateMd5sig($responseCheckout)
    {
        $merchantId = Configuration::get('SKRILL_GENERAL_MERCHANTID');
        $secretWord = Tools::strtoupper(Configuration::get('SKRILL_GENERAL_SECRETWORD'));
        $transactionId = $responseCheckout['transaction_id'];
        $mbAmount = $responseCheckout['mb_amount'];
        $mbCurrency = $responseCheckout['mb_currency'];
        $status = $responseCheckout['status'];
        $string = $merchantId.$transactionId.$secretWord.$mbAmount.$mbCurrency.$status;

        return Tools::strtoupper(md5($string));
    }

    private function saveTransactionLog($transactionLog, $responseCheckout)
    {
        $sql = "INSERT INTO skrill_order_ref
            (transaction_id, payment_method, order_status, ref_id, payment_code, currency, amount) VALUES "."('".
                pSQL($transactionLog['transaction_id'])."','".
                pSQL($transactionLog['payment_method'])."','".
                pSQL($responseCheckout['status'])."','".
                pSQL($responseCheckout['mb_transaction_id'])."','".
                pSQL($transactionLog['payment_type'])."','".
                pSQL($transactionLog['currency'])."','".
                (float)$transactionLog['amount'].
            "')";
        if (!Db::getInstance()->execute($sql)) {
            die('Erreur etc.');
        }
    }

    private function updateTransLog($orderId, $responseCheckout)
    {
        $updateInfo = '';
        $additionalInfo = array();
        if ($responseCheckout["ip_country"] && $responseCheckout['payment_instrument_country']) {
            $additionalInfo[0] = 'SKRILL_BACKEND_ORDER_ORIGIN=>'.$responseCheckout["ip_country"];
            $additionalInfo[1] = 'SKRILL_BACKEND_ORDER_COUNTRY=>'.$responseCheckout['payment_instrument_country'];
            $updateInfo = ", add_information = '".serialize($additionalInfo)."'";
        }

        $sql = "UPDATE skrill_order_ref SET id_order = '".(int)$orderId."'
            ".$updateInfo." where ref_id = '".pSQL($responseCheckout['mb_transaction_id'])."'";
        if (!Db::getInstance()->execute($sql)) {
            die('Erreur etc.');
        }
    }
}
