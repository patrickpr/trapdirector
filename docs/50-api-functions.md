API and functions
==========================

Note : Beginning at version 1.0.3, this feature is still beta. 

The Functions part is stable and can be used.
The API part is still under development.

API
---

API is a php file & class which can be use to : 
- Host functions that can be used in rule evaluation
- (not implemented) get all trap data from a trap and return a status (OK/WARN/CRIT/UNKNOWN)
- (not implemented) catch all traps and return a status and a host/hostgroup.

To create a new plugin named `MyPlugin` : 
- Create a file in `library\Trapdirector\Plugins` and call it `MyPlugin.php`
- In this file create a class derived from PluginTemplate : 
	`class MyPlugin extends PluginTemplate`
	
See the NetworkRule.php file for an example.

You must ACTIVATE the plugin (see below).

(More doc when implemented)

Functions
---------

Functions are freely added in a plugin (you can use existing one to just add one function)

Add the function parameters in the plugin->functions array : 

-  $functions[name]['function'] : Name of the function to be called in this class
-  $functions[name]['params'] : Description of input parameters of the function.
-  $functions[name]['description'] : Description. Can be multiline.

Create a new function in the plugin class : 

`public function functionName(array $params) : bool`

where 

- functionName = $functions[name]['function']
- $params = params passed by rule. The function MUST check params and throw an exception on error.
- return an evaluation as a boolean

Example with inNetwork function
-------------------------------

```
class NetworkRule extends PluginTemplate
{ 

   public $functions=array(
        'inNetwork' => array( // The name of the function in rules
            'function'      =>  'isInNetwork', // Name of the function 
            'params'        =>  '<IP to test>,<Network IP>,<Network mask (CIDR)>', // parameters description
            'description'   =>  'Test if IP is in network, ex : __inNetwork(192.168.123.5,192.168.123.0,24) returns true
Does not work with IPV6' // Description (can be multiline).
        ),
```		
		[... other functions...}
```
    );
```

Note : You can setup parameters in the plugin construct method.
```
    public function isInNetwork(array $params) : bool
    {
        // Check param numbers and thrown exception if not correct.
        if (count($params)!=3)
        {
            throw new Exception('Invalid number of parameters : ' . count($params));
        }
	  ....
	  return true or false
	}
```

After activation of the plugin, you can use the function in a rule : 

- In a rule handler, have an OID which gives an IP (here $1$), and another oid (here $2$), the rule would be like : 

`__inNetwork($1$,192.168.123.0,24) & ( $2$ = 1)`

Plugin activation
-----------------

To activate a plugin, go to Status & Mibs -> Plugins Management and click the "Enabled" checkbox next to the plugin you want to activate.



Go back to the [user guide](02-userguide.md).

