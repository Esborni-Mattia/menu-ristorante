<?php
require 'connessione.php';

// Inizializzo variabili vuote per il form
$id_prodotto = "";
$nome_val = "";
$prezzo_val = "";
$descrizione_val = "";
$categoria_val = "";
$ingredienti_val = "";
$allergeni_val = "";

$msgErrore = "";
$msgSuccesso = "";

try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ------------------------------------------------------------------
    // 1. GESTIONE AGGIORNAMENTO (POST)
    // ------------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Controllo campi obbligatori base
        if (!isset($_POST['id']) || !isset($_POST['nome']) || !isset($_POST['prezzo']) || !isset($_POST['id_categoria'])){
            $msgErrore = 'Dati mancanti.';
        } else {
            $id_prodotto = $_POST['id'];
            $nome_val = $_POST['nome'];
            $prezzo_val = $_POST['prezzo'];
            $categoria_val = $_POST['id_categoria'];
            $descrizione_val = trim($_POST['descrizione'] ?? '');
            
            // Mantengo i valori "grezzi" per il form in caso di ricaricamento
            $ingredienti_val = $_POST['ingredienti'] ?? '';
            $allergeni_val = $_POST['allergeni'] ?? '';

            // ---- UPDATE PRODOTTO ----
            $sql = 'UPDATE prodotto SET nome=:n, prezzo=:p, descrizione=:desc, id_categoria=:cat WHERE id_prodotto=:id';
            $stm = $pdo->prepare($sql);
            $stm->execute([
                ':n' => $nome_val,
                ':p' => $prezzo_val,
                ':desc' => $descrizione_val,
                ':cat' => $categoria_val,
                ':id' => $id_prodotto
            ]);

            $msgSuccesso = "Prodotto aggiornato con successo!";

            // ---- GESTIONE INGREDIENTI E ALLERGENI (Logica originale mantenuta) ----
            if (isset($_POST['ingredienti'])) {
                // 1. Preparo array nuovi ingredienti
                $ingredientiArr = array_map('trim', array_filter(explode(",", $_POST['ingredienti'])));

                // 2. Recupero ingredienti attuali
                $stmCurrent = $pdo->prepare("SELECT i.id_ingrediente, i.nome FROM ingrediente i
                                             JOIN prodotto_ingrediente pi ON i.id_ingrediente = pi.id_ingrediente
                                             WHERE pi.id_prodotto = :id");
                $stmCurrent->execute([':id' => $id_prodotto]);
                $currentIngredients = $stmCurrent->fetchAll(PDO::FETCH_ASSOC);

                // 3. Rimozione ingredienti tolti
                foreach ($currentIngredients as $ing) {
                    if (!in_array($ing['nome'], $ingredientiArr)) {
                        $id_ing_del = $ing['id_ingrediente'];
                        // Elimino relazione
                        $pdo->prepare("DELETE FROM prodotto_ingrediente WHERE id_prodotto = :id AND id_ingrediente = :iding")
                            ->execute([':id' => $id_prodotto, ':iding' => $id_ing_del]);
                        
                        // Pulizia orfani (se l'ingrediente non è usato da nessuno)
                        $stmCheck = $pdo->prepare("SELECT COUNT(*) FROM prodotto_ingrediente WHERE id_ingrediente = :iding");
                        $stmCheck->execute([':iding' => $id_ing_del]);
                        if ($stmCheck->fetchColumn() == 0) {
                            $pdo->prepare("DELETE FROM ingrediente WHERE id_ingrediente = :iding")->execute([':iding' => $id_ing_del]);
                            $pdo->prepare("DELETE FROM ingrediente_allergene WHERE id_ingrediente = :iding")->execute([':iding' => $id_ing_del]);
                        }
                    }
                }

                // 4. Aggiunta/Verifica nuovi ingredienti
                foreach ($ingredientiArr as $ingName) {
                    if (empty($ingName)) continue;

                    // Cerco ID ingrediente o lo creo
                    $stmIng = $pdo->prepare("SELECT id_ingrediente FROM ingrediente WHERE nome = :nome");
                    $stmIng->execute([':nome' => $ingName]);
                    $resIng = $stmIng->fetch(PDO::FETCH_ASSOC);

                    if ($resIng) {
                        $id_ingrediente = $resIng['id_ingrediente'];
                    } else {
                        $stmNew = $pdo->prepare("INSERT INTO ingrediente (nome) VALUES (:nome)");
                        $stmNew->execute([':nome' => $ingName]);
                        $id_ingrediente = $pdo->lastInsertId();
                    }

                    // Collego al prodotto
                    $pdo->prepare("INSERT IGNORE INTO prodotto_ingrediente (id_prodotto, id_ingrediente) VALUES (:id, :iding)")
                        ->execute([':id' => $id_prodotto, ':iding' => $id_ingrediente]);

                    // ---- GESTIONE ALLERGENI PER INGREDIENTE ----
                    if (isset($_POST['allergeni'])) {
                        $allergeniArr = array_map('trim', array_filter(explode(",", $_POST['allergeni'])));
                        
                        // Recupero allergeni attuali di questo ingrediente
                        $stmAllCurr = $pdo->prepare("SELECT a.nome FROM allergene a JOIN ingrediente_allergene ia ON a.id_allergene = ia.id_allergene WHERE ia.id_ingrediente = :iding");
                        $stmAllCurr->execute([':iding' => $id_ingrediente]);
                        $allCurrent = $stmAllCurr->fetchAll(PDO::FETCH_COLUMN);

                        // Aggiungo nuovi
                        foreach ($allergeniArr as $allName) {
                            if (!in_array($allName, $allCurrent)) {
                                $stmAll = $pdo->prepare("SELECT id_allergene FROM allergene WHERE nome = :nome");
                                $stmAll->execute([':nome' => $allName]);
                                $resAll = $stmAll->fetch(PDO::FETCH_ASSOC);
                                
                                if ($resAll) {
                                    $id_allergene = $resAll['id_allergene'];
                                } else {
                                    $stmNewAll = $pdo->prepare("INSERT INTO allergene (nome) VALUES (:nome)");
                                    $stmNewAll->execute([':nome' => $allName]);
                                    $id_allergene = $pdo->lastInsertId();
                                }
                                $pdo->prepare("INSERT IGNORE INTO ingrediente_allergene (id_ingrediente, id_allergene) VALUES (:iding, :idall)")
                                    ->execute([':iding' => $id_ingrediente, ':idall' => $id_allergene]);
                            }
                        }
                        // Rimuovo vecchi (se non più presenti nella lista inviata)
                        foreach ($allCurrent as $currAll) {
                            if (!in_array($currAll, $allergeniArr)) {
                                $pdo->prepare("DELETE ia FROM ingrediente_allergene ia JOIN allergene a ON ia.id_allergene = a.id_allergene WHERE ia.id_ingrediente = :iding AND a.nome = :nome")
                                    ->execute([':iding' => $id_ingrediente, ':nome' => $currAll]);
                            }
                        }
                    }
                }
            }
        }
    } 

    // ------------------------------------------------------------------
    // 2. GESTIONE CARICAMENTO DATI (GET o dopo POST)
    // ------------------------------------------------------------------
    // Se non abbiamo i dati dal POST (quindi è un primo caricamento GET) o se vogliamo ricaricarli freschi
    // Nota: Se abbiamo appena fatto POST con successo, i valori nelle variabili sono già aggiornati sopra.
    // Ma se è una GET pura, dobbiamo caricarli.
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $id_prodotto = $_GET['id'];
        
        // Dati Prodotto
        $sql = 'SELECT * FROM prodotto WHERE id_prodotto = :id';
        $stm = $pdo->prepare($sql);
        $stm->execute([':id' => $id_prodotto]);
        $r = $stm->fetch(PDO::FETCH_ASSOC);

        if ($r) {
            $nome_val = $r['nome'];
            $prezzo_val = $r['prezzo'];
            $descrizione_val = $r['descrizione'];
            $categoria_val = $r['id_categoria'];

            // Dati Ingredienti
            $sqlIng = 'SELECT i.nome FROM ingrediente i
                       JOIN prodotto_ingrediente pi ON i.id_ingrediente = pi.id_ingrediente
                       WHERE pi.id_prodotto = :id';
            $stmIng = $pdo->prepare($sqlIng);
            $stmIng->execute([':id' => $id_prodotto]);
            $ingList = $stmIng->fetchAll(PDO::FETCH_COLUMN);
            $ingredienti_val = implode(", ", $ingList);

            // Dati Allergeni (Derivati dagli ingredienti del prodotto)
            $sqlAll = 'SELECT DISTINCT a.nome FROM allergene a
                       INNER JOIN ingrediente_allergene ia ON a.id_allergene = ia.id_allergene
                       INNER JOIN prodotto_ingrediente pi ON ia.id_ingrediente = pi.id_ingrediente
                       WHERE pi.id_prodotto = :id';
            $stmAll = $pdo->prepare($sqlAll);
            $stmAll->execute([':id' => $id_prodotto]);
            $allList = $stmAll->fetchAll(PDO::FETCH_COLUMN);
            $allergeni_val = implode(", ", $allList);
        } else {
            $msgErrore = "Prodotto non trovato.";
        }
    }

} catch (PDOException $e) {
    $msgErrore = "Errore: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Prodotto</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAFvklEQVR4nOWaSWwbZRiGBwQIhISEOHAArogTEgfEuVLAhwoOVCWO421sz19CBajlgERT0tJCm2ahabOqKdBaAdqUViWVEIuSFqFGytIkzR5n8aSpdyde4iZObb9oZjyLM6lUQLHH4pU+xaMvI/2P/+/957Vlivq/qqoHj3FFFbNMF6IWQ30gVlYTSBi/j+ylilHGMyszpZUelB7IVqUH5U0hN90ZfYMqJtlqMjC3xqGv8sowXFV5YWhf7rVfjT9PFYPoaoCvkymUN67wO6IEKjvqS5nORdqoKjxKFQVItVCWpg0Y6oK5u8MB1QZWyztiDkqrokWIkSugG+PC6+OApfUeyo74coE4/zSH5syd8VcpzYLgAOj1Izm7Q9dy/olB/7naP+Xt4evlztAzlFZEi4t2OQWY7HVD54A8bqfuo/z0smrc9Mf8GyZnpJ7SgugWxQ5ccEmvkSzFmOvLXP+0rMNwIqACMjQEw6bO2LsFBSELgKMboBtyTb8atfEw4vUHX6f5v9YawNyagH6Tf/SVHhhawxNll2KvFAaEBfhyAfYrAF0jLPzDUzF097VLIHNuH85cXYU927fWp2FsifIAOUCHvRnjt8uXLT14sjAgrFDMKGA/n7s7XHk8Hr5GJgM45lyXe5x/GsJq/1T7k6aOyMGCgZBsOW4CtiYZpLpjDaPTfgmouy+MT5pTUt/csoayY361f04FfeZL8R15A6HHY2DcmVwgN+D4HbDVCYt11IAfr3nWy8PcWfLip+4oKuozMlDbKvSHNh3XBz0wtIWH91xMvLDtIMY+D0wDPtimEuodmgLslwD6hLDYj06ncfVGBHfvCrszPetD8+UEbNm+9WQaxuaIKu6UHvWmTedWnLsv4oltBRHLPBSEYzapAmKGAPtZedwq2zfQOxKSxq1/NIhD3yTl47px67izq242mhcQsSy3l8EspNT++QugTwuLtVUD9T+uYcIl+Oeux4Nfe5exrzGliDtr0H/lx3uHF7Hzsz9RYu1CXkH46vdu7Z85wH6NizECEKnLwPlLDOwdwT/sope/JrVZ/9RmUGLv4iEKA5It06Aftql7av9MAvYL8rjtb0rht95ladzGZ/xST4QoKIjkn+EQHHMbav/0A7Y2GeiL75IYHA/yMLQWQYRx82z57OGP6+ty3LGfAFquJDQM0ieD2CZX1UCzgONnOe7QxQDCj9utAOwz6+pxGwNsTjXIm/s1CGIeDMjH9XAYjvn7aqA+wNYKlFR04a2aLujOahCEH6+pBJ8KxJ51NAJmIa3yj65dgNAmiDv7ri+kYR2L8geBFHc4/2T7hIUEoUkQ060AHAp/cEczd0RLx/WtYHGASP4Y4fwhxxn7zBrMg/6c/9VpGYQej/MxRni+eEFPxECycYaLNfREPL8g1tsr/9rsXLDkDC72TAP+LT8O6LYVxI0+ab5nN2AeCv0jEO6eB92/+eOAbjtBKOARwmI348aiNN/Ta/y7+rAe2Rz3uXCpvJ/kBSQroxdPMywOERZrwrGa4eO7NP8PBBH6Jt4fnB+ycV9xP8kniKiKJbxEWJyXxmU+BcsW/nlQ37wp7itPMl0+QUSRBewgboxI4zab5J8JW5md77uSMA3JfctwCIzCP6RQIJyqgEf3sDAzLPy58++TFmcdU8QRN5eGFXGl38P3SaFBRFWweJZhcZywSIpxJOf50+/b5I+04I+sf4hWQEQ5lvAyYXFNOS7mkbD8/FDFlfv8E59oDUQUw6KEYTEuLdi1zkNIhufi/Jw6zuu0BsKJDOBxhsXHhEVE8s/kKn8MS3F+LJrz7YtOiyCi9i7hOcKig7iRUYK8c7nXTRWjmEW8zrC4ufOHnsSuP8aK80c7yrhT1dNT3D+j+i/6G/A00WmFPz+jAAAAAElFTkSuQmCC">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        #suggerimentiIngredienti, #suggerimentiAllergeni { 
            max-height: 150px; 
            overflow-y: auto; 
            position: absolute; 
            z-index: 1000; 
            background: white; 
            width: 90%; 
            border: 1px solid #ccc;
        }
        #suggerimentiIngredienti div, #suggerimentiAllergeni div {
            padding: 8px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        #suggerimentiIngredienti div:hover, #suggerimentiAllergeni div:hover {
            background-color: #f1f1f1;
        }
        .autocomplete-container { position: relative; }
    </style>
