<?php
// cerca_ingredienti.php
require 'connessione.php';

try {
    // creo PDO con charset UTF-8
    $conn = new PDO($conn_str . ';charset=utf8', $conn_usr, $conn_psw);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione fallita: " . $e->getMessage());
}

// prendo il termine di ricerca
$q = $_GET['q'] ?? '';
$q = "%$q%";  // già pronto per LIKE

$sql = "SELECT nome FROM ingrediente WHERE nome LIKE :q LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':q', $q, PDO::PARAM_STR);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<div onclick="scegliIngrediente(\'' . addslashes($row['nome']) . '\')">' 
         . htmlspecialchars($row['nome']) 
         . '</div>';
}
