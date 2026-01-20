<?php 
require 'connessione.php'; 

// Inizializzo variabili
$tipologia_selezionata = "";
$nome_val = "";
$prezzo_val = "";
$descrizione_val = ""; // Nuova variabile per la descrizione
$ingredienti_val = "";
$allergeni_val = "";

$msgErrore = "";
$msgSuccesso = "";

// Mappatura stringhe select -> ID database (basato sul tuo file SQL)
$mappaCategorie = [
    'pizza-classica' => 1,
    'pizza-gustosa' => 2,
    'pizza-speciale' => 3,
    'bibita-analcolica' => 4,
    'bibita-alcolica' => 5,
    'acqua' => 6,
    'dolce' => 7,
    'contorno' => 8
];

// GESTIONE POST (Quando si preme "Inserisci")
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupero dati input
    $tipologia_selezionata = $_POST['tipologia'] ?? '';
    $nome_val = trim($_POST['nome'] ?? '');
    $prezzo_val = $_POST['prezzo'] ?? '';
    $descrizione_val = trim($_POST['descrizione'] ?? ''); // Recupero descrizione
    $ingredienti_val = $_POST['ingredienti'] ?? '';
    $allergeni_val = $_POST['allergeni'] ?? '';

    // Validazione base
    if (empty($nome_val) || empty($prezzo_val) || empty($tipologia_selezionata)) {
        $msgErrore = "Compila tutti i campi obbligatori.";
    } else {
        try {
            $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 1. CONTROLLO DUPLICATI
            // Verifico se esiste già un prodotto con lo stesso nome (case insensitive di solito su MySQL)
            $stmCheck = $pdo->prepare("SELECT COUNT(*) FROM prodotto WHERE nome = :nome");
            $stmCheck->execute([':nome' => $nome_val]);
            
            if ($stmCheck->fetchColumn() > 0) {
                $msgErrore = "Attenzione: Esiste già un prodotto con questo nome!";
            } else {
                // Procedo con l'inserimento
                
                // Recupero ID Categoria dalla mappa
                $id_categoria = $mappaCategorie[$tipologia_selezionata] ?? null;

                if ($id_categoria) {
                    // 2. INSERIMENTO PRODOTTO
                    // Aggiornata query per includere la descrizione
                    $sql = "INSERT INTO prodotto (nome, prezzo, id_categoria, descrizione) VALUES (:nome, :prezzo, :id_cat, :descrizione)";
                    $stm = $pdo->prepare($sql);
                    $stm->execute([
                        ':nome' => $nome_val,
                        ':prezzo' => $prezzo_val,
                        ':id_cat' => $id_categoria,
                        ':descrizione' => $descrizione_val
                    ]);
                    $id_prodotto = $pdo->lastInsertId();

                    // 3. GESTIONE INGREDIENTI E ALLERGENI
                    // (Logica simile a modifica.php: inserisce ingredienti se nuovi e li collega)
                    if (!empty($ingredienti_val)) {
                        $ingredientiArr = array_map('trim', array_filter(explode(",", $ingredienti_val)));
                        
                        foreach ($ingredientiArr as $ingName) {
                            if (empty($ingName)) continue;

                            // Cerca o Crea Ingrediente
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

                            // Collega Prodotto -> Ingrediente
                            $pdo->prepare("INSERT INTO prodotto_ingrediente (id_prodotto, id_ingrediente) VALUES (:id, :iding)")
                                ->execute([':id' => $id_prodotto, ':iding' => $id_ingrediente]);

                            // Gestione Allergeni (collegati agli ingredienti inseriti)
                            if (!empty($allergeni_val)) {
                                $allergeniArr = array_map('trim', array_filter(explode(",", $allergeni_val)));
                                foreach ($allergeniArr as $allName) {
                                    // Cerca o Crea Allergene
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

                                    // Collega Ingrediente -> Allergene (evita duplicati con IGNORE o controllo)
                                    // Usiamo INSERT IGNORE se il DB lo supporta per la chiave primaria composta, altrimenti try/catch silenzioso
                                    try {
                                        $pdo->prepare("INSERT INTO ingrediente_allergene (id_ingrediente, id_allergene) VALUES (:iding, :idall)")
                                            ->execute([':iding' => $id_ingrediente, ':idall' => $id_allergene]);
                                    } catch (PDOException $e) {
                                        // Ignora se la relazione esiste già
                                    }
                                }
                            }
                        }
                    }

                    $msgSuccesso = "Prodotto inserito con successo!";
                    // Pulisco i campi per permettere un nuovo inserimento pulito
                    $tipologia_selezionata = ""; $nome_val = ""; $prezzo_val = ""; $descrizione_val = ""; $ingredienti_val = ""; $allergeni_val = "";
                } else {
                    $msgErrore = "Categoria selezionata non valida.";
                }
            }
        } catch (PDOException $e) {
            $msgErrore = "Errore durante l'inserimento: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserisci Prodotto</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=">
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
        
        h1, h2, h3, .prezzo-tag { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

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
    <h1><i class="fas fa-plus"></i> Inserisci Prodotto</h1>
    <p>Aggiungi un nuovo piatto al menu</p>
</div>

<!-- Messaggi di Feedback (Successo/Errore) -->
<div class="container-lista">
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

    <!-- FORM -->
    <form action="" method="post" class="form-card">

        <!-- Tipologia prodotto -->
        <div class="form-group">
            <label for="tipologia"><i class="fas fa-tag"></i> Tipologia Prodotto *</label>
            <select name="tipologia" id="tipologia" onchange="mostraCampiIngredientiAllergeni()" required>
                <option value="">-- Seleziona --</option>
                <option value="pizza-classica" <?= $tipologia_selezionata === 'pizza-classica' ? 'selected' : '' ?>>Pizza Classica</option>
                <option value="pizza-gustosa" <?= $tipologia_selezionata === 'pizza-gustosa' ? 'selected' : '' ?>>Pizza Gustosa</option>
                <option value="pizza-speciale" <?= $tipologia_selezionata === 'pizza-speciale' ? 'selected' : '' ?>>Pizza Speciale</option>
                <option value="acqua" <?= $tipologia_selezionata === 'acqua' ? 'selected' : '' ?>>Acqua</option>
                <option value="bibita-analcolica" <?= $tipologia_selezionata === 'bibita-analcolica' ? 'selected' : '' ?>>Bibita Analcolica</option>
                <option value="bibita-alcolica" <?= $tipologia_selezionata === 'bibita-alcolica' ? 'selected' : '' ?>>Bibita Alcolica</option>
                <option value="dolce" <?= $tipologia_selezionata === 'dolce' ? 'selected' : '' ?>>Dolce</option>
                <option value="contorno" <?= $tipologia_selezionata === 'contorno' ? 'selected' : '' ?>>Contorno</option>
            </select>
        </div>

        <!-- Nome prodotto -->
        <div class="form-group">
            <label for="nome"><i class="fas fa-circle-question"></i> Nome Prodotto *</label>
            <input type="text" id="nome" name="nome" placeholder="Es. Margherita" minlength="3" value="<?= htmlspecialchars($nome_val) ?>" required>
        </div>

        <!-- Prezzo prodotto -->
        <div class="form-group">
            <label for="prezzo"><i class="fas fa-euro-sign"></i> Prezzo (€) *</label>
            <input type="number" id="prezzo" name="prezzo" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($prezzo_val) ?>" required>
        </div>

        <!-- Descrizione prodotto -->
        <div class="form-group">
            <label for="descrizione"><i class="fas fa-pen"></i> Descrizione</label>
            <textarea id="descrizione" name="descrizione" placeholder="Descrizione del prodotto..."><?= htmlspecialchars($descrizione_val) ?></textarea>
        </div>

        <!-- Campi ingredienti e allergeni -->
        <div id="campiIngredientiAllergeni" style="display:none;" class="hidden-fields">
            <div class="form-group">
                <label for="ingredienti"><i class="fas fa-list"></i> Ingredienti</label>
                <div class="autocomplete-container">
                    <input type="text" id="ingredienti" name="ingredienti" placeholder="Es. Pomodoro, Mozzarella..." autocomplete="off" value="<?= htmlspecialchars($ingredienti_val) ?>">
                    <div id="suggerimentiIngredienti" class="w3-white w3-border"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="allergeni"><i class="fas fa-warning"></i> Allergeni</label>
                <div class="autocomplete-container">
                    <input type="text" id="allergeni" name="allergeni" placeholder="Es. Glutine, Lattosio..." autocomplete="off" value="<?= htmlspecialchars($allergeni_val) ?>">
                    <div id="suggerimentiAllergeni" class="w3-white w3-border"></div>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Inserisci</button>
            <a href="index.php" class="btn-back" style="text-decoration: none; display: flex; align-items: center; justify-content: center;"><i class="fas fa-arrow-left"></i> Torna indietro</a>
        </div>
    </form>
</div>

<script>
function mostraCampiIngredientiAllergeni() {
    let tipo = document.getElementById("tipologia").value.toLowerCase();
    let icona = document.getElementById("iconaProdotto");

    // Mostra/Nasconde campi
    let mostra = (tipo === "pizza-classica" || tipo === "pizza-gustosa" || tipo === "pizza-speciale" || tipo === "contorno" || tipo === "dolce");
    document.getElementById("campiIngredientiAllergeni").style.display = mostra ? "block" : "none";

    // Gestione icone
    switch(tipo){
        case "pizza-classica":
        case "pizza-gustosa":
        case "pizza-speciale":
            icona.className = "w3-xxlarge fa-solid fa-pizza-slice"; break;
        case "acqua":
            icona.className = "w3-xxlarge fa-solid fa-glass-water"; break;
        case "bibita-analcolica":
            icona.className = "w3-xxlarge fa-solid fa-glass-whiskey"; break;
        case "bibita-alcolica":
            icona.className = "w3-xxlarge fa-solid fa-wine-glass"; break;
        case "dolce":
            icona.className = "w3-xxlarge fa-solid fa-cake-candles"; break;
        case "contorno":
            icona.className = "w3-xxlarge fa-solid fa-bowl-food"; break;
        default:
            icona.className = "w3-xxlarge fa-regular fa-circle-question";
    }
}

// Mostra i campi se già selezionato al caricamento
window.addEventListener('DOMContentLoaded', mostraCampiIngredientiAllergeni);

// ---------------------------
// GESTIONE AUTOCOMPLETE INGREDIENTI
// ---------------------------
document.getElementById("ingredienti").addEventListener("keyup", function() {
    let valori = this.value.split(',');
    let ultimoTermine = valori[valori.length - 1].trim();

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

// Chiudi suggerimenti se si clicca fuori
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