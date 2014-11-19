<?php
namespace mindplay\jsondoc;

interface Mutex
{
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
    public function lock($exclusive = false);

    /**
     * Release a lock on the database associated with this session.
     *
     * @return void
     */
    public function unlock();

    /**
     * @return bool true, if the database is currently locked
     */
    public function isLocked();
}
