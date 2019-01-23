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
 * Traps related actions
 * 
 * Remove old traps, ...
*/
class TrapsCommand extends Command
{
	/** delete old traps
	*
	*	USAGE 
	*
	*	icingli trapdirector traps rotate [options]
	*	
	*	OPTIONS
	*	
	*	--days	remove traps older than <n> days
	*/
	public function rotateAction()
	{
		$module=Icinga::app()->getModuleManager()->getModule($this->getModuleName());
		require_once($module->getBaseDir() .'/bin/trap_class.php');
		$icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
		$debug_level=2;
		$trap = new Trap($icingaweb2_etc);
		$trap->setLogging($debug_level,'display');
		try
		{
			$days = $this->params->get('days', '<default>');
			echo $this->screen->colorize("Deleting traps older than $days days\n", 'lightblue');
			if ($days=='<default>')
			{
				$trap->eraseOldTraps();
			}
			else
			{
				$trap->eraseOldTraps($days);
			}
			
		}
		catch (Exception $e)
		{
			echo 'Error in updating : ' . $e->getMessage();
		}	   
	}  	

}
