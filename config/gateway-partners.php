<?php

return [
    'cardstream' => [
        'name' => 'Cardstream',
        'contact_email' => env('CARDSTREAM_CONTRACT_EMAIL', 'rachel.attwood@g2pay.co.uk'),
        'contract_template' => 'pdf.gateway-contracts.cardstream',
    ],
    'acquired' => [
        'name' => 'Acquired',
        'contact_email' => env('ACQUIRED_CONTRACT_EMAIL', 'contracts@acquired.com'),
        'contract_template' => 'pdf.gateway-contracts.acquired',
    ],
];