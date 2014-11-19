<?php

namespace mindplay\jsondoc;

class FileMutex implements Mutex
{
    /**
     * @var string absolute path to lock file
     */
    private $_path;

    /**
     * @var resource file-handle used when locking the database
     */
    private $_lock = null;

    /**
     * @param string $path absolute path to lock file directory
     */
    public function __construct($path)
    {
        $this->_path = $path . DIRECTORY_SEPARATOR . '.lock';
    }

    /**
     * @inheritdoc
     */
    public function lock($exclusive = false)
    {
        $this->unlock();

        $mask = umask(0);

        $lock = @fopen($this->_path, 'a');

        @chmod($this->_path, 0666);

        umask($mask);

        if ($lock === false) {
            throw new DocumentException("unable to create the lock-file: {$this->_path}");
        }

        if (@flock($lock, $exclusive === true ? LOCK_EX : LOCK_SH) === false) {
            throw new DocumentException("unable to lock the database: {$this->_path}");
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
}
