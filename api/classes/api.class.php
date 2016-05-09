<?php

 include_once "../classes/mysqlData.class.php";
 include_once "../functions.php";
 include_once "../classes/person.class.php";
 include_once "../classes/company.class.php";
 include_once "../classes/address.class.php";
 include_once "../classes/subscription.class.php";
 include_once "../classes/payment.class.php";
 
  include_once "../classes/archive.class.php";		//archive class
 
 include_once "../FileMagDemo.php";
 include_once "../classes/note.class.php";
 include_once "../classes/product.class.php"; 
 
 include_once ("../classes/group.class.php");
 
 include_once ("../classes/mailing.class.php"); 
 
 include_once ("../connect_fm.php");
 include_once ("../FileMaker.php");
 
  include_once ("../classes/salesPerson.class.php");

  include_once ("../classes/helper.class.php");  
   
 include_once ("../classes/user.class.php"); 
 include_once ("../classes/dbreport.class.php");
 include_once ("../classes/master_entry.class.php"); 
 
/******************************************************************

 Create PDO connection to Main server

****************************************************************/

global $host, $database, $username, $password;

include_once ("../defaults.php"); // Contains values for $host, $database, $username, $password - can be changed for testbed

global $connect;

$connect = new PDO('mysql:host='.$host.';dbname='.$database.';charset=utf8', $username, $password);


/******************************************************************

	Setup all classes

****************************************************************/



global $data;
$data = new Mysql();


abstract class API
{
    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: verb
     * An optional additional descriptor about the endpoint, used for things that can
     * not be handled by the basic methods. eg: /files/process
     */
    protected $verb = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = Array();
    /**
     * Property: file
     * Stores the input of the PUT request
     */
     protected $file = Null;

    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct($request) {
        header("Access-Control-Allow-Orgin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");

        $this->args = explode('/', rtrim($request, '/'));
     
        $this->endpoint = array_shift($this->args);
        if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
            $this->verb = array_shift($this->args);
        }
  
        $this->method = $_SERVER['REQUEST_METHOD'];
        // var_dump($this);
        if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
            if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
                $this->method = 'DELETE';
            } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
                $this->method = 'PUT';
            } else {
                throw new Exception("Unexpected Header");
            }
        }

        switch($this->method) {
        case 'DELETE':
        case 'POST':
            $this->request = $this->_cleanInputs($_POST);
            break;
        case 'GET':
            $this->request = $this->_cleanInputs($_GET);
            break;
        case 'PUT':
            $this->request = $this->_cleanInputs($_GET);
            $this->file = file_get_contents("php://input");
            break;
        default:
            $this->_response('Invalid Method', 405);
            break;
        }
    }
    
    public function get($args){    				
    		$subsFM_No = $args[0];
			
			$subscription = new Subscription(0,$subsFM_No);
			$person = new Person(0,$subscription->person_no);			
			$company = new Company($person->companyNo);
							
			$obj_merged = (array) array_merge((array) $subscription, (array) $person, (array) $company);
			
    		return $obj_merged;
    }


