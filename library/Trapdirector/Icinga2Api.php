<?php


namespace Icinga\Module\Trapdirector;

use RuntimeException;
use Exception;

class Icinga2API 
{
    protected $version = 'v1';      //< icinga2 api version
    
    protected $host;                //< icinga2 host name or IP
    protected $port;                //< icinga2 api port
    
    protected $user;                //< user name
    protected $pass;                //< user password
    protected $usercert;            //< user key for certificate auth (NOT IMPLEMENTED)
    protected $authmethod='pass';   //< Authentication : 'pass' or 'cert'

    protected $curl;
    // http://php.net/manual/de/function.json-last-error.php#119985
    protected $errorReference = [
        JSON_ERROR_NONE => 'No error has occurred.',
        JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded.',
        JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON.',
        JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded.',
        JSON_ERROR_SYNTAX => 'Syntax error.',
        JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded.',
        JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded.',
        JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded.',
        JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given.',
    ];
    const JSON_UNKNOWN_ERROR = 'Unknown error.';
    
    /**
     * Creates Icinga2API object
     * 
     * @param string $host host name or IP
     * @param number $port API port
     */
    public function __construct($host, $port = 5665)
    {
        $this->host=$host;
        $this->port=$port;
    }
    /**
     * Set user & pass
     * @param string $user
     * @param string $pass
     */
    public function setCredentials($user,$pass)
    {
        $this->user=$user;
        $this->pass=$pass;
        $this->authmethod='pass';
    }
    
    /**
     * Set user & certificate (NOT IMPLEMENTED @throws RuntimeException)
     * @param string $user
     * @param string $usercert
     */
    public function setCredentialskey($user,$usercert)
    {
        $this->user=$user;
        $this->usercert=$usercert;
        $this->authmethod='cert';
        throw new RuntimeException('Certificate auth not implemented');
    }

    public function test(array $permissions)
    {
       try
        {
            $result=$this->request('GET', "", NULL, NULL);
        } 
        catch (Exception $e)
        {
            return array(true, 'Error with API : '.$e->getMessage());
        }
        //var_dump($result);
        $permOk=1;
        $permMissing='';
        if (property_exists($result, 'results') && property_exists($result->results[0], 'permissions'))
        {
            
            foreach ( $permissions as $mustPermission)
            {
                $curPermOK=0;
                foreach ( $result->results[0]->permissions as $curPermission)
                {
                    $curPermission=preg_replace('/\*/','.*',$curPermission); // put * as .* to created a regexp
                    if (preg_match('#'.$curPermission.'#',$mustPermission))
                    {
                        $curPermOK=1;
                        break;
                    }
                }
                if ($curPermOK == 0)
                {
                    $permOk=0;
                    $permMissing=$mustPermission;
                    break;
                }
            }
            if ($permOk == 0)
            {
                return array(true,'API connection OK, but missing permission : '.$permMissing);
            }
            return array(false,'API connection OK');
            
        }
        return array(true,'API connection OK, but cannot get permissions');
    }
    
    
    protected function url($url) {
        return sprintf('https://%s:%d/%s/%s', $this->host, $this->port, $this->version, $url);
    }
    
    /**
     * Create or return curl ressource
     * @throws Exception
     * @return resource
     */
    protected function curl() {
        if ($this->curl === null) {
            $this->curl = curl_init(sprintf('https://%s:%d', $this->host, $this->port));
            if (!$this->curl) {
                throw new Exception('CURL INIT ERROR: ' . curl_error($this->curl));
            }
        }
        return $this->curl;
    }

    /**
     * Send a passive service check
     * @param string $host : host name 
     * @param string $service : service name
     * @param int $state : state of service
     * @param string $display : service passive check output
     * @param string $perfdata : performance data as string
     * @return array (status = true (oK) or false (nok), string message)
     */
    public function serviceCheckResult($host,$service,$state,$display,$perfdata='')
    {
        //Send a POST request to the URL endpoint /v1/actions/process-check-result
        //actions/process-check-result?service=example.localdomain!passive-ping6
        $url='actions/process-check-result';
        $body=array(
            "filter"        => 'service.name=="'.$service.'" && service.host_name=="'.$host.'"',
            'type'          => 'Service',
            "exit_status"   => $state,
            "plugin_output" => $display,
            "performance_data" => $perfdata
        );
        try 
        {
            $result=$this->request('POST', $url, null, $body);
        } catch (Exception $e) 
        {
            return array(false, $e->getMessage());
        }
        if (property_exists($result,'error') )
        {
            if (property_exists($result,'status'))
            {
                $message=$result->status;
            }
            else 
            {
                $message="Unkown status";
            }
            return array(false , 'Ret code ' .$result->error.' : '.$message);
        }
        if (property_exists($result, 'results'))
        {
            if (isset($result->results[0]))
            {
                return array(true,'code '.$result->results[0]->code.' : '.$result->results[0]->status);
            }
            else
            {
                return array(false,'Service not found');
            }
            
        }
        return array(false,'Unkown result, open issue with this : '.print_r($result,true));
    }
 
