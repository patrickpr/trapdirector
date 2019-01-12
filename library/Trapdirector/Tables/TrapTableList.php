<?php

namespace Icinga\Module\TrapDirector\Tables;

use Icinga\Application\Icinga;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterNot;
use Icinga\Data\Filter\FilterOr;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Selectable;
use Icinga\Data\Paginatable;
use Icinga\Exception\QueryException;
use Icinga\Web\Request;
use Icinga\Web\Url;
use Icinga\Web\Widget;
use Icinga\Web\Widget\Paginator;

use Icinga\Module\Trapdirector\Config\TrapModuleConfig;
use Icinga\Module\Trapdirector\Tables\TrapTable;
use stdClass;



class TrapTableList extends TrapTable
{	
	// Db connection : getConnection / setConnection
	protected $connection;
	
	// Filters 
	
    protected $filter;
    protected $enforcedFilters = array();
    protected $searchColumns = array();
	
	protected function getTitles() {
		// TODO : check moduleconfig is set
		return $this->moduleConfig->getTrapListTitles();
	}
	// ******************  Render table in html  
    public function __toString()
    {
        return $this->render();
    }
	
	public function render()
	{
		$data=$this->getTable();
		$view = $this->getView();
		$this->columnCount = count($this->getTitles());
		$this->lastDay=null;
		// Table start
		$htm  = '<table class="simple common-table table-row-selectable">';
		
		// Titles
		$htm .= "<thead>\n  <tr>\n";
		$titles = $this->getTitles();
		foreach ($titles as $key => $title) 
		{
			$htm .= '    <th>' . $view->escape($view->translate($title)) . "</th>\n";
		}
		$htm .= "  </tr>\n</thead>\n";
		
		// Rows
		$htm .= "<tbody>\n";
		
		foreach ($data as $row) 
		{
			$firstCol = true;
			// Put date header
			$htm .= $this->renderDayIfNew($row->timestamp);
			
			
			// Render row
			$htm .= '<tr '.' >';
			foreach ( $titles as $rowkey => $title) 
			{
				// Check missing value
				if (property_exists($row, $rowkey)) 
				{
					$val = ($rowkey=='timestamp') ?  strftime('%T',$row->$rowkey) : $row->$rowkey;
				} else {
					$val = '-';
				}
				if ($firstCol == true) { // Put link in first column for trap detail.
					$htm .= '<td>' 
							. $view->qlink(
									$view->escape($val),  
									Url::fromPath(
										$this->moduleConfig->urlPath() . '/received/trapdetail', 
										array('id' => $row->id)
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
		
		$query = $db->select()->from(
            $this->moduleConfig->getTrapTableName(),
            array('COUNT(*)')
        );
		$this->applyFiltersToQuery($query);
		
        return $db->fetchOne($query);
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
            $this->moduleConfig->getTrapListDisplayColumns()
        )->order('timestamp DESC');

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