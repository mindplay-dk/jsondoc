<?php

namespace mindplay\jsondoc;

use mindplay\jsonfreeze\JsonSerializer;

/**
 * This component represents a document store.
 */
class DocumentStore
{
    protected $path;
    protected $serializer;

    protected $namePattern = '/^[\w-]+$/';

    public $dirMode = 0755;
    public $fileMode = 0755;

    public function __construct($path, JsonSerializer $serializer = null)
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        if (!is_dir($path)) {
            throw new DocumentException("path does not exist: {$path}");
        }

        if ($serializer === null) {
            $serializer = new JsonSerializer();
        }

        $this->path = $path;
        $this->serializer = $serializer;
    }

    /**
     * Creates a new DocumentSession with the given database-name.
     */
    public function openSession($database)
    {
        return new DocumentSession($this, $database);
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * Returns true if the given name is valid - otherwise returns false.
     */
    public function isValidName($name)
    {
        return preg_match($this->namePattern, $name) === 1;
    }

    /**
     * Ensures that the given path is a directory - or throws a DocumentException
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
        $success = @mkdir($path, $this->dirMode, true) !== false;
        umask($mask);

        if (false === $success) {
            throw new DocumentException("unable to create directory: {$path}");
        }
    }

    /**
     * Reads the contents of a physical file at the given path.
     * Throws a DocumentException if the given path does not point to a file.
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
     * Writes data to a physical file at the given path, creating the path if necessary.
     */
    public function writeFile($path, $data)
    {
        $this->ensureDir(dirname($path));

        $mask = umask(0);

        $file_written = @file_put_contents($path, $data) !== false;
        $mode_set = @chmod($path, $this->fileMode) !== false;

        umask($mask);

        if (false === $file_written) {
            throw new DocumentException("unable to write file: {$path}");
        }

        if (false === $mode_set) {
            throw new DocumentException("unable to set file mode: {$path}");
        }
    }

    /**
     * Move a file, replacing any existing file in the destination path.
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
     * Delete a file - throws a DocumentException if the file does not exist.
     */
    public function deleteFile($path)
    {
        if (@unlink($path) === false) {
            throw new DocumentException("unable to delete file: {$path}");
        }
    }
}
