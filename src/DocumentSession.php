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
     * @var Persistence document persistence layer
     */
    protected $persistence;

    /**
     * @var Mutex mutual exclusion handler for the Persistence layer
     */
    protected $mutex;

    /**
     * @var object[] map where document ID => object
     */
    protected $objects = array();

    /**
     * @var int[] map where document ID => object status (see STATUS_* constants)
     */
    protected $status = array();

    /**
     * @var string[] map of documents to be written on save(), where document ID => JSON string
     */
    protected $docs = array();

    const STATUS_KEEP = 0;
    const STATUS_STORE = 1;
    const STATUS_DELETE = - 1;

    /**
     * Opens a new session with the given store and the specified database.
     *
     * @param DocumentStore $store       the Store from which this Session was generated
     * @param Persistence   $persistence the document persistence layer
     */
    public function __construct(DocumentStore $store, Persistence $persistence)
    {
        $mutex = $persistence->createMutex();
        $mutex->sharedLock();

        $this->store = $store;
        $this->persistence = $persistence;
        $this->mutex = $mutex;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
         $this->close();
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
        if (! $this->mutex->isLocked()) {
            throw new DocumentException("cannot load an object into a closed session");
        }

        if (!array_key_exists($id, $this->status)) {
            $data = $this->persistence->readDocument($id);

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
        if (! $this->mutex->isLocked()) {
            throw new DocumentException("cannot store an object in a closed session");
        }

        if (array_key_exists($id, $this->status)) {
            if ($this->status[$id] === self::STATUS_DELETE) {
                throw new DocumentException("cannot store a document queued for deletion within the same session: {$id}");
            }
        }

        $this->docs[$id] = $this->store->getSerializer()->serialize($object);
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
        if (! $this->mutex->isLocked()) {
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
        return array_key_exists($id, $this->objects) || $this->persistence->documentExists($id);
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
        if (! $this->mutex->isLocked()) {
            throw new DocumentException("cannot delete an object from a closed session");
        }

        if (!$this->persistence->documentExists($id)) {
            throw new DocumentException("the given document id does not exist: {$id}");
        }

        if (array_key_exists($id, $this->status) && $this->status[$id] === self::STATUS_STORE) {
            throw new DocumentException("cannot delete a document queued for storage within the same session: {$id}");
        }

        $this->status[$id] = self::STATUS_DELETE;
        $this->docs[$id] = null;
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
        if (! $this->mutex->isLocked()) {
            throw new DocumentException("cannot evict an object from a closed session");
        }

        unset($this->docs[$id]);
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
        if (! $this->mutex->isLocked()) {
            throw new DocumentException("this session has been closed - only an open session can be saved");
        }

        mt_srand();

        $temp = '.' . md5(mt_rand()) . '.tmp';

        $this->mutex->exclusiveLock();

        try {
            foreach ($this->docs as $id => $data) {
                if ($data === null) {
                    $this->persistence->moveDocument($id, $id . $temp); // move deleted document to temp-file
                } else {
                    $this->persistence->writeDocument($id . $temp, $data); // write stored document to temp-file
                }
            }
        } catch (DocumentException $e) {
            // Roll back any changes:

            foreach ($this->docs as $id => $data) {
                if ($data === null) {
                    if ($this->persistence->documentExists($id . $temp)) {
                        $this->persistence->moveDocument($id . $temp, $id); // move deleted document back in place
                    }
                } else {
                    if ($this->persistence->documentExists($id . $temp)) {
                        $this->persistence->deleteDocument($id . $temp); // delete stored document temp-file
                    }
                }
            }

            $this->mutex->sharedLock();

            throw new DocumentException("an error occurred while committing changes to documents - changes were rolled back", $e);
        }

        try {
            foreach ($this->docs as $id => $data) {
                if ($data === null) {
                    $this->persistence->deleteDocument($id . $temp); // remove deleted document
                } else {
                    $this->persistence->moveDocument($id . $temp, $id); // move stored document from temp-file in place
                }
            }
        } catch (DocumentException $e) {
            throw new DocumentException("an error occurred while committing changes to documents - changes could not be rolled back!", $e);
        }

        $this->mutex->sharedLock();

        $this->docs = array();

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
     * commit() or flush() any pending changes prior to calling this method.
     *
     * Note that the session is automatically closed when is falls out of scope.
     *
     * @throws DocumentException if closed without either calling flush() or commit() first
     *
     * @return void
     */
    public function close()
    {
        if (count($this->docs) > 0) {
            throw new DocumentException("unable to close session with pending changes - you must either flush() or commit() pending changes before calling close()");
        }

        $this->mutex->unlock();
    }
}
