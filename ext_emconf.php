<?php
$EM_CONF[$_EXTKEY] = array(
    'title' => 'Apache Solr for TYPO3 - File Indexing - Text extracting',
    'description' => 'Add text extracting for indexing of FileAbstractionLayer based files in TYPO3 CMS',
    'category' => 'misc',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'author' => 'Frans Saris (Beech.it)',
    'author_email' => 't3ext@beech.it',
    'author_company' => 'Beech IT',
    'clearCacheOnLoad' => 1,
    'lockType' => '',
    'version' => '1.1.2',
    'constraints' =>
        [
            'depends' => [
                'typo3' => '11.5.4-11.5.99',
                'solrfal' => 'dev-master',
            ],
            'conflicts' => [],
        ],
);
