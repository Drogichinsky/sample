#!/usr/bin/php -q
<?php
// classes for ASR 07.02.2017
// CLASES DESCRIBED: Subscriber, Call, Colors

// Class of num which not exist in DB Класс для номера B, которого нет в БД
class Ghost {
	public $num;
	public $num5;
	public $code_zone;
	public $index_rgd;
	public $lin;
	
	public function __construct($num, $num5, $code_zone, $index_rgd, $lin)
	{
		$this->num = $num;
		$this->num5 = $num5;
		$this->code_zone = $code_zone;
		$this->index_rgd = $index_rgd;
		$this->lin = $lin;
	}

	public function getNum()
	{
		return $this->num;
	}
	public function getNum5()
	{
		return $this->num5;
	}
	public function getCode_zone()
	{
		return $this->code_zone;
	}
	public function getIndex_rgd()
	{
		return $this->index_rgd;
	}
	public function getLin()
	{
		return $this->lin;
	}

} // end of class Ghost


// Subscriber as object from ASRDB.users
class Subscriber {
	public $user_id;
	public $username;
	public $digs5;
	public $out_number;
	public $initpoint;
	public $user_type;
	public $record;
	public $priority;
	public $forwarding_flag;
// INNER JOIN FROM INITPOINTS
	public $ban;
	public $nas;
	public $node;
	public $code_zone;
	public $index_out;
	public $direction;
	public $cpn_pref;
	public $direction_rgd;
	public $index_rgd;
	public $code_rw;	
	public $lin;
	
	public function __construct($user_id, $username, $out_number, $initpoint, $user_type, $record, $priority, $forwarding_flag, $ban, $nas, $node, $code_zone, $index_out, $direction, $cpn_pref, $direction_rgd, $index_rgd, $code_rw, $digs5, $lin)
	{
		$this->user_id = $user_id;
		$this->username = $username;
		$this->digs5 = $digs5;
		$this->out_number = $out_number;
		$this->initpoint = $initpoint;
		$this->user_type = $user_type;
		$this->record = $record;
		$this->priority = $priority;
		$this->forwarding_flag = $forwarding_flag;
		$this->ban = $ban;
		$this->nas = $nas;
		$this->node = $node;
		$this->code_zone = $code_zone;
		$this->index_out = $index_out;
		$this->direction = $direction;
		$this->direction_rgd = $direction_rgd;
		$this->cpn_pref = $cpn_pref;
		$this->index_rgd = $index_rgd;
		$this->code_rw = $code_rw;
		$this->lin = $lin;
	}

	public function getUser_id()
	{
		return $this->user_id;
	}
	public function getUsername()
	{
		return $this->username;
	}
	public function getDigs5()
	{
		return $this->digs5;
	}
	public function getOut_number()
	{
		return $this->out_number;
	}
	public function getInitpoint()
	{
		return $this->initpoint;
	}
	public function getUser_type()
	{
		return $this->user_type;
	}
	public function getRecord()
	{
		return $this->record;
	}
	public function getPriority()
	{
		return $this->priority;
	}
	public function getForwarding_flag()
	{
		return $this->forwarding_flag;
	}
	public function getBan()
	{
		return $this->ban;
	}
	public function getNas()
	{
		return $this->nas;
	}
	public function getNode()
	{
		return $this->node;
	}
	public function getCode_zone()
	{
		return $this->code_zone;
	}
	public function getIndex_out()
	{
		return $this->index_out;
	}
	public function getDirection()
	{
		return $this->direction;
	}
	public function getCpn_pref()
	{
		return $this->cpn_pref;
	}
	public function getDirection_rgd()
	{
		return $this->direction_rgd;
	}
	public function getIndex_rgd()
	{
		return $this->index_rgd;
	}
	public function getCode_rw()
	{
		return $this->code_rw;
	}
	public function getLin()
	{
		return $this->lin;
	}

} //END OF CLASS SUBSCRIBER

