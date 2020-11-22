<?php

namespace Trapdirector;

use Exception;


class RuleElmt
{
    /** @var RuleObject $parent */
    private $parent;
    /** @var array $ruleDef */
    public $ruleDef;
    
    function __construct(RuleObject $parent, array $ruleDef)
    {
        $this->parent = $parent;
        $this->ruleDef = $ruleDef;
    }
    
    

}