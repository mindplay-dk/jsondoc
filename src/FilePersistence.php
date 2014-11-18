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
    public $dir_mode;

    /**
     * @var int permission mask applied to created files
     */
    public $file_mode;

    /**
     * @var string absolute path to the database lock-file.
     */
    protected $lock_path;

    /**
     * @var resource file-handle used when locking the database
     */
    private $_lock = null;

    /**
     * @var string absolute path to persistence root folder
     */
    private $_root;

    /**
     * @param string $root_path absolute path to persistence root folder
     * @param int    $dir_mode  permission mask applied to created directories
     * @param int    $file_mode permission mask applied to created files
     *
     * @throws DocumentException if the given path does not exist
     */
    public function __construct($root_path, $dir_mode = 0755, $file_mode = 0755)
    {
        $this->dir_mode = $dir_mode;
        $this->file_mode = $file_mode;

        $root_path = rtrim($root_path, DIRECTORY_SEPARATOR);

        if (!is_dir($root_path)) {
            throw new DocumentException("path does not exist: {$root_path}");
        }

        $this->_root = $root_path;

        $this->ensureDir($root_path);

        $this->lock_path = $root_path . DIRECTORY_SEPARATOR . '.lock';
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
    protected function ensureDir($path)
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
     * Maps the given id to a physical path.
     *
     * @param string $id document ID
     *
     * @return string absolute path to JSON document file
     *
     * @throws DocumentException if the given document ID is invalid
     */
    protected function mapPath($id)
    {
        if (!is_string($id)) {
            throw new DocumentException("the given id is not a string");
        }

        $path = $this->_root;

        foreach (explode('/', $id) as $part) {
            if (!$this->isValidName($part)) {
                throw new DocumentException("invalid document id: {$id} - invalid name: {$part}");
            }

            $path .= DIRECTORY_SEPARATOR . $part;
        }

        $path .= '.json';

        return $path;
    }

    /**
     * @param string $name
     *
     * @return bool true, if the given name is valid; otherwise false
     */
    protected function isValidName($name)
    {
        return preg_match('/^[-\w\.]+$/', $name) === 1;
    }

    /**
     * @inheritdoc
     */
    public function lock($exclusive = false)
    {
        $this->unlock();

        $mask = umask(0);

        $lock = @fopen($this->lock_path, 'a');

        @chmod($this->lock_path, 0666);

        umask($mask);

        if ($lock === false) {
            throw new DocumentException("unable to create the lock-file: {$this->lock_path}");
        }

        if (@flock($lock, $exclusive === true ? LOCK_EX : LOCK_SH) === false) {
            throw new DocumentException("unable to lock the database: {$this->lock_path}");
        }

        $this->_lock = $lock;
    }

    /**
     * @inheritdoc
     */
    public function unlock()
    {
        if ($this->isLocked()) {
            flock($this->_lock, LOCK_UN);

            $this->_lock = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function isLocked()
    {
        return $this->_lock !== null;
    }

    /**
     * @inheritdoc
     */
    public function readFile($id)
    {
        $path = $this->mapPath($id);

        $data = @file_get_contents($path);

        if ($data === false) {
            throw new DocumentException("unable to read file: {$path}");
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function writeFile($id, $data)
    {
        $path = $this->mapPath($id);

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
    public function moveFile($from_id, $to_id)
    {
        $from = $this->mapPath($from_id);
        $to = $this->mapPath($to_id);

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
    public function deleteFile($id)
    {
        $path = $this->mapPath($id);

        if (@unlink($path) === false) {
            throw new DocumentException("unable to delete file: {$path}");
        }
    }

    /**
     * @inheritdoc
     */
    public function fileExists($id)
    {
        $path = $this->mapPath($id);

        return file_exists($path);
    }
}
