<?php
require 'connessione.php';

try {
    $conn = new PDO($conn_str . ';charset=utf8', $conn_usr, $conn_psw);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione fallita: " . $e->getMessage());
}

$q = $_GET['q'] ?? '';

// MODIFICA IMPORTANTE: Cerca solo all'inizio della parola
$q = "$q%"; 

$sql = "SELECT nome FROM allergene WHERE nome LIKE :q ORDER BY nome ASC LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':q', $q, PDO::PARAM_STR);
$stmt->execute();

$risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($risultati) > 0) {
    foreach ($risultati as $row) {
        echo '<div onclick="scegliAllergene(\'' . addslashes($row['nome']) . '\')">' 
             . htmlspecialchars($row['nome']) 
             . '</div>';
    }
}
?>