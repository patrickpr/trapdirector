<?php

namespace Icinga\Module\Trapdirector\Tables;


trait TrapDirectorTableGrouping
{
    
  
    /*************** Grouping ************/
    
    /** @var boolean $grouppingActive set to true if grouping is active by query or function call */
    protected $grouppingActive=false;
    
    /** @var string $groupingColumn Name of column (can be hidden) for grouping */
    protected $groupingColumn='';
    
    /**@var string $groupingVal Current value of grouping column in row (used while rendering) */
    protected $groupingVal='';
    
    /** @var integer $groupingColSpan colspan of grouping line : set to table titles in init */
    protected $groupingColSpan=1;
      
    /*****************  Grouping ****************/
    
    /**
     * Set grouping. column must be DB name
     * @param string $columnDBName
     */
    public function setGrouping(string $columnDBName)
    {
        $this->groupingColumn = $columnDBName;
        $this->grouppingActive = TRUE;
    }
    
    /**
     * Init of grouping before rendering
     */
    public function initGrouping()
    {
        $this->groupingVal = '';
        $this->groupingColSpan = count($this->titles);
    }
    
    /**
     * Function to print grouping value (for ovveride in specific tables)
     * @param string $value
     * @return string
     */
    public function groupingPrintData( string $value )
    {
        $html = "$value";
        return $html;
    }
    
    /**
     * When to display new grouping line  (for ovveride in specific tables)
     * @param string $val1 Current value in grouping
     * @param string $val2 Value of current line
     * @return boolean TRUE if a new grouping line is needed.
     */
    public function groupingEvalNext(string $val1, string $val2)
    {
        if ($val1 != $val2)
            return TRUE;
        else
            return FALSE;
    }
    
    /**
     * Called before each line to check if grouping line is needed.
     * @param mixed $values
     * @return string with line or empty.
     */
    public function groupingNextLine( $values)
    {
        if ($this->grouppingActive === FALSE) return '';

        $dbcol = $this->groupingColumn;
        $dbVal = $values->$dbcol;
        if ( $dbVal === NULL ) $dbVal = '0'; // Set default to 0
        if ($this->groupingVal == '' || $this->groupingEvalNext($this->groupingVal ,$dbVal) === TRUE )
        {
            $this->groupingVal = $dbVal;
            $html = '<tr><th colspan="'. $this->groupingColSpan .'">'. $this->groupingPrintData($this->groupingVal) .'</th></tr>';
            return $html;
        }
        return '';

    }
 
}
