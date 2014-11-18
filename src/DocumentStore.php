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
     * @var Persistence
     */
    protected $persistence;

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
        $this->persistence = $this->createPersistence();
    }

    /**
     * @return Persistence
     */
    protected function createPersistence()
    {
        return new FilePersistence();
    }

    /**
     * Opens a new Session with the given database-name.
     *
     * @param string $database database name
     *
     * @return DocumentSession
     */
    public function openSession($database)
    {
        return new DocumentSession($this, $this->persistence, $database);
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
     * @param string $name
     *
     * @return bool true, if the given name is valid; otherwise false
     */
    public function isValidName($name)
    {
        return preg_match('/^[\w-]+$/', $name) === 1;
    }
}
