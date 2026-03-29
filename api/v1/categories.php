<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$categories = [
    [
        'id' => 'INVESTOR_INTRO',
        'title' => 'Predstavenie investorovi',
        'description' => 'Hľadáte intro alebo kontakt na investora pre váš projekt.',
        'slug' => 'predstavenie-investorovi',
    ],
    [
        'id' => 'HIRING',
        'title' => 'Nábor',
        'description' => 'Potrebujete nájsť vhodného človeka do tímu alebo externú posilu.',
        'slug' => 'nabor',
    ],
    [
        'id' => 'SPEAKING_OPPORTUNITY',
        'title' => 'Príležitosť na vystúpenie',
        'description' => 'Máte záujem vystúpiť, prezentovať alebo moderovať na podujatí.',
        'slug' => 'prilezitost-na-vystupenie',
    ],
    [
        'id' => 'MARKETING_SUPPORT',
        'title' => 'Marketingová podpora',
        'description' => 'Potrebujete pomoc s propagáciou, obsahom alebo komunikačnou stratégiou.',
        'slug' => 'marketingova-podpora',
    ],
    [
        'id' => 'SALES_SUPPORT',
        'title' => 'Podpora predaja',
        'description' => 'Potrebujete podporu v oblasti predaja, outreachu alebo obchodného procesu.',
        'slug' => 'podpora-predaja',
    ],
    [
        'id' => 'PARTNERSHIP',
        'title' => 'Partnerstvo',
        'description' => 'Hľadáte strategického partnera pre spoluprácu alebo spoločný projekt.',
        'slug' => 'partnerstvo',
    ],
    [
        'id' => 'PRODUCT_FEEDBACK',
        'title' => 'Spätná väzba na produkt',
        'description' => 'Potrebujete spätnú väzbu na produkt, funkcie alebo používateľský zážitok.',
        'slug' => 'spatna-vazba-na-produkt',
    ],
    [
        'id' => 'LEGAL_FINANCE',
        'title' => 'Právo a financie',
        'description' => 'Potrebujete konzultáciu v právnej alebo finančnej oblasti.',
        'slug' => 'pravo-a-financie',
    ],
    [
        'id' => 'OPERATIONS',
        'title' => 'Operatíva',
        'description' => 'Potrebujete zefektívniť interné procesy, riadenie alebo prevádzku.',
        'slug' => 'operativa',
    ],
    [
        'id' => 'OTHER',
        'title' => 'Iné',
        'description' => 'Iný typ požiadavky, ktorý nespadá do uvedených kategórií.',
        'slug' => 'ine',
    ],
];

respond($categories);
