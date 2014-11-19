<?php

namespace mindplay\jsondoc;

/**
 * This interface defines a mutually exclusive lock.
 */
interface Mutex
{
    /**
     * Lock in exclusive mode, blocking until any other shared/exclusive locks are released.
     *
     * @return void
     *
     * @throws DocumentException if unable to acquire the lock
     */
    public function exclusiveLock();

    /**
     * Lock in shared mode, blocking until any other exclusive lock is released.
     *
     * @return void
     *
     * @throws DocumentException if unable to acquire the lock
     */
    public function sharedLock();

    /**
     * Release any previously acquired shared/exclusive lock.
     *
     * @return void
     */
    public function unlock();

    /**
     * @return bool true, if the database is currently locked
     */
    public function isLocked();
}
