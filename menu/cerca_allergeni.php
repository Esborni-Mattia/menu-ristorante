<?php
require 'connessione.php';

$q = $_GET['q'] ?? '';
if ($q == '') exit;

$pdo = new PDO($conn_str, $conn_usr, $conn_psw);

$sql = "SELECT nome FROM allergeni WHERE nome LIKE :q LIMIT 5";
$stm = $pdo->prepare($sql);
$stm->execute([':q' => "%$q%"]);

foreach ($stm as $row) {
    $nome = htmlspecialchars($row['nome']);
    echo "<div class='w3-padding w3-hover-light-grey' onclick=\"scegliAllergene('$nome')\">$nome</div>";
}
?>