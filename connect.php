<?php
$servername = "pharmacyinventory-pims.g.aivencloud.com";
$username = "avnadmin";
$password = "AVNS_i_8rycbSeTXKyVFFeS1";
$port = "24966";

try {
    $dsn = "mysql:host=$servername;port=$port;dbname=defaultdb;charset=utf8mb4";
    
    // Options pour forcer le SSL requis par Aiven
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Désactive la vérification stricte du certificat si besoin
    ];

    // Vous pouvez aussi ajouter un tableau d'options vide ou avec SSL selon votre configuration Aiven
    $db = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Si Aiven demande un certificat, on peut l'activer ou configurer les options SSL ici
    ]);
    
} catch(PDOException $e) {
    die("connection faild :" . $e->getMessage());
}
?>
