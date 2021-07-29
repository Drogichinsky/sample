#!/usr/bin/php -q
<?php
//  ASR Route 21.02.2017 (copyleft by Stas)

require_once("libyate.php");
require_once("ASRClasses.php");

Yate::Init(True,"127.0.0.1",10000,"global");
Yate::Install("call.route",50);
Yate::Output(true);

cfgload();
$NasNodeHash = array();
$NasIndex_rgdHash = array();
$ZoneIndex_rgdHash = array();
$ZoneLinHash = array();
$NasUUIHash = array();
$NasDirHash = array();
$NasDirRGDHash = array();
$NasIndex_outHash = array();
$ZoneIndex_outHash = array();
$is_mobile = 0; // A
$is_mobileB = 0; // B
$count = 0;
$VirtualB=0; // Переменная объекта класса Ghost - для номера B, которого нет в БД
$dbname = $cfghash["dbname"];                                                                                                             
$dbcredentials = "host=".$cfghash["dbhost"]." dbname=".$cfghash["dbname"]." user=".$cfghash["dbuser"]." password=".$cfghash["dbpassword"];
$default_direction = $cfghash["default_direction"];
$default_zone = $cfghash["default_zone"];
$default_road = $cfghash["default_road"];
$default_node = $cfghash["default_node"];
$index_rgd = $cfghash["index_rgd"];
$cdr = $cfghash["cdr"];
$cdr_err = $cfghash["cdr_err"];
$catched_callerid = ""; // billid из входящего звонка для режима эмуляции

initconnect2base();  // init hash load
if ($cfghash["elcom_exist"] == "Yes") {$make_call_object = 'make_call_objectELC';} else {$make_call_object = 'make_call_objectDef';};
/* The main loop. We pick events and handle them */
for (;;) 
{
 $ev=Yate::GetEvent();
if ($ev === false) break;
if ($ev === true) continue;
switch ($ev->type) {
case "incoming":
$B=0;$A=0;
$starttimerglobal = microtime(); 
$count++;
Yate::Output("ASR: "."count $count");
PatchOneZeroMore(); // отсечение лишнего 0 в начале номера B, если есть
$caller = $ev->GetValue("caller"); $called = $ev->GetValue("called");
$addrlen = strlen($ev->GetValue("address")); 
if ($cfghash["recserv"] != "NULL") 
	{
		if (($cfghash["recserv"] != substr($ev->GetValue("address"), 0, $addrlen-5))) // сравнение IP рекордсервера из конфига с адресом без порта из звонка. Если не равен, то отправляем на запись.
		{
		$addrlen = strlen($ev->GetValue("address"));
		Yate::Output("ASR: "."=== ".substr($ev->GetValue("address"), 0, $addrlen-5)."\n");
		$ipout = $cfghash["recserv"];
		$ev->params["sip_uri"] = 'sip:'.$called.'@'.$ipout;
		$ev->params["sip_to"] =  'sip:'.$called.'@'.$ipout;
		$ev->retval = 'sip/sip:'.$called.'@'.$ipout;
		$ev->Acknowledge();
		}
	}

// for 2 terminators
//if (substr($called, 0,3) == "999") {$addpref = "999"; $called = substr($called, 3);$ev->params["called"] = $called;};
Yate::Output("ASR: "."----------============== DBCred: $dbcredentials LABEL1 caller: $caller called: $called");
Yate::Output("ASR: "."~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~>NEW CALL\n");
Yate::Output("ASR: "."Caller = $caller "."Called = $called\n");
$call = $make_call_object($ev);
Yate::Output("ASR: "."BODY: mobilegate = ".$cfghash["mobilegate"]."\n");
Yate::Output("ASR: "."BODY: obtsgate = ".$cfghash["obtsgate"]."\n");
// Проверка на эмуляцию
switch ($cfghash["emulation"]) {
case "Yes":
	$default_node = $cfghash["emulation_node"];
//	Yate::Output("ASR: "."Cfg: Emulation: Yes\n");
break;
case "Mid":
	$catched_callername = $ev->params["callername"]; 
	if ($catched_callername) {Yate::Output("ASR: "."BODY.Emulation:  catched_callername = ".$catched_callername." \n");} else {Yate::Output("ASR: "."BODY.Emulation: Callername not catched because not present in callerid\n");};
	$default_node = $cfghash["emulation_node"];
//	Yate::Output("ASR: "."Cfg: Emulation: Mid\n");
break;
}

if ($cfghash["elcom_only"] == "Yes") { $is_mobile = Check4mobileElcom($call);} else {$is_mobile = Check4mobile($call);};
if ($is_mobile) // если А мобильный
	{ // leg01-01
	Yate::Output("ASR: "."BODY.LEG: is_mobile for CALLER is TRUE \n");
	if ($A = MakeObjectFromNum($caller, "users.out_number", "Main body, if A=is_mobile ")) 
		{ // leg01-02 Абонент A найден в базе, объект $A создан. 
		Yate::Output("ASR: "."A object from $caller created \n");
		if ($A->getBan() == "f") // Если абонент A не забанен
			{ // leg01-03
			$A->digs5 = substr($A->getUsername(), -5);
			Yate::Output("ASR: "."BODY.LEG: A not banned, A.username: ".$A->getUsername()." A.node: ".$A->getNode()." IP_Host: ".$call->getIp_host()."\n");
			if (IsIpInNode($A->getNode(), $call->getIp_host()))
				{ // leg01-04: A - в домашней ноде
				Yate::Output("ASR: "."BODY.LEG: IsIpInNode Node TRUE: ".$A->getNode()." Ip_Host: ".$call->getIp_host()."\n");
				B5("IsIpInNode True, Comflag = False; "); 
				Yate::Output("ASR: "."Return to BODY.LEG: IsIpInNode = True \n");
				} 
				else
				{ // /leg01-04: A - НЕ в домашней ноде
				B5("IsIpInNode False, Comflag = True; ");
				Yate::Output("ASR: "."Return to BODY.LEG: IsIpInNode = False \n");
				}
			} // /leg01-03
			else
			{ // A banned
			setError("Forbidden", "Caller are banned");
			} 
		} // /leg01-02
		else
		{ // begin of else leg01-02: Если абонент A не в базе
		Yate::Output("ASR: "."--- Caller is not in DB: alles supergut? ---\n"); 	// Отлуп. Заглушка.
		Yate::Output("ASR: "."--- Caller is not in DB ---"); 	// Отлуп. Заглушка.
		setError("Forbidden", "Caller not exist");
		} // /end of else leg01-02
	} // /leg01-01 END OF $is_mobile TRUE
	else
	{ // begin of else leg02-01: BEGIN OF $is_mobile FALSE  - если A не мобильный (значит прилетел из ОБТС)
	if (strlen($call->getCalled()) == 5)
		{ 
		Yate::Output("ASR: "."BODY.LEG: is_mobile False goto -> fromOBTS_B_part, def zone + called = ".$default_zone.$call->getCalled());
		fromOBTS_B_part($default_zone.$call->getCalled());
		}
		else
		{ // Если B длиной не 5
		if ($cfghash["b_with_cz"] == "Yes") 
			{
			if (strlen($call->getCalled()) == 8) {$prefLen = 3;} else {$prefLen = 4;}; // вычисляем длину префикса 
			if (substr($call->getCalled(), 0, $preflen) == $default_zone)
				{ // если зона абонента B - равна текущей, то 
				fromOBTS_B_part($call->getCalled());
				Yate::Output("ASR: "."BODY.LEG: is_mobile False goto -> fromOBTS_B_part, called 5");
				}
				else
				{ // Ошибочно выброшен сюда из ОБТС - выдаем ошибку
	//			set2ev($call->getCaller(), $call->getCalled(), False, "01 ", "BODY.LEG: is_mobile False, pref of called <> default_zone ", NULL); // Call(A,B) marker = 01
				setError("Forbidden", "Called not from our zone!");
				}
			}
			else
			{
			setError("Forbidden", "Called with code_zone! Forbidden from config: parameter b_with_cz");		
			}
		}
	}; // /end of else leg02-01: END OF $is_mobile FALSE
break;

case "answer":
    Yate::Output("ASR: "."PHP Answered: " . $ev->name . " id: " . $ev->id);
    break;
case "installed":
    Yate::Output("ASR: "."PHP Installed: " . $ev->name);
    break;
case "uninstalled":
    Yate::Output("ASR: "."PHP Uninstalled: " . $ev->name);
    break;
default:
    Yate::Output("ASR: "."PHP Event: " . $ev->type);
}    // END OF SWITCH $ev->type
} // ============== END OF MAIN LOOP ==================

