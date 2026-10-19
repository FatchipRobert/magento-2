<?php

namespace Payone\Core\Model\Api\Request\Genericpayment;

use Magento\Quote\Model\Quote;
use Payone\Core\Model\Methods\PayoneMethod;

/**
 * Class for the PAYONE Server API request genericpayment - "init_applepay_session"
 */
class InitApplepaySession extends Base
{
    /**
     * Send request to PAYONE Server-API with request-type "genericpayment" and action "init_applepay_session"
     *
     * @param  PayoneMethod $oPayment    payment object
     * @param  Quote        $oQuote      quote object
     * @param  string       $sDomainName shop domain Apple Pay is used on
     * @return array
     */
    public function sendRequest(PayoneMethod $oPayment, Quote $oQuote, $sDomainName)
    {
        $this->addParameter('request', 'genericpayment');
        $this->addParameter('add_paydata[action]', 'init_applepay_session');

        $this->addParameter('mode', $oPayment->getOperationMode());
        $this->addParameter('aid', $this->shopHelper->getConfigParam('aid')); // ID of PayOne Sub-Account

        $this->addParameter('clearingtype', 'wlt');
        $this->addParameter('wallettype', 'APL');

        $this->addParameter('currency', $this->apiHelper->getCurrencyFromQuote($oQuote));

        #$oBilling = $oQuote->getBillingAddress();
        #$this->addParameter('country', $oBilling->getCountryId());
        #$this->addParameter('lastname', $oBilling->getLastname());

        #$this->addParameter('reference', $this->getReservedOrderId($oQuote, $oPayment));

        $this->addParameter('add_paydata[display_name]', $this->shopHelper->getStoreName());
        $this->addParameter('add_paydata[domain_name]', $sDomainName);

        return $this->send($oPayment);
    }

    /**
     * Reserves order id if not set yet
     * Returns reserved order id
     *
     * @param  Quote        $oQuote
     * @param  PayoneMethod $oPayment
     * @return string
     */
    protected function getReservedOrderId(Quote $oQuote, PayoneMethod $oPayment)
    {
        ///@TODO: CAN BE REMOVED IF REFERENCE IS NOT NEEDED!
        if (!$oQuote->getReservedOrderId()) {
            try {
                $oQuote->reserveOrderId()->save();
            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
            }
        }
        $sRefNr = $this->shopHelper->getConfigParam('ref_prefix').$oQuote->getReservedOrderId();
        $sRefNr = $oPayment->formatReferenceNumber($sRefNr);
        return $sRefNr;
    }
}