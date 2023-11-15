<?php
/***************************************************************
 * Extension Manager/Repository config file for ext "migrator".
 ***************************************************************/
$EM_CONF['migrator'] = [
    'title' => 'DB Migrator',
    'description' => 'TYPO3 DB Migrator',
    'category' => 'be',
    'state' => 'beta',
    'author' => 'Sebastian Michaelsen, portrino GmbH',
    'author_email' => 'sebastian@app-zap.de, dev@portrino.de',
    'author_company' => 'app zap, portrino GmbH',
    'version' => '2.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-12.4.99',
        ],
    ],
];