Yate::Output("ASR: "."PHP: bye!");

// FUNCTIONS 

// set2ev: финальный этап - добавление исходящих префиксов РОРС; замена Yate-параметров caller, called, etc; запись лога cdr; закрытие соединения к БД
function set2ev($anum, $bnum, $is_mobB, $marker, $uplevelfunc, $ObjectB)
{
global $conn, $ev, $A, $B, $VirtualB, $index_rgd, $caller, $called, $cdr, $starttimerglobal, $NasIndex_rgdHash, $NasIndex_outHash, $cfghash, $default_node, $catched_callerid, $addpref;

if (is_object($ObjectB)) 
	{ // Если B - объект, т.е. найден в базе
	Yate::Output("ASR: "."B is object\n");
	if ($is_mobB) // Если B - мобильный (звонок в текущую ноду)
		{
		$ipout = $cfghash["mobilegate"];
		$fcaller = $ObjectB->getCpn_pref().$anum;		
		$fcalled = $ObjectB->getIndex_out().$bnum;
		$indexout = $ObjectB->getIndex_out();
		Yate::Output("ASR: "."set2ev: 01 is_mob = $is_mobB ipout = $ipout\n");
		}
		else
		{
		if ($cfghash["emulation"] == "Yes")
			{
			$ipout = $cfghash["obtsgate"];
			};
		$fcaller = $anum;			
		$fcalled = $ObjectB->getIndex_rgd().$bnum;		
		$indexout = $ObjectB->getIndex_out();
		Yate::Output("ASR: "."set2ev: 02 is_mob = $is_mobB\n");
		}
	}
	else
	{
	$fcaller = $anum;			
	Yate::Output("ASR: "."Uplevelfunc: $uplevelfunc");
	$fcalled = $VirtualB->getIndex_rgd().$bnum;
	$ipout = $cfghash["obtsgate"];
	Yate::Output("ASR: "."VirtualB getIndex_rgd = ".$VirtualB->getIndex_rgd().", VirtualB.code_zone = ".$VirtualB->getCode_zone()."\n");
	}
//Yate::Output("ASR: "."\naddpref = ".$addpref."\n"; 
if ($addpref) {Yate::Output("ASR: "."addpref = ".$addpref."\n"); $fcalled = $addpref.$fcalled;$addpref = "";};
$ev->params["caller"] = $fcaller;			
$ev->params["callername"] = $catched_callerid;
if ($cfghash["callername_enable"] = "No")
	{
	$ev->params["callername"] = $fcaller;			
	};
$ev->params["sip_from"] = 'sip:'.$fcaller.'@'.$default_node;
if ($cfghash["mustroute"] != "No")
	{
	$ev->params["called"] = $fcalled;		
	$ev->params["sip_uri"] = 'sip:'.$fcalled.'@'.$ipout;
	$ev->params["sip_to"] =  'sip:'.$fcalled.'@'.$ipout;
	$ev->params["isup.CalledPartyNumber"] = $fcalled;
	$ev->retval = 'sip/sip:'.$fcalled.'@'.$ipout;
	};
$ev->Acknowledge();
Yate::Output("ASR: "."Marker = $marker  set2ev UpLevelFunc: $uplevelfunc ; is_mobB?: $is_mobB\n");
Yate::Output("ASR: "."******* Prepare to call: Caller ".$ev->params["caller"].", Called ".$ev->params["called"]." \n");
$stoptimerglobal = microtime(); 
Yate::Output("ASR: "."ALL time of call processing: ".round($stoptimerglobal-$starttimerglobal,4)." s\n");
}

function setError($error, $reason)
{
global $conn, $ev, $A, $B, $cdr_err;
$ev->params["error"] = $error;
$ev->params["reason"] = $reason;
Yate::Output("ASR: "."WARN: Error processing call:"." << $reason >> to ".$ev->params["caller"]." ".$ev->params["called"]." pair\n");
}
                                                    
function prefMakerB_in_DB($ObjectA, $ObjectB, $uplevelfunc) // формирование префикса B, в случае существования его в БД
{
global $default_road;
if  ($default_road != $ObjectB->getCode_rw())
	{
	$prefB = $ObjectB->getCode_rw().substr($ObjectB->getCode_zone(), -$ObjectB->getLin()+5, $ObjectB->getLin()-5);
	if ($ObjectA->getCode_rw() == $ObjectB->getCode_rw()) 
		{
		if ($ObjectA->getCode_zone() == $ObjectB->getCode_zone()) 
			{
			$prefA = ""; 
			Yate::Output("ASR: "."prefMakerB_in_DB1: A.code_rw = ".$ObjectA->getCode_rw()." pref = $prefA");
			}
			else
			{
			$prefA = $ObjectA->getCode_zone(); 
			Yate::Output("ASR: "."prefMakerB_in_DB2: A.code_rw = ".$ObjectA->getCode_rw()." pref = $prefA");		
			}							
		}
		else 
		{
		$prefA = $ObjectA->getCode_rw().substr($ObjectA->getCode_zone(), -$ObjectA->getLin()+5, $ObjectA->getLin()-5);
		Yate::Output("ASR: "."prefMakerB_in_DB3: A.code_rw = ".$ObjectA->getCode_rw()." pref = $prefA");
		}	
	}
	else
	{
	$prefB = $ObjectB->getCode_zone();
	if ($ObjectA->getCode_zone() == $ObjectB->getCode_zone()) 
		{
		$prefA = ""; 
		Yate::Output("ASR: "."prefMakerB_in_DB4: A.code_rw = ".$ObjectA->getCode_rw()." pref = $prefA");
		}
		else
		{
		$prefA = $ObjectA->getCode_zone(); 
		if ($ObjectA->getCode_rw() != $ObjectB->getCode_rw())
			{
			if (($code_rw = PatchCode0900($ObjectB)) && ($ObjectA->getLin() != 5)) // если код дороги 911 то переводим его в 900 для B
				{
				Yate::Output("ASR: "."prefMakerB_in_DB5: PatchCode0900 = ".PatchCode0900($ObjectB)." \n");
				$prefA = $ObjectA->getCode_rw().substr($ObjectA->getCode_zone(), 1); 
				}
			$prefB = $code_rw.substr($prefB, 1);
			Yate::Output("ASR: "."prefMakerB_in_DB6: A.code_rw = ".$ObjectA->getCode_rw()." prefA = $prefA\n");
			}
		}				
	}
set2ev($prefA.$ObjectA->getDigs5(), $prefB.$ObjectB->getDigs5(), False, "03-1", "prefMakerB_in_DB: $uplevelfunc->: Komandirovka: Current node is NOT home for B", $ObjectB); // Call2Mob(A``, B) marker = 03-1
}


