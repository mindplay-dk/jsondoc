<?php

namespace mindplay\jsondoc;

/**
 * This class is responsible for creating, reading, writing and deleting files and folders.
 */
interface Persistence
{
    /**
     * Creates a Mutex for use in a Session
     *
     * @return Mutex
     */
    public function createMutex();

    /**
     * Reads the contents of a document with the given document ID.
     *
     * @param string $id document ID
     *
     * @return string content
     *
     * @throws DocumentException if the specified document could not be read
     */
    public function readDocument($id);

    /**
     * Writes (or overwrites) a document with the given document ID.
     *
     * @param string $id document ID
     * @param string $data content
     *
     * @return void
     *
     * @throws DocumentException if the document could not be written
     */
    public function writeDocument($id, $data);

    /**
     * Change the ID of an existing document, possibly replacing another
     * existing document with the same ID.
     *
     * @param string $from_id source document ID
     * @param string $to_id   destination document ID
     *
     * @return void
     *
     * @throws DocumentException if the document could not be moved
     */
    public function moveDocument($from_id, $to_id);

    /**
     * Delete a document.
     *
     * @param string $id document ID
     *
     * @return void
     *
     * @throws DocumentException if the document could not be deleted
     */
    public function deleteDocument($id);

    /**
     * Checks if a document exists.
     *
     * @param string $id document ID
     *
     * @return bool true, if the document exists; otherwise false
     */
    public function documentExists($id);
}
