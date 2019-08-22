<?php

namespace Icinga\Module\TrapDirector\Tables;


use Icinga\Web\Request;
use Icinga\Web\Url;
use Icinga\Web\Widget;
use Icinga\Web\Widget\Paginator;


use Icinga\Module\Trapdirector\Tables\TrapTable;


class TrapTableHostList extends TrapTable
{	
	// Db connection : getConnection / setConnection
	protected $connection;
	
	// Host grouping
	protected $lastHost;
	// Filters 
	
    protected $filter;
    protected $enforcedFilters = array();
    protected $searchColumns = array();
	
	protected function getTitles() {
		// TODO : check moduleconfig is set
	    return $this->moduleConfig->getTrapHostListTitles();
	}
		
	// ******************  Render table in html 
	
	// Host grouping
	protected function renderHostIfNew($IP,$hostname)
	{
	    $view = $this->getView();
	    
	    if ($this->lastHost === $IP) {
	        return;
	    }
	    
	    if ($this->lastHost === null) 
	    {
	        $htm = "<thead>\n  <tr>\n";
	    } else {
	        $htm = "</tbody>\n<thead>\n  <tr>\n";
	    }
	    
	    if ($this->columnCount === null) 
	    {
	        $this->columnCount = count($this->getTitles());
	    }
	    
	    $htm .= '<th colspan="' . $this->columnCount . '">' . $view->escape($IP);
	    if ($hostname != null)
	    {
	        $htm .= ' ('.$hostname.')';
	    }
	    $htm .= '</th>' . "\n";
	    if ($this->lastHost === null) {
	        $htm .= "  </tr>\n";
	    } else {
	        $htm .= "  </tr>\n</thead>\n";
	    }
	    
	    $this->lastHost = $IP;
	    
	    return $htm . "<tbody>\n";
	}		
	
    public function __toString()
    {
        return $this->render();
    }
	
	public function render()
	{
		$data=$this->getTable();
		$view = $this->getView();
		$this->columnCount = count($this->getTitles());
		$this->lastHost=null;
		// Table start
		$htm  = '<table class="simple common-table table-row-selectable">';
		
		// Titles
		$htm .= "<thead>\n  <tr>\n";
		$titles = $this->getTitles();
		foreach ($titles as $title) 
		{
			$htm .= '    <th>' . $view->escape($view->translate($title)) . "</th>\n";
		}
		$htm .= "  </tr>\n</thead>\n";
		
		// Rows
		$htm .= "<tbody>\n";
		
		foreach ($data as $row) 
		{

			$firstCol = true;
			// Put host header
			$source_name=(property_exists($row,'source_name'))?$row->source_name:null;
			$htm .= $this->renderHostIfNew($row->source_ip,$source_name);
			
			
			// Render row
			$htm .= '<tr '.' >';
			foreach ( $titles as $rowkey => $title) 
			{
				// Check missing value
				if (property_exists($row, $rowkey)) 
				{
					$val = ($rowkey=='last_sent') ?  strftime('%c',$row->$rowkey) : $row->$rowkey;
				} else {
					$val = '-';
				}
				if ($firstCol == true) { // Put link in first column for trap detail.
					$htm .= '<td>' 
							. $view->qlink(
									$view->escape($val),  
									Url::fromPath(
										$this->moduleConfig->urlPath() . '/received', 
										array('q' => $row->trap_oid)
									)
							)
							. '</td>';
				} else {
					$htm .= '<td>' . $view->escape($val) . '</td>';
				}
				$firstCol=false;
			}
			$htm .= "<tr>\n";
		}
		$htm .= "</tbody></table>\n";
		//$htm .= "Filter : " . $this->filter."<br>\n";
		return $htm;

	}

    public function count()
    {  
        $db=$this->db();
		
		$query = $this->getBaseQuery();
		$this->applyFiltersToQuery($query);
		$values=$db->fetchAll($query);
		
		return count($values);
		
        //return $db->fetchOne($query);
    }
	
    public function getPaginator()
    {
        $paginator = new Paginator();
        $paginator->setQuery($this);

        return $paginator;
    }
	
	// ****************** DB connection and query
	
	protected function getTable()
	{
		$db=$this->db();
		
		$query = $this->getBaseQuery();
		$this->applyFiltersToQuery($query);
       if ($this->hasLimit() || $this->hasOffset()) {
            $query->limit($this->getLimit(), $this->getOffset());
        }		
		
		return $db->fetchAll($query);
	}
	 
    public function getBaseQuery()
    {
		$db=$this->db();
		
		$query = $db->select()->from(
            $this->moduleConfig->getTrapTableName(),
		    $this->moduleConfig->getTrapHostListDisplayColumns()
		    )->group(array('t.source_ip','t.trap_oid')
		    )->order('t.source_ip');

        return $query;
    }	 
	
	// ****************** Filters

	protected $filter_Handler;
	protected $filter_query='';
	protected $filter_done='';
	protected $filter_query_list=array('q','done');
	public function renderFilterHTML()
	{
		$htm=' <form id="filter" name="mainFilter" 
				enctype="application/x-www-form-urlencoded" 
				action="'.$this->filter_Handler.'" 
				method="get">';
		$htm.='<input type="text" name="q" title="Search is simple! Try to combine multiple words" 
		placeholder="Search..." class="search" value="'.$this->filter_query.'">';
		$htm.='<input type="checkbox" id="checkbox_done" name="done" value="1" class="autosubmit" ';
		if	($this->filter_done == 1) { $htm.=' checked ';}
		$htm.='> <label for="checkbox_done">Hide processed traps</label>';
		$htm.='</form>';
		return $htm;
	}
	
	public function updateFilter($handler,$filter)
	{
		$this->filter_Handler=$handler->remove($this->filter_query_list)->__toString();
		$this->filter_query=(isset($filter['q']))?$this->filter_query=$filter['q']:'';
		$this->filter_done=(isset($filter['done']))?$this->filter_done=$filter['done']:0;
	}
	
    protected function getSearchColumns()
    {
        return $this->getColumns();
    }
	
	public function getColumns()
	{
		return $this->moduleConfig->getTrapListDisplayColumns();
	}

    public function setFilter($filter)
    {
        $this->filter = $filter;
        return $this;
    }
	
	public function getFilterEditor(Request $request)
    {
        $filterEditor = Widget::create('filterEditor')
            ->setColumns(array_keys($this->getColumns()))
            ->setSearchColumns(array_keys($this->getSearchColumns()))
            ->preserveParams('limit', 'sort', 'dir', 'view', 'backend')
            ->ignoreParams('page')
            ->handleRequest($request);

        $filter = $filterEditor->getFilter();
        $this->setFilter($filter);

        return $filterEditor;
    }
	
    protected function applyFiltersToQuery($query)
    {
		
		$sql='';
		if ($this->filter_query != '')
		{
			$sql.='(';
			$first=1;
			foreach($this->moduleConfig->getTrapListSearchColumns() as $column)
			{
				if ($first==0) $sql.=' OR ';
				$first=0;
				$sql.=" ".$column." LIKE  '%".$this->filter_query."%' ";
			}
			$sql.=')';			
		}
		if ($this->filter_done == 1)
		{
			if ($sql != '') $sql.=' AND ';
			$sql.="(status != 'done')";
		}
		if ($sql != '') $query->where($sql);		
        return $query;
    }	

}


?>