// --------------------------------------------------------------------
//
// HANDLE ALL EMAIL INCOMING FUNCTIONS
//
// --------------------------------------------------------------------
    
    public function email($args){
    		
    		$action = $args[1];
    		
    		if($action == 'open'){
					
					// Send blank_pixel.gif so there's no image not found symbol 
					header("Content-type: image/gif");
					$img = imagecreatefromgif('email/blank_pixel.gif');
					imagegif($img);
					
			
					// Save results
					global $connect;
					
					
					/**
					**	get the location from the ip address and save it to the database 
					**/
					
					$resultCountry = Mailer::get_open_geoLocation($_SERVER['REMOTE_ADDR']);	//get the country from the ip address of the user	
					
					foreach($resultCountry as $key=>$value){			
						if($key == "geoplugin_countryName")
							$country = $value;						
					}
		
					$insert = "INSERT INTO
									email_opens 
								(		
									person_id,	
									email_id,					
									mailing_list_id,
									user_IP	,
									country		
								) 
								VALUES
								(
									:person_id,
									:email_id,
									:mailing_list_id,
									:user_IP,
									:country
								)";	
				
					$data = $connect->prepare($insert);
			
					$save_data = array( 
											':person_id' => $args[2],
											':email_id' => $args[3],
											':mailing_list_id'	=> $args[4],
											':user_IP' => $_SERVER['REMOTE_ADDR'],
											':country' =>$country
								);
		
					$result = $data->execute($save_data);
			}
			
			if($action == 'click'){
			
					global $connect, $data;
			
					$query = "SELECT * FROM email_links WHERE id=".$args[2];

			
					$result = $data->getRecordArray($query);
// 					var_dump($result);
			
					if($args[3]){
						
						$insert = "INSERT INTO
										email_clicks 
									(		
										person_id,	
										link_id,					
										user_IP			
									) 
									VALUES
									(
										:person_id,
										:link_id,
										:user_IP
									)";	
					
						$data = $connect->prepare($insert);
				
						$save_data = array( 
												':person_id' => $args[3],
												':link_id' => $args[2],
												':user_IP' => $_SERVER['REMOTE_ADDR']
									);
		
						$done = $data->execute($save_data);
					}
// 					echo 'Would redirect to:'.$result[0]['link'];
					header ('Location: '.$result[0]['link']);
					
			}
			
			if($action == 'unsub'){				
				
				// Save unsub result
					global $connect;
		
					$insert = "INSERT INTO
									email_unsubs 
								(		
									person_id,	
									email_id,					
									mailing_list_id,
									user_IP			
								) 
								VALUES
								(
									:person_id,
									:email_id,
									:mailing_list_id,
									:user_IP
								)";	
		//		echo $insert;
					$data = $connect->prepare($insert);
			
					$save_data = array( 
											':person_id' => $args[2],
											':email_id' => $args[3],
											':mailing_list_id' => $args[4],
											':user_IP' => $_SERVER['REMOTE_ADDR']
								);
		
					$result = $data->execute($save_data);
					
				//remove email address from the mailing list
				$groupObj = new Group($args[4]);			
				
				$deleteFromGroup = $groupObj->delete_member_from_group($args[2]);
				
//				echo 'address Location: http://192.168.0.3/crm/unsubscribe.html'; 
				header ('Location: http://unionpress.co.uk/crm/unsubscribe.html');	
				//header ('Location: http://80.87.30.113/crm/unsubscribe.html');	
					
			}
    }
    
    	
     public function insert($args){
    		
			
			if(isset($_POST) && $_POST['MC_action'] == 'subs_return'){
			
//							var_dump($_POST);
    					
							//include '../save_subs.php';
							
							include '../models/saveSubs.php';
    		
    						subscriptionData($_POST); // run from save_subs_MQ.php
			}
			
    		//return TRUE;
    	
    }
	
	//function to get sub details on basis of publication and subscriber email
	public function sub_details($args){		
			
		if($args[0] != "" && $args[1] != ""){	
			
			$obj_temp_subs = new Subscription(0,0,$args[1],0,0);	//getting person no from subs on basis of email

			//check if subs details found in subscription database
			if($obj_temp_subs->person_no){

				$objSubs = new Subscription(0,0,'',$obj_temp_subs->person_no,$args[0]); //getting actual subscription details						
				//add the login details of the user in the crm online login table
				//get the login user id/ person no
				
				$person_no = $objSubs->person_no; 
//							echo $person_no; 
				$objUser = new User();
				
				//adds the login details only if people no is found
				if($person_no)
					$objUser->addOnlineLogin($person_no); 		
			
				//setting subscription is active on basis of end date to current date
				if(date("Y-m-d",strtotime($objSubs->end_date)) > date("Y-m-d")){			
					$objSubs->IsActive = "yes"; //setting property to yes if active
				}							
				return $objSubs; //returning subscription details	
			}
			else 			
				return NULL;			
		}
		else{
			return NULL;
		}
	}
    
	
	//function to log online activity of subscriber in crm
	public function log_subscriber_activity($args)
	{
		if(isset($_POST) && $_POST['action'] == 'log'){			
				$objUser = new user();
				
				$return = $objUser->add_online_user_activity($_POST);								
			}
	}
	
	
	//added new person in crm
	public function insert_company_person($args)
	{				
		if(isset($_POST) && $_POST['action'] == 'new_user'){			
						
			$companyData = array("company_name" =>$_POST["user_company_name"],							
								"telephone" =>$_POST['user_telephone'],
								"email" =>"",
								"address1" =>$_POST['user_address1'],
								"address2" =>$_POST['user_address2'],
								"address3" =>$_POST['user_address3'],
								"address4" =>$_POST['user_address4'],
								"country" =>$_POST['user_country'],
								"address5" =>$_POST['user_postcode'],
								"web" =>$_POST['website'],
								"source" =>"WEBSITE REPORT",
								"entered_by" =>'8',
								"group" => '922');
		
					
		//address array for address class when adding new address
		$address_array = array("address1" =>$_POST['user_address1'],
								"address2" =>$_POST['user_address2'],
								"address3" =>$_POST['user_address3'],
								"city" =>$_POST['user_address4'],
								"country" =>$_POST['user_country'],
								"postcode" =>$_POST['user_postcode'],						
								'date_added' => date('Y-m-d'));
					
		$peopleData=array("firstname" => $_POST["user_first_name"],
							"lastname" => $_POST["user_last_name"],
							"email" => $_POST["user_email"],
							"jobTitle" => $_POST["user_job_title"],						
							'modifydate' => date("Y-m-d"),
							"group" => '922');			
							
		$objCompany = new Company();
		$objCompany->add_new_company($companyData); //adding new company
		
		$objAddress = new Address();
		$objAddress->add_new_address($address_array,0,$objCompany->company_no); //adding new address to company added
		
		$objPeople = new Person();
		$peopleData["companyNo"] = $objCompany->company_no; //assigning companyNo to person details
		$objPeople->add_new_person($peopleData); //adding new person to the company added
				
		}
	}
	
	//add DB REPORT sale in CRM
	public function insert_dbreport($args){		
		if(isset($_POST) && $_POST['action'] == 'new_report'){			
			//echo "call received inside new_report";
			$objReport = new DBReport();
			$objReport->save($_POST);			
		}				
	}
	
	
	//add SB MASTERS in CRM
	public function insert_master_entry($args){
		if(isset($_POST) && $_POST['action'] == 'master_entry'){			

			$objMaster = new MasterEntry();						
			$objMaster->arr_temp_company_details = $_POST["company_details"][0];				  
			$objMaster->arr_meta_key_value =  $_POST["entry_details"];
			$objMaster->save();						
		}				
	}
	
	
	public function report(){
			
			$date = date("Y-m-d");
//			echo ' date is '.$date;

			$record = new Mysql(); 
						
			$query = "SELECT * FROM emails WHERE update_date='".$date."' AND status='sent'"; // changed from date(creation_date) to update_date
//			echo $query;
			$result = $record->getRecordArray($query); 
			
			$reportArray = array(); 
			foreach($result as $column){
				$campaignTitle = $column['email_subject'];
				$mailer_id = $column['email_id']; 
				
				//echo $campaignTitle; 
				
				$group_id = $column['mailing_list_id'];	
				
				$sentList = "SELECT name FROM groups WHERE group_id='".$group_id."'";
				$campaignList = $record->getRecordArray($sentList);
				
				foreach($campaignList as $list){
					$sentTo = $list['name']; 					
				}
				
				$mailerObj = new Mailer($mailer_id);
				
				$receipent =  $mailerObj->get_receipent_count();
				$totalOpen = $mailerObj->get_all_email_open_count();
				$totalClicks = $mailerObj->get_email_clicks();
				$totalUnsubs = $mailerObj->get_all_email_unsub_count();
				$totalComplaint = $mailerObj->get_all_email_complaint_count();
				$totalBounces = $mailerObj->get_all_email_bounce_count();
				
				
				$a = $campaignTitle.' '.$sentTo.' '.$receipent.' '.$totalOpen.' '.$totalClicks.' '.$totalBounces.' '.$totalUnsubs.' '.$totalComplaint.' '.'CRM '; 
				$reportArray[] = array("Title"=>$campaignTitle, "sentList"=>$sentTo, "receipentNo"=>$receipent, "open"=>$totalOpen, "clicks"=>$totalClicks, "bounces"=>$totalBounces,
										"unsubs"=>$totalUnsubs,"complaints"=>$totalComplaint, "platform"=>"CRM");
				 
			}
			return $reportArray;
		
	}
	
    public function processAPI() {
        if ((int)method_exists($this, $this->endpoint) > 0) {
            return $this->_response($this->{$this->endpoint}($this->args));
        }
        return $this->_response("No Endpoint: $this->endpoint", 404);
    }

    private function _response($data, $status = 200) {
     //   header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
        return json_encode($data);
    }

    private function _cleanInputs($data) {
        $clean_input = Array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }

    private function _requestStatus($code) {
        $status = array(  
            200 => 'OK',
            404 => 'Not Found',   
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        ); 
        return ($status[$code])?$status[$code]:$status[500]; 
    }
}


class MyAPI extends API
{
    protected $User;

    public function __construct($request, $origin) {
        parent::__construct($request);
/*
        // Abstracted out for example
        $APIKey = new Models\APIKey();
        $User = new Models\User();

        if (!array_key_exists('apiKey', $this->request)) {
            throw new Exception('No API Key provided');
        } else if (!$APIKey->verifyKey($this->request['apiKey'], $origin)) {
            throw new Exception('Invalid API Key');
        } else if (array_key_exists('token', $this->request) &&
             !$User->get('token', $this->request['token'])) {

            throw new Exception('Invalid User Token');
        }
*/
        $this->User = $User;
    }

    /**
     * Example of an Endpoint
     */
     protected function example() {
        if ($this->method == 'GET') {
            return "Your name is " . $this->User->name;
        } else {
            return "Only accepts GET requests";
        }
     }
 }

?>