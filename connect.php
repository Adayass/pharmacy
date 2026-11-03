<?php
$servername = "localhost";
$username = "root";
$password="";


try{
    $db = new PDO("mysql:host=$servername;dbname=4776357_pharmacy" , $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "connection successful !";
}

catch(PDOException $e){
    echo "connection faild :". $e->getMessage();

}


?>