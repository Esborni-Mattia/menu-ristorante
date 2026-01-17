<?php
require 'connessione.php';

// Prende il parametro q passato dall'input
$q = $_GET['q'] ?? '';

if ($q == '') exit; // se vuoto, non fare nulla

$pdo = new PDO($conn_str, $conn_usr, $conn_psw);

// Query per trovare ingredienti che contengono il testo digitato
$sql = "SELECT nome FROM ingredienti WHERE nome LIKE :q LIMIT 5";
$stm = $pdo->prepare($sql);
$stm->execute([':q' => "%$q%"]);

// Restituisce ogni ingrediente come div cliccabile
foreach ($stm as $row) {
    $nome = htmlspecialchars($row['nome']); // sicurezza XSS
    echo "<div class='w3-padding w3-hover-light-grey' onclick=\"scegliIngrediente('$nome')\">$nome</div>";
}
