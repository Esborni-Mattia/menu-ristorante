<?php
// cerca_allergeni.php
require 'connessione.php';

try {
    $conn = new PDO($conn_str . ';charset=utf8', $conn_usr, $conn_psw);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione fallita: " . $e->getMessage());
}

$q = $_GET['q'] ?? '';
$q = "%$q%";

$sql = "SELECT nome FROM allergene WHERE nome LIKE :q LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':q', $q, PDO::PARAM_STR);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<div onclick="scegliAllergene(\'' . addslashes($row['nome']) . '\')">' 
         . htmlspecialchars($row['nome']) 
         . '</div>';
}
?>
