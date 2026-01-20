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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* --- STILI GENERALI --- */
        body { 
            background-color: #faf9f6; 
            font-family: 'Roboto', sans-serif; 
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        h1, h2, h3 { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* --- HEADER --- */
        .hero-header {
            background-color: #b71c1c; 
            color: white;
            padding: 30px 16px;
            text-align: center;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .hero-header h1 {
            margin: 0;
            font-size: 1.8rem;
            letter-spacing: 1px;
        }

        .hero-header p {
            margin: 8px 0 0 0;
            font-size: 0.9rem;
            opacity: 0.95;
        }

        /* --- CONTAINER --- */
        .container-lista {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 16px;
        }

        /* --- FORM --- */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-family: 'Oswald', sans-serif;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Roboto', sans-serif;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #b71c1c;
            box-shadow: 0 0 0 3px rgba(183, 28, 28, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Autocomplete */
        #suggerimentiIngredienti, #suggerimentiAllergeni { 
            max-height: 150px; 
            overflow-y: auto; 
            position: absolute; 
            z-index: 1000; 
            background: white; 
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 0 0 8px 8px;
            border-top: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        #suggerimentiIngredienti div, #suggerimentiAllergeni div {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        #suggerimentiIngredienti div:hover, #suggerimentiAllergeni div:hover {
            background-color: #f5f5f5;
            color: #b71c1c;
        }
        .autocomplete-container { position: relative; }

        /* Pulsanti */
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-submit, .btn-back {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-family: 'Oswald', sans-serif;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-submit {
            background: #b71c1c;
            color: white;
        }

        .btn-submit:hover {
            background: #8b1515;
        }

        .btn-back {
            background: #f0f0f0;
            color: #333;
        }

        .btn-back:hover {
            background: #e0e0e0;
        }

        /* Messaggi */
        .error-message, .success-message {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            font-weight: 500;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            border-left-color: #c62828;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            border-left-color: #2e7d32;
        }

        .close-btn {
            float: right;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .close-btn:hover {
            opacity: 1;
        }

        .hidden-fields {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
    </style>
</head>

<body>

<!-- HERO HEADER -->
<div class="hero-header">
    <h1><i class="fas fa-pen-to-square"></i> Modifica Prodotto</h1>
    <p>Aggiorna i dettagli del piatto</p>
</div>

<div class="container-lista">
    <!-- Messaggi -->
    <?php if ($msgErrore): ?>
        <div class="error-message">
            <span class="close-btn" onclick="this.parentElement.style.display='none'">&times;</span>
            <strong>Attenzione!</strong> <?= htmlspecialchars($msgErrore) ?>
        </div>
    <?php endif; ?>

    <?php if ($msgSuccesso): ?>
        <div class="success-message">
            <span class="close-btn" onclick="this.parentElement.style.display='none'">&times;</span>
            <strong>Fatto!</strong> <?= htmlspecialchars($msgSuccesso) ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <?php if ($id_prodotto || $msgSuccesso): ?>
        <form action="modifica.php" method="post" class="form-card">

            <input name="id" type="hidden" value="<?= htmlspecialchars($id_prodotto) ?>">

            <!-- Nome -->
            <div class="form-group">
                <label for="nome"><i class="fas fa-circle-question"></i> Nome Prodotto</label>
                <input type="text" id="nome" name="nome" minlength="3" value="<?= htmlspecialchars($nome_val) ?>" required>
            </div>

            <!-- Prezzo -->
            <div class="form-group">
                <label for="prezzo"><i class="fas fa-euro-sign"></i> Prezzo (€)</label>
                <input type="number" id="prezzo" name="prezzo" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($prezzo_val) ?>" required>
            </div>

            <!-- Categoria -->
            <div class="form-group">
                <label for="id_categoria"><i class="fas fa-tag"></i> Tipologia</label>
                <select name="id_categoria" id="id_categoria" onchange="mostraCampi()">
                    <option value="1" <?= $categoria_val == 1 ? 'selected' : '' ?>>Pizza Classica</option>
                    <option value="2" <?= $categoria_val == 2 ? 'selected' : '' ?>>Pizza Gustosa</option>
                    <option value="3" <?= $categoria_val == 3 ? 'selected' : '' ?>>Pizza Speciale</option>
                    <option value="4" <?= $categoria_val == 4 ? 'selected' : '' ?>>Bibita Analcolica</option>
                    <option value="5" <?= $categoria_val == 5 ? 'selected' : '' ?>>Bibita Alcolica</option>
                    <option value="6" <?= $categoria_val == 6 ? 'selected' : '' ?>>Acqua</option>
                    <option value="7" <?= $categoria_val == 7 ? 'selected' : '' ?>>Dolce</option>
                    <option value="8" <?= $categoria_val == 8 ? 'selected' : '' ?>>Contorno</option>
                </select>
            </div>
            
            <!-- Descrizione -->
            <div class="form-group">
                <label for="descrizione"><i class="fas fa-pen"></i> Descrizione</label>
                <textarea id="descrizione" name="descrizione" placeholder="Descrizione del prodotto" maxlength="200"><?= htmlspecialchars($descrizione_val) ?></textarea>
            </div>
            
            <!-- Wrapper per campi opzionali -->
            <div id="campiOpzionali" style="display:none;" class="hidden-fields">
                    <!-- Ingredienti -->
                    <div class="form-group">
                        <label for="ingredienti"><i class="fas fa-list"></i> Ingredienti</label>
                        <div class="autocomplete-container">
                            <input type="text" id="ingredienti" name="ingredienti" placeholder="Es. Pomodoro, Mozzarella..." autocomplete="off" value="<?= htmlspecialchars($ingredienti_val) ?>">
                            <div id="suggerimentiIngredienti"></div>
                        </div>
                    </div>
                    
                    <!-- Allergeni -->
                    <div class="form-group">
                        <label for="allergeni"><i class="fas fa-warning"></i> Allergeni</label>
                        <div class="autocomplete-container">
                            <input type="text" id="allergeni" name="allergeni" placeholder="Es. Glutine, Lattosio..." autocomplete="off" value="<?= htmlspecialchars($allergeni_val) ?>">
                            <div id="suggerimentiAllergeni"></div>
                        </div>
                    </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Modifica</button>
                <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Torna</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// ---------------------------
// GESTIONE VISIBILITÀ CAMPI
// ---------------------------
function mostraCampi() {
    let select = document.getElementById("id_categoria");
    let container = document.getElementById("campiOpzionali");
    let val = select.value;
    
    // ID Categorie che mostrano ingredienti/allergeni:
    // 1,2,3 (Pizzas), 7 (Dolce), 8 (Contorno)
    let categorieVisibili = ["1", "2", "3", "7", "8"];
    
    if (categorieVisibili.includes(val)) {
        container.style.display = "block";
    } else {
        container.style.display = "none";
    }
}

// Esegui al caricamento
window.addEventListener('DOMContentLoaded', mostraCampi);

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