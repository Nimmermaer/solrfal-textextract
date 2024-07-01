<?php
$EM_CONF['solrfal_textextract'] = [
    'title' => 'Apache Solr for TYPO3 - File Indexing - Text extracting',
    'description' => 'Add text extracting for indexing of FileAbstractionLayer based files in TYPO3 CMS',
    'category' => 'misc',
    'state' => 'beta',
    'author' => 'Frans Saris (Beech.it)',
    'author_email' => 't3ext@beech.it',
    'author_company' => 'Beech IT',
    'version' => '1.1.2',
    'constraints' =>
        [
            'depends' => [
                'typo3' => '12.4.0-12.4.99',
                'solrfal' => 'dev-master',
            ],
            'conflicts' => [],
        ],
];
