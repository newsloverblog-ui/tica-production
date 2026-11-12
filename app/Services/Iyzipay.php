<?php

namespace App\Services;

use App\Models\PaymentGateway\OnlineGateway;
use RuntimeException;

class Iyzipay
{
    public static function options(): \Iyzipay\Options
    {
        $gateway = OnlineGateway::where('keyword', 'iyzico')->first();
        $information = $gateway ? json_decode($gateway->information, true) : null;

        if (!$gateway || !is_array($information)) {
            throw new RuntimeException('Iyzico payment gateway is not configured.');
        }

        $options = new \Iyzipay\Options();
        $options->setApiKey($information['api_key'] ?? '');
        $options->setSecretKey($information['secret_key'] ?? '');
        $options->setBaseUrl(!empty($information['sandbox_status'])
            ? 'https://sandbox-api.iyzipay.com'
            : 'https://api.iyzipay.com');

        return $options;
    }
}
