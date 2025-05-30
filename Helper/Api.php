<?php

/**
 * PAYONE Magento 2 Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PAYONE Magento 2 Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with PAYONE Magento 2 Connector. If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 *
 * @category  Payone
 * @package   Payone_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2003 - 2016 Payone GmbH
 * @license   <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link      http://www.payone.de
 */

namespace Payone\Core\Helper;

use Magento\Quote\Model\Quote;
use Payone\Core\Model\Methods\PayoneMethod;
use Magento\Sales\Model\Order as SalesOrder;
use Payone\Core\Model\Methods\Ratepay\RatepayBase;

/**
 * Helper class for everything that has to do with APIs
 *
 * @category  Payone
 * @package   Payone_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2003 - 2016 Payone GmbH
 * @license   <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link      http://www.payone.de
 */
class Api extends Base
{
    /**
     * PAYONE connection curl php
     *
     * @var \Payone\Core\Helper\Connection\CurlPhp
     */
    protected $connCurlPhp;

    /**
     * PAYONE connection curl cli
     *
     * @var \Payone\Core\Helper\Connection\CurlCli
     */
    protected $connCurlCli;

    /**
     * PAYONE connection fsockopen
     *
     * @var \Payone\Core\Helper\Connection\Fsockopen
     */
    protected $connFsockopen;

    /**
     * Checkout session object
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * Fields to copy from the request array to the order
     *
     * @var array
     */
    protected $requestToOrder = [
        'reference' => 'payone_refnr',
        'request' => 'payone_authmode',
        'mode' => 'payone_mode',
        'mandate_identification' => 'payone_mandate_id',
        'workorderid' => 'payone_workorder_id',
        'add_paydata[installment_duration]' => 'payone_installment_duration',
        'add_paydata[shop_id]' => 'payone_ratepay_shop_id',
    ];

    /**
     * Fields to copy from the response to the order
     *
     * @var array
     */
    protected $responseToOrder = [
        'txid' => 'payone_txid',
        'mandate_identification' => 'payone_mandate_id',
        'clearing_reference' => 'payone_clearing_reference',
        'add_paydata[clearing_reference]' => 'payone_clearing_reference',
        'add_paydata[workorderid]' => 'payone_workorder_id',
        'clearing_bankaccount' => 'payone_clearing_bankaccount',
        'clearing_bankcode' => 'payone_clearing_bankcode',
        'clearing_bankcountry' => 'payone_clearing_bankcountry',
        'clearing_bankname' => 'payone_clearing_bankname',
        'clearing_bankaccountholder' => 'payone_clearing_bankaccountholder',
        'clearing_bankcity' => 'payone_clearing_bankcity',
        'clearing_bankiban' => 'payone_clearing_bankiban',
        'clearing_bankbic' => 'payone_clearing_bankbic',
        'clearing_duedate' => 'payone_clearing_duedate',
    ];

    /**
     * Fields to copy from the session to the order
     *
     * @var array
     */
    protected $sessionToOrder = [
        'payone_express_type' => 'payone_express_type',
    ];

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context      $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Payone\Core\Helper\Shop                   $shopHelper
     * @param \Magento\Framework\App\State               $state
     * @param \Payone\Core\Helper\Connection\CurlPhp     $connCurlPhp
     * @param \Payone\Core\Helper\Connection\CurlCli     $connCurlCli
     * @param \Payone\Core\Helper\Connection\Fsockopen   $connFsockopen
     * @param \Magento\Checkout\Model\Session            $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Payone\Core\Helper\Shop $shopHelper,
        \Magento\Framework\App\State $state,
        \Payone\Core\Helper\Connection\CurlPhp $connCurlPhp,
        \Payone\Core\Helper\Connection\CurlCli $connCurlCli,
        \Payone\Core\Helper\Connection\Fsockopen $connFsockopen,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        parent::__construct($context, $storeManager, $shopHelper, $state);
        $this->connCurlPhp = $connCurlPhp;
        $this->connCurlCli = $connCurlCli;
        $this->connFsockopen = $connFsockopen;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Check which communication possibilities are existing and send the request
     *
     * @param  string $sRequestUrl
     * @return array
     */
    public function sendApiRequest($sRequestUrl)
    {
        $aParsedRequestUrl = parse_url($sRequestUrl);
        if ($aParsedRequestUrl === false) {
            return ["errormessage" => "Payone API request URL could not be parsed."];
        }

        if ($this->connCurlPhp->isApplicable()) {
            // php native curl exists so we gonna use it for requesting
            $aResponse = $this->connCurlPhp->sendCurlPhpRequest($aParsedRequestUrl);
        } elseif ($this->connCurlCli->isApplicable()) {
            // cli version of curl exists on server
            $aResponse = $this->connCurlCli->sendCurlCliRequest($aParsedRequestUrl);
        } else {
            // last resort => via sockets
            $aResponse = $this->connFsockopen->sendSocketRequest($aParsedRequestUrl);
        }

        $aResponse = $this->formatOutputByResponse($aResponse);

        if (!array_key_exists('status', $aResponse)) {
            $aResponse['status'] = 'ERROR';
            $aResponse['errorcode'] = '0';
            $aResponse['customermessage'] = 'No connection to external service provider possible (timeout)';
        }

        return $aResponse;
    }

    /**
     * Format response to a clean output array
     *
     * @param  array $aResponse
     * @return array
     */
    protected function formatOutputByResponse($aResponse)
    {
        $aOutput = [];

        if (is_array($aResponse)) { // correct response existing?
            foreach ($aResponse as $iLinenum => $sLine) { // go through line by line
                $iPos = strpos($sLine, "=");
                if ($iPos > 0) { // is a "=" as delimiter existing?
                    $aOutput[substr($sLine, 0, $iPos)] = trim(substr($sLine, $iPos + 1));
                } elseif (!empty($sLine)) { // is line not empty?
                    $aOutput[$iLinenum] = $sLine; // add the line unedited
                }
            }
        }

        return $aOutput;
    }

