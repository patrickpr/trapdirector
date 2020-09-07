<?php

namespace Icinga\Module\Trapdirector\Tables;


trait TrapDirectorTablePaging
{
   
    /*************** Paging *************/
    protected $maxPerPage = 25;
    
    protected $currentPage = 0;
        
    /**** var & func of TrapDirectorTable used ***/
    protected $query;
    abstract protected function getCurrentURLAndQS(string $caller);
    abstract public function applyFilter();
    
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
    
    
}