<?php
require 'connessione.php';

try {
    $conn = new PDO($conn_str . ';charset=utf8', $conn_usr, $conn_psw);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione fallita: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recupero dati dal form
    $nome = trim($_POST['nome']);
    $prezzo = floatval($_POST['prezzo']);
    $tipologia = $_POST['tipologia'] ?? '';

    // Prendo id_categoria dal nome della categoria
    $stmtCat = $conn->prepare("SELECT id_categoria FROM categoria WHERE nome = :nome");
    $stmtCat->execute([':nome' => $tipologia]);
    $resCat = $stmtCat->fetch(PDO::FETCH_ASSOC);
    $id_categoria = $resCat['id_categoria'] ?? 1; // default a 1 se non trovato

    // Controllo se il prodotto esiste già (stesso nome + stessa categoria)
    $stmtCheck = $conn->prepare("
        SELECT id_prodotto 
        FROM prodotto 
        WHERE LOWER(nome) = LOWER(:nome)
        AND id_categoria = :id_categoria
        LIMIT 1
    ");
    $stmtCheck->execute([
        ':nome' => $nome,
        ':id_categoria' => $id_categoria
    ]);

    if ($stmtCheck->fetch()) {
        // Prodotto già esistente → interrompo
        header("Location: inserimento.php?errore=prodotto_esistente");
        exit;
    }

    // Inserisco prodotto
    $stmtProd = $conn->prepare("INSERT INTO prodotto (nome, prezzo, id_categoria) VALUES (:nome, :prezzo, :id_categoria)");
    $stmtProd->execute([
        ':nome' => $nome,
        ':prezzo' => $prezzo,
        ':id_categoria' => $id_categoria
    ]);
    $id_prodotto = $conn->lastInsertId();

    // Se è pizza, gestisco ingredienti e allergeni
    if (strtolower($tipologia) === 'pizza') {

        // --- Ingredienti ---
        if (!empty($_POST['ingredienti'])) {
            $ingredienti = array_map('trim', explode(",", $_POST['ingredienti']));
            foreach ($ingredienti as $ing) {
                // Verifico se esiste
                $stmtIng = $conn->prepare("SELECT id_ingrediente FROM ingrediente WHERE nome = :nome");
                $stmtIng->execute([':nome' => $ing]);
                $resIng = $stmtIng->fetch(PDO::FETCH_ASSOC);

                if ($resIng) {
                    $id_ingrediente = $resIng['id_ingrediente'];
                } else {
                    // Inserisco nuovo ingrediente
                    $stmtNew = $conn->prepare("INSERT INTO ingrediente (nome) VALUES (:nome)");
                    $stmtNew->execute([':nome' => $ing]);
                    $id_ingrediente = $conn->lastInsertId();
                }

                // Collego prodotto e ingrediente
                $stmtProdIng = $conn->prepare("INSERT INTO prodotto_ingrediente (id_prodotto, id_ingrediente) VALUES (:id_prodotto, :id_ingrediente)");
                $stmtProdIng->execute([
                    ':id_prodotto' => $id_prodotto,
                    ':id_ingrediente' => $id_ingrediente
                ]);
            }
        }

        // --- Allergeni ---
        if (!empty($_POST['allergeni'])) {
            $allergeni = array_map('trim', explode(",", $_POST['allergeni']));
            foreach ($allergeni as $all) {
                // Verifico se esiste
                $stmtAll = $conn->prepare("SELECT id_allergene FROM allergene WHERE nome = :nome");
                $stmtAll->execute([':nome' => $all]);
                $resAll = $stmtAll->fetch(PDO::FETCH_ASSOC);

                if ($resAll) {
                    $id_allergene = $resAll['id_allergene'];
                } else {
                    // Inserisco nuovo allergene
                    $stmtNewAll = $conn->prepare("INSERT INTO allergene (nome) VALUES (:nome)");
                    $stmtNewAll->execute([':nome' => $all]);
                    $id_allergene = $conn->lastInsertId();
                }

                // Collego ingrediente e allergene per tutti gli ingredienti di questo prodotto
                $stmtIngred = $conn->prepare("SELECT id_ingrediente FROM prodotto_ingrediente WHERE id_prodotto = :id_prodotto");
                $stmtIngred->execute([':id_prodotto' => $id_prodotto]);
                $ingredientiProd = $stmtIngred->fetchAll(PDO::FETCH_ASSOC);

                foreach ($ingredientiProd as $ingProd) {
                    $stmtIngAll = $conn->prepare("INSERT IGNORE INTO ingrediente_allergene (id_ingrediente, id_allergene) VALUES (:id_ingrediente, :id_allergene)");
                    $stmtIngAll->execute([
                        ':id_ingrediente' => $ingProd['id_ingrediente'],
                        ':id_allergene' => $id_allergene
                    ]);
                }
            }
        }
    }

    // Redirect alla lista prodotti
    header("Location: index.php");
    exit;
}
?>