    /**
     * Generate the request url out of the params and die api url
     *
     * @param  array  $aParameters
     * @param  string $sApiUrl
     * @return string
     */
    public function getRequestUrl($aParameters, $sApiUrl)
    {
        $sRequestUrl = '';
        foreach ($aParameters as $sKey => $mValue) {
            if (is_array($mValue)) { // might be array
                foreach ($mValue as $i => $sSubValue) {
                    $sRequestUrl .= "&".$sKey."[".$i."]=".urlencode($sSubValue ?? '');
                }
            } else {
                $sRequestUrl .= "&".$sKey."=".urlencode($mValue ?? '');
            }
        }
        $sRequestUrl = $sApiUrl."?".substr($sRequestUrl, 1);
        return $sRequestUrl;
    }

    /**
     * Copy Data to order by given map
     *
     * @param SalesOrder $oOrder
     * @param array $aData
     * @param array $aMap
     * @return SalesOrder
     */
    protected function addDataToOrder(SalesOrder $oOrder, $aData, $aMap)
    {
        foreach ($aMap as $sFrom => $sTo) {
            if (isset($aData[$sFrom])) {
                $oOrder->setData($sTo, $aData[$sFrom]);
            }
        }
        return $oOrder;
    }

    /**
     * Get data from session
     *
     * @return array
     */
    protected function getSessionData()
    {
        $aData = [];
        foreach ($this->sessionToOrder as $from => $to) {
            $aData[$from] = $this->checkoutSession->getData($from, true); // get data and clear
        }
        return $aData;
    }

    /**
     * Add PAYONE information to the order object to be saved in the DB
     *
     * @param  SalesOrder  $oOrder
     * @param  array|false $aRequest
     * @param  array       $aResponse
     * @return void
     */
    public function addPayoneOrderData(SalesOrder $oOrder, $aRequest, $aResponse)
    {
        $this->addDataToOrder($oOrder, $aRequest, $this->requestToOrder);
        $this->addDataToOrder($oOrder, $aResponse, $this->responseToOrder);
        $this->addDataToOrder($oOrder, $this->getSessionData(), $this->sessionToOrder);
    }

    /**
     * Check if invoice-data has to be added to the authorization request
     *
     * @param  PayoneMethod $oPayment
     * @param  array|null   $aPositions
     * @return bool
     */
    public function isInvoiceDataNeeded(PayoneMethod $oPayment, $aPositions = null)
    {
        $oInfoInstance = false;
        try {
            $oInfoInstance = $oPayment->getInfoInstance(); // using getInfoInstance when it is not set will throw an exception
        } catch (\Exception $exc) {
            // do nothing
        }

        $sStoreCode = null;
        if ($oInfoInstance && $oInfoInstance->getOrder()) {
            $sStoreCode = $oInfoInstance->getOrder()->getStore()->getCode();
        }

        if ($oPayment instanceof RatepayBase && is_array($aPositions) && empty($aPositions)) { // empty array means products and shipping costs were deselected
            return false; // RatePay demands that adjustment refunds without products and shipping costs are sent without basket info
        }

        $blInvoiceEnabled = (bool)$this->getConfigParam('transmit_enabled', 'invoicing', 'payone_general', $sStoreCode); // invoicing enabled?
        if ($blInvoiceEnabled || $oPayment->needsProductInfo()) {
            return true; // invoice data needed
        }
        return false; // invoice data not needed
    }

    /**
     * Return base or display currency of the order depending on the config
     *
     * @param  SalesOrder $oOrder
     * @return null|string
     */
    public function getCurrencyFromOrder(SalesOrder $oOrder)
    {
        $sCurrency = $oOrder->getBaseCurrencyCode();
        if ($this->getConfigParam('currency', 'global', 'payone_general', $oOrder->getStore()->getCode()) == 'display') {
            $sCurrency = $oOrder->getOrderCurrencyCode();
        }
        return $sCurrency;
    }

    /**
     * Return base or display amount of the order depending on the config
     *
     * @param  SalesOrder $oQuote
     * @return float
     */
    public function getOrderAmount(SalesOrder $oOrder)
    {
        $dAmount = $oOrder->getBaseGrandTotal();
        if ($this->getConfigParam('currency', 'global', 'payone_general', $oOrder->getStore()->getCode()) == 'display') {
            $dAmount = $oOrder->getGrandTotal(); // send display amount instead of base amount
        }
        return $dAmount;
    }

    /**
     * Return base or display currency of the quote depending on the config
     *
     * @param  Quote $oQuote
     * @return string
     */
    public function getCurrencyFromQuote(Quote $oQuote)
    {
        $sCurrency = $oQuote->getBaseCurrencyCode();
        if ($this->getConfigParam('currency', 'global', 'payone_general', $oQuote->getStore()->getCode()) == 'display') {
            $sCurrency = $oQuote->getQuoteCurrencyCode();
        }
        return $sCurrency;
    }

    /**
     * Return base or display amount of the quote depending on the config
     *
     * @param  Quote $oQuote
     * @return float
     */
    public function getQuoteAmount(Quote $oQuote)
    {
        $dAmount = $oQuote->getBaseGrandTotal();
        if ($this->getConfigParam('currency', 'global', 'payone_general', $oQuote->getStore()->getCode()) == 'display') {
            $dAmount = $oQuote->getGrandTotal(); // send display amount instead of base amount
        }
        return $dAmount;
    }
}
