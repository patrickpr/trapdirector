<?php

namespace Icinga\Module\Trapdirector\Tables;


trait TrapDirectorTableGrouping
{
    
  
    /*************** Grouping ************/
    protected $grouppingActive=false;
    
    protected $groupingColumn='';
    
    protected $groupingVal='';
    
    protected $groupingColSpan=1;
      
    /*****************  Grouping ****************/
    
    public function setGrouping($columnDBName)
    {
        $this->groupingColumn = $columnDBName;
        $this->grouppingActive = TRUE;
    }
    
    public function initGrouping()
    {
        $this->groupingVal = '';
        $this->groupingColSpan = count($this->titles);
    }
    
    public function groupingPrintData( $value)
    {
        $html = "$value";
        return $html;
    }
    
    public function groupingNextLine( $values)
    {
        if ($this->grouppingActive === FALSE) return '';
        
        $dbcol = $this->groupingColumn;
        if ($this->groupingVal == '' || $this->groupingVal != $values->$dbcol )
        {
            $this->groupingVal = $values->$dbcol;
            $html = '<tr><th colspan="'. $this->groupingColSpan .'">'. $this->groupingPrintData($this->groupingVal) .'</th></tr>';
            return $html;
        }
        return '';
        
    }
    
  
}