<?php

namespace App\Support\Demo;

final class WalkthroughDemoFixture
{
    public const CLINIC_CNPJ = '12345678000190';

    public const USER_EMAIL = 'walkthrough@vetflow.local';

    public const TUTOR_EMAIL = 'mariana.demo@vetflow.local';

    public const PATIENT_NAME = 'Luna';

    public const APPOINTMENT_TITLE = 'Consulta de retorno - Luna';

    public const SOURCE = 'walkthrough_demo';

    public const SALE_CODE = 'DEMO-SALE-0001';

    public const SALE_PAYMENT_REFERENCE = 'DEMO-PAY-0001';

    /**
     * @var array<int, string>
     */
    public const FINANCIAL_REFERENCES = [
        'DEMO-SALE-0001',
        'DEMO-EXPENSE-0001',
    ];

    /**
     * @var array<int, string>
     */
    public const GLOBAL_PRODUCT_GTINS = [
        '7891000100103',
        '7891000200209',
    ];

    /**
     * @var array<int, string>
     */
    public const PRODUCT_SKUS = [
        'DEMO-RACAO-3KG',
        'DEMO-VERM-10KG',
        'DEMO-SHAMPOO-500',
    ];
}
