<?php

namespace mindplay\jsondoc;

use mindplay\jsonfreeze\JsonSerializer;

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
     * @var string
     */
    protected $database;

    /**
     * Root path to the database-folder.
     */
    protected $path;

    /**
     * Database file-handle for locking.
     */
    protected $lock = null;

    /**
     * Path to the database lock-file.
     */
    protected $lockPath;

    /**
     * Hash where object_id => object
     */
    protected $objects = array();

    /**
     * Hash where object_id => object status
     */
    protected $status = array();

    /**
     * Hash of files to be written on save() - where path => JSON string
     */
    protected $files = array();

    const STATUS_KEEP = 0;
    const STATUS_STORE = 1;
    const STATUS_DELETE = - 1;

    /**
     * Opens a new session with the given store and the specified database.
     */
    public function __construct(DocumentStore $store, $database)
    {
        if (!$store->isValidName($database)) {
            throw new DocumentException("invalid database name: {$database}");
        }

        $path = $store->getPath() . DIRECTORY_SEPARATOR . $database;
        $store->ensureDir($path);

        $this->store = $store;
        $this->database = $database;
        $this->path = $path;

        $this->lockPath = $this->path . DIRECTORY_SEPARATOR . '.lock';
        $this->lockDatabase();
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Lock the database associated with this session.
     */
    protected function lockDatabase($exclusive = false)
    {
        $this->releaseDatabase();

        $mask = umask(0);

        $lock = @fopen($this->lockPath, 'a');

        @chmod($this->lockPath, 0666);

        umask($mask);

        if ($lock === false) {
            throw new DocumentException("unable to create the lock-file: {$this->lockPath}");
        }

        if (@flock($lock, $exclusive === true ? LOCK_EX : LOCK_SH) === false) {
            throw new DocumentException("unable to lock the database: {$this->lockPath}");
        }

        $this->lock = $lock;
    }

    /**
     * Release a lock on the database associated with this session.
     */
    protected function releaseDatabase()
    {
        if ($this->lock !== null) {
            flock($this->lock, LOCK_UN);
            $this->lock = null;
        }
    }

    /**
     * Maps the given id to a physical path.
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
     * @return object
     */
    public function load($id)
    {
        if ($this->lock === null) {
            throw new DocumentException("cannot load an object into a closed session");
        }

        if (!array_key_exists($id, $this->status)) {
            $path = $this->mapPath($id);
            $data = $this->store->readFile($path);

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
     */
    public function store($object, $id)
    {
        if ($this->lock === null) {
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
     */
    public function getId($object)
    {
        if ($this->lock === null) {
            throw new DocumentException("cannot determine the id of an object in a closed session");
        }

        $id = array_search($object, $this->objects, true);

        if ($id === false) {
            throw new DocumentException("the given object is not present in this session");
        }

        return $id;
    }

    /**
     * @return bool true if an object with the given id is active in this session.
     */
    public function contains($id)
    {
        return array_key_exists($id, $this->objects);
    }

    /**
     * Deletes the object stored under the specified id.
     *
     * The object remains in session until save() is called.
     */
    public function delete($id)
    {
        if ($this->lock === null) {
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
     */
    public function evict($id)
    {
        if ($this->lock === null) {
            throw new DocumentException("cannot evict an object from a closed session");
        }

        $path = $this->mapPath($id);
        unset($this->files[$path]);

        unset($this->status[$id]);
        unset($this->objects[$id]);
    }

    /**
     * Commits any changes made during this session.
     */
    public function commit()
    {
        if ($this->lock === null) {
            throw new DocumentException("this session has been closed - only an open session can be saved", $e);
        }

        mt_srand();

        $temp = '.' . md5(mt_rand()) . '.tmp';

        $this->lockDatabase(true);

        try {
            foreach ($this->files as $path => $data) {
                if ($data === null) {
                    $this->store->moveFile($path, $path . $temp); // move deleted document to temp-file
                } else {
                    $this->store->writeFile($path . $temp, $data); // write stored document to temp-file
                }
            }
        } catch (DocumentException $e) {
            // Roll back any changes:

            foreach ($this->files as $path => $data) {
                if ($data === null) {
                    if (file_exists($path . $temp)) {
                        $this->store->moveFile($path . $temp, $path); // move deleted document back in place
                    }
                } else {
                    if (file_exists($path . $temp)) {
                        $this->store->deleteFile($path . $temp); // delete stored document temp-file
                    }
                }
            }

            $this->lockDatabase(false);

            throw new DocumentException("an error occurred while committing changes to documents - changes were rolled back", $e);
        }

        try {
            foreach ($this->files as $path => $data) {
                if ($data === null) {
                    $this->store->deleteFile($path . $temp); // remove deleted document
                } else {
                    $this->store->moveFile($path . $temp, $path); // move stored document from temp-file in place
                }
            }
        } catch (DocumentException $e) {
            throw new DocumentException("an error occurred while committing changes to documents - changes could not be rolled back!", $e);
        }

        $this->lockDatabase(false);

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
     */
    public function flush()
    {
        foreach (array_keys($this->objects) as $id) {
            $this->evict($id);
        }
    }

    /**
     * Closes the session and releases the lock on the database - you must either
     * save() or cancel() any pending changes prior to calling this method.
     *
     * Note that the session is automatically closed when is falls out of scope.
     */
    public function close()
    {
        if (count($this->files) > 0) {
            throw new DocumentException("unable to close session with pending changes - you must either flush() or commit() pending changesm before calling close()");
        }

        $this->releaseDatabase();
    }
}
