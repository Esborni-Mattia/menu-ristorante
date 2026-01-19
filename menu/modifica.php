<?php
require 'connessione.php';

// Controllo parametri.
if (!isset($_POST['id']) || !isset($_POST['nome']) || !isset($_POST['prezzo']) || !isset($_POST['id_categoria'])){
    $msgErrore = 'Impossibile procedere con l\'aggiornamento: dati mancanti o incompleti.';
}
else{

    // Modifica.
    if ($_POST['prezzo'] == null) $_POST['prezzo'] = '';
    try {
        $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
        $descrizione = trim($_POST['descrizione'] ?? '');
        
        // ---- UPDATE PRODOTTO ----
        $sql = 'UPDATE prodotto SET nome=:n, prezzo=:p, descrizione=:desc, id_categoria=:cat WHERE id_prodotto=:id';
        $stm = $pdo->prepare($sql);

        $stm->bindparam(":id", $_POST['id']);
        $stm->bindparam(":n", $_POST['nome']);
        $stm->bindparam(":p", $_POST['prezzo']);
        $stm->bindparam(":desc", $descrizione);
        $stm->bindparam(":cat", $_POST['id_categoria']);
        $stm->execute();
        // Non usiamo più rowCount() per mostrare messaggio di successo

        $msgErrore = "nessun errore"; // ✅ Fix 1: messaggio positivo anche se il prodotto non è stato modificato

        // ---- GESTIONE COMPLETA INGREDIENTI E ALLERGENI ----
        if (isset($_POST['ingredienti'])) {
            // 1. Preparo l'elenco dei nuovi ingredienti
            $ingredienti = array_map('trim', array_filter(explode(",", $_POST['ingredienti'])));

            // 2. Recupero gli ingredienti attuali del prodotto
            $stmCurrent = $pdo->prepare("SELECT i.id_ingrediente, i.nome 
                                         FROM ingrediente i
                                         JOIN prodotto_ingrediente pi ON i.id_ingrediente = pi.id_ingrediente
                                         WHERE pi.id_prodotto = :id");
            $stmCurrent->execute([':id' => $_POST['id']]);
            $currentIngredients = $stmCurrent->fetchAll(PDO::FETCH_ASSOC);

            // 3. Rimuovo ingredienti eliminati
            foreach ($currentIngredients as $ing) {
                if (!in_array($ing['nome'], $ingredienti)) {
                    $id_ingrediente = $ing['id_ingrediente'];

                    // Elimino collegamento prodotto-ingrediente
                    $stmDel = $pdo->prepare("DELETE FROM prodotto_ingrediente 
                                             WHERE id_prodotto = :id AND id_ingrediente = :iding");
                    $stmDel->execute([':id' => $_POST['id'], ':iding' => $id_ingrediente]);

                    // Controllo se l'ingrediente è collegato ad altri prodotti
                    $stmCheck = $pdo->prepare("SELECT COUNT(*) FROM prodotto_ingrediente WHERE id_ingrediente = :iding");
                    $stmCheck->execute([':iding' => $id_ingrediente]);
                    if ($stmCheck->fetchColumn() == 0) {
                        // Se nessun prodotto lo usa, elimino l'ingrediente
                        $pdo->prepare("DELETE FROM ingrediente WHERE id_ingrediente = :iding")
                            ->execute([':iding' => $id_ingrediente]);
                        // Elimino anche eventuali allergeni collegati
                        $pdo->prepare("DELETE FROM ingrediente_allergene WHERE id_ingrediente = :iding")
                            ->execute([':iding' => $id_ingrediente]);
                    }
                }
            }

            // 4. Aggiungo i nuovi ingredienti se non già presenti
            foreach ($ingredienti as $ing) {
                if (empty($ing)) continue;

                // Verifico se esiste
                $stmIng = $pdo->prepare("SELECT id_ingrediente FROM ingrediente WHERE nome = :nome");
                $stmIng->execute([':nome' => $ing]);
                $resIng = $stmIng->fetch(PDO::FETCH_ASSOC);

                if ($resIng) {
                    $id_ingrediente = $resIng['id_ingrediente'];
                } else {
                    // Inserisco nuovo ingrediente
                    $stmNew = $pdo->prepare("INSERT INTO ingrediente (nome) VALUES (:nome)");
                    $stmNew->execute([':nome' => $ing]);
                    $id_ingrediente = $pdo->lastInsertId();
                }

                // Collego prodotto e ingrediente se non già collegato
                $stmCheckLink = $pdo->prepare("SELECT COUNT(*) FROM prodotto_ingrediente 
                                               WHERE id_prodotto = :id AND id_ingrediente = :iding");
                $stmCheckLink->execute([':id' => $_POST['id'], ':iding' => $id_ingrediente]);
                if ($stmCheckLink->fetchColumn() == 0) {
                    $stmProdIng = $pdo->prepare("INSERT INTO prodotto_ingrediente (id_prodotto, id_ingrediente) 
                                                 VALUES (:id_prodotto, :id_ingrediente)");
                    $stmProdIng->execute([':id_prodotto' => $_POST['id'], ':id_ingrediente' => $id_ingrediente]);
                }

                // ---- GESTIONE ALLERGENI ----
                if (isset($_POST['allergeni'])) {
                    $allergeniInput = array_map('trim', array_filter(explode(",", $_POST['allergeni'])));
                    // Recupero allergeni attuali dell'ingrediente
                    $stmAllCurrent = $pdo->prepare("SELECT a.nome FROM allergene a
                                                   JOIN ingrediente_allergene ia ON a.id_allergene = ia.id_allergene
                                                   WHERE ia.id_ingrediente = :iding");
                    $stmAllCurrent->execute([':iding' => $id_ingrediente]);
                    $allCurrent = $stmAllCurrent->fetchAll(PDO::FETCH_COLUMN);

                    // Aggiungo nuovi allergeni
                    foreach ($allergeniInput as $all) {
                        if (!in_array($all, $allCurrent)) {
                            // Verifico se allergene esiste
                            $stmAll = $pdo->prepare("SELECT id_allergene FROM allergene WHERE nome = :nome");
                            $stmAll->execute([':nome' => $all]);
                            $resAll = $stmAll->fetch(PDO::FETCH_ASSOC);

                            if ($resAll) {
                                $id_allergene = $resAll['id_allergene'];
                            } else {
                                // Creo nuovo allergene
                                $stmNewAll = $pdo->prepare("INSERT INTO allergene (nome) VALUES (:nome)");
                                $stmNewAll->execute([':nome' => $all]);
                                $id_allergene = $pdo->lastInsertId();
                            }

                            // Collego ingrediente e allergene
                            $pdo->prepare("INSERT INTO ingrediente_allergene (id_ingrediente, id_allergene) 
                                          VALUES (:iding, :idall)")
                                ->execute([':iding' => $id_ingrediente, ':idall' => $id_allergene]);
                        }
                    }

                    // Rimuovo allergeni non più presenti
                    foreach ($allCurrent as $all) {
                        if (!in_array($all, $allergeniInput)) {
                            $stmDelAll = $pdo->prepare("DELETE ia FROM ingrediente_allergene ia
                                                       JOIN allergene a ON ia.id_allergene = a.id_allergene
                                                       WHERE ia.id_ingrediente = :iding AND a.nome = :nome");
                            $stmDelAll->execute([':iding' => $id_ingrediente, ':nome' => $all]);
                        }
                    }
                }
            }
        }

    } catch (PDOException $e) {
        $msgErrore = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizze</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,...">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body>
    <!-- Banner -->
    <div class="w3-container w3-light-blue w3-xlarge">
        <p><i class="fa fa-users"></i> Pizza</p>
    </div>

    <div class="w3-container w3-margin">
        <div class="w3-card-4 w3-center w3-large w3-margin w3-padding-16">
            <div class="w3-margin">
                <p>
                <?php if ($msgErrore != "nessun errore"):?>
                    <?= $msgErrore?>
                <?php else:?>
                    Dati aggiornati
                <?php endif?>
                </p>
                <a href="." class="w3-button w3-block w3-green w3-padding-large">Torna all'elenco</a>
            </div>
        </div>
    </div>

</body>
</html>
