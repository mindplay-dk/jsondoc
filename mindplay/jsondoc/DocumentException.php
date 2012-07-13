<?php

namespace mindplay\jsondoc;

use \Exception;

class DocumentException extends Exception
{
  protected $innerException = null;
  
  public function __construct($message, $innerException = null)
  {
    parent::__construct($message);
    
    $this->innerException = $innerException;
  }
  
  public function __toString()
  {
    $str = parent::__toString();
    
    if ($this->innerException !== null) {
      $str .= "\nInner exception: {$innerException}";
    }
    
    return $str;
  }
}
