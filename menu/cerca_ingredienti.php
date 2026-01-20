<?php
require 'connessione.php';

try {
    // Assicurati che le variabili di connessione ($conn_str, $conn_usr, $conn_psw) siano definite in connessione.php
    $conn = new PDO($conn_str . ';charset=utf8', $conn_usr, $conn_psw);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione fallita: " . $e->getMessage());
}

$q = $_GET['q'] ?? '';

// MODIFICA IMPORTANTE:
// Prima era "%$q%" (cerca ovunque). 
// Ora è "$q%" (cerca solo all'INIZIO della parola).
$q = "$q%"; 

// Ho aggiunto ORDER BY nome ASC per avere i risultati in ordine alfabetico
$sql = "SELECT nome FROM ingrediente WHERE nome LIKE :q ORDER BY nome ASC LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':q', $q, PDO::PARAM_STR);
$stmt->execute();

$risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($risultati) > 0) {
    foreach ($risultati as $row) {
        // Usa addslashes per evitare problemi con apici nei nomi (es. l'olio)
        echo '<div onclick="scegliIngrediente(\'' . addslashes($row['nome']) . '\')">' 
             . htmlspecialchars($row['nome']) 
             . '</div>';
    }
} else {
    // Opzionale: messaggio se non trova nulla
    // echo '<div style="color: #999; cursor: default;">Nessun ingrediente trovato</div>';
}
?>