function BUniqBanOutnum ($ObjectA, $ObjectB, $pref, $uplf, $uplevelfunc) // Проверка номера B на уникальность, и незабаненность
{
global $A, $default_node, $default_zone, $default_road, $call;
$ActualCodeRW = $pref ? $pref : $ObjectA->getCode_rw();
if (GetUniqUsernameByCodeRW($ObjectB, $ActualCodeRW))
	{
	Yate::Output("ASR: "."--- Uniq: B exist in this road");
//	if ($ObjectB->getBan() == "f")
//		{
//		Yate::Output("ASR: "."--- Called are NOT banned");		
		Yate::Output("ASR: "."--- B1: ".$ObjectB->GetUsername()." A2: ".$ObjectA->getDigs5());
		if ($ObjectB->getNode() == $default_node)
			{ // текущая нода - домашняя для B 
			Yate::Output("ASR: "."--- Current node is home for B");
			if ($ObjectA->getCode_zone() == $default_zone)
				{ // A в домашней зоне - зона А совпадает с текущей
				set2ev($ObjectA->getDigs5(), $ObjectB->getOut_number(), True, "02", $uplevelfunc."->BUniqBanOutnum", $ObjectB); //  marker 02
				}
				else
				{ // A НЕ в домашней зоне
				set2ev($ObjectA->getUsername(), $ObjectB->getOut_number(), True, "02-1", $uplevelfunc."->BUniqBanOutnum", $ObjectB); //  Call2Mob(A``, B``) marker 02-1
				}
			}
			else
			{ // текущая нода - НЕ домашняя для B 
			Yate::Output("ASR: "."--- Current node is NOT home for B");
			Yate::Output("ASR: "."marker 02.5 BEFORE set2ev\n");			// marker 02.5
			$pref = "";
			if ($ObjectA->getNode() != $default_node)
				{
				Yate::Output("ASR: "."BUniqBanOutnum: marker 2-6\n"); // marker 2-6
				prefMakerB_in_DB($ObjectA, $ObjectB, "BUniqBanOutnum");
				}
				else
				{
				Yate::Output("ASR: "."BUniqBanOutnum: marker 2-7\n"); // marker 2-7
				set2ev($ObjectA->getUsername(), $call->getCalled(), False, "03-2", " $uplevelfunc->BUniqBanOutnum: Current node is NOT home for B", $ObjectB); // Call2Mob(A``, B) marker 03-2
				}
			}
//		}
//		else
//		{
//		Yate::Output("ASR: "."--- Called are banned");
//		setError("Forbidden", "Called are banned");
//		}
	}
	else
	{ // В домашней дороге абонента А такого called нет, отправляем а ОБТС-РЖД
	Yate::Output("ASR: "."--- Uniq: B NOT exist in this road");
	Yate::Output("ASR: "."--- B1: ".$ObjectB->getUsername()." A2: ".$A->getDigs5());
	prefMakerB_in_DB($ObjectA, $ObjectB, $uplevelfunc."->BUniqBanOutnum: GetUniqUsernameByCodeRW = False ");
	}
}

function fromOBTS_B_part($called_username) // звонок из ОБТС
{
global $call, $B;
if ($B = MakeObjectFromNum($called_username, "users.username", "fromOBTS_B_part->")) // создаем объект called username из БД, ЕСЛИ ЕСТЬ
	{ 
	set2ev($call->getCaller(), $B->getOut_number(), True, "05", "fromOBTS_B_part: B in DB", $B); // ЗВОНОК Call2Mob(A,B``) marker = 05
	Yate::Output("ASR: "."Transformation B: ".$call->getCalled()." to ".$B->getOut_number());
	}
	else
	{ // Нет called в БД, B - не РОРС - ошибочно прилетевший номер
	setError("Forbidden", "Called $called_username is not RORS - abnormal arrived from OBTS");
	}
}

function makeBprefforB5($ObjectA, $called) // Когда B = 5 и не существует в БД, A - командировочный
{
global $default_road, $default_zone, $VirtualB, $ZoneLinHash, $NasIndex_rgdHash;
$VirtualB = new Ghost($called, $called, $ObjectA->getCode_zone(), $NasIndex_rgdHash[$ObjectA->getNas()], $ZoneLinHash[$ObjectA->getCode_zone()]);
if ($default_zone != $ObjectA->getCode_zone())
	{
	if ($default_road != $ObjectA->getCode_rw())
		{
		$prefB = $ObjectA->getCode_rw().substr($ObjectA->getCode_zone(), -($ObjectA->getLin()-5), $ObjectA->getLin()-5);
		Yate::Output("ASR: "."makeBprefforB5: ObjectA->getLin()= ".$ObjectA->getLin().", prefB = $prefB");
		}
		else
		{ $prefB = $ObjectA->getCode_zone();}
	}
	else { $prefB = "";}
return $prefB;
}

function makeBprefforB89($ObjectA, $called, $preflen) // Когда B = 89, A - командировочный
{
global $default_road, $default_zone, $index_rgd, $VirtualB, $ZoneLinHash;
$zonepref = substr($called, 0, $preflen);
if ($ZoneLinHash[$zonepref]) // есть ли зона в базе?
	{
	if ($default_zone != $ObjectA->getCode_zone())
		{
		if ($default_road != $ObjectA->getCode_rw())
			{
			$prefB = $ObjectA->getCode_rw().substr($VirtualB->getCode_zone(), -$ZoneLinHash[$VirtualB->getCode_zone()]+5, $ZoneLinHash[$VirtualB->getCode_zone()]-5);
			Yate::Output("ASR: "."makeBprefforB89: preflen = $preflen, ObjectA->getLin()= ".$ObjectA->getLin().", prefB = $prefB");
			}
			else
			{
			$prefB = $ObjectA->getCode_zone();
			}
		}
		else
		{$prefB = "";}
	}
	else
	{
	if ($default_road != $ObjectA->getCode_rw())
		{
		$prefB = $ObjectA->getCode_rw().$VirtualB->getCode_zone();
		}
		else
		{
		$prefB = $VirtualB->getCode_zone();
		}
	}
return $prefB;
}

