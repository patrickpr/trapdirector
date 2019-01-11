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
		$htm .= "Filter : " . $this->filter."<br>\n";
		return $htm;

	}

    public function count()
    {
        $db = $this->connection()->getConnection();
        $query = clone($this->getBaseQuery());
        $query->reset('order')->columns(array('COUNT(*)'));
        $this->applyFiltersToQuery($query);

		$db=$this->db();
		
		$query = $db->select()->from(
            $this->moduleConfig->getTrapTableName(),
            array('COUNT(*)')
        );
		
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
		// TODO : implement
		
        /*$filter = null;
        $enforced = $this->enforcedFilters;
        if ($this->filter && ! $this->filter->isEmpty()) {
            $filter = $this->filter;
        } elseif (! empty($enforced)) {
            $filter = array_shift($enforced);
        }
        if ($filter) {
            foreach ($enforced as $f) {
                $filter->andFilter($f);
            }
            $query->where($this->renderFilter($filter));
        }
		*/
		
        return $query;
    }	

}


?>