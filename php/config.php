<?php
// Configuration de la Base de Données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');  // MAMP default password
define('DB_NAME', 'bettermaurion');

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Définir le charset UTF-8
$conn->set_charset("utf8mb4");
    