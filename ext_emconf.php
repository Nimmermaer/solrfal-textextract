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
                'typo3' => '13.4.0-13.4.99',
                'solrfal' => '13.0.0-13.9.99',
            ],
            'conflicts' => [],
        ],
];
