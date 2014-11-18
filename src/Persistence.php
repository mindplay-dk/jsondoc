<?php
namespace mindplay\jsondoc;


/**
 * This class is responsible for creating, reading, writing and deleting files and folders.
 */
interface Persistence
{
    /**
     * Ensures that the given path is a directory and/or creates it.
     *
     * @param string $path absolute path to directory
     *
     * @return void
     *
     * @throws DocumentException if the given path is not a directory, or could not be created
     */
    public function ensureDir($path);

    /**
     * Reads the contents of a physical file at the given path.
     *
     * @param string $path absolute path to a file
     *
     * @return string file contents
     *
     * @throws DocumentException if the specified file could not be read
     */
    public function readFile($path);

    /**
     * Writes data to a physical file at the given path, creating the path if necessary.
     *
     * @param string $path absolute path to the file to write
     * @param string $data file contents
     *
     * @return void
     *
     * @throws DocumentException if unable to write the file (or set permissions)
     */
    public function writeFile($path, $data);

    /**
     * Move a file, replacing any existing file in the destination path.
     *
     * @param string $from absolute source path
     * @param string $to   absolute destination path
     *
     * @return void
     *
     * @throws DocumentException if the specified file could not be moved
     */
    public function moveFile($from, $to);

    /**
     * Delete a file.
     *
     * @param string $path absolute path of the file to be deleted
     *
     * @return void
     *
     * @throws DocumentException if the specified file could not be deleted
     */
    public function deleteFile($path);

    /**
     * Checks if a file exists
     *
     * @param string $path absolute path to the file to check
     *
     * @return bool true, if the specified file exists
     */
    public function fileExists($path);
}