function B5($uplevelfunc) // Ветка B = 5
{
global $call, $A, $B;
if (strlen($call->getCalled()) == 5)
	{ // Звонок из командировки в дом. зону и/или ноду
	if ($B = MakeObjectFromNum($A->getCode_zone().$call->getCalled(), "users.username", "B5->")) // создаем объект called username из БД, ЕСЛИ ЕСТЬ
		{						
		Yate::Output("ASR: "."\nB5: ".$uplevelfunc." B = MakeObjectFromNum SUCCESSfully!\n");
		BUniqBanOutnum($A, $B, NULL, "B5", $uplevelfunc."->B5: MakeObjectFromNum Yes");	
		}
		else
		{
		Yate::Output("ASR: "."\nB5: ".$uplevelfunc." B = MakeObjectFromNum FAILED!\n");
		$prefB = makeBprefforB5($A, $call->getCalled());
		set2ev($A->getDigs5(), $prefB.$call->getCalled(), False, "08", $uplevelfunc."->B5: MakeObjectFromNum No, B is NOT RORS", NULL);
		}
	}
	else
	{ 
	Yate::Output("ASR: "."B5: goto B89 \n");
	B89();
	}				
}

function B89() // Ветка B = 8, 9
{ 
global $A, $B, $call, $default_zone, $default_road, $VirtualB, $index_rgd, $ZoneIndex_rgdHash;
if ((strlen($call->getCalled()) == 7) || (strlen($call->getCalled()) == 8) || (strlen($call->getCalled()) == 9)) // leg01-05-00 Если B длиной 8 или 9
	{ // leg01-05-01
	switch (strlen($call->getCalled())) {
	case "7":
	$preflen = 2;
	Yate::Output("ASR: "."B89: preflen = $preflen");
	break;
	case "8":
	$preflen = 3;
	Yate::Output("ASR: "."B89: preflen = $preflen");
	break;
	case "9":
	$preflen = 4;
	Yate::Output("ASR: "."B89: preflen = $preflen");
	break;					}
	$zoneprefB = substr($call->getCalled(), 0, $preflen);
	if ($B = MakeObjectFromNum($call->getCalled(), "users.username", " B89")) // создаем объект called username из БД, ЕСЛИ ЕСТЬ
		{ // leg01-05-01-1
		Yate::Output("ASR: "."B89: B = MakeObjectFromNum SUCCESSfully!\n");
		BUniqBanOutnum($A, $B, NULL, "B89", " B89 MakeObjectFromNum");	
		} // leg01-05-01-1
		else
		{ // нет called в БД, объект B не создан ; B - абонент РЖД
		Yate::Output("ASR: "."B89: B = MakeObjectFromNum FAILED!\n");
		$index_rgd_4VB = $ZoneIndex_rgdHash[$zoneprefB]?:$index_rgd; // Если наса нет в хеше, индекс выхода ставим дефолтный
		Yate::Output("ASR: "."zoneprefB = $zoneprefB , hash = ".$ZoneIndex_rgdHash[$zoneprefB]);
		$VirtualB = new Ghost($call->getCalled(), substr($call->getCalled(), -5), $zoneprefB, $index_rgd_4VB, $ZoneLinHash[$zoneprefB]);
		if (substr($call->getCalled(), 0, $preflen) == $A->getCode_zone())
			{
			if ($zoneprefB == $default_zone)
				{
				set2ev($A->getDigs5(), substr($call->getCalled(), -5), False, "06-1", " B89: B is NOT object, but B zone same as A zone, set A->digs5 gone to ev", NULL); // ЗВОНОК Call(A`, B) marker 06-1			
				}
				else
				{
				$prefB = makeBprefforB89($A, $call->getCalled(), $preflen);
				set2ev($A->getDigs5(), $prefB.$VirtualB->getNum5(), False, "06-11", " B89: B is NOT object, but B zone same as A zone, set A as username to ev", NULL); // ЗВОНОК Call(A`, B) marker 06-1				
				}
			}
			else
			{
			if ($zoneprefB == $default_zone)
				{
				Yate::Output("ASR: "."Prefix include in called: ".$zoneprefB.",  A code_zone: ".$A->getCode_zone()." Default_zone: $default_zone\n");
				set2ev($A->getUsername(), substr($call->getCalled(), -5), False, "06-2", " B89: B is NOT object, Prefix_zone of B eq default_zone", NULL); // ЗВОНОК Call(A`, B) marker 06-2
				}
				else
				{
				if ((substr($call->getCalled(), 0, 2) == "09") && ($A->getCode_rw() !="0911") && ($A->getCode_rw() !="0900") && ((substr($call->getCalled(), 0, 4) !="0900")))
					{
					Yate::Output("ASR: "."Prefix of called - 09: dis is a road pref only, so will set2ev Caller with a code_rw. Called: ".$call->getCalled().",  A.code_zone: ".$A->getCode_zone()."\n");
					set2ev($A->getCode_rw().$A->getUsername(), $call->getCalled(), False, "06-3", " B89: B is NOT object, Prefix_zone of A<>B", NULL); // ЗВОНОК Call(A`, B) marker 06-3
        				}
					else
					{
					if ($A->getCode_rw() != $default_road) 
						{
						$prefB = makeBprefforB89($A, $call->getCalled(), $preflen);
						if (substr($call->getCalled(), 0, 4) =="0900") // exception
							{
							$A2set = $A->getCode_rw().substr($A->getCode_zone(), -$A->getLin()+5, $A->getLin()-5).$A->getDigs5();
							set2ev($A2set, $call->getCalled(), False, "06-41", " B89: B is NOT object, Prefix_zone of A<>B Comflag = TRue", NULL); // ЗВОНОК Call(A`, B) marker 06-3
							}
							else
							{
							Yate::Output("ASR: "."Prefix include in called: ".$zoneprefB.", prefB = $prefB, A code_zone: ".$A->getCode_zone()."\n");
							set2ev($A->getUsername(), $prefB.$VirtualB->getNum5(), False, "06-42", " B89: B is NOT object, Prefix_zone of A<>B Comflag = TRue", NULL); // ЗВОНОК Call(A`, B) marker 06-3
							}
					        }
						else
						{
						$prefB = makeBprefforB89($A, $call->getCalled(), $preflen);
						Yate::Output("ASR: "."Prefix include in called: ".$zoneprefB.",  A code_zone: ".$A->getCode_zone()."\n");
						set2ev($A->getUsername(), $call->getCalled(), False, "06-5", " B89: B is NOT object, Prefix_zone of A<>B", NULL); // ЗВОНОК Call(A`, B) marker 06-3
	
						}
					}
				}
			}
		} // end of else leg01-05-01-1
	} // end of leg01-05-00
	else
	{ // Если called не (7 или 8 или 9)
	if ((strlen($call->getCalled()) == 11) || (strlen($call->getCalled()) == 12) || (strlen($call->getCalled()) == 13)) // Если B длиной 11 или 12
		{
		switch (strlen($call->getCalled()))
		{
		case 11:
		$preflen = 3; // fake preflen for concat code_rw + code_zone
//		$zoneprefB = PatchAbakan($call->getCalled(), $preflen);
		$zoneprefB = substr($call->getCalled(), 0, $preflen);
		Yate::Output("ASR: "."B1112, case 11, from PatchAbakan zone pref = $zoneprefB , preflen = $preflen \n");
		break;
		case 12:
		$preflen = 4;
		$zoneprefB = substr($call->getCalled(), 0, $preflen);
		break;
		case 13:
		$preflen = 4;
		$zoneprefB = substr($call->getCalled(), 0, $preflen);
		break;
		}; // end of switch	
		$called = $call->getCalled();
		$calledlen = strlen($called)-$preflen;
		$sc = substr($called, $preflen, $calledlen);
//		Yate::Output("ASR: "."\ncalled = $called , preflen = $preflen , sc = $sc\n";
		Yate::Output("ASR: "."B1113, num = ".$called." zoneprefB = $zoneprefB , preflen = $preflen, substr = $sc.\n");		
		$pref = substr($call->getCalled(), 0, $preflen); // выдираем префикс - код дороги
		if ($B = MakeObjectFromNum($sc, "users.username", " B1112")) // создаем объект called username из БД, ЕСЛИ ЕСТЬ
			{
			BUniqBanOutnum($A, $B, $pref, "B1112", " B89-1112 B in DB");
			}
			else
			{ // нет такого called в БД
			Yate::Output("ASR: "."B1113-1: zoneprefB = $zoneprefB ZoneIndex_rgdHash = ".$ZoneIndex_rgdHash[$zoneprefB]);
			$VirtualB = new Ghost($call->getCalled(), substr($call->getCalled(), -5), $A->getCode_zone(), $ZoneIndex_rgdHash[$zoneprefB], NULL);
			set2ev($A->getCode_rw().substr($A->getUsername(), -$A->getLin()), $call->getCalled(), False, "07-0", " B89-1112 B NOT in DB", NULL); // ЗВОНОК В ОБТС-РЖД
			}
		}
		else
		{ // Если called не (7 или 8 или 9) и не (11 или 12 или 13)
		if ((strlen($call->getCalled()) == 6) && (substr($call->getCalled(), -1) == 'a')) // Проверка на присутствие в конце 5-значного набора, символа #
			{ 
			Yate::Output("ASR: "."B with #\n");
			$VirtualB = new Ghost($call->getCalled(), substr($call->getCalled(), 0, 5), $A->getCode_zone(), $NasIndex_rgdHash[$A->getCode_zone()]);
			set2ev($A->getUsername(), $call->getCalled(), False, "07", " B89-1112 TO RZD  with #", NULL); // ЗВОНОК В ОБТС-РЖД marker = 07
			}
			else
			{
			set2ev($A->getCode_rw().substr($A->getUsername(), -$A->getLin()), $call->getCalled(), False, "07-1", " B89-1112 TO RZD", NULL); // ЗВОНОК В ОБТС-РЖД marker = 07-1
			}
		}
	} // end of else leg01-05-00
} 

