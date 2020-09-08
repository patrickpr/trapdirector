<?php

namespace Icinga\Module\Trapdirector\Tables;

use Icinga\Web\Url;

class HandlerTable extends TrapDirectorTable
{

    protected $status_display=array(
        -2	=>'ignore',
        -1 => '-',
        0	=> 'OK',
        1	=> 'warning',
        2	=> 'critical',
        3	=> 'unknown',);
    
    // translate
    protected $doTranslate=false;
    protected $MIB;
    
    // categories
    protected $categories = NULL;
    
    public function setCategoriesArray(array $categories)
    {
        $this->categories = $categories;
    }
    
    public function groupingPrintData( string $value)
    {
        if ($this->groupingColumn == 'rule_type')
        {
            if ($this->categories == NULL || (! isset($this->categories[$value])))
                return 'Unknown category ('.$value.')';
            return 'Category : '. $this->categories[$value];
        }
        $html = "$value";
        return $html;
    }
    
    public function setMibloader($mibloader)
    {
        $this->MIB=$mibloader;
        $this->doTranslate=true;
    }

    public function titleOrder($name)
    {
        switch ($name)
        {
            case 'host_name' : return $this->content[$name]; break;
            case 'source_ip' : return 'ip4'; break;
            default: return $this->content[$name];	
        }
    }
    
    public function getCurrentURL()
    {
        return Url::fromPath($this->urlPath . '/handler');
    }
    
    public function renderLine($row)
      {
          $html = '';
          $firstCol = true;
               
          $titleNames = array_keys($this->titles);
          foreach ($titleNames as $rowkey )
          {        
              // Check missing value
              if (property_exists($row, $rowkey))
              {
                  switch ($rowkey)
                  {
                      case 'action_match': // display text levels
                      case 'action_nomatch':
                          $val=$this->status_display[$row->$rowkey];
                          break;
                      case 'trap_oid': // try to traslate oids.
                          
                          if ($this->doTranslate === true)
                          {
                              $oidName = $this->MIB->translateOID($row->$rowkey);
                              if (isset($oidName['name']))
                              {
                                  $val=$oidName['name'];
                              }
                              else
                              {
                                  $val = $row->$rowkey;
                              }
                          }
                          else
                          {
                              $val = $row->$rowkey;
                          }
                          break;
                      case 'host_name': // switch to hostgroup if name is null
                          if ($row->$rowkey == null)
                          {
                              $val = $row->host_group_name;
                          }
                          else
                          {
                              $val = $row->$rowkey;
                          }
                          break;
                      default:
                          $val = $row->$rowkey;
                  }
                  if ($rowkey == 'trap_oid' && $this->doTranslate===true)
                  {
                      
                  }
              } else {
                  $val = '-';
              }
              if ($firstCol === true) { // Put link in first column for trap detail.
                  $html .= '<td class="traphover">'
                      . $this->view->qlink(
                          $this->view->escape($val),
                          Url::fromPath(
                              $this->urlPath . '/handler/add',
                              array('ruleid' => $row->id)
                              )
                          );
                  if ($row->comment != '')
                  {
                      $html.= '<span class="tohover">'. $row->comment .'</span></td>';
                  }
                  $html.= '</td>';
              } else {
                  $html .= '<td>' . $this->view->escape($val) . '</td>';
              }
              $firstCol=false;
              
          }
          return $html;
      }

}