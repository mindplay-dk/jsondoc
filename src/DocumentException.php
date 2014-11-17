<?php

namespace mindplay\jsondoc;

use Exception;

class DocumentException extends Exception
{
    /**
     * @var Exception
     */
    protected $inner_exception = null;

    /**
     * @param string    $message
     * @param Exception $inner_exception
     */
    public function __construct($message, $inner_exception = null)
    {
        parent::__construct($message);

        $this->inner_exception = $inner_exception;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $str = parent::__toString();

        if ($this->inner_exception !== null) {
            $str .= "\nInner exception: {$this->inner_exception}";
        }

        return $str;
    }
}