function Check4mobile($call_obj) // 
{
global $cfghash; //$mobilegate;
if ($call_obj->getValue("ip_host") == $cfghash["mobilegate"])
	{ $is_mobile = True; } else { $is_mobile = False;}; 
Yate::Output("ASR: "."--- Check4mobile: is mobile: $is_mobile \n");
return $is_mobile;
}

function check4UUI($call_obj)
{ //global $UUI, $ev;
	$UUI = $call_obj->GetValue("isup.UserToUserInformation"); 
if ($direction_in = base_convert(substr($UUI, 19, 2), 16, 10))  // Если есть UUI, извлекаем из него направление, конвертируем из 16 в 10 систему - ELCOM only
	{
	Yate::Output("ASR: "."--- check4UUI: UUI: $UUI\n");
	Yate::Output("ASR: "."--- check4UUI === ELCOM direction from UUI: ".$direction_in.".\n"); 
	}
return $direction_in;
};

function check4mobileElcom($call_obj) // Режим с Элкомом на все направления, ОБТС - направление = 1
{
$direction = check4UUI($call_obj);
if ($direction == '1') // Если ОБТС...
	{ $is_mobile = False; } else { $is_mobile = True; };
Yate::Output("ASR: "."--- Check4mobile: Emulation mode Off, CHECKED BY NAS IP, is mobile: $is_mobile \n");
return $is_mobile;
}

