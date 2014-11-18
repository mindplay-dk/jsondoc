<?php

namespace mindplay\jsondoc;

use mindplay\jsonfreeze\JsonSerializer;

/**
 * This component represents a document store.
 */
class DocumentStore
{
    /**
     * @var JsonSerializer
     */
    protected $serializer;

    /**
     * @var Persistence
     */
    protected $persistence;

    /**
     * @param Persistence         $persistence persistence layer
     * @param JsonSerializer|null $serializer
     *
     * @throws DocumentException if the given path does not exist
     */
    public function __construct(Persistence $persistence, JsonSerializer $serializer = null)
    {
        if ($serializer === null) {
            $serializer = new JsonSerializer();
        }

        $this->serializer = $serializer;
        $this->persistence = $persistence;
    }

    /**
     * Opens a new Session with this Store.
     *
     * @return DocumentSession
     */
    public function openSession()
    {
        return new DocumentSession($this, $this->persistence);
    }

    /**
     * @return JsonSerializer
     */
    public function getSerializer()
    {
        return $this->serializer;
    }
}
