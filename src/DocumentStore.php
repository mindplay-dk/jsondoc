<?php

namespace mindplay\jsondoc;

use mindplay\jsonfreeze\JsonSerializer;

/**
 * This component represents a document store.
 */
class DocumentStore
{
    /**
     * @var string absolute path to document store root folder
     */
    protected $path;

    /**
     * @var JsonSerializer
     */
    protected $serializer;

    /**
     * @var int permission mask applied to created directories
     */
    public $dirMode = 0755;

    /**
     * @var int permission mask applied to created files
     */
    public $fileMode = 0755;

    /**
     * @param string              $path absolute path to document store root folder
     * @param JsonSerializer|null $serializer
     *
     * @throws DocumentException if the given path does not exist
     */
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
     * Opens a new Session with the given database-name.
     *
     * @return DocumentSession
     */
    public function openSession($database)
    {
        return new DocumentSession($this, $database);
    }

    /**
     * @return string absolute path to document store root folder
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return JsonSerializer
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @return bool true, if the given name is valid; otherwise false
     */
    public function isValidName($name)
    {
        return preg_match('/^[\w-]+$/', $name) === 1;
    }

    /**
     * Ensures that the given path is a directory and/or creates it.
     *
     * @param string $path absolute path to directory
     *
     * @return void
     *
     * @throws DocumentException if the given path is not a directory, or could not be created
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
     *
     * @param string $path absolute path to a file
     *
     * @return string file contents
     *
     * @throws DocumentException if the specified file could not be read
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
     *
     * @param string $path absolute path to the file to write
     * @param string $data file contents
     *
     * @return void
     *
     * @throws DocumentException if unable to write the file (or set permissions)
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
     *
     * @param string $from absolute source path
     * @param string $to absolute destination path
     *
     * @return void
     *
     * @throws DocumentException if the specified file could not be moved
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
     * Delete a file.
     *
     * @param string $path absolute path of the file to be deleted
     *
     * @return void
     *
     * @throws DocumentException if the specified file could not be deleted
     */
    public function deleteFile($path)
    {
        if (@unlink($path) === false) {
            throw new DocumentException("unable to delete file: {$path}");
        }
    }
}
