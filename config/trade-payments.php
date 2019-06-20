<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'gates' =>[
        'payboutique'=>'cryptofx\payments\gateway\PayBoutique',
        'paylane'=>'cryptofx\payments\gateway\Paylane',
        'megatransfer'=>'cryptofx\payments\gateway\MegaTransfer',
        'advcash'=>'cryptofx\payments\gateway\Advcash',
        'perfectmoney'=>'cryptofx\payments\gateway\PerfectMoney',
        'pbs'=>'cryptofx\payments\gateway\PBSDirectPayment',
        'netpay'=>'cryptofx\payments\gateway\Netpay',
        'ikajo'=>'cryptofx\payments\gateway\Ikajo',
        'payeer'=>'cryptofx\payments\gateway\Payeer',
        'interkassa'=>'cryptofx\payments\gateway\Interkassa',
        'blockchain'=>'cryptofx\payments\gateway\Blockchain',
        'fondy'=>'cryptofx\payments\gateway\Fondy',
        'startajob'=>'cryptofx\payments\gateway\StartAJob',
        'liqpay'=>'cryptofx\payments\gateway\LiqPay',
        'paysol'=>'cryptofx\payments\gateway\Paysol',
        'accentpay'=>'cryptofx\payments\gateway\Accentpay',
        'terrexa'=>'cryptofx\payments\gateway\Terrexa',
        'yandexkassa'=>'cryptofx\payments\gateway\YandexKassa',
        'xcp'=>'cryptofx\payments\gateway\Xcp',
    ],
];
