<?php

return [
    'alipay' => [
        'app_id'         => '',
        'ali_public_key' => '',
        'private_key'    => '',
        'log'            => [
            'file' => storage_path('logs/alipay.log'),
        ],
    ],

    'wechat' => [
        'app_id'      => '',
        'mch_id'      => '',
        'key'         => '',
        'cert_client' => '',
        'cert_key'    => '',
        'log'         => [
            'file' => storage_path('logs/wechat_pay.log'),
        ],
    ],
    'credentials' => [
        'username' => 'Aehr1z_51vtXjdFglQS-hF3NwGhHA8fNbvXe8-Vc6Dtw_kliduUGcKhqE6oFdZABCbtVyXdb1W1Qevla',
        'password' => 'EK0RS5VUHGVI4HIxa9pRXGPNrSBc23jMTMeHuEX6qoylxiHCkIjPCVLvYI4EnKqXHQv9m3nDmsrGnefC',
    ],

];