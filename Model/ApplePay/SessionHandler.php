<?php

namespace Payone\Core\Model\ApplePay;

use Payone\Core\Model\PayoneConfig;

class SessionHandler
{
    /**
     * Magento 2 Curl library
     *
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    /**
     * Payone shop helper object
     *
     * @var \Payone\Core\Helper\Shop
     */
    protected $shopHelper;

    /**
     * Payone Apple Pay helper object
     *
     * @var \Payone\Core\Helper\ApplePay
     */
    protected $applePayHelper;

    /**
     * Checkout session object
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * Request object
     *
     * @var \Payone\Core\Model\Api\Request\Genericpayment\InitApplePaySession
     */
    protected $initApplepaySessionRequest;

    /**
     * Request URL for collecting the Apple Pay session
     *
     * @var string
     */
    protected $applePaySessionUrl =  "https://apple-pay-gateway-cert.apple.com/paymentservices/startSession";

    /**
     * Constructor
     *
     * @param \Magento\Framework\HTTP\Client\Curl                               $curl
     * @param \Payone\Core\Helper\Shop                                          $shopHelper
     * @param \Payone\Core\Helper\ApplePay                                      $applePayHelper
     * @param \Magento\Checkout\Model\Session                                   $checkoutSession
     * @param \Payone\Core\Model\Api\Request\Genericpayment\InitApplePaySession $initApplepaySessionRequest
     */
    public function __construct(
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Payone\Core\Helper\Shop $shopHelper,
        \Payone\Core\Helper\ApplePay $applePayHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Payone\Core\Model\Api\Request\Genericpayment\InitApplePaySession $initApplepaySessionRequest
    ) {
        $this->curl = $curl;
        $this->shopHelper = $shopHelper;
        $this->applePayHelper = $applePayHelper;
        $this->checkoutSession = $checkoutSession;
        $this->initApplepaySessionRequest = $initApplepaySessionRequest;
    }

    /**
     * Returns path to certificate file
     *
     * @return string
     * @throws \Exception
     */
    protected function getCertificateFilePath()
    {
        $sCertFile = $this->shopHelper->getConfigParam("certificate_file", PayoneConfig::METHOD_APPLEPAY, "payment");
        $sCertFilePath = $this->applePayHelper->getApplePayUploadPath().$sCertFile;

        if ($this->applePayHelper->hasCertificateFile() === false) {
            throw new \Exception("Certificate file not configured or missing");
        }
        return $sCertFilePath;
    }

    /**
     * Returns path to private key file
     *
     * @return string
     * @throws \Exception
     */
    protected function getPrivateKeyFilePath()
    {
        $sPrivateKeyFile = $this->shopHelper->getConfigParam("private_key_file", PayoneConfig::METHOD_APPLEPAY, "payment");
        $sPrivateKeyFilePath = $this->applePayHelper->getApplePayUploadPath().$sPrivateKeyFile;

        if ($this->applePayHelper->hasPrivateKeyFile() === false) {
            throw new \Exception("Private key file not configured or missing");
        }
        return $sPrivateKeyFilePath;
    }

    protected function getSessionByCertFile()
    {
        $aRequest = [
            'merchantIdentifier' => $this->shopHelper->getConfigParam("merchant_id", PayoneConfig::METHOD_APPLEPAY, "payment"),
            'displayName' => 'PAYONE Apple Pay Magento2',
            'initiative' => 'web',
            'initiativeContext' => $this->shopHelper->getShopDomain(),
        ];

        $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, 0);
        $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, 0);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, 1);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, 1);
        $this->curl->setOption(CURLOPT_SSLCERT, $this->getCertificateFilePath());
        $this->curl->setOption(CURLOPT_SSLKEY, $this->getPrivateKeyFilePath());

        $sKeyPass = $this->shopHelper->getConfigParam("private_key_password", PayoneConfig::METHOD_APPLEPAY, "payment");
        if (!empty($sKeyPass)) {
            $this->curl->setOption( CURLOPT_KEYPASSWD, $sKeyPass);
        }

        $this->curl->post($this->applePaySessionUrl, json_encode($aRequest));
        return $this->curl->getBody();
    }

    /**
     * @return string|false
     */
    protected function getSessionFromPayoneApi()
    {
        $aReturn = ['success' => false];;

        $oQuote = $this->checkoutSession->getQuote();

        $aResult = $this->initApplepaySessionRequest->sendRequest($oQuote->getPayment()->getMethodInstance(), $oQuote);
        if (isset($aResult['status'])) {
            if ($aResult['status'] == 'OK' && !empty($aResult['add_paydata[applepay_payment_session]'])) {
                return base64_decode($aResult['add_paydata[applepay_payment_session]']);
            } elseif ($aResult['status'] == 'ERROR' && !empty($aResult['errormessage'])) {
                throw new \Exception($aResult['errormessage']);
            }
        }
        return false;
    }

    /**
     * Requests apple pay session and returns it
     *
     * @return bool|string
     * @throws \Exception
     */
    public function getApplePaySession()
    {
        if ((bool)$this->shopHelper->getConfigParam("new_auth_active", PayoneConfig::METHOD_APPLEPAY, "payment") === true) {
            return $this->getSessionFromPayoneApi(); // new auth
        }
        return $this->getSessionByCertFile(); // old auth
    }
}
