<?php

/**
 * An extension that will download files from the internet to the destination, unzipping before tests.
 *
 * @package PublishPress\Codeception\Extension
 */

namespace PublishPress\Codeception\Extension;

use Codeception\Configuration as Config;
use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;
use tad\WPBrowser\Filesystem\Utils;

/**
 * Class Downloader
 *
 * @package tad\WPBrowser\Extension
 */
class Downloader extends Extension
{
    /**
     * A map of the events and callbacks the extension hooks on.
     *
     * @var array<string,string>
     */
    public static $events = [
        Events::MODULE_INIT => 'downloadAndCopyFiles',
        Events::SUITE_AFTER => 'removeFiles'
    ];

    /**
     * @var mixed[]
     */
    protected $config;

    /**
     * Downloader constructor.
     *
     * @param array<string,mixed> $config The extension configuration.
     * @param array<string,mixed> $options The extension options.
     */
    public function __construct($config, $options)
    {
        if (!empty($config['files'])) {
            $this->ensureCacheDir($config['cache_dir']);
            array_walk($config['files'], [$this, 'ensureDestination']);
        }

        $this->config = $config;

        parent::__construct($config, $options);
    }

    /**
     * Copies the directory and files from the extension configuration.
     *
     * @return void
     */
    public function downloadAndCopyFiles()
    {
        if (empty($this->config['files'])) {
            return;
        }

        array_walk($this->config['files'], [$this, 'downloadAndCopy']);
    }

    /**
     * Removes the copied directories and files.
     *
     * @return void
     */
    public function removeFiles()
    {
        if (empty($this->config['files'])) {
            return;
        }

//        array_walk($this->config['files'], [$this, 'remove']);
    }

    /**
     * Checks the source to ensure it's accessible and readable.
     *
     * @param string $source The path to the source directory or file.
     *
     * @return void
     *
     * @throws ExtensionException If the source directory or file is not readable or not accessible.
     */
    protected function ensureCacheDir($source)
    {
        if (
            !(
                file_exists($source)
                || file_exists(getcwd() . DIRECTORY_SEPARATOR . trim($source, DIRECTORY_SEPARATOR))
            )
        ) {
            throw new ExtensionException($this, sprintf('Cache dir [%s] does not exist.', $source));
        }

        if (!is_readable($source)) {
            throw new ExtensionException($this, sprintf('Cache dir [%s] is not readable.', $source));
        }
    }

    /**
     * Checks a destination directory or file are accessible.
     *
     * @param string $destination The path to the copy destination.
     *
     * @return void
     *
     * @throws ExtensionException If the destination is not accessible.
     */
    protected function ensureDestination($destination)
    {
        $filename = dirname($destination);

        if (!(is_dir($filename))) {
            throw new ExtensionException($this, sprintf('Destination parent dir [%s] does not exist.', $filename));
        }

        if (!is_writable($filename)) {
            throw new ExtensionException($this, sprintf('Destination parent dir [%s] is not writeable.', $filename));
        }

//        if (file_exists($destination)) {
//            $this->remove($destination);
//        }
    }

    /**
     * Removes a previously created destination directory or file.
     *
     * @param string $destination The absolute path to the destination to remove.
     *
     * @return void
     *
     * @throws ExtensionException If the destination directory of file removal fails.
     */
    protected function remove($destination)
    {
        if (!file_exists($destination)) {
            return;
        }

        if (!\tad\WPBrowser\rrmdir($destination)) {
            throw new ExtensionException($this, sprintf('Removal of [%s] failed.', $destination));
        }
    }

    /**
     * Download and copy one source to one destination.
     *
     * @param string $destinationDir The absolute path to the destination.
     * @param string $source The absolute path to the source.
     *
     * @return void
     *
     * @throws ExtensionException If the copy from the source to the destination fails.
     */
    protected function downloadAndCopy($destinationDir, $source)
    {
        $fileName  = basename($source);
        $cacheFile = Config::projectDir() . $this->config['cache_dir'] . '/' . $fileName;

        if (!file_exists($cacheFile)) {
            echo "Downloading file from: " . $source . ': ';
            if (file_put_contents($cacheFile, file_get_contents($source))) {
                echo "successfully";
            } else {
                echo "error";
            }
            echo "\n";
        } else {
            echo "Using cached file: " . $cacheFile . "\n";
        }

        $zip = new \ZipArchive();
        if ($zip->open($cacheFile) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (file_exists($destinationDir . '/' . $zip->getNameIndex($i))) {
                    $this->remove($destinationDir . '/' . $zip->getNameIndex($i));
                }
                $zip->extractTo($destinationDir, [$zip->getNameIndex($i)]);
            }

            $zip->close();
        } else {
            throw new ExtensionException(
                $this,
                sprintf('Download and copy of [%s:%s] failed.', $source, $destinationDir)
            );
        }
    }
}
