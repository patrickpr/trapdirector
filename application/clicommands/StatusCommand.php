<?php

namespace Icinga\Module\Trapdirector\Clicommands;

use Icinga\Cli\Command;

/**
 * Status of the SNMP trap receiver system
*/
class StatusCommand extends Command
{
  /**
   * Get Status
   * Status of bla bla
  */
   public function worldAction()
   {
       echo "Hello World!\n";
   }  
	/**
	 * This action will always fail
	 */
	public function failAction()
	{
	    throw new ProgrammingError('No way');
	}
}
/*

$from = $this->params->shift(null, 'Nowhere');
$from = $this->params->get('from', 'Nowhere');

$this->fail('An error occured'); // return failure
echo $this->screen->colorize("Hello from $from!\n", 'lightblue');

*/
