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
     * return array of host by filter
     * @param string $hostfilter
     * @throws Exception
     * @return array objects : array('__name','name','display_name','id' (=__name), 'address', 'ip4' (=address), 'address6', 'ip6' (=address6)
     */
    public function getHostByFilter(string $hostfilter)
    {
        $hosts = $this->standardQuery(
            'host',
            $hostfilter,
            //'match("*' . $ip . '*",host.address) || match("*' . $ip . '*",host.address6) || match("*' . $ip . '*",host.name) || match("*' . $ip . '*",host.display_name)',
            array('__name','name','display_name','address','address6')
            );
        foreach ( array_keys($hosts) as $key )
        {
            $hosts[$key]->id = $hosts[$key]->__name;
            $hosts[$key]->ip4 = $hosts[$key]->address;
            $hosts[$key]->ip6 = $hosts[$key]->address6;
        }
        return $hosts;
    }
    
    /**
     * return array of host by IP (4 or 6)
     * @param string $ip
     * @throws Exception
     * @return array objects : array('__name','name','display_name')
     */
    public function getHostByIP(string $ip) 
    {
        return $this->getHostByFilter('match("*' . $ip . '*",host.address) || match("*' . $ip . '*",host.address6)');
    }

    
    /**
     * Get host(s) by name in API
     * @param string $name
     * @return array|NULL[] : see getHostByIP
     */
    public function getHostByName(string $name)
    {
        return $this->getHostByFilter('match("*' . $name . '*",host.name) || match("*' . $name . '*",host.display_name)');
    }

    /**
     * Get host(s) by name in API
     * @param string $name
     * @return array|NULL[] : see getHostByIP
     */
    public function getHostByNameOrIP(string $name)
    {
        return $this->getHostByFilter( 
            'match("*' . $name . '*",host.name) || match("*' . $name . '*",host.display_name) || match("*' . $name . '*",host.address) || match("*' . $name . '*",host.address6)');
    }
    
    public function getHostInfoByID(string $name)
    {
        $host = $this->getHostByFilter(
            'host.__name=="'. $name .'"');
        if (isset($host[0]))
            return $host[0];
        else
            return NULL;
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


    /** Get services from host in API
     *	
     *  @throws Exception
     *	@param $id string host name
     *  @param bool $active
     *  @param bool $passive_svc
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
        $services =  $this->standardQuery(
            'service',
            $filter,
            array('__name','name','display_name','active')
            );
        
        foreach ( array_keys($services) as $key )
        {
            $services[$key]->id = $services[$key]->__name;
        }
        
        return $services;
        
    }

/************  Host group query ************/    
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
                        array('num'=> 1 ,'__name' => $service->__name,'display_name' => $service->display_name);
                }
                else
                {
                    $serviceList[$service->name]['num']++;
                }
            }
        }
        $commonServices=array();
        foreach ($serviceList as $key => $values)
        {
            if ($values['num'] >= $hostNum)
            {
                array_push($commonServices,array($key,$values['display_name']));
            }
        }
        return $commonServices;
    }
 
    /**
     * Get all host and IP from hostgroup
     * @param string $hostGroup
     * @throws Exception
     * @return array : attributes : address, address6, name
     */
    public function getHostGroupByName($name)
    {
        $hosts = $this->standardQuery(
            'hostgroup',
            'match("*' . $name . '*",hostgroup.name)',
            array('__name','name','display_name')
            
            );
        foreach ( array_keys($hosts) as $key )
        {
            $hosts[$key]->id = $hosts[$key]->__name;
        }
        return $hosts;
    }
    
    /**
     * Get hostgroup by id (__name)
     * @param string $hostGroup
     * @throws Exception
     * @return array : __name, name, display_name
     */
    public function getHostGroupById($name)
    {
        $hosts = $this->standardQuery(
            'hostgroup',
            'hostgroup.__name=="'. $name .'"',
            array('__name','name','display_name')
            
            );
        $hosts[0]->id = $hosts[0]->__name;
        return $hosts[0];
    }
    
/****************   Service queries ************/
    /** Get services object id by host name / service name
     *	does not catch exceptions
     *	@param $hostname string host name
     *	@param $name string service name
     *  @param bool $active : if true, return only active service
     *  @param bool $passive_svc : if true, return only service accepting passive checks
     *	@return array  service id
     */
    public function getServiceIDByName($hostname,$name,bool $active = TRUE, bool $passive_svc = TRUE)
    {
        $filter = 'service.__name=="' . $hostname . '!'. $name .'"';
        if ($active === TRUE)
        {
            $filter .= ' && service.active==true';
        }
        if ($passive_svc === TRUE)
        {
            $filter .= ' && service.enable_passive_checks==true';
        }
        $services =  $this->standardQuery(
            'service',
            $filter,
            array('__name','name','display_name','active','enable_passive_checks')
            );
        
        foreach ( array_keys($services) as $key )
        {
            $services[$key]->id = $services[$key]->__name;
        }
        
        return $services;
    }
 
    /** Get services object by id (host!name)
     *	does not catch exceptions
     *	@param $name string service __name (host!name)
     *	@return array  service id
     */
    public function getServiceById($name)
    {
        $filter = 'service.__name=="' .  $name .'"';
        $services =  $this->standardQuery(
            'service',
            $filter,
            array('__name','name','display_name','active','enable_passive_checks')
            );
        
        foreach ( array_keys($services) as $key )
        {
            $services[$key]->id = $services[$key]->__name;
        }
        
        return $services;
    }
    
    
}

