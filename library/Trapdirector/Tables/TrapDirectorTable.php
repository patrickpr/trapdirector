<?php

namespace Icinga\Module\Trapdirector\Tables;


abstract class TrapDirectorTable
{
    /** @var array $titles table titles (name, display value) */
    protected $titles = null;

    /** @var array $content table content  (name, sb column name). name index must be the same as $titles*/
    protected $content = null;
    
    /** @var array $columnNames names of columns for filtering */
    protected $columnNames = array();
    
    /** @var mixed $dbConn connection to database  */
    protected $dbConn = null;
    
    /** Current view **/
    protected $view;
    
    protected $urlPath;
    
    // Database stuff
   /** @var array $table (db ref, name) */
    protected $table = array();
    
    /** @var mixed  $query Query in database; */
    protected $query = null;
    
    /** @var array $order : (db column, 'ASC' | 'DESC') */
    protected $order = array();
    protected $orderQuery = '';
    
    /********* Filter ********/
    /** @var string $filterString : string filter for db columns */
    protected $filterString = '';
    
    /** @var array $filterColumn : columns to apply filter to */
    protected $filterColumn = array();
    
    protected $filterQuery='';
    
    /*************** Paging *************/
    protected $maxPerPage = 25;
    
    protected $currentPage = 0;
    
    function __construct(array $table,array $titles, array $columns, array $columnNames, $dbConn , $view, $urlPath)
    {
        $this->table = $table;
        $this->titles = $titles;
        $this->content = $columns;
        $this->columnNames = $columnNames;
        $this->dbConn = $dbConn;
        
        $this->view = $view;
        $this->urlPath = $urlPath;
        
        return $this;
    }

    
    /************** GET variables and URLs *************/
    public function getParams(array $getVars)
    {
        $this->getFilterQuery($getVars);
        $this->getPagingQuery($getVars);
        $this->getOrderQuery($getVars);
    }
    
    public function getCurrentURL()
    {
        return '?';
    }
    
    protected function getCurrentURLAndQS(string $caller)
    {
        $actionURL = $this->getCurrentURL() . '?' ;
        $QSList=array();
        if ($caller != 'filter' && $this->curFilterQuery() != '')
            array_push($QSList , $this->curFilterQuery());
        
        if ($caller != 'paging' && $caller != 'filter' && $this->curPagingQuery() != '')
            array_push($QSList , $this->curPagingQuery());
 
        if ($caller != 'order' && $this->curOrderQuery() != '')
            array_push($QSList , $this->curOrderQuery());
        
        if (count($QSList) != 0)
            $actionURL .=  implode('&', $QSList);
        
        return $actionURL;
    }
    
    /************* DB queries ******************/
    /**
     * Get base query in $this->query
     * @return  TrapDirectorTable
     */
    public function getBaseQuery()
    {
        $this->query = $this->dbConn->select();
        $this->query = $this->query->from(
            $this->table,
            $this->content
            );
        
        return $this;
    }
    
    public function fullQuery()
    {
        $this->getBaseQuery()
        ->applyFilter()
        ->applyPaging()
        ->applyOrder();
        
        return $this->dbConn->fetchAll($this->query);
        //return $this->query->fetchAll();
    }
    
    
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
    
    /*****************  Paging and counting *********/
    
    public function countQuery()
    {
        $this->query = $this->dbConn->select();
        $this->query = $this->query
            ->from(
                $this->table,
                array('COUNT(*)')
                );
        $this->applyFilter();                   
    }
    
    public function count()
    {
        $this->countQuery();
        return $this->dbConn->fetchOne($this->query);
    }
    
    public function setMaxPerPage(int $max)
    {
        $this->maxPerPage = $max;
    }
    
    protected function getPagingQuery(array $getVars)
    {
        if (isset($getVars['page']))
        {
            $this->currentPage = $getVars['page'];
        }
    }

    protected function curPagingQuery()
    {
        if ($this->currentPage == '') return '';
        return 'page='.$this->currentPage;
    }
    
