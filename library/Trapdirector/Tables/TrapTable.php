<?php

namespace Icinga\Module\TrapDirector\Tables;

use Icinga\Application\Icinga;

use Icinga\Data\Selectable;
use Icinga\Data\Paginatable;

use Icinga\Web\Request;

use Icinga\Web\Widget;
use Icinga\Web\Widget\Paginator;


abstract class TrapTable implements Paginatable
{
	// View : getView / setView 
	protected $view;
	
	// Db connection : getConnection / setConnection
	protected $connection;
	
	// Static configuration
	protected $moduleConfig;

	// view limits
	protected $limit;
    protected $offset;

	// Used for rendering days in list
	protected $columnCount;
	protected $lastDay;
	
	// Filters 
	
    protected $filter;
    protected $enforcedFilters = array();
    protected $searchColumns = array();
	
	
	// Must return titles in array ( id => display_name )
	abstract protected function getTitles();
	// ****************** Day header
    protected function renderDayIfNew($timestamp)
    {
        $view = $this->getView();

		// Check for date local format
        if (in_array(setlocale(LC_ALL, 0), array('en_US.UTF-8', 'C'))) {
            $day = date('l, jS F Y', (int) $timestamp);
        } else {
            $day = strftime('%A, %e. %B, %Y', (int) $timestamp);
        }

        if ($this->lastDay === $day) {
            return;
        }

        if ($this->lastDay === null) {
            $htm = "<thead>\n  <tr>\n";
        } else {
            $htm = "</tbody>\n<thead>\n  <tr>\n";
        }

        if ($this->columnCount === null) {
            $this->columnCount = count($this->getTitles());
        }

        $htm .= '<th colspan="' . $this->columnCount . '">' . $view->escape($day) . '</th>' . "\n";
        if ($this->lastDay === null) {
            $htm .= "  </tr>\n";
        } else {
            $htm .= "  </tr>\n</thead>\n";
        }

        $this->lastDay = $day;

        return $htm . "<tbody>\n";
    }		
	// ******************  Render table in html  
    public function __toString()
    {
        return $this->render();
    }
	
	abstract public function render();
	
	// ******************  Static config set
	public function setConfig($conf)
	{
		$this->moduleConfig = $conf;
	}
	// ******************  View get/set
    protected function getView()
    {
        if ($this->view === null) {
            $this->view = Icinga::app()->getViewRenderer()->view;
        }
        return $this->view;
    }

    public function setView($view)
    {
        $this->view = $view;
    }	
	
	// Limits
	
    public function limit($count = null, $offset = null)
    {
        $this->limit = $count;
        $this->offset = $offset;

        return $this;
    }

    public function hasLimit()
    {
        return $this->limit !== null;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function hasOffset()
    {
        return $this->offset !== null;
    }

    public function getOffset()
    {
        return $this->offset;
    }
	
	abstract function count();
	
    public function getPaginator()
    {
        $paginator = new Paginator();
        $paginator->setQuery($this);

        return $paginator;
    }
	
	// ****************** DB connection and query
	
    public function setConnection(Selectable $connection)
    {
        $this->connection = $connection;
        return $this;
    }

    protected function connection()
    {
        return $this->connection;
    }

    protected function db()
    {
        return $this->connection()->getConnection();
    }
	
	protected function getTable()
	{
		$db=$this->db();
		
		$query = $this->getBaseQuery();
		
       if ($this->hasLimit() || $this->hasOffset()) {
            $query->limit($this->getLimit(), $this->getOffset());
        }		
		
		return $db->fetchAll($query);
	}
	 
    abstract public function getBaseQuery(); 
	
	// ****************** Filters

    protected function getSearchColumns()
    {
        return $this->getColumns();
    }
	
	abstract public function getColumns();

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
	
	protected function renderFilter($filter)
	{ // TODO
	}
	
    protected function applyFiltersToQuery($query)
    {
        /*
		$filter = null;
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
			//$this->renderFilter($filter);
            $query->where($this->renderFilter($filter));
        }
		*/
        return $query;
    }	

}


?>