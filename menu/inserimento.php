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
    <title>Prodotti</title>
    <!-- Placeholder icona -->
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .form-card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .w3-input, .w3-select { border-radius: 8px; padding: 10px; }
        .w3-button { border-radius: 8px; font-weight: bold; }
        #suggerimentiIngredienti, #suggerimentiAllergeni { 
            max-height: 150px; 
            overflow-y: auto; 
            position: absolute; 
            z-index: 1000; 
            background: white; 
            width: 90%; /* Adatta alla larghezza se necessario */
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
        /* Contenitore relativo per posizionare i suggerimenti */
        .autocomplete-container { position: relative; }
    </style>
</head>
<body class="w3-black">

<!-- Banner -->
<div class="w3-container w3-blue w3-xlarge w3-padding-16">
    <p class="w3-center" style="font-weight: 600; color: var(--primary)"><i class="fa fa-utensils"></i> Inserisci Prodotto</p>
</div>

<!-- Messaggi di Feedback (Successo/Errore) -->
<?php if ($msgErrore): ?>
    <div class="w3-panel w3-red w3-display-container w3-margin">
        <span onclick="this.parentElement.style.display='none'" class="w3-button w3-large w3-display-topright">&times;</span>
        <h3>Attenzione!</h3>
        <p><?= htmlspecialchars($msgErrore) ?></p>
    </div>
<?php endif; ?>

<?php if ($msgSuccesso): ?>
    <div class="w3-panel w3-green w3-display-container w3-margin">
        <span onclick="this.parentElement.style.display='none'" class="w3-button w3-large w3-display-topright">&times;</span>
        <h3>Fatto!</h3>
        <p><?= htmlspecialchars($msgSuccesso) ?></p>
    </div>
<?php endif; ?>


<div class="w3-padding-16 w3-center">
    <div class="w3-content" style="max-width:900px;">

        <!-- ACTION vuota = invia alla stessa pagina -->
        <form action="" method="post" class="w3-container w3-card-2 w3-light-grey w3-text-blue w3-margin form-card w3-padding-24">

            <!-- Tipologia prodotto -->
            <h3>Tipologia prodotto</h3>
            <select name="tipologia" id="tipologia" class="w3-select w3-margin-bottom" onchange="mostraCampiIngredientiAllergeni()" required>
                <option value="">-- Seleziona --</option>
                <option value="pizza-classica" <?= $tipologia_selezionata === 'pizza-classica' ? 'selected' : '' ?>>Pizza Classica</option>
                <option value="pizza-gustosa" <?= $tipologia_selezionata === 'pizza-gustosa' ? 'selected' : '' ?>>Pizza Gustosa</option>
                <option value="pizza-speciale" <?= $tipologia_selezionata === 'pizza-speciale' ? 'selected' : '' ?>>Pizza Speciale</option>
                <option value="acqua" <?= $tipologia_selezionata === 'acqua' ? 'selected' : '' ?>>Acqua</option>
                <option value="bibita-analcolica" <?= $tipologia_selezionata === 'bibita-analcolica' ? 'selected' : '' ?>>Bibita analcolica</option>
                <option value="bibita-alcolica" <?= $tipologia_selezionata === 'bibita-alcolica' ? 'selected' : '' ?>>Bibita alcolica</option>
                <option value="dolce" <?= $tipologia_selezionata === 'dolce' ? 'selected' : '' ?>>Dolce</option>
                <option value="contorno" <?= $tipologia_selezionata === 'contorno' ? 'selected' : '' ?>>Contorno</option>
            </select>

            <!-- Nome prodotto -->
            <div class="w3-row w3-section">
                <div class="w3-col" style="width:60px">
                    <i id="iconaProdotto" class="w3-xxlarge fa-regular fa-circle-question"></i>
                </div>
                <div class="w3-rest">
                    <input class="w3-input w3-border" name="nome" type="text" placeholder="Nome" minlength="3" value="<?= htmlspecialchars($nome_val) ?>" required>
                </div>
            </div>

            <!-- Prezzo prodotto -->
            <div class="w3-row w3-section">
                <div class="w3-col" style="width:60px"><i class="w3-xxlarge fa-solid fa-euro-sign"></i></div>
                <div class="w3-rest">
                    <input class="w3-input w3-border" name="prezzo" type="number" step="0.01" placeholder="Prezzo" value="<?= htmlspecialchars($prezzo_val) ?>" required>
                </div>
            </div>

            <!-- Descrizione prodotto -->
            <div class="w3-row w3-section">
                <div class="w3-col" style="width:60px"><i class="w3-xxlarge fa-solid fa-pen"></i></div>
                <div class="w3-rest">
                    <textarea class="w3-input w3-border" name="descrizione" placeholder="Descrizione del prodotto" rows="3" style="resize: vertical;"><?= htmlspecialchars($descrizione_val) ?></textarea>
                </div>
            </div>

            <br>
            
            <!-- Campi ingredienti e allergeni -->
            <div id="campiIngredientiAllergeni" style="display:none; margin-top:10px;">
                <h3>Ingredienti</h3>
                <div class="autocomplete-container">
                    <input class="w3-input w3-border" type="text" name="ingredienti" id="ingredienti" placeholder="Es. Pomodoro, Mozzarella..." autocomplete="off" value="<?= htmlspecialchars($ingredienti_val) ?>">
                    <div id="suggerimentiIngredienti" class="w3-white w3-border"></div>
                </div>
                
                <br>
                <h3>Allergeni</h3>
                <div class="autocomplete-container">
                    <input class="w3-input w3-border" type="text" name="allergeni" id="allergeni" placeholder="Es. Glutine, Lattosio..." autocomplete="off" value="<?= htmlspecialchars($allergeni_val) ?>">
                    <div id="suggerimentiAllergeni" class="w3-white w3-border"></div>
                </div>
            </div>

            <button class="w3-button w3-block w3-large w3-blue w3-margin-top"><i class="fa-solid fa-square-check"></i> Inserisci</button>
            <a href="." class="w3-button w3-block w3-large w3-margin-top w3-light-gray"><i class="fa-solid fa-arrow-left"></i> Torna all'elenco</a>
        </form>
    
    </div>
    <div class="w3-col l3 m1 w3-hide-small"></div>
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