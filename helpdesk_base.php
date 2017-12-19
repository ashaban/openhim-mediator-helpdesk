<?php
require ("openHimConfig.php");
require ("openHimUtilities.php");
class helpdesk_base extends openHimUtilities {
  function __construct($rapidpro_token,$rapidpro_url,$ohimApiHost,$ohimApiUser,$ohimApiPassword) {
    $this->rapidpro_token = $rapidpro_token;
    $this->rapidpro_host = $rapidpro_url;
  }

  public function broadcast($subject="",$contacts_uuid = array(),$msg) {
    $url = $this->rapidpro_host."api/v2/broadcasts.json";
    $header = Array(
                     "Content-Type: application/json",
                     "Authorization: Token $this->rapidpro_token",
                   );
    $broadcast_data = array();
    foreach($contacts_uuid as $uuid) {
      $post_data = '{ "contacts": ["'.$uuid.'"], "text": "'.$msg.'" }';
      $this->exec_request($subject,$url,"","","POST",$post_data,$header);
    }
  }

  public function get_contacts_in_grp ($group_name) {
    $group_uuid = $this->get_group_uuid ($group_name);
    if($group_uuid == "")
    return array();
    $url = $this->rapidpro_host."api/v2/contacts.json?group=$group_uuid";
    $header = Array(
                         "Content-Type: application/json",
                         "Authorization: Token $this->rapidpro_token"
                   );
    $res=  $this->exec_request("Getting Rapidpro Contacts In A Group",$url,"","","GET","",$header);
    $res = json_decode($res,true);
    if(count($res["results"]) > 0){
      foreach($res["results"] as $conts) {
        $contact_uuids[] = $conts["uuid"];
      }
    }
    return $contact_uuids;
  }

  public function get_group_uuid ($group_name) {
    $url = $this->rapidpro_host."api/v2/groups.json?name=$group_name";
    $header = Array(
                         "Content-Type: application/json",
                         "Authorization: Token $this->rapidpro_token"
                   );
    $res=  $this->exec_request("Getting Raidpro Group UUID",$url,"","","GET","",$header);
    $res = json_decode($res,true);
    if(count($res["results"]) > 0){
      return $res["results"][0]["uuid"];
    }
  }

  public function exec_request($request_name,$url,$user,$password,$req_type,$post_data,$header = Array("Content-Type: text/xml"),$get_header=false) {
    if($request_name == "") {
      error_log("Name of the request is missing");
      return false;
    }

    $curl =  curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_HEADER, true);
    if($req_type == "POST") {
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    else if($req_type == "PUT") {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    if($user or $password)
      curl_setopt($curl, CURLOPT_USERPWD, $user.":".$password);
    $curl_out = curl_exec($curl);
    if ($err = curl_errno($curl) ) {
      error_log("An error occured while accessing url ".$url .". CURL error number ".$err);
      return false;
    }

    //Orchestrations
    //prepare data for orchestration
    $status_code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
    if($status_code >= 400 && $status_code <= 600)
    $this->transaction_status = "Completed with error(s)";
    $beforeTimestamp = date("Y-m-d G:i:s");
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($curl_out, 0, $header_size);
    $body = substr($curl_out, $header_size);
    //implement in case header is missing
    //send orchestration
    $newOrchestration = $this->buildOrchestration($request_name,$beforeTimestamp,$req_type,$url,$post_data,$status_code,$header,$body);
    array_push($this->orchestrations, $newOrchestration);
    //End of orchestration

    curl_close($curl);
    if($get_header === false) {
      return $body;
    }
    else
    return $curl_out;
  }
}
?>