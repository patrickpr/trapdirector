<?php

namespace Icinga\Module\Trapdirector\Clicommands;

use Icinga\Data\Db\DbConnection as IcingaDbConnection;

use Icinga\Cli\Command;

use Exception;

use Icinga\Module\Trapdirector\Config\TrapModuleConfig;

/**
 * Status of the SNMP trap receiver system
 * 
*/
class StatusCommand extends Command
{
	/**
	* Get database counts
	*
	* Get number of traps, rules
	*/
	public function dbAction()
	{
		$db_prefix=$this->Config()->get('config', 'database_prefix');
		$Config = new TrapModuleConfig($db_prefix);
		
		try
		{
			
			$dbresource=$this->Config()->get('config', 'database');
			echo "DB name : $dbresource\n";
			$db = IcingaDbConnection::fromResourceName($dbresource)->getConnection();
			
			$query = $db->select()->from($Config->getTrapTableName(),array('COUNT(*)'));			
			echo "Number of traps : " . $db->fetchOne($query) ."\n";
			$query = $db->select()->from($Config->getTrapDataTableName(),array('COUNT(*)'));			
			echo "Number of trap objects : " . $db->fetchOne($query) ."\n";
			$query = $db->select()->from($Config->getTrapRuleName(),array('COUNT(*)'));			
			echo "Number of rules : " . $db->fetchOne($query) ."\n";		
			
		}
		catch (Exception $e)
		{
			echo 'Error in DB : ' . $e->getMessage();
		}	   
	}  
}
