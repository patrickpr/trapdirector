<?php

namespace Trapdirector;

use Exception;

define("ERROR", 1);define("WARN", 2);define("INFO", 3);define("DEBUG", 4);

class Logging
{

    //**** Options from config database
    // Default values
    public $debugLevel=2;  // 0=No output 1=critical 2=warning 3=trace 4=ALL
    public $outputMode='syslog'; // alert type : file, syslog, display
    public $outputFile="/tmp/trapdebug.txt";
    protected $logLevels=array("","Error","Warning","Info","Debug");
    protected $outputList=array('file', 'syslog', 'display');
    
    /** Send log. Throws exception on critical error
     *	@param	string $message Message to log
     *	@param	int $level 1=critical 2=warning 3=trace 4=debug
     *	@param  string $destination file/syslog/display
     *	@return void
     *  @throws Exception
     **/
    public function log( $message, $level, $destination ='')
    {
        if ($this->debugLevel >= $level)
        {
            $message = '['.  date("Y/m/d H:i:s") . '] ' .
                '[TrapDirector] ['.$this->logLevels[$level].']: ' .$message . "\n";
            
            $output = ( $destination != '' ) ? $destination : $this->outputMode;
            switch ($output)
            {
                case 'file':
                    file_put_contents ($this->outputFile, $message , FILE_APPEND);
                    break;
                case 'syslog':
                    switch($level)
                    {
                        case 1 : $prio = LOG_ERR;break;
                        case 2 : $prio = LOG_WARNING;break;
                        case 3 : $prio = LOG_INFO;break;
                        case 4 : $prio = LOG_DEBUG;break;
                        default: $prio = LOG_ERR;
                    }
                    syslog($prio,$message);
                    break;
                case 'display':
                    echo $message;
                    break;
                default : // nothing we can do at this point
                    throw new Exception($message);
            }
        }
        if ($level == 1)
        {
            throw new Exception($message);
        }
    }
    
        
    public function setLogging($debugLvl,$outputType,$outputFile=null)
    {
        $this->setLevel($debugLvl);
        switch ($outputType)
        {
            case 'file':
                if ($outputFile == null) throw new Exception("File logging without file !");
                $this->setFile($outputFile);
                $this->setDestination('file');
                break;
            default:
                $this->setDestination($outputType);
        }
    }
    
    /**
     * Set logging level
     * @param integer $level
     * @throws Exception
     */
    public function setLevel($level)
    {
        if (!is_integer($level) || $level < 0 || $level > 10)
        {
            throw new Exception('Invalid log level');
        }
        $this->debugLevel=$level;
    }

    /**
     * Set logging destination
     * @param string $destination
     * @throws Exception
     */
    public function setDestination($destination)
    {
        if (!is_string($destination) || ! in_array($destination, $this->outputList))
        {
            throw new Exception('Invalid log destination');
        }
        $this->outputMode=$destination;
    }
    /**
     * Set file destination
     * @param string $file
     * @throws Exception
     */
    public function setFile($file)
    {
        if (!is_string($file))
        {
            throw new Exception('Invalid log file');
        }
        $this->outputFile=$file;
    }
    
}