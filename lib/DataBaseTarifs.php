<?php
class DataBaseTarifs
{
    private static $_instance = null;
    private static $DB_HOST = '';
    private static $DB_NAME = '';
    private static $DB_USER = '';
    private static $DB_PASS = '';
    
    private function __construct () {
        
        if(self::$_instance === null) {
            $db = new mysqli(self::$DB_HOST, self::$DB_USER, self::$DB_PASS, self::$DB_NAME);
            $db->set_charset('utf8');
            if(empty($db->connect_errno)) {
                self::$_instance = $db;
            } else {
                $result = new StdClass();
                $result->result = "error";
                $result->error_message = "Не удалось подсоедениться к базе данных";
                die(json_encode($result));
            }
        }
        return self::$_instance;
    }
    private function __clone () {}
    private function __wakeup () {}
    private static function setDB_HOST($DB_HOST){
        self::$DB_HOST = $DB_HOST;
    }
    private static function setDB_NAME($DB_NAME){
        self::$DB_NAME = $DB_NAME;
    }
    private static function setDB_USER($DB_USER){
        self::$DB_USER = $DB_USER;
    }
    private static function setDB_PASS($DB_PASS){
        self::$DB_PASS = $DB_PASS;
    }
       
    public static function getInstance( $dbHost, $dbName, $dbUser, $dbPass ) {
        if (self::$_instance != null) {
            return self::$_instance;
        }
        if($dbHost != '' && $dbName != '' && $dbUser != '' && $dbPass != '') {
            self::setDB_HOST($dbHost);
            self::setDB_NAME($dbName);
            self::setDB_USER($dbUser);
            self::setDB_PASS($dbPass);
        }
        
        return new self;
    }

    private function GetNewPayDay($payPeriod){
        return strtotime("+ ".$payPeriod." month", strtotime(date("Y-m-d 00:00:00")));
    }

    public static function getTarifs($userID, $serviceID) {
        // SELECT * FROM tarifs tf1 LEFT JOIN tarifs tf2 ON tf1.tarif_group_id = tf2.tarif_group_id LEFT JOIN services srvc ON srvc.tarif_id = tf2.ID WHERE srvc.ID = 2 AND srvc.user_id=2;        
        $q = "SELECT tf1.ID, tf1.title, tf1.price, tf1.pay_period, tf1.speed, tf1.link FROM tarifs tf1 LEFT JOIN tarifs tf2 ON tf1.tarif_group_id = tf2.tarif_group_id LEFT JOIN services srvc ON srvc.tarif_id = tf2.ID WHERE srvc.ID = $serviceID AND srvc.user_id=$userID ORDER BY tf1.ID;";
        $query = self::$_instance->query($q);
        $error = self::$_instance->error;
        $info = self::$_instance->info;
        if($query && !$error && $query->num_rows > 0){

            $result = new StdClass();
            $i = 0;
            while( $row = $query->fetch_assoc() ){
                if($i == 0){
                    $result->title = $row["title"];
                    $result->link = $row["link"];
                    $result->speed = $row["speed"];
                }
                $newPayDay = self::GetNewPayDay($row["pay_period"]).date("O");
                $result->tarifs[] = array("ID" => (int)$row["ID"], "title" => $row["title"], "price" => $row["price"], "pay_period" => $row["pay_period"], "new_payday" => $newPayDay, "speed" => (int)$row["speed"]);
                $i++;
            }    
        } else {
            $result->error = $error;
            $result->message = "Ничего не найдено по вашему запросу";
        }
        $query->free_result;
        return $result; 
    }

    public static function putTarif($userID, $serviceID, $tarifID) {
        // Получаем наш сервис
        $result = new StdClass();
        $c = "SELECT * FROM services WHERE ID = $serviceID AND user_id=$userID;";
        $check = self::$_instance->query($c);
        $error = self::$_instance->error;
        if($error){
            $result->error = $error;
            $result->message = "Ошибка при поиске сервиса";
            return $result;
        } elseif($check->num_rows === 0) {            
            $result->error = $error;
            $result->message = "Не найнед запрашиваемый сервис";
            return $result;
        }
        $service = $check->fetch_assoc();
        $check->free_result;
        
        // Получаем группу тарифод для нашего срвиса
        $c = "SELECT tarif_group_id FROM tarifs WHERE ID=".$service['tarif_id'].";";
        $check = self::$_instance->query($c);
        $error = self::$_instance->error;
        if($error){
            $result->error = $error;
            $result->message = "Ошибка при запросе группы тарифов";
            return $result;
        } elseif($check->num_rows === 0) {            
            $result->error = $error;
            $result->message = "Не найден тариф для группы тарифов";
            return $result;
        }
        $check_tarif = $check->fetch_assoc();
        $check->free_result;      

        // получаем новый тариф и проверяем что он входит в ту-же группу,
        // если входит то выставляем новй payday
        $r = "SELECT pay_period, tarif_group_id FROM tarifs WHERE ID=$tarifID LIMIT 1";
        $request = self::$_instance->query($r);
        $error = self::$_instance->error;
        if(!$error && $request->num_rows > 0){
            $newTarif = $request->fetch_assoc();
            if($check_tarif['tarif_group_id'] == $newTarif['tarif_group_id']){
                $newPayDay = date("Y-m-d",self::GetNewPayDay($newTarif["pay_period"]));
            } else {
                $result->error = true;
                $result->message = "Тариф не соотвествует запрашиваемой группе";
                return $result;
                }
        } else {
            $result->error = $error;
            $result->message = "Ошибка при запросе нового тарифа";
            return $result;
    }
        $request->free_result;    
        
        // Обновляем сервис
        $q = "UPDATE services SET tarif_id=$tarifID, payday='$newPayDay' WHERE ID = $serviceID AND user_id=$userID;";
        $query = self::$_instance->query($q);
        $error = self::$_instance->error;
        if(!$error){
            $result = $query;
        } else {
            $result->error = $error;
            $result->message = "Ошибка при обновлении тарифа";
        }
        $query->free_result;
        return $result;         
    }
}