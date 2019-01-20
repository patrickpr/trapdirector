<?php

namespace Icinga\Module\Trapdirector\Clicommands;

use Icinga\Application\Icinga;
use Icinga\Data\Db\DbConnection as IcingaDbConnection;

use Icinga\Cli\Command;
use Icinga\Module\Trapdirector\TrapsController;
use Icinga\Module\Trapdirector\Config\TrapModuleConfig;
use Icinga\Module\Trapdirector\Config\MIBLoader;
use Trap;

/**
 * MIB related actions
 * 
 * Databse update, remove old traps
*/
class MibCommand extends Command
{
	/** Update mib database
	*
	*	USAGE 
	*
	*	icingli trapdirector mib update
	*	
	*	OPTIONS
	*	
	*	none
	*/
	public function updateAction()
	{
		$module=Icinga::app()->getModuleManager()->getModule($this->getModuleName());
		require_once($module->getBaseDir() .'/bin/trap_class.php');
		$icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
		$debug_level=2;
		$trap = new Trap($icingaweb2_etc);
		$trap->setLogging($debug_level,'display');
		try
		{
			echo "Update main mib database : \n";
			$trap->update_mib_database(true);
			echo "Updating options : \n";
			$trap->update_mibs_options();
			echo "Done : \n";
			
		}
		catch (Exception $e)
		{
			echo 'Error in updating : ' . $e->getMessage();
		}	   
	}
	/** purge all mib database
	*
	*	USAGE 
	*
	*	icingli trapdirector mib purge --confirm yes
	*	
	*	OPTIONS
	*	
	*	--confirm yes : needed to execute purge
	*/
	public function dbAction()
	{
		$db_prefix=$this->Config()->get('config', 'database_prefix');
		echo "Not implemented";
		// TODO
		return;
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