function query_check($query) // проверка на успешность факта запроса
{
global $conn;
 if (!$query)
	 {
	 Yate::Output("ASR: "."\nWARN:"." Query error: pg_last_error = ".pg_last_error($conn)."\n");
	 Yate::Output("ASR: "."\nWARN:"." Query error: pg_connection_status = ".pg_connection_status($conn)." (1 - connection NOT exist)\n");
	 };
};

function LoadNasNode()
{
global $conn, $NasNodeHash, $ZoneLinHash, $NasUUIHash, $NasDirHash, $NasDirRGDHash, $NasIndex_rgdHash, $default_node, $NasIndex_outHash, $ZoneIndex_rgdHash;
 $query = pg_query ($conn, "Select nas, node, code_zone, index_rgd, lin, uui, direction, direction_rgd, index_out from initpoints;");
query_check($query);
while ($row = pg_fetch_row($query))
	{
	$NasNodeHash[$row[0]] = $row[1]; // Ключ - нас, значение нода
	$ZoneLinHash[$row[2]] = $row[4]; // Ключ - зона, значение - Lin
	$NasUUIHash[$row[0]] = $row[5]; // Ключ - нас, значение uui
	$NasDirHash[$row[0]] = $row[6]; // Ключ - нас, значение direction
	$NasDirRGDHash[$row[0]] = $row[7]; // Ключ - нас, значение direction_rgd
	$NasIndex_rgdHash[$row[0]] = $row[3]; // Ключ - нас, значение - индекс выхода на ОБТС
	$ZoneIndex_rgdHash[$row[2]] = $row[3]; // Ключ - зона, значение - индекс выхода на ОБТС
	};
	foreach ($NasNodeHash as $key => $value) {Yate::Output("ASR: "."---------- HASH ============== $key => $value");}; 	// DEBUG OUTPUT - 2 REMOVE
$query = pg_query ($conn, "select nas, index_out from initpoints where node = "."'".$default_node."' group by nas, index_out;");
query_check($query);
while ($row = pg_fetch_row($query))
	{
	$NasIndex_outHash[$row[1]] = $row[0];
	}
};
				
function IsIpInNode($node, $ip) 
{
global $NasNodeHash;
if ($NasNodeHash[$ip] == $node)	{return true;}  else { return false;};
}

function GetUniqUsernameByCodeRW ($username_object, $road)
{
 Yate::Output("ASR: "."---------- GetUniqUsernameByCodeRW ".$username_object->getCode_rw()); 
if ($username_object->getCode_rw() == $road) {return true;} else { return false;};
}

function MakeObjectFromNum($num, $type, $uplevelfunc) // Создание объекта номера на основании инфо из базы
{
global $conn, $dbname;
 $starttimer=microtime(); 
 $sql = "select users.user_id, users.username, users.out_number, users.initpoint, users.user_type, users.record, users.priority,
 users.forwarding_flag, users.ban, initpoints.nas, initpoints.node, initpoints.code_zone, initpoints.index_out, initpoints.direction, initpoints.cpn_pref,
 initpoints.direction_rgd, initpoints.index_rgd, initpoints.code_rw, initpoints.lin from users inner join initpoints on (users.initpoint = initpoints.id) where $type = "."'".$num."'";
 Yate::Output("ASR: "."---------- make_subscriber_object ============== Making subscriber object of number: ".$num);
 $query = pg_query ($conn, $sql);
 query_check($query);
 Yate::Output("ASR: "."MakeObjectFromNum: ".$num.", uplevelfunc: $uplevelfunc\n");
 $stoptimer = microtime(); 
Yate::Output("ASR: "."Query time: ".round($stoptimer-$starttimer,4)." s\n");
while ($row = pg_fetch_row($query)) 
	{ // Создаем объект класса Subscriber, заполняем его значениями полей таблицы users
	Yate::Output("ASR: "."MakeObjectFromNum: From query row: $row[1], type = $type, num = $num, Uplevel: $uplevelfunc\n"); // DEBUG
	$subscriber_object = new Subscriber($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7],
	$row[8], $row[9], $row[10], $row[11], $row[12], $row[13], $row[14], $row[15], $row[16], $row[17], substr($num, -5), $row[18]);

	}
        return $subscriber_object;
}

