<?php

/**
 * Rule evaluation utilities
 *
 * @license GPL
 * @author Patrick Proy
 * @package trapdirector
 * @subpackage Processing
 *
 */
trait RuleUtils
{
    
    /** rule eval starting from $token
     * @param string $rule
     * @param int $item
     */
    abstract public function evaluation($rule,&$item);

    /**
     * Get full number
     * @param string $rule Rule as string
     * @param int $item current eval position
     * @return array<int,string>
     */
    private function get_number(string $rule,int &$item)
    {
        $item2=$item+1;
        while (
            ($item2!=strlen($rule))
            && (preg_match('/[\-0-9\.]/',$rule[$item2])))
        {
            $item2++ ;
        }
        $val=substr($rule,$item,$item2-$item);
        $item=$item2;
        //echo "number ".$val."\n";
        
        return array(0,$val);
    }

    /**
     * Get a string (between ") 
     * @param string $rule Rule as string
     * @param int $item current eval position
     * @return array<int,string>
     */
    private function get_string(string $rule,int &$item)
    {
        $item++;
        $item2=$this->eval_getNext($rule,$item,'"');
        $val=substr($rule,$item,$item2-$item-1);
        $item=$item2;
        //echo "string : ".$val."\n";
        return array(1,$val);
        
    }
    
    /**
     * Parse elements inside () : jumps over "" and count parenthesis.
     * Ex : ( "test" != ")test" & (1==2) ) will return "test" != ")test" & (1==2)
     * @param string $rule : the current rule
     * @param int $item : actual position in rule
     * @throws Exception
     * @return string : everything inside parenthesis
     */
    private function parse_parenthesis(string $rule,int &$item) : string
    {
        $item++;
        $start=$item;
        $parenthesisCount=0;
        while (($item < strlen($rule)) // Not end of string AND
            && ( ($rule[$item] != ')' ) || $parenthesisCount > 0) ) // Closing ')' or embeded ()
        {
            if ($rule[$item] == '"' )
            { // pass through string
                $item++;
                $item=$this->eval_getNext($rule,$item,'"');
                continue;
            }
            if ($rule[$item] == '(')
            {
                $parenthesisCount++;
            }
            if ($rule[$item] == ')')
            {
                $parenthesisCount--;
            }
            $item++;
        }
        
        if ($item==strlen($rule)) {throw new Exception("no closing () in ".$rule ." at " .$item);}
        $val=substr($rule,$start,$item-$start);
        $item++;
        return $val;
    }

    /**
     * Get and eval a grouped condition - ex : (1==1)
     * @param string $rule
     * @param int $item
     * @return array<int,string>
     */
    private function get_group(string $rule,int &$item) : array
    {
        // gets eveything inside parenthesis
        $val=$this->parse_parenthesis($rule, $item);
        // Returns boolean with evaluation of all inside parenthesis
        $start=0;
        return array(2,$this->evaluation($val,$start));
    }
    
    /**
     * @param string $rule
     * @param int $item
     * @throws Exception
     * @return array<int,string>
     */
    private function get_function(string $rule,int &$item) : array
    {
        // function is : __function(param1,param2...)
        $start=$item;
        while (($item < strlen($rule)) && ($rule[$item] != '(' )) // Not end of string AND not opening '('
        {
            $item++;
        }
        if ($item==strlen($rule)) {throw new Exception("no opening () for function in ".$rule ." at " .$item);}
        
        // get parameters between parenthesis
        
        $this->parse_parenthesis($rule, $item);
        
        $val=substr($rule,$start,$item-$start);
        
        $this->logging->log('got function ' . $val,DEBUG);
        
        return array(2,$this->trapClass->pluginClass->evaluateFunctionString($val));
        
    }

    /** Find next token $tok in $rule starting at $item 
     * @param string $rule
     * @param int $item
     * @param string $tok : token to search for
     * @throws Exception
     * @return array<int,string>
     */
    protected function eval_getNext(string $rule,int $item,string $tok)
    {
        while (
            ($rule[$item] != $tok )
            && ($item < strlen($rule)))
        {
            $item++;
        }
        if ($item==strlen($rule)) {
            throw new Exception("closing '".$tok."' not found in ".$rule ." at " .$item);
        }
        return $item+1;
    }

    /** get negate (!) and return true if found - and pass it with item++ - 
     * @param string $rule
     * @param int $item
     * @return boolean
     */
    private function check_negate_first(string $rule,int &$item)
    {
        if ( $rule[$item] == '!') // If '!' found, negate next expression.
        {
            $item++;
            return true;
        }
        else
        {
            return false;
        }
    }
    
}