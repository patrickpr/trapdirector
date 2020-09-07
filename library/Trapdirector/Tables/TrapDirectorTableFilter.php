<?php

namespace Icinga\Module\Trapdirector\Tables;


trait TrapDirectorTableFilter
{
    
    /********* Filter ********/
    /** @var string $filterString : string filter for db columns */
    protected $filterString = '';
    
    /** @var array $filterColumn : columns to apply filter to */
    protected $filterColumn = array();
    
    protected $filterQuery='';
    
    /**** var & func of TrapDirectorTable used ***/
    protected $query;
    abstract protected function getCurrentURLAndQS(string $caller);
    
    /**************** Filtering ******************/
    
    public function applyFilter()
    {
        if ($this->filterString == '' || count($this->filterColumn) == 0)
        {
            return $this;
        }
        $filter='';
        foreach ($this->filterColumn as $column)
        {
            if ($filter != "") $filter.=' OR ';
            //$filter .= "'" . $column . "' LIKE '%" . $this->filterString. "%'";
            $filter .= $column  . " LIKE '%" . $this->filterString. "%'";
        }
        //echo $filter;
        
        $this->query=$this->query->where($filter);

        return $this;
    }

    public function setFilter(string $filter, array $filterCol)
    {
        $this->filterString = $filter;
        $this->filterColumn = $filterCol;
        return $this;
    }
   
    public function renderFilter()
    {
        
        $html=' <form id="genfilter" name="mainFilterGen"
			enctype="application/x-www-form-urlencoded"
			action="'.$this->getCurrentURLAndQS('filter').'"
			method="get">';
        $html.='<input type="text" name="f" title="Search is simple! Try to combine multiple words"
	placeholder="Search..."  value="'.$this->filterQuery.'">';

        $html.='</form>';
        return $html;
    }
 
    public function getFilterQuery(array $getVars)
    {
        if (isset($getVars['f']))
        {
            $this->filterQuery = $getVars['f'];
            $this->setFilter(html_entity_decode($getVars['f']), $this->columnNames);
        }
    }
    
    protected function curFilterQuery()
    {
        if ($this->filterQuery == '') return '';
        return 'f='.$this->filterQuery;
    }
    
}