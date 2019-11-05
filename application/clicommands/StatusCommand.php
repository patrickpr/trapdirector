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
			printf("DB name : %s\n",$dbresource);
			$dataBase = IcingaDbConnection::fromResourceName($dbresource)->getConnection();
			
			$query = $dataBase->select()->from($Config->getTrapTableName(),array('COUNT(*)'));			
			printf("Number of traps : %s\n", $dataBase->fetchOne($query) );
			$query = $dataBase->select()->from($Config->getTrapDataTableName(),array('COUNT(*)'));			
			printf("Number of trap objects : %s\n", $dataBase->fetchOne($query) );
			$query = $dataBase->select()->from($Config->getTrapRuleName(),array('COUNT(*)'));			
			printf("Number of rules : %s\n", $dataBase->fetchOne($query) );
			
		}
		catch (Exception $e)
		{
			printf('Error in DB : %s\n', $e->getMessage());
		}	   
	}  
}
