<?php

namespace Icinga\Module\Trapdirector\Tables;


abstract class TrapDirectorTable
{
    
    use TrapDirectorTableFilter;
    use TrapDirectorTablePaging;
    use TrapDirectorTableOrder;
    use TrapDirectorTableGrouping;
    
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
            $actionURL .=  implode('&', $QSList) . '&';
        
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
            $titleOrder = $this->titleOrder($name);  // TODO : put this as function of order trait
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
           $html .= $this->groupingNextLine($value);
           
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
        $this->initGrouping();
        
        $html.="<table class='simple common-table table-row-selectable'>\n";
        
        $html .= $this->renderTitles();
        $html .= $this->renderTable($values);
        $html .= '</table>'; 
        

        return $html;
    }
    
}