</head>

<body>
    <!-- Banner -->
    <div class="w3-container w3-light-blue w3-xlarge">
        <p><i class="fa fa-pizza-slice"></i> Pizza</p>
    </div>

    <!-- Messaggi -->
    <?php if ($msgErrore): ?>
        <div class="w3-panel w3-red w3-display-container">
            <span onclick="this.parentElement.style.display='none'" class="w3-button w3-large w3-display-topright">&times;</span>
            <p><?= $msgErrore ?></p>
        </div>
    <?php endif; ?>

    <?php if ($msgSuccesso): ?>
        <div class="w3-panel w3-green w3-display-container">
            <span onclick="this.parentElement.style.display='none'" class="w3-button w3-large w3-display-topright">&times;</span>
            <p><?= $msgSuccesso ?></p>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <?php if ($id_prodotto || $msgSuccesso): ?>
    <div class="w3-row">
        <div class="w3-col w3-container l3 m3 s12"></div>

        <div class="w3-col w3-container l6 m8 s12">
            <div class="w3-container">&nbsp;</div>
            <form action="modifica.php" method="post" class="w3-container w3-card-2 w3-light-grey w3-text-blue w3-margin">
                <h2 class="w3-center">Modifica Prodotto <i class="fa-solid fa-pizza-slice"></i></h2>

                <input name="id" type="hidden" value="<?= htmlspecialchars($id_prodotto) ?>">

                <!-- Nome -->
                <div class="w3-row w3-section">
                    <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-regular fa-user"></i></div>
                    <div class="w3-rest">
                        <input class="w3-input w3-border" name="nome" type="text" placeholder="Nome" minlength="3" value="<?= htmlspecialchars($nome_val) ?>" required>
                    </div>
                </div>

                <!-- Prezzo -->
                <div class="w3-row w3-section">
                    <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-euro-sign"></i></div>
                    <div class="w3-rest">
                        <label>Prezzo (€)</label>
                        <input class="w3-input w3-border w3-round" name="prezzo" type="number" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($prezzo_val) ?>" required>
                    </div>
                </div>

                <!-- Categoria -->
                <div class="w3-row w3-section">
                    <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-list"></i></div>
                    <div class="w3-rest">
                        <label>Tipologia</label>
                        <select class="w3-select w3-border w3-round" name="id_categoria">
                            <option value="1" <?= $categoria_val == 1 ? 'selected' : '' ?>>Pizza Classica</option>
                            <option value="2" <?= $categoria_val == 2 ? 'selected' : '' ?>>Pizza Gustosa</option>
                            <option value="3" <?= $categoria_val == 3 ? 'selected' : '' ?>>Pizza Speciale</option>
                            <option value="4" <?= $categoria_val == 4 ? 'selected' : '' ?>>Bibita analcolica</option>
                            <option value="5" <?= $categoria_val == 5 ? 'selected' : '' ?>>Bibita alcolica</option>
                            <option value="6" <?= $categoria_val == 6 ? 'selected' : '' ?>>Acqua</option>
                            <option value="7" <?= $categoria_val == 7 ? 'selected' : '' ?>>Dolce</option>
                            <option value="8" <?= $categoria_val == 8 ? 'selected' : '' ?>>Contorno</option>
                        </select>
                    </div>
                </div>
                
                <!-- Descrizione -->
                <div class="w3-row w3-section">
                    <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-pen"></i></div>
                    <div class="w3-rest">
                        <label>Descrizione</label>
                        <textarea class="w3-input w3-border w3-round" name="descrizione" placeholder="Descrizione del prodotto" rows="3" style="resize: vertical;" maxlength="200"><?= htmlspecialchars($descrizione_val) ?></textarea>
                    </div>
                </div>
                
                <!-- Ingredienti -->
                <div class="w3-row w3-section">
                    <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-plus"></i></div>
                    <div class="w3-rest">
                        <label>Ingredienti</label>
                        <div class="autocomplete-container">
                            <input class="w3-input w3-border w3-round" type="text" name="ingredienti" id="ingredienti" placeholder="Inizia a digitare..." autocomplete="off" value="<?= htmlspecialchars($ingredienti_val) ?>">
                            <div id="suggerimentiIngredienti" class="w3-white w3-border"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Allergeni -->
                <div class="w3-row w3-section">
                    <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-triangle-exclamation"></i></div>
                    <div class="w3-rest">
                        <label>Allergeni</label>
                        <div class="autocomplete-container">
                            <input class="w3-input w3-border w3-round" type="text" name="allergeni" id="allergeni" placeholder="Inizia a digitare..." autocomplete="off" value="<?= htmlspecialchars($allergeni_val) ?>">
                            <div id="suggerimentiAllergeni" class="w3-white w3-border"></div>
                        </div>
                    </div>
                </div>

                <button class="w3-button w3-block w3-large w3-orange"><i class="fa-solid fa-square-check"></i> Modifica</button>
                <a href="." class="w3-button w3-block w3-large w3-margin-bottom w3-light-gray"><i class="fa-solid fa-delete-left"></i> Torna all'elenco</a>
            </form>
        </div>

        <div class="w3-col w3-container l3 m1 w3-hide-small"></div>
    </div>
    <?php endif; ?>

