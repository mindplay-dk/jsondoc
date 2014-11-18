<?php

namespace mindplay\jsondoc;

/**
 * This component represents a session with a DocumentStore
 */
class DocumentSession
{
    /**
     * @var DocumentStore the Document Store from which this Session was created
     */
    protected $store;

    /**
     * @var string database name
     */
    protected $database;

    /**
     * @var string root path to the database-folder.
     */
    protected $path;

    /**
     * @var object[] map where object_id => object
     */
    protected $objects = array();

    /**
     * @var int[] map where object_id => object status (see STATUS_* constants)
     */
    protected $status = array();

    /**
     * @var string[] map of data to be written on save(), where absolute path => JSON string
     */
    protected $files = array();

    /**
     * @var string absolute path to the database lock-file.
     */
    protected $lock_path;

    /**
     * @var Persistence document persistence layer
     */
    protected $persistence;

    /**
     * @var resource file-handle used when locking the database
     */
    private $_lock = null;

    const STATUS_KEEP = 0;
    const STATUS_STORE = 1;
    const STATUS_DELETE = - 1;

    /**
     * Opens a new session with the given store and the specified database.
     *
     * @param DocumentStore $store       the Store from which this Session was generated
     * @param Persistence   $persistence the document persistence layer
     * @param string        $database    database name
     *
     * @throws DocumentException on invalid database name
     */
    public function __construct(DocumentStore $store, Persistence $persistence, $database)
    {
        if (!$store->isValidName($database)) {
            throw new DocumentException("invalid database name: {$database}");
        }

        $path = $store->getPath() . DIRECTORY_SEPARATOR . $database;

        $this->persistence = $persistence;

        $this->persistence->ensureDir($path);

        $this->store = $store;
        $this->database = $database;
        $this->path = $path;

        $this->lock_path = $this->path . DIRECTORY_SEPARATOR . '.lock';
        $this->lock();
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Lock the database associated with this session.
     *
     * @param bool $exclusive true to lock the session exclusively;
     *                        or false to lock in shared mode, allowing other sessions to read
     *
     * @return void
     *
     * @throws DocumentException if unable to lock the database
     */
    protected function lock($exclusive = false)
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
     * Release a lock on the database associated with this session.
     *
     * @return void
     */
    protected function unlock()
    {
        if ($this->isLocked()) {
            flock($this->_lock, LOCK_UN);

            $this->_lock = null;
        }
    }

    /**
     * @return bool true, if the database is currently locked
     */
    protected function isLocked()
    {
        return $this->_lock !== null;
    }

    /**
     * Maps the given id to a physical path.
     *
     * @param string $id document ID
     *
     * @return string absolutel path to JSON document file
     *
     * @throws DocumentException if the given document ID is invalid
     */
    protected function mapPath($id)
    {
        if (!is_string($id)) {
            throw new DocumentException("the given id is not a string");
        }

        $path = $this->path;

        foreach (explode('/', $id) as $part) {
            if (!$this->store->isValidName($part)) {
                throw new DocumentException("invalid document id: {$id} - invalid name: {$part}");
            }

            $path .= DIRECTORY_SEPARATOR . $part;
        }

        $path .= '.json';

        return $path;
    }

    /**
     * Loads the object with the specified id.
     *
     * @param string $id document ID
     *
     * @return object the loaded object
     *
     * @throws DocumentException if the object could not be loaded for any reason
     */
    public function load($id)
    {
        if (! $this->isLocked()) {
            throw new DocumentException("cannot load an object into a closed session");
        }

        if (!array_key_exists($id, $this->status)) {
            $path = $this->mapPath($id);
            $data = $this->persistence->readFile($path);

            $this->objects[$id] = $this->store->getSerializer()->unserialize($data);
            $this->status[$id] = self::STATUS_KEEP;
        }

        if ($this->status[$id] === self::STATUS_DELETE) {
            throw new DocumentException("cannot load a document queued for deletion within the same session: {$id}");
        }

        return $this->objects[$id];
    }

    /**
     * Stores the given object under the specified id.
     *
     * @param object $object the object to store
     * @param string $id the document ID to store the object under
     *
     * @throws DocumentException if the document could not be stored for any reason
     */
    public function store($object, $id)
    {
        if (! $this->isLocked()) {
            throw new DocumentException("cannot store an object in a closed session");
        }

        $path = $this->mapPath($id);

        if (array_key_exists($id, $this->status)) {
            if ($this->status[$id] === self::STATUS_DELETE) {
                throw new DocumentException("cannot store a document queued for deletion within the same session: {$id}");
            }
        }

        $this->files[$path] = $this->store->getSerializer()->serialize($object);
        $this->objects[$id] = $object;
        $this->status[$id] = self::STATUS_STORE;
    }

    /**
     * Returns the id of a given object, already present in the active session.
     * If no object with the given id is present, a DocumentException is thrown.
     *
     * @param object $object an object already present in this Session
     *
     * @throws DocumentException if the given object isn't present in this Session
     *
     * @return object
     */
    public function getId($object)
    {
        if (! $this->isLocked()) {
            throw new DocumentException("cannot determine the id of an object in a closed session");
        }

        $id = array_search($object, $this->objects, true);

        if ($id === false) {
            throw new DocumentException("the given object is not present in this session");
        }

        return $id;
    }

    /**
     * Check if this Session currently contains an object with the given document ID.
     *
     * @param string $id document ID
     *
     * @return bool true, if an object with the given id is active in this session; otherwise false
     */
    public function contains($id)
    {
        return array_key_exists($id, $this->objects);
    }

    /**
     * Check if the database contains a document with the given ID.
     *
     * @param string $id document ID
     *
     * @return bool true, if a document with the given ID has been stored; otherwise false
     */
    public function exists($id)
    {
        return array_key_exists($id, $this->objects) || file_exists($this->mapPath($id));
    }

    /**
     * Deletes the object stored under the specified id.
     *
     * The object remains in session until save() is called.
     *
     * @param string $id document ID
     *
     * @throws DocumentException if unable to delete the document with the given ID
     */
    public function delete($id)
    {
        if (! $this->isLocked()) {
            throw new DocumentException("cannot delete an object from a closed session");
        }

        $path = $this->mapPath($id);

        if (!file_exists($path)) {
            throw new DocumentException("the given document id does not exist");
        }

        if (array_key_exists($id, $this->status) && $this->status[$id] === self::STATUS_STORE) {
            throw new DocumentException("cannot delete a document queued for storage within the same session: {$id}");
        }

        $this->status[$id] = self::STATUS_DELETE;
        $this->files[$path] = null;
    }

    /**
     * Evicts the object with the given id from the current session, canceling any changes
     * made to the object during this session.
     *
     * @param string $id document ID
     *
     * @throws DocumentException on attempt to evict an object from a Session that is already closed
     *
     * @return void
     */
    public function evict($id)
    {
        if (! $this->isLocked()) {
            throw new DocumentException("cannot evict an object from a closed session");
        }

        $path = $this->mapPath($id);
        unset($this->files[$path]);

        unset($this->status[$id]);
        unset($this->objects[$id]);
    }

    /**
     * Commits any changes made during this session.
     *
     * @return void
     *
     * @throws DocumentException if the commit operation is in any way unsuccessful
     */
    public function commit()
    {
        if (! $this->isLocked()) {
            throw new DocumentException("this session has been closed - only an open session can be saved");
        }

        mt_srand();

        $temp = '.' . md5(mt_rand()) . '.tmp';

        $this->lock(true);

        try {
            foreach ($this->files as $path => $data) {
                if ($data === null) {
                    $this->persistence->moveFile($path, $path . $temp); // move deleted document to temp-file
                } else {
                    $this->persistence->writeFile($path . $temp, $data); // write stored document to temp-file
                }
            }
        } catch (DocumentException $e) {
            // Roll back any changes:

            foreach ($this->files as $path => $data) {
                if ($data === null) {
                    if (file_exists($path . $temp)) {
                        $this->persistence->moveFile($path . $temp, $path); // move deleted document back in place
                    }
                } else {
                    if (file_exists($path . $temp)) {
                        $this->persistence->deleteFile($path . $temp); // delete stored document temp-file
                    }
                }
            }

            $this->lock(false);

            throw new DocumentException("an error occurred while committing changes to documents - changes were rolled back", $e);
        }

        try {
            foreach ($this->files as $path => $data) {
                if ($data === null) {
                    $this->persistence->deleteFile($path . $temp); // remove deleted document
                } else {
                    $this->persistence->moveFile($path . $temp, $path); // move stored document from temp-file in place
                }
            }
        } catch (DocumentException $e) {
            throw new DocumentException("an error occurred while committing changes to documents - changes could not be rolled back!", $e);
        }

        $this->lock(false);

        $this->files = array();

        foreach ($this->status as $id => $status) {
            if ($status === self::STATUS_DELETE) {
                // Evict deleted object from session:
                $this->evict($id);
            } else {
                // Reset status of other objects in session:
                $this->status[$id] = self::STATUS_KEEP;
            }
        }
    }

    /**
     * Cancel all changes made during this session and evict all objects currently in the session.
     *
     * @return void
     */
    public function flush()
    {
        foreach (array_keys($this->status) as $id) {
            $this->evict($id);
        }
    }

    /**
     * Closes the session and releases the lock on the database - you must either
     * save() or cancel() any pending changes prior to calling this method.
     *
     * Note that the session is automatically closed when is falls out of scope.
     *
     * @throws DocumentException if closed without either calling flush() or commit() first
     *
     * @return void
     */
    public function close()
    {
        if (count($this->files) > 0) {
            throw new DocumentException("unable to close session with pending changes - you must either flush() or commit() pending changes before calling close()");
        }

        $this->unlock();
    }
}
