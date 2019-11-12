<?php

namespace Icinga\Module\Trapdirector\Clicommands;

use Icinga\Application\Icinga;
use Icinga\Data\Db\DbConnection as IcingaDbConnection;
use Icinga\Cli\Command;

use Exception;

use Icinga\Module\Trapdirector\Config\TrapModuleConfig;

use Trap;

/**
 * MIB related actions
 * 
 * Databse update, remove old traps
*/
class MibCommand extends Command
{
	/**
	*	Update mib database
	*
	*	USAGE 
	*
	*	icingli trapdirector mib update
	*	
	*	OPTIONS
	*	
	*	--pid <file> : run in background with pid in <file>
	*
	*	--verb    : Set output log to verbose
	*
	*   --force-check : force check of all traps & objects for change. (NOT IMPLEMENTED)
	*/
	public function updateAction()
	{
	    $background = $this->params->get('pid', null);
	    $logLevel= $this->params->has('verb') ? 4 : 2;
	    if ($this->params->has('force-check')) { echo "Not implemented"; return;}
	    $forceCheck=$this->params->has('force-check')?True:False;
	    $pid=1;
	    if ($background != null)
	    {
	        $file=@fopen($background,'w');
	        if ($file == false)
	        {
	            echo 'Error : cannot open pid file '.$background;
	            return 1;
	        }
	        $pid = pcntl_fork();
	        if ($pid == -1) {
	            echo 'Error : Cannot fork process';
	            return 1;
	        }
	    }
	    $module=Icinga::app()->getModuleManager()->getModule($this->getModuleName());
		require_once($module->getBaseDir() .'/bin/trap_class.php');
		$icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
		$trap = new Trap($icingaweb2_etc);
		if ($pid == 1)
		{
		    $trap->setLogging($logLevel,'display');
		}
		else
		{  // use default display TODO : if default is 'display' son process will be killed at first output....
		    if ($pid != 0)
		    {
		        // father process
		        fwrite($file,$pid);
		        fclose($file);
		        echo "OK : process $pid in bckground";
		        return 0;
		    }
		    else
		    {  // son process : close all file descriptors and go to a new session
		        fclose($file);		        
// 		        $sid = posix_setsid();
                fclose(STDIN);
                fclose(STDOUT);
                fclose(STDERR);
                try
                {
                    $trap->mibClass->update_mib_database(false,$forceCheck);
                }
                catch (Exception $e)
                {
                    $trap->trapLog('Error in updating : ' . $e->getMessage(),2);
                }
                unlink($background);
                return 0;
		    }
		    
		}
		
		try
		{
			echo "Update main mib database : \n";
			echo "# (trap found) C (trap already processed) . (every 2 seconds) : \n";
			$trap->mibClass->update_mib_database(true,$forceCheck);
			echo "Done\n";
			
		}
		catch (Exception $e)
		{
			echo 'Error in updating : ' . $e->getMessage();
		}
		if ($pid != 1)
		{
		    unlink($background);
		}
	}
	/**
	*	purge all mib database NOT IMPLEMENTED
	*
	*	USAGE 
	*
	*	icingli trapdirector mib purge --confirm yes
	*	
	*	OPTIONS
	*	
	*	--confirm yes : needed to execute purge
	*/
	public function purgeAction()
	{
		$db_prefix=$this->Config()->get('config', 'database_prefix');
		echo "Not implemented";
		// TODO : implement
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