// Call object, filled from call.route information
class Call {
	public $id;
	public $module;
	public $status;
	public $billid;
	public $answered;
	public $direction;
	public $callid;
	public $caller;
	public $called;
	public $callername;
	public $ip_host;
	public $ip_port;
	public $device;
	public $sip_date;
	public $isup_UUI;

	public function __construct($id, $module, $status, $billid, $answered, $direction, $callid, $caller, $called, $callername, $ip_host, $ip_port, $device, $sip_date, $isup_UUI)
	{
		$this->id = $id;
		$this->module = $module;
		$this->status = $status;
		$this->billid = $billid;
		$this->answered = $answered;
		$this->direction = $direction;
		$this->callid = $callid;
		$this->caller = $caller;
		$this->called = $called;
		$this->callername = $callername;
		$this->ip_host = $ip_host;
		$this->ip_port = $ip_port;
		$this->device = $device;
		$this->sip_date = $sip_date;
		$this->isup_UUI = $isup_UUI;
	}

	public function getChan_id()
	{
		return $this->id;
	}
	public function getModule()
	{
		return $this->module;
	}
	public function getStatus()
	{
		return $this->status;
	}
	public function getBillid()
	{
		return $this->billid;
	}
	public function getAnswered()
	{
		return $this->answered;
	}
	public function getDirection()
	{
		return $this->direction;
	}
	public function getCallid()
	{
		return $this->callid;
	}
	public function getCaller()
	{
		return $this->caller;
	}
	public function getCalled()
	{
		return $this->called;
	}
	public function getCallername()
	{
		return $this->callername;
	}
	public function getIp_host()
	{
		return $this->ip_host;
	}
	public function getIp_port()
	{
		return $this->ip_port;
	}
	public function getDevice()
	{
		return $this->device;
	}
	public function getSip_date()
	{
		return $this->sip_date;
	}
	public function getIsup_UUI()
	{
		return $this->isup_UUI;
	}

	public function GetValue($key)
    {
	    return $this->$key;

    }

} // END OF CLASS CALL


 
 class Colors {
 private $foreground_colors = array();
 private $background_colors = array();
 
 public function __construct() {
 // Set up shell colors
 $this->foreground_colors['black'] = '0;30';
 $this->foreground_colors['dark_gray'] = '1;30';
 $this->foreground_colors['blue'] = '0;34';
 $this->foreground_colors['light_blue'] = '1;34';
 $this->foreground_colors['green'] = '0;32';
 $this->foreground_colors['light_green'] = '1;32';
 $this->foreground_colors['cyan'] = '0;36';
 $this->foreground_colors['light_cyan'] = '1;36';
 $this->foreground_colors['red'] = '0;31';
 $this->foreground_colors['light_red'] = '1;31';
 $this->foreground_colors['purple'] = '0;35';
 $this->foreground_colors['light_purple'] = '1;35';
 $this->foreground_colors['brown'] = '0;33';
 $this->foreground_colors['yellow'] = '1;33';
 $this->foreground_colors['light_gray'] = '0;37';
 $this->foreground_colors['white'] = '1;37';
 
 $this->background_colors['black'] = '40';
 $this->background_colors['red'] = '41';
 $this->background_colors['green'] = '42';
 $this->background_colors['yellow'] = '43';
 $this->background_colors['blue'] = '44';
 $this->background_colors['magenta'] = '45';
 $this->background_colors['cyan'] = '46';
 $this->background_colors['light_gray'] = '47';
 }
 
 // Returns colored string
 public function getColoredString($string, $foreground_color = null, $background_color = null) {
 $colored_string = "";
 
 // Check if given foreground color found
 if (isset($this->foreground_colors[$foreground_color])) {
 $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
 }
 // Check if given background color found
 if (isset($this->background_colors[$background_color])) {
 $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
 }
 
 // Add string and end coloring
 $colored_string .=  $string . "\033[0m";
 
 return $colored_string;
 }
 
 // Returns all foreground color names
 public function getForegroundColors() {
 return array_keys($this->foreground_colors);
 }
 
 // Returns all background color names
 public function getBackgroundColors() {
 return array_keys($this->background_colors);
 }
 }
 


?>