<script>
// ---------------------------
// GESTIONE AUTOCOMPLETE INGREDIENTI
// ---------------------------
document.getElementById("ingredienti").addEventListener("keyup", function() {
    let valori = this.value.split(',');
    let ultimoTermine = valori[valori.length - 1].trim();

    // Cerca già alla prima lettera
    if(ultimoTermine.length < 1){ 
        document.getElementById("suggerimentiIngredienti").innerHTML = ""; 
        return; 
    }

    fetch("cerca_ingredienti.php?q=" + encodeURIComponent(ultimoTermine))
        .then(res => res.text())
        .then(data => {
            document.getElementById("suggerimentiIngredienti").innerHTML = data;
        });
});

function scegliIngrediente(nome){
    let input = document.getElementById("ingredienti");
    let val = input.value;
    let lastComma = val.lastIndexOf(',');

    if (lastComma === -1) {
        input.value = nome;
    } else {
        input.value = val.substring(0, lastComma) + ', ' + nome;
    }
    
    document.getElementById("suggerimentiIngredienti").innerHTML = "";
    input.focus();
}

// ---------------------------
// GESTIONE AUTOCOMPLETE ALLERGENI
// ---------------------------
document.getElementById("allergeni").addEventListener("keyup", function() {
    let valori = this.value.split(',');
    let ultimoTermine = valori[valori.length - 1].trim();

    if(ultimoTermine.length < 1){ 
        document.getElementById("suggerimentiAllergeni").innerHTML = ""; 
        return; 
    }

    fetch("cerca_allergeni.php?q=" + encodeURIComponent(ultimoTermine))
        .then(res => res.text())
        .then(data => {
            document.getElementById("suggerimentiAllergeni").innerHTML = data;
        });
});

function scegliAllergene(nome){
    let input = document.getElementById("allergeni");
    let val = input.value;
    let lastComma = val.lastIndexOf(',');

    if (lastComma === -1) {
        input.value = nome;
    } else {
        input.value = val.substring(0, lastComma) + ', ' + nome;
    }

    document.getElementById("suggerimentiAllergeni").innerHTML = "";
    input.focus();
}

// Chiudi suggerimenti al click esterno
document.addEventListener('click', function(e) {
    if (e.target.id !== 'ingredienti') {
        document.getElementById("suggerimentiIngredienti").innerHTML = "";
    }
    if (e.target.id !== 'allergeni') {
        document.getElementById("suggerimentiAllergeni").innerHTML = "";
    }
});
</script>

</body>
</html>