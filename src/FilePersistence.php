<?php

namespace mindplay\jsondoc;

/**
 * This class is responsible for creating, reading, writing and deleting files and folders.
 */
class FilePersistence implements Persistence
{
    /**
     * @var int permission mask applied to created directories
     */
    public $dir_mode = 0755;

    /**
     * @var int permission mask applied to created files
     */
    public $file_mode = 0755;

    /**
     * @inheritdoc
     */
    public function ensureDir($path)
    {
        if (file_exists($path)) {
            if (!is_dir($path)) {
                throw new DocumentException("path is not a directory: {$path}");
            }
            return;
        }

        $mask = umask(0);
        $success = @mkdir($path, $this->dir_mode, true) !== false;
        umask($mask);

        if (false === $success) {
            throw new DocumentException("unable to create directory: {$path}");
        }
    }

    /**
     * @inheritdoc
     */
    public function readFile($path)
    {
        $data = @file_get_contents($path);

        if ($data === false) {
            throw new DocumentException("unable to read file: {$path}");
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function writeFile($path, $data)
    {
        $this->ensureDir(dirname($path));

        $mask = umask(0);

        $file_written = @file_put_contents($path, $data) !== false;
        $mode_set = @chmod($path, $this->file_mode) !== false;

        umask($mask);

        if (false === $file_written) {
            throw new DocumentException("unable to write file: {$path}");
        }

        if (false === $mode_set) {
            throw new DocumentException("unable to set file mode: {$path}");
        }
    }

    /**
     * @inheritdoc
     */
    public function moveFile($from, $to)
    {
        if (file_exists($to)) {
            if (@unlink($to) === false) {
                throw new DocumentException("unable to move file from: {$from} - over existing file: {$to}");
            }
        }

        if (@rename($from, $to) === false) {
            throw new DocumentException("unable to move file from: {$from} - to: {$to}");
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteFile($path)
    {
        if (@unlink($path) === false) {
            throw new DocumentException("unable to delete file: {$path}");
        }
    }

    /**
     * @inheritdoc
     */
    public function fileExists($path)
    {
        return file_exists($path);
    }
}
