<?php



namespace Icinga\Module\Trapdirector\Rest;

use Icinga\Module\Trapdirector\TrapsController;

use RuntimeException;
use Exception;
use Icinga\Web\Request;
use Icinga\Web\Response;


class RestAPI
{
    public $version=1;
    
    /**
     * @var TrapsController $trapController
     */
    protected $trapController=null;
    
    public function __construct(TrapsController $trapCtrl)
    {
        $this->trapController=$trapCtrl;
    }
    
    public function sendJson($object)
    {
        $this->trapController->getResponse()->setHeader('Content-Type', 'application/json', true);        
        $this->trapController->getResponse()->sendHeaders();
        $this->trapController->helper_ret(json_encode($object, JSON_PRETTY_PRINT));
        //$this->trapController->_helper->json($object);
        //echo json_encode($object, JSON_PRETTY_PRINT) . "\n";
    }
    
    protected function sendJsonError(string $error, int $retCode = 200)
    {
        //TODO
        $this->sendJson('{"Error":"'.$error.'"}');
    }
    
    public function last_modified()
    {
        try 
        {
            $query = $this->trapController->getUIDatabase()->lastModification();
            return array('lastModified' => $query);
        } 
        catch (\ErrorException $e) 
        {
            return array('Error' =>  $e->getMessage());
        }
    }
}