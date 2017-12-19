<?php
require_once("helpdesk_base.php");
class ask extends helpdesk_base{
	function __construct(	$name,$phone,$rp_id,$globalid,$question,$database,$openHimTransactionID,
							$ohimApiHost,$ohimApiUser,$ohimApiPassword,$rapidpro_token,$rapidpro_url,$helpdesk_group
						) {
		parent::__construct($rapidpro_token,$rapidpro_url,$ohimApiHost,$ohimApiUser,$ohimApiPassword);

		$question = str_ireplace("help","",$question);
		$this->name = $name;
		$this->phone = $phone;
		$this->rp_id = $rp_id;
		$this->globalid = $globalid;
		$this->openHimTransactionID = $openHimTransactionID;
		$this->question = $question;
		$this->database = $database;
		$this->helpdesk_group = $helpdesk_group;
		$this->orchestrations = array();
	}

	function send_question() {
		error_log("Sending Question");
		$ticket_number = $this->save_question();
		$question = "Ticket $ticket_number: ".$this->question;
		$cont_helpdesk = array();
		foreach($this->helpdesk_group as $helpdesk_group) {
	      $other_contacts = $this->get_contacts_in_grp(urlencode($helpdesk_group));
	      if(count($other_contacts)>0)
	      $cont_helpdesk = array_merge($cont_helpdesk,$other_contacts);
		}
		$this->broadcast("Alert Helpdesk",$cont_helpdesk,$question);
	}

	function save_question() {
		error_log("Saving Question");
		$collection = (new MongoDB\Client)->{$this->database}->questions;
	    $date = date("Y-m-d\TH:m:s");
	    $ticket_number = $this->get_ticket_number();
	    $insertOneResult = $collection->insertOne([	
	    											"ticket_number"=>$ticket_number,
	                                                "question"=>$this->question,
	                                                "globalid"=>$this->globalid,
	                                                "rapidpro_id"=>$this->rp_id,
	                                                "name"=>$this->name,
	                                                "phone"=>$this->phone,
	                                                "openHimTransactionID"=>$this->openHimTransactionID,
	                                                "date"=>$date
	                                              ]);
	    error_log("Question saved to database with id ".$insertOneResult->getInsertedId());
	    return $ticket_number;
	}

	function get_ticket_number() {
		$collection = (new MongoDB\Client)->{$this->database}->questions;
		$date = date("dmy");
        $regex = new MongoDB\BSON\Regex("^$date");
		$questions = $collection->find(array("ticket_number"=>$regex));
		$number = 0;
		foreach($questions as $question) {
			$current = str_replace($date,"",$question["ticket_number"])."\n";
			if($current > $number)
			$number = $current;
		}
		$number = $number+1;
		return $date.$number;
	}
}

class answer extends helpdesk_base{
	function __construct(	$name,$phone,$rp_id,$globalid,$answer,$database,$openHimTransactionID,
							$ohimApiHost,$ohimApiUser,$ohimApiPassword,$rapidpro_token,$rapidpro_url,$helpdesk_group
						) {
		parent::__construct($rapidpro_token,$rapidpro_url,$ohimApiHost,$ohimApiUser,$ohimApiPassword);
		$answer = str_ireplace("answer","",$answer);
		$this->name = $name;
		$this->phone = $phone;
		$this->rp_id = $rp_id;
		$this->globalid = $globalid;
		$this->openHimTransactionID = $openHimTransactionID;
		$this->answer = $answer;
		$this->database = $database;
		$this->helpdesk_group = $helpdesk_group;
		$this->orchestrations = array();
	}

	function send_answer() {
		$answer = explode(".",$this->answer);
		$ticket_number = false;
		foreach($answer as $ans) {
			$ans1 = preg_replace('/\s+/', '', $ans);
			if(is_numeric($ans1) and strlen($ans1) >= 7) {
				$ticket_number = $ans1;
				//remove ticket number from answer
				$this->answer = str_ireplace($ans, "", $this->answer);
				//remove fullstops at the begining of an answer
				$this->answer = $this->str_replace_first(".","",$this->answer);
				$this->answer = $this->str_replace_first(".","",$this->answer);
			}
		}
		error_log($ticket_number);
		if(!$ticket_number) {
			$msg = "Ticket number were not found for the answer ".$this->answer;
			$this->broadcast("Reply respondent",array($this->rp_id),$msg);
			return;
		}
		else {
			//save this answer
			$this->save_answer($ticket_number);
			//alert every helpdesk officers about response
			$cont_helpdesk = array();
			foreach($this->helpdesk_group as $helpdesk_group) {
		      $other_contacts = $this->get_contacts_in_grp(urlencode($helpdesk_group));
		      if(count($other_contacts)>0)
		      $cont_helpdesk = array_merge($cont_helpdesk,$other_contacts);
    		}
			$msg = "$this->name has responded to question with ticket number $ticket_number";
			$this->broadcast("Alert Helpdesk",$cont_helpdesk,$msg);

			//search person asked question
			$rapidpro_id = $this->search_by_ticket($ticket_number);
			if($rapidpro_id) {
				$this->broadcast("Send Answer",array($rapidpro_id),$this->answer);
			}
		}

	}

	function save_answer($ticket_number) {
		$collection = (new MongoDB\Client)->{$this->database}->answers;
	    $date = date("Y-m-d\TH:m:s");
	    $insertOneResult = $collection->insertOne([	
	    											"ticket_number"=>$ticket_number,
	                                                "answer"=>$this->answer,
	                                                "globalid"=>$this->globalid,
	                                                "rapidpro_id"=>$this->rp_id,
	                                                "name"=>$this->name,
	                                                "phone"=>$this->phone,
	                                                "openHimTransactionID"=>$this->openHimTransactionID,
	                                                "date"=>$date
	                                              ]);
	    error_log("Answer saved to database with id ".$insertOneResult->getInsertedId());
	}

	public function search_by_ticket($ticket_number) {
		$collection = (new MongoDB\Client)->{$this->database}->questions;
		$data = $collection->findOne(array("ticket_number"=>$ticket_number));
		$rapidpro_id = $data["rapidpro_id"];
		return $rapidpro_id;
	}

	public function str_replace_first($search, $replace, $subject) {
	    $pos = strpos($subject, $search);
	    if ($pos !== false) {
	        return substr_replace($subject, $replace, $pos, strlen($search));
	    }
	    return $subject;
	}
}

$headers = getallheaders();
$openHimTransactionID = $headers["X-OpenHIM-TransactionID"];
ob_start();
$size = ob_get_length();
http_response_code(200);
header("Content-Encoding: none");
header("Content-Length: {$size}");
header("Connection: close");
ob_end_flush();
ob_flush();
flush();
if(session_id())
session_write_close();

require_once("config.php");
require_once("openHimConfig.php");
require_once __DIR__ . "/vendor/autoload.php";
$name = $_REQUEST["name"];
$phone = $_REQUEST["phone"];
$rp_id = $_REQUEST["rp_id"];
$globalid = $_REQUEST["globalid"];
$content = $_REQUEST["content"];
$category = $_REQUEST["category"];


if($category == "ask") {
	$askObj = new ask($name,$phone,$rp_id,$globalid,$content,$database,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$rapidpro_token,$rapidpro_url,$helpdesk_group);
	error_log("Here");
	$askObj->send_question();
}

else if($category == "answer") {
	$answerObj = new answer($name,$phone,$rp_id,$globalid,$content,$database,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$rapidpro_token,$rapidpro_url,$helpdesk_group);
	$answerObj->send_answer();
}
?>