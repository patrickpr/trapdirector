<?php

namespace Icinga\Module\Trapdirector\TrapsActions;

use Exception;

/**
 * Database queries for UI (on IDO database)
 * Calling class must implement : getTrapCtrl , getIdoDbConn
 * @license GPL
 * @author Patrick Proy
 * @package trapdirector
 * @subpackage UI
 *
 */
trait IdoDBQuery
{

    /** Get host(s) by IP (v4 or v6) or by name in IDO database
     *	does not catch exceptions
     *	@return array of objects ( name, id (object_id), display_name)
     */
    public function getHostByIP($ip)
    {
        // select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        
        // TODO : check for SQL injections
        $query=$dbConn->select()
        ->from(
            array('a' => 'icinga_objects'),
            array('name' => 'a.name1','id' => 'object_id'))
            ->join(
                array('b' => 'icinga_hosts'),
                'b.host_object_id=a.object_id',
                array('display_name' => 'b.display_name'))
                ->where("(b.address LIKE '%".$ip."%' OR b.address6 LIKE '%".$ip."%' OR a.name1 LIKE '%".$ip."%' OR b.display_name LIKE '%".$ip."%') and a.is_active = 1");
                return $dbConn->fetchAll($query);
    }
    
    /** Get host(s) by name in IDO database
     *	does not catch exceptions
     *	@return array of objects ( name, id (object_id), display_name)
     */
    public function getHostByName($name)
    {
        // select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        
        // TODO : check for SQL injections
        $query=$dbConn->select()
        ->from(
            array('a' => 'icinga_objects'),
            array('name' => 'a.name1','id' => 'object_id'))
            ->join(
                array('b' => 'icinga_hosts'),
                'b.host_object_id=a.object_id',
                array('display_name' => 'b.display_name'))
                ->where("a.name1 = '$name'");
                return $dbConn->fetchAll($query);
    }
    
    /** Get host groups by  name in IDO database
     *	does not catch exceptions
     *	@return array of objects ( name, id (object_id), display_name)
     */
    public function getHostGroupByName($ip)
    {
        // select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        // TODO : check for SQL injections
        $query=$dbConn->select()
        ->from(
            array('a' => 'icinga_objects'),
            array('name' => 'a.name1','id' => 'object_id'))
            ->join(
                array('b' => 'icinga_hostgroups'),
                'b.hostgroup_object_id=a.object_id',
                array('display_name' => 'b.alias'))
                ->where("(a.name1 LIKE '%".$ip."%' OR b.alias LIKE '%".$ip."%') and a.is_active = 1");
                return $dbConn->fetchAll($query);
    }
    
    
    /** Get host IP (v4 and v6) by name in IDO database
     *	does not catch exceptions
     *	@return array ( name, display_name, ip4, ip6)
     */
    public function getHostInfoByID($id)
    {
        if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        $query=$dbConn->select()
        ->from(
            array('a' => 'icinga_objects'),
            array('name' => 'a.name1'))
            ->join(
                array('b' => 'icinga_hosts'),
                'b.host_object_id=a.object_id',
                array('ip4' => 'b.address', 'ip6' => 'b.address6', 'display_name' => 'b.display_name'))
                ->where("a.object_id = '".$id."'");
                return $dbConn->fetchRow($query);
    }
    
    
    /** Get host by objectid  in IDO database
     *	does not catch exceptions
     *	@return array of objects ( id, name, display_name, ip, ip6,  )
     */
    public function getHostByObjectID($id) // TODO : duplicate of getHostInfoByID above
    {
        if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        $query=$dbConn->select()
        ->from(
            array('a' => 'icinga_objects'),
            array('name' => 'a.name1','id' => 'a.object_id'))
            ->join(
                array('b' => 'icinga_hosts'),
                'b.host_object_id=a.object_id',
                array('display_name' => 'b.display_name' , 'ip' => 'b.address', 'ip6' => 'b.address6'))
                ->where('a.object_id = ?',$id);
                return $dbConn->fetchRow($query);
    }
    
    /** Get services from object ( host_object_id) in IDO database
     *	does not catch exceptions
     *	@param $id	int object_id
     *	@return array display_name (of service), service_object_id
     */
    public function getServicesByHostid($id)
    {
        // select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
        if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        $query=$dbConn->select()
        ->from(
            array('s' => 'icinga_services'),
            array('name' => 's.display_name','id' => 's.service_object_id'))
            ->join(
                array('a' => 'icinga_objects'),
                's.service_object_id=a.object_id',
                array('is_active'=>'a.is_active','name2'=>'a.name2'))
                ->where('s.host_object_id='.$id.' AND a.is_active = 1');
                return $dbConn->fetchAll($query);
    }
    
    /** Get services from hostgroup object id ( hostgroup_object_id) in IDO database
     * 	gets all hosts in hostgroup and return common services
     *	does not catch exceptions
     *	@param $id	int object_id
     *	@return array display_name (of service), service_object_id
     */
    public function getServicesByHostGroupid($id)
    {
        if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        $query=$dbConn->select()
        ->from(
            array('s' => 'icinga_hostgroup_members'),
            array('host_object_id' => 's.host_object_id'))
            ->join(
                array('a' => 'icinga_hostgroups'),
                's.hostgroup_id=a.hostgroup_id',
                'hostgroup_object_id')
                ->where('a.hostgroup_object_id='.$id);
                $hosts=$dbConn->fetchAll($query);
                $common_services=array();
                $num_hosts=count($hosts);
                foreach ($hosts as $key => $host)
                { // For each host, get all services and add in common_services if not found or add counter
                    $host_services=$this->getServicesByHostid($host->host_object_id);
                    foreach($host_services as $service)
                    {
                        if (isset($common_services[$service->name2]['num']))
                        {
                            $common_services[$service->name2]['num'] +=1;
                        }
                        else
                        {
                            $common_services[$service->name2]['num']=1;
                            $common_services[$service->name2]['name']=$service->name;
                        }
                    }
                }
                $result=array();
                
                //print_r($common_services);
                foreach (array_keys($common_services) as $key)
                {
                    if ($common_services[$key]['num'] == $num_hosts)
                    {
                        array_push($result,array($key,$common_services[$key]['name']));
                    }
                }
                
                return $result;
    }
    
    /** Get services object id by host name / service name in IDO database
     *	does not catch exceptions
     *	@param $hostname string host name
     *	@param $name string service name
     *	@return array  service id
     */
    public function getServiceIDByName($hostname,$name)
    {
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        
        if ($name == null)
        {
            return array();
        }
        
        $query=$dbConn->select()
        ->from(
            array('s' => 'icinga_services'),
            array('name' => 's.display_name','id' => 's.service_object_id'))
            ->join(
                array('a' => 'icinga_objects'),
                's.service_object_id=a.object_id',
                'is_active')
                ->where('a.name2=\''.$name.'\' AND a.name1=\''.$hostname.'\' AND a.is_active = 1');
                
                return $dbConn->fetchAll($query);
    }
    
    /** Get object name from object_id  in IDO database
     *	does not catch exceptions
     *	@param int $id object_id (default to null, used first if not null)
     *	@return array name1 (host) name2 (service)
     */
    public function getObjectNameByid($id)
    {
        // select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
        if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
        $dbConn = $this->getIdoDbConn();
        if ($dbConn === null) throw new \ErrorException('uncatched db error');
        
        $query=$dbConn->select()
        ->from(
            array('a' => 'icinga_objects'),
            array('name1' => 'a.name1','name2' => 'a.name2'))
            ->where('a.object_id='.$id.' AND a.is_active = 1');
            
            return $dbConn->fetchRow($query);
    }
    
    
}