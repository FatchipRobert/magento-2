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
 * PHP version 8
 *
 * @category  Payone
 * @package   Payone_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2003 - 2025 Payone GmbH
 * @license   <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link      http://www.payone.de
 */

namespace Payone\Core\Model\Api\Request\Genericpayment;

use Payone\Core\Model\Methods\PayoneMethod;
use Magento\Quote\Model\Quote;

/**
 * Class for the PAYONE Server API request genericpayment - "init_applepay_session"
 */
class InitApplePaySession extends Base
{
    /**
     * Send request to PAYONE Server-API with request-type "genericpayment" and action "init_applepay_session"
     *
     * @param  PayoneMethod $oPayment payment object
     * @param  Quote        $oQuote
     * @return array
     */
    public function sendRequest(PayoneMethod $oPayment, Quote $oQuote)
    {
        $this->addParameter('request', 'genericpayment');
        $this->addParameter('add_paydata[action]', 'init_applepay_session');

        $this->addParameter('add_paydata[display_name]', $this->shopHelper->getStoreName());

        $this->addParameter('add_paydata[domain_name]', $this->shopHelper->getShopDomain());

        $this->addParameter('reference', $this->apiHelper->getReferenceNrByQuote($oQuote, $oPayment, $this->storeCode));

        $this->addParameter('mode', $oPayment->getOperationMode());
        $this->addParameter('aid', $this->shopHelper->getConfigParam('aid')); // ID of PayOne Sub-Account

        $this->addParameter('clearingtype', $oPayment->getClearingtype());
        $this->addParameter('wallettype', 'APL');

        $oBilling = $oQuote->getBillingAddress();
        $this->addParameter('country', $oBilling->getCountryId());
        $this->addParameter('lastname', $oBilling->getLastname());

        $this->addParameter('currency', $this->apiHelper->getCurrencyFromQuote($oQuote));

        return $this->send($oPayment);
    }
}
