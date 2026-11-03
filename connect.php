<?php
$servername = "pharmacyinventory-pims.g.aivencloud.com";
$username = "avnadmin";
$password="AVNS_i_8rycbSeTXKyVFFeS1";
$port = "24966"


try{
    $db = new PDO("mysql:host=$servername;$port=$port; dbname=defaultdb" , $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "connection successful !";
}

catch(PDOException $e){
    echo "connection faild :". $e->getMessage();

}


?>