function make_call_objectELC($ev1)
{ global $UUI;
if (!is_null($ev1->GetValue("isup.UserToUserInformation" ))) { $UUI = $ev1->GetValue("isup.UserToUserInformation"); } else { $UUI = null; }; // ПРОВЕРКА НА ELCOM
 $call_object = new Call($ev1->GetValue("id"), $ev1->GetValue("module"), $ev1->GetValue("status"), $ev1->GetValue("billid"), $ev1->GetValue("answered"), $ev1->GetValue("direction"),
 $ev1->GetValue("callid"), $ev1->GetValue("caller"), $ev1->GetValue("called"), $ev1->GetValue("callername"), $ev1->GetValue("ip_host"), $ev1->GetValue("ip_port"),
 $ev1->GetValue("device"), $ev1->GetValue("sip_date"), $UUI); 				
return $call_object;
}

function make_call_objectDef($ev1)
{
 $call_object = new Call($ev1->GetValue("id"), $ev1->GetValue("module"), $ev1->GetValue("status"), $ev1->GetValue("billid"), $ev1->GetValue("answered"), $ev1->GetValue("direction"),
 $ev1->GetValue("callid"), $ev1->GetValue("caller"), $ev1->GetValue("called"), $ev1->GetValue("callername"), $ev1->GetValue("ip_host"), $ev1->GetValue("ip_port"),
 $ev1->GetValue("device"), $ev1->GetValue("sip_date"), null); 				
return $call_object;
}


function connect2base()
{
global $dbcredentials, $conn, $dbname;
 $starttimer=time()+microtime(); // для измерения времени коннекта к базе
// $conn = pg_connect($dbcredentials) or die("DB Credentials ".$dbcredeintials." Couldn't Connect".pg_last_error());
 $stoptimer = time()+microtime(); 
Yate::Output("ASR: "."~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~>\nNEW CALL: SUCCESSFULLY CONNECTED TO ".$dbname." in ".round($stoptimer-$starttimer,4)." s\n");
}

function initconnect2base()
{
global $dbcredentials, $conn, $dbname;
 $starttimer=microtime(true); // для измерения времени коннекта к базе
 $conn = pg_connect($dbcredentials) or die("\nWARN: DB Credentials: Couldn't Connect to DB,"." check the  postgres is running\n".pg_last_error());
 $stoptimer = microtime(true); 
 $delta = round($stoptimer-$starttimer,4);

Yate::Output("ASR: "."INIT: SUCCESSFULLY CONNECTED TO ".$dbname." in ".$delta." s \n");
 LoadNasNode(); 
}


function PatchOneZeroMore() // заплатка. отсечение лишнего нолика в номере B - часто встречается
{
global $ev;
$called = $ev->GetValue("called");
if ((substr($called, 0, 2) == "00") && (substr($called, 0, 3) != "001") &&  (((substr($called, 0, 3) != "005") ) || ((substr($called, 0, 3) == "005") && (strlen($called) >8 ))))
	{
	$calledreal = substr($called, 1); $ev->params["called"] = $calledreal;
	}
}

function PatchAbakan($called, $preflen) // заплатка. Эксклюзивная обработка длины номера
{
if ((substr($called, 0, 4) == "0990") && (((substr($called, 4, 2) == "47") || (substr($called, 4, 2) == "01"))))
	{
	$zonepref = "0".substr($called, 4, 2);
	}
	else
	{
	$zonepref = substr($called, 0, $preflen);
	}
return $zonepref;	
}

function PatchCode0900($ObjectB) // если код дороги 911 то переводим его в 900 для B - эксклюзивное правило трансформации номера B для зоны 911
{
if ($ObjectB->getCode_rw() == '0911')
	{$Code_rwB = '0900';} else {$Code_rwB = NULL;}
return $Code_rwB;
}


function cfgload() // Инициализация хеша с предустановленными ключами - доступными параметрами. Загрузка конфигурационного файла.
{
global $cfghash;

Yate::Output("ASR: "."Loading configuration:\n\n");
$cfghash = array(
'emulation' => '',
'emulation_node' => '',
'obtsgate' => '',
'mobilegate' => '',
'elcom_only' => '',
'elcom_exist' => '',
'callername_enable' => '',
'mustroute' => '',
'recserv' => '',
'dbname' => '',
'dbhost' => '',
'dbport' => '',
'dbuser' => '',
'dbpassword' => '',
'default_direction' => '',
'default_zone' => '',
'default_road' => '', 
'default_node' => '',
'index_rgd' => '',
'b_with_cz' => '');

$handle = @fopen("asr.cfg", "r");

if ($handle)
	{
	while (($buffer = fgets($handle, 4096)) !== false) 
		{
		if ((substr(ltrim($buffer), 0, 1) != '#') && (ltrim($buffer) !=""))
			{
			if ($pieces0 = explode("#", $buffer))
			{ $buffer = $pieces0[0];}
			splitter($buffer);
			}
		}
	}
}

function splitter($buffer) // сплиттит строки конфига на параметр/значение, заносит в хеш
{
global $cfghash;
if ($pieces = explode("=", $buffer))
	{ $left = $pieces[0];$right = $pieces[1];};
$left = ltrim(rtrim($pieces[0]));
$right = ltrim(rtrim($pieces[1]));
Yate::Output("ASR: "."$left = $right\n");
if (array_key_exists($left, $cfghash))
	{ $cfghash[$left] = $right;} // Если параметр, считанный из файла, существует как предустановленный ключ в хеше.
       else
	{ // Если параметр левый
	Yate::Output("ASR: "."Cfg parsing ");
	Yate::Output("ASR: "."WARN: "."parameter ");
	Yate::Output("ASR: "."$left unknown, ignored \n");
	}
}

?>