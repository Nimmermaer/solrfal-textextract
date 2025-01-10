<?php
namespace BeechIt\SolrfalTextextract\Aspects;

/*
 * This source file is proprietary property of Beech Applications B.V.
 * Date: 1-04-2015 11:07
 * All code (c) Beech Applications B.V. all rights reserved
 */

use ApacheSolrForTypo3\Solrfal\Queue\Item;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class SolrFalAspect
 */
class SolrFalAspect implements SingletonInterface
{
    /**
     * @var string
     */
    protected mixed $pathTika = '/usr/bin';

    /**
     * @var string
     */
    protected mixed $pathPdftotext = '';

    /**
     * @var array
     */
    protected array $supportedFileExtensions = [];

    /**
     * @var bool
     */
    protected bool $debug = TRUE;

    /**
     * @var Logger
     */
    protected \Psr\Log\LoggerInterface|Logger|null $logger = null;

    /**
     * Contructor
     */
    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $extConf = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solrfal_textextract'];
        if (!empty($extConf['pathTika'])) {
            $this->pathTika = $extConf['pathTika'];


            if (!PathUtility::isAbsolutePath($this->pathTika)) {
                $this->pathTika = PathUtility::getCanonicalPath(Environment::getPublicPath() . '/' . $this->pathTika);
            }

            if (!@is_file($this->pathTika)) {
                $this->pathTika = GeneralUtility::getFileAbsFileName($this->pathTika, false);
            }

            if (!@is_file($this->pathTika)) {
                $this->pathTika = NULL;
            }
        }
        if (!empty($extConf['pathPdftotext'])) {
            $this->pathPdftotext = $extConf['pathPdftotext'];
        }
        if (!empty($extConf['supportedFileExtensions'])) {
            $this->supportedFileExtensions = GeneralUtility::trimExplode(',', $extConf['supportedFileExtensions']);
        }
        $this->debug = !empty($extConf['debugMode']);

        if ($this->debug) {
            $messages = array();
            if (!$this->pathTika) {
                $messages[] = 'Tika jar not found.';
            }
            if (!$this->pathPdftotext) {
                $messages[] = 'No pdftotext path set';
            }
            if ($this->supportedFileExtensions === array()) {
                $messages[] = 'No supported file extensions set';
            }
            if ($messages !== array()) {
                $this->logger->error('Configuration error: ' . implode(',', $messages));
            }
        }
    }

    /**
     * Add correct fe_group info and public_url
     *
     * @param Item $item
     * @param \ArrayObject $metadata
     */
    public function fileMetaDataRetrieved(Item $item, \ArrayObject $metadata): void
    {
        if ($item->getFile() instanceof File && in_array(mb_strtolower($item->getFile()->getExtension()), $this->supportedFileExtensions)) {
            $content = NULL;
            if ($item->getFile()->getExtension() === 'pdf') {
                $content = $this->pdfToText($item->getFile());
            }
            if ($content === NULL && $this->pathTika) {
                $content = $this->fileToText($item->getFile());
            }
            if ($content !== NULL) {
                $metadata['content'] = $content;
                $this->logger->debug('Parsed content of ' . $item->getFile()->getIdentifier());
            } else {
                $this->logger->debug('Could not parse ' . $item->getFile()->getIdentifier());
            }
        } elseif ($item->getFile() instanceof File) {
            $this->logger->debug('Could not parsed content of ' . $item->getFile()->getIdentifier() . ' unsupported file extension');
        }
    }

    /**
     * Use pdftotext to extract contents of pdf file
     *
     * @param File $file
     * @return string
     */
    protected function pdfToText(File $file): ?string
    {
        if ($this->isPdfEncrypted($file)) {
            return '';
        }
        $tempFile = GeneralUtility::tempnam('pdfToText');
        $cmd = rtrim($this->pathPdftotext, '/') . '/pdftotext -enc UTF-8 -q '
            . escapeshellarg($file->getForLocalProcessing(FALSE))
            . ' ' . $tempFile;
        exec($cmd);
        $content = file_get_contents($tempFile);
        GeneralUtility::unlink_tempfile($tempFile);

        // Last check for encrypted document
        if ($this->textHasEncryptionMarks($content)) {
            $content = NULL;
        }

        return $content;
    }

    /**
     * Check if pdf is encrypted
     *
     * @param File $file
     * @return bool
     */
    protected function isPdfEncrypted(File $file): bool
    {
        $encrypted = FALSE;
        $cmd = rtrim($this->pathPdftotext, '/') . '/pdfinfo '
            . escapeshellarg($file->getForLocalProcessing(FALSE));
        CommandUtility::exec($cmd, $pdfInfoArray);

        $form = '';
        $version = 0;
        $copyEncrypted = FALSE;
        $changeEncrypted = FALSE;
        $optimized = FALSE;
        $pageFormatA4 = FALSE;

        // Find some info about pdf to determine if we can read its contents
        foreach ($pdfInfoArray as $line) {
            list($key, $value) = explode(':', $line, 2);
            $value = trim($value);

            if ($key === 'Encrypted') {
                if ($value !== 'no') {
                    $encrypted = TRUE;
                    $copyEncrypted = strpos($value, 'copy:no') === FALSE;
                    $changeEncrypted = strpos($value, 'change:no') === FALSE;
                }
            }

            if ($key === 'Form') {
                $form = $value;
            }
            if ($key === 'PDF version') {
                $version = (float)$value;
            }
            if ($key === 'Optimized') {
                $optimized = ($value === 'Yes');
            }
            if ($key === 'Page size') {
                $pageFormatA4 = (strpos($value, 'A4') === FALSE);
            }
        }

        // Forms are readable (AcroForm we know for sure, but we expect all forms)
        if ($form !== 'none') {
            $encrypted = FALSE;
        }
        // Version < 1.6 can also be read if copy of change isn't encrypted
        if ($version < 1.6 && (!$copyEncrypted || !$changeEncrypted)) {
            $encrypted = FALSE;
        }

        // PDF version 1.6 is also readable for if not optimized
        if ($version === 1.6 && !$optimized) {
            $encrypted = FALSE;
        }
        // PDF version 1.6 and no A4 value is also (most times) readable
        if ($version === 1.6 && !$pageFormatA4) {
            $encrypted = FALSE;
        }

        return $encrypted;
    }

    /**
     * Check if text has markers that are consistent with an encrypted document
     *
     * @param $text
     * @return bool
     */
    protected function textHasEncryptionMarks($text)
    {
        if (str_contains($text, '%#$#')) {
            return TRUE;
        }
        if (str_contains($text, '!%!')) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Use tika to extract contents of pdf file
     *
     * @param File $file
     * @return string
     */
    protected function fileToText(File $file): ?string
    {
        $content = NULL;
        $tikaCommand = CommandUtility::getCommand('java')
            . ' -Dfile.encoding=UTF8' // forces UTF8 output
            . ' -jar ' . escapeshellarg($this->pathTika)
            . ' -t'
            . ' ' . escapeshellarg($file->getForLocalProcessing(FALSE));

        exec($tikaCommand, $output);

        if ($output) {
            $content = implode(PHP_EOL, $output);

            // Last check for encrypted document
            if ($this->textHasEncryptionMarks($content)) {
                $content = NULL;
            }
        }

        return $content;
    }
}