    /**
     * return array of host by IP (4 or 6)
     * @param string $ip
     * @throws Exception
     * @return array objects : array('__name','name','display_name')
     */
    public function getHostByIP($ip) 
    {
        /*
         *  curl -k -s -u  trapdirector:trapdirector -H 'X-HTTP-Method-Override: GET' -X POST 'https://localhost:5665/v1/objects/hosts' 
         *  -d '{"filter":"host.group==\"test_trap\"","attrs": ["address" ,"address6"]}'
            
            {"results":[{"attrs":{"__name":"Icinga host","address":"127.0.0.1","display_name":"Icinga host","name":"Icinga host"},"joins":{},"meta":{},"name":"Icinga host","type":"Host"}]}
         */
        
        $url='objects/hosts';
        $body=array(
            "filter"        => 'host.address=="'.$ip.'" || host.address6=="'.$ip.'"',
            "attrs"         => array('__name','name','display_name')
        );
        try
        {
            $result=$this->request('POST', $url, array('X-HTTP-Method-Override: GET'), $body);
        } catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
        
        if (property_exists($result,'error') )
        {
            if (property_exists($result,'status'))
            {
                throw new Exception('Ret code ' .$result->error.' : ' . $result->status);
            }
            else
            {
                throw new Exception('Ret code ' .$result->error.' : Unkown status');
            }
        }
        if (property_exists($result, 'results'))
        {
            $numHost=0;
            $hostArray=array();
            while (isset($result->results[$numHost]) && property_exists ($result->results[$numHost],'attrs'))
            {
                $hostArray[$numHost] = $result->results[$numHost]->attrs;
                $numHost++;
            }
            return $hostArray;            
        }
        throw new Exception('Unkown result');
    }

    /**
     * Get all host and IP from hostgroup
     * @param string $hostGroup
     * @throws Exception
     * @return array : attributes : address, address6, name
     */
    public function getHostsIPByHostGroup($hostGroup)
    {        
        $url='objects/hosts';
        $body=array(
            "filter"        => '\"'.$hostGroup.'\" in host.groups',
            "attrs"         => array('address','address','name')
        );
        try
        {
            $result=$this->request('POST', $url, array('X-HTTP-Method-Override: GET'), $body);
        } catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
        
        if (property_exists($result,'error') )
        {
            if (property_exists($result,'status'))
            {
                throw new Exception('Ret code ' .$result->error.' : ' . $result->status);
            }
            else
            {
                throw new Exception('Ret code ' .$result->error.' : Unkown status');
            }
        }
        if (property_exists($result, 'results'))
        {
            $numHost=0;
            $hostArray=array();
            while (isset($result->results[$numHost]) && property_exists ($result->results[$numHost],'attrs'))
            {
                $hostArray[$numHost] = $result->results[$numHost]->attrs;
                $hostArray[$numHost]->name = $result->results[$numHost]->name;
                $numHost++;
            }
            return $hostArray;
        }
        throw new Exception('Unkown result');
    }
    
    /**
     * Send request to API
     * @param string $method get/post/...
     * @param string $url (after /v1/ )
     * @param array $headers
     * @param array $body 
     * @throws Exception
     * @return array
     */
    public function request($method, $url, $headers, $body) {
        $auth = sprintf('%s:%s', $this->user, $this->pass);
        $curlHeaders = array("Accept: application/json");
        if ($body !== null) {
            $body = json_encode($body);
            array_push($curlHeaders, 'Content-Type: application/json');
            //array_push($curlHeaders, 'X-HTTP-Method-Override: GET');
        }
        //var_dump($body);
        //var_dump($this->url($url));
        if ($headers !== null) {
            $curlFinalHeaders = array_merge($curlHeaders, $headers);
        } else 
        {
            $curlFinalHeaders=$curlHeaders;
        }
        $curl = $this->curl();
        $opts = array(
            CURLOPT_URL		=> $this->url($url),
            CURLOPT_HTTPHEADER 	=> $curlFinalHeaders,
            CURLOPT_USERPWD		=> $auth,
            CURLOPT_CUSTOMREQUEST	=> strtoupper($method),
            CURLOPT_RETURNTRANSFER 	=> true,
            CURLOPT_CONNECTTIMEOUT 	=> 10,
            CURLOPT_SSL_VERIFYHOST 	=> false,
            CURLOPT_SSL_VERIFYPEER 	=> false,
        );
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $opts);
        $res = curl_exec($curl);
        if ($res === false) {
            throw new Exception('CURL ERROR: ' . curl_error($curl));
        }
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode === 401) {
            throw new Exception('Unable to authenticate, please check your API credentials');
        }
        return $this->fromJsonResult($res);
    }
    
    /**
     * 
     * @param string $json json encoded 
     * @throws Exception
     * @return array json decoded
     */
    protected function fromJsonResult($json) {
        $result = @json_decode($json);
        //var_dump($json);
        if ($result === null) {
            throw new Exception('Parsing JSON failed: '.$this->getLastJsonErrorMessage(json_last_error()));
        }
        return $result;
    }
    
    /**
     * Return text error no json error
     * @param string $errorCode
     * @return string
     */
    protected function getLastJsonErrorMessage($errorCode) {
        if (!array_key_exists($errorCode, $this->errorReference)) {
            return self::JSON_UNKNOWN_ERROR;
        }
        return $this->errorReference[$errorCode];
    }
}

