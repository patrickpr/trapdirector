<?php


namespace Icinga\Module\Trapdirector;

use Icinga\Module\Trapdirector\IcingaApi\IcingaApiBase;
use RuntimeException;
use Exception;

class Icinga2API extends IcingaApiBase
{
    
    /**
     * Creates Icinga2API object
     * 
     * @param string $host host name or IP
     * @param number $port API port
     */
    public function __construct($host, $port = 5665)
    {
        parent::__construct($host,$port);
    }
    /**

/************ Host query ************/  
  
    /**
     * return array of host by IP (4 or 6) or name
     * @param string $ip
     * @throws Exception
     * @return array objects : array('__name','name','display_name')
     */
    public function getHostByIP(string $ip) 
    {
        return $this->standardQuery(
            'host', 
            'match("*' . $ip . '*",host.address) || match("*' . $ip . '*",host.address6) || match("*' . $ip . '*",host.name) || match("*' . $ip . '*",host.display_name)',
            array('__name','name','display_name')
        );
    }

    
    /**
     * Get host(s) by name in API
     * @param string $name
     * @return array|NULL[] : see getHostByIP
     */
    public function getHostByName(string $name)
    {
        return $this->getHostByIP($name);
    }
 
    /**
     * Get all host and IP from hostgroup
     * @param string $hostGroup
     * @throws Exception
     * @return array : attributes : address, address6, name
     */
    public function getHostsIPByHostGroup($hostGroup)
    {        
        return $this->standardQuery(
            'host', 
            '"' . $hostGroup . '" in host.groups',
            array('address','address6','name')
                
        );
    }
    
    /**
     * Get all host and IP from hostgroup
     * @param string $hostGroup
     * @throws Exception
     * @return array : attributes : address, address6, name
     */
    public function getHostGroupByName($name)
    {
        return $this->standardQuery(
            'hostgroup',
            'match("*' . $name . '*",hostgroup.name)',
            array('name','display_name')
            
            );
    }

    /** Get services from host in API
     *	
     *  @throws Exception
     *	@param $id string host name
     *	@return array display_name (of service), service_object_id
     */
    public function getServicesByHostid(string $id, bool $active = TRUE, bool $passive_svc = TRUE)
    {
        $filter = 'match("' . $id . '!*", service.__name)';
        if ($active === TRUE)
        {
            $filter .= ' && service.active==true';
        }
        if ($passive_svc === TRUE)
        {
            $filter .= ' && service.enable_passive_checks==true';
        }
        return $this->standardQuery(
            'service',
            $filter,
            array('__name','name','display_name','active')
            );
        
    }

    /**
     * return array of host by IP (4 or 6) or name
     * @param string $group Host group name
     * @throws Exception
     * @return array objects : array('name','display_name')
     */
    public function getHostsByGroup(string $group)
    {
         return $this->standardQuery(
            'host',
            '"' . $group . '" in host.groups',
            array('name','display_name')
            );
    }
    
    public function getServicesByHostGroupid(string $group)
    {
        $hostList = $this->getHostsByGroup($group);
        //return $hostList;
        $hostNum = count($hostList);
        $serviceList=array();
        foreach ($hostList as $curHost)
        {
            $services = $this->getServicesByHostid($curHost->name);
            foreach ($services as $service)
            {
                //return $service;
                if (! isset($serviceList[$service->name]))
                {
                    $serviceList[$service->name]=
                        array('num'=> 1 ,'display_name' => $service->display_name);
                }
                else
                {
                    $serviceList[$service->name]['num']++;
                }
            }
        }
        $commonServices=array();
        foreach ($serviceList as $serviceName => $values)
        {
            if ($values['num'] >= $hostNum)
            {
                $commonServices[$serviceName] = $values['display_name'];
            }
        }
        return $commonServices;
    }
    
}

