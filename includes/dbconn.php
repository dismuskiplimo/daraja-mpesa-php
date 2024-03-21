<?php
    define("DB_HOST","localhost");
    define("DB_PORT","3306");
    define("DB_USER","root");
    define("DB_PASSWORD","");
    define("DB_DATABASE","mpesa");

    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE, DB_USER, DB_PASSWORD);
        
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }