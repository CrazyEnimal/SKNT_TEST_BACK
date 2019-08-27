<?php
    // echo phpinfo();
    include_once("db_cfg.php");
    include_once("./lib/DataBaseTarifs.php");

    $result = new StdClass();
    $db = DataBaseTarifs::getInstance(DB_HOST, DB_NAME,  DB_USER, DB_PASSWORD);
    $uri = strtok($_SERVER['REQUEST_URI'], '?');
    $url = substr($uri,1);

    $requestArray = explode("/",$uri);
    $requestMethod = $_SERVER["REQUEST_METHOD"];
    $user_id = (int)(in_array("users",$requestArray))?$requestArray[array_search("users",$requestArray)+1]:null;
    $service_id = (int)(in_array("services",$requestArray))?$requestArray[array_search("services",$requestArray)+1]:null;
    $methodFor = end($requestArray);

    if(!$db){
        $result->result = "error";
        $result->error_message = "Не удалось подсоедениться к базе данных";
        die(json_encode($result));
    }

    if($user_id > 0 && $service_id > 0) {
        if($requestMethod == "GET" && $methodFor == "tarifs"){
            $query = $db->getTarifs($user_id,$service_id);
            if($query && !isset($query->error)){
                $result->result = "ok";
                $result->tarifs[] = $query;
            } else {
                $result->result = "error";
                $result->error_message = $query->message;                                                 
            }
        } elseif ($requestMethod == "PUT" && $methodFor == "tarif"){
            $request = json_decode(file_get_contents("php://input"));
            $query = $db->putTarif($user_id,$service_id,(int)$request->tarif_id);
            if($query && !isset($query->error)){
                $result->result = "ok";    
            } else {
                $result->result = "error";
                $result->error_message = $query->message;    
            }
        } else {
            $result->result = "error";
            $result->error_message = "Неверно указан метод! {$requestMethod} {$methodFor}";
                           
        }
    }

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    
    if(!json_last_error()){
        die($json);
    } else {
        die(json_last_error_msg());
    }
    