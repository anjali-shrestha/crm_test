<?php 

require_once 'classes/api.class.php'; 
include_once '../mysqlData.php';		


// Requests from the same server don't have a HTTP_ORIGIN header
if (!array_key_exists('HTTP_ORIGIN', $_SERVER)) {
    $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
}

try {
    $API = new MyAPI($_REQUEST['request'], $_SERVER['HTTP_ORIGIN']);
     
    $result = $API->processAPI();
    
    if($result != 'null')
		     echo $result; 

	//var_dump($result);
     
} catch (Exception $e) {
    echo json_encode(Array('error' => $e->getMessage()));
}





?>

