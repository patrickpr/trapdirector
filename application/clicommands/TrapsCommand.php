<?php

namespace Icinga\Module\Trapdirector\Clicommands;

use Icinga\Application\Icinga;

use Icinga\Cli\Command;
use Exception;

use Trap;

/**
 * Traps related actions
 * 
 * Remove old traps, ...
*/
class TrapsCommand extends Command
{
	/**
	*	Delete old traps
	*
	*	USAGE 
	*
	*	icingali trapdirector traps rotate [options]
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
	
	/**
	*	Reset services to OK state if timeout is passed
	*
	*	USAGE
	*
	*	icingali trapdirector traps reset_services
	*
	*/
	public function resetOKAction()
	{
		$module=Icinga::app()->getModuleManager()->getModule($this->getModuleName());
		require_once($module->getBaseDir() .'/bin/trap_class.php');
		$icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
		$debug_level=2;
		$trap = new Trap($icingaweb2_etc);
		$trap->setLogging($debug_level,'display');
		try 
		{
			$trap->reset_services();
		} 
		catch (Exception $e) 
		{
			echo 'ERROR : '. $e->getMessage();
		}
	} 	
	
}