    public function renderPagingHeader()
    {
        $count = $this->count();
        if ($count <= $this->maxPerPage )
        {
            return  'count : ' . $this->count() . '<br>';
        }
        
        if ($this->currentPage == 0) $this->currentPage = 1;
        
        $numPages = intdiv($count , $this->maxPerPage);
        if ($count % $this->maxPerPage != 0 ) $numPages++;
        
        $html = '<div class="pagination-control" role="navigation">';
        $html .= '<ul class="nav tab-nav">';
        if ($this->currentPage <=1)
        {
            $html .= '
                <li class="nav-item disabled" aria-hidden="true">
                    <span class="previous-page">
                            <span class="sr-only">Previous page</span>
                            <i aria-hidden="true" class="icon-angle-double-left"></i>            
                     </span>
                </li>
               ';
        }
        else 
        {
            $html .= '
                <li class="nav-item">
                    <a href="'. $this->getCurrentURLAndQS('paging') .'&page='. ($this->currentPage - 1 ).'" class="previous-page" >
                            <i aria-hidden="true" class="icon-angle-double-left"></i>            
                     </a>
                </li>
            ';
        }
        
        for ($i=1; $i <= $numPages ; $i++)
        {
            $active = ($this->currentPage == $i) ? 'active' : '';
            $first = ($i-1)*$this->maxPerPage+1;
            $last = $i * $this->maxPerPage;
            if ($last > $count) $last = $count;
            $display = 'Show rows '. $first . ' to '. $last .' of '. $count;
            $html .= '<li class="' . $active . ' nav-item">
                    <a href="'. $this->getCurrentURLAndQS('paging') .'&page='. $i .'" title="' . $display . '" aria-label="' . $display . '">
                    '.$i.'                
                    </a>
                </li>';
        }
        
        if ($this->currentPage == $numPages)
        {
            $html .= '
                <li class="nav-item disabled" aria-hidden="true">
                    <span class="previous-page">
                            <span class="sr-only">Previous page</span>
                            <i aria-hidden="true" class="icon-angle-double-right"></i>
                     </span>
                </li>
               ';
        }
        else
        {
            $html .= '
                <li class="nav-item">
                    <a href="'. $this->getCurrentURLAndQS('paging') .'&page='. ($this->currentPage + 1 ).'" class="next-page">
                            <i aria-hidden="true" class="icon-angle-double-right"></i>
                     </a>
                </li>
            ';
        }
        
        $html .= '</ul> </div>';
        
        return $html;
    }
    
    public function applyPaging()
    {
        $this->query->limitPage($this->currentPage,$this->maxPerPage);
        return $this;
    }
    
    /*************** Rendering *************************/
    
    public function titleOrder($name)
    {
        return $this->content[$name];
    }
    
    public function renderTitles()
    {
        $html = "<thead>\n<tr>\n";
        foreach ($this->titles as $name => $values)
        {
            $titleOrder = $this->titleOrder($name);
            if ($titleOrder != NULL)
            {
                if (isset($this->order[$titleOrder]))
                {
                    if ($this->order[$titleOrder] == 'ASC')
                    {
                        $titleOrder.='DESC';
                    }
                    else
                    {
                        $titleOrder.='ASC';
                    }
                }
                else 
                {
                    $titleOrder.='ASC';
                }
                $actionURL = $this->getCurrentURLAndQS('order').'o='.$titleOrder;
                $html .= '<th><a href="'.$actionURL.'">' . $values . '</a></th>';
            }
            else 
            {
                $html .= '<th>' . $values . '</th>';
            }          
        }
        $html .= "</tr>\n</thead>\n";
        return $html;
    }
    
    public function renderLine( $value)
    {
        $html = '';
        $titleNames = array_keys($this->titles);
        foreach ($titleNames as $name )
        {
            $html .= '<td>';
            $html .= $value->$name;
            $html .= "</td>\n";
        }
        return $html;
    }
    
    public function renderTable(array $values)
    {
       $html = '<tbody id="obj_table_body">';
       foreach($values as $value)
       {
           $html .= "<tr>\n";
           $html .= $this->renderLine($value);
           $html .= "</tr>\n";
       }
       $html .= '</tbody>';
       return $html;
    }
    
    public function render()
    {
        $html = '';
        
        
        $values = $this->fullQuery();
        
        $html.="<table class='simple common-table table-row-selectable'>\n";
        
        $html .= $this->renderTitles();
        $html .= $this->renderTable($values);
        $html .= '</table>'; 
        

        return $html;
    }
    
}