<?php

namespace Icinga\Module\Trapdirector\Tables;


trait TrapDirectorTableOrder
{ 
    /** @var array $order : (db column, 'ASC' | 'DESC') */
    protected $order = array();
    /** @var string $orderQuery passed by GET */
    protected $orderQuery = '';   
    
    /** used var & functions of trapDirectorTable **/
    protected $query;
    
   /***************** Ordering ********************/
    
    public function applyOrder()
    {
        if (count($this->order) == 0)
        {
            return $this;
        }
        $orderSQL='';
        foreach ($this->order as $column => $direction)
        {
            if ($orderSQL != "") $orderSQL.=',';
            
            $orderSQL .= $column . ' ' . $direction;
        }
        $this->query = $this->query->order($orderSQL);
        
        return $this;
    }
    
    public function setOrder(array $order)
    {
        $this->order = $order;
        return $this;
    }

    public function isOrderSet()
    {
        if (count($this->order) == 0) return FALSE;
        return TRUE;
    }
    
    public function getOrderQuery(array $getVars)
    {
        if (isset($getVars['o']))
        {
            $this->orderQuery = $getVars['o'];
            $match = array();
            if (preg_match('/(.*)(ASC|DESC)$/', $this->orderQuery , $match))
            {
                $orderArray=array($match[1] => $match[2]);
                echo "$match[1] => $match[2]";
                $this->setOrder($orderArray);
            }
        }
    }
    
    protected function curOrderQuery()
    {
        if ($this->orderQuery == '') return '';
        return 'o='.$this->orderQuery;
    }
        
}