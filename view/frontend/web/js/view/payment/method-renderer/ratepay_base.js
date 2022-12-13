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
 * @copyright 2003 - 2022 Payone GmbH
 * @license   <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link      http://www.payone.de
 */
define(
    [
        'Payone_Core/js/view/payment/method-renderer/base',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'mage/translate'
    ],
    function (Component, quote, customer, $t) {
        'use strict';
        return Component.extend({
            isPlaceOrderActionAllowedRatePay: function () {
                return (quote.billingAddress() != null && quote.billingAddress().getCacheKey() == quote.shippingAddress().getCacheKey());
            },
            getData: function () {
                var parentReturn = this._super();
                if (parentReturn.additional_data === null) {
                    parentReturn.additional_data = {};
                }
                if (this.requestBirthday()) {
                    parentReturn.additional_data.dateofbirth = this.birthyear() + this.birthmonth() + this.birthday();
                }
                if (this.requestTelephone()) {
                    parentReturn.additional_data.telephone = this.telephone();
                }
                return parentReturn;
            },

            /** Returns payment method instructions */
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            requestBirthday: function () {
                if (customer.customerData.dob == undefined || customer.customerData.dob === null) {
                    return true;
                }
                return false;
            },
            requestTelephone: function () {
                if (quote.billingAddress() == null || (typeof quote.billingAddress().telephone != 'undefined' && quote.billingAddress().telephone != '')) {
                    return false;
                }
                return true;
            },
            requestBic: function () {
                if (!quote.billingAddress() || !quote.billingAddress().countryId) { // no billing address selected currently
                    return false;
                }

                var sepaCountries = ['AT', 'BE', 'GB', 'BG', 'CY', 'HR', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SI', 'SK', 'ES', 'SE', 'NO', 'LI', 'IS', 'CH', 'MC'];
                if (sepaCountries.includes(quote.billingAddress().countryId) === false) {
                    return true;
                }
                return false;
            },
            isPostcodeValid: function () {
                var countryCode = quote.billingAddress().countryId;
                var postcode = quote.billingAddress().postcode.replace(" ", "");
                if (countryCode.toLowerCase() == "de" && postcode.length != 5) {
                    return false;
                } else if (countryCode.toLowerCase() == "at" && postcode.length != 4) {
                    return false;
                } else if (countryCode.toLowerCase() == "ch" && postcode.length != 4) {
                    return false;
                } else if (countryCode.toLowerCase() == "nl" && postcode.length != 6) {
                    return false;
                }
                return true;
            },
            validate: function () {
                if (this.requestBirthday() === true && !this.isBirthdayValid(this.birthyear(), this.birthmonth(), this.birthday())) {
                    this.messageContainer.addErrorMessage({'message': $t('You have to be at least 18 years old to use this payment type!')});
                    return false;
                }
                if (this.requestTelephone() === true && this.telephone() == '') {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter your telephone number!')});
                    return false;
                }
                if (this.isPostcodeValid() === false) {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter a valid postcode!')});
                    return false;
                }
                return true;
            }
        });
    }
);
