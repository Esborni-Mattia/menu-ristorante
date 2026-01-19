<?php
require 'connessione.php';

// Ottengo i dati da modificare.
try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $sql = 'SELECT p.id_prodotto, p.nome, p.prezzo, p.id_categoria, p.descrizione, c.nome AS categoria
            FROM prodotto p
            LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
            WHERE p.id_prodotto = :id';
    $stm = $pdo->prepare($sql);
    $stm->bindparam(":id", $_GET['id']);
    $stm->execute();
    $numRighe = $stm->rowCount();

    if ($numRighe == 0) {
        $msgErrore = 'Dato non trovato.';
    } else {
        $ris = $stm->fetchAll(PDO::FETCH_ASSOC);
        $r = $ris[0];
        $msgErrore = "nessun errore";
        
        // Recupero ingredienti
        $sqlIng = 'SELECT i.nome FROM ingrediente i
                   JOIN prodotto_ingrediente pi ON i.id_ingrediente = pi.id_ingrediente
                   WHERE pi.id_prodotto = :id';
        $stmIng = $pdo->prepare($sqlIng);
        $stmIng->bindparam(":id", $_GET['id']);
        $stmIng->execute();
        $ingredienti = $stmIng->fetchAll(PDO::FETCH_COLUMN);
        $ingredienti_str = implode(", ", $ingredienti);
        
        // Recupero allergeni collegati agli ingredienti del prodotto
        $sqlAll = 'SELECT DISTINCT a.nome FROM allergene a
                   INNER JOIN ingrediente_allergene ia ON a.id_allergene = ia.id_allergene
                   INNER JOIN prodotto_ingrediente pi ON ia.id_ingrediente = pi.id_ingrediente
                   WHERE pi.id_prodotto = :id';
        $stmAll = $pdo->prepare($sqlAll);
        $stmAll->bindparam(":id", $_GET['id']);
        $stmAll->execute();
        $allergeni = $stmAll->fetchAll(PDO::FETCH_COLUMN);
        $allergeni_str = implode(", ", $allergeni);
    }
} catch (PDOException $e) {
    $msgErrore = $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizze</title>
    <link rel="icon" type="image/x-icon"
        href="data:image/png;base64,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">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        #suggerimentiIngredienti, #suggerimentiAllergeni { max-height: 150px; overflow-y: auto; }
    </style>
</head>

<body>
    <!-- Banner -->
    <div class="w3-container w3-light-blue w3-xlarge">
        <p><i class="fa fa-pizza-slice"></i> Pizza</p>
    </div>

    <!-- Banner per l'errore -->
    <?php if ($msgErrore != 'nessun errore'):?>
        <div>
            <?= $msgErrore?>
        </div>
    <?php endif?>

    <!-- Visualizzazione della form di inserimento -->
    <?php if ($msgErrore == 'nessun errore'):?>

        <div class="w3-row">
            <div class="w3-col w3-container l3 m3 s12"></div>

            <div class="w3-col w3-container l6 m8 s12">
                <div class="w3-container">&nbsp;</div>
                <form action="modifica.php" method="post" class="w3-container w3-card-2 w3-light-grey w3-text-blue w3-margin">
                    <h2 class="w3-center">Modifica Prodotto<i class="fa-solid fa-pizza-slice"></i></h2>

                    <input name="id" type="hidden" value="<?= $_GET['id']?>">

                    <div class="w3-row w3-section">
                    <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-regular fa-user"></i></div>
                        <div class="w3-rest">
                        <input class="w3-input w3-border" name="nome" type="text" placeholder="Nome" minlength="3" value="<?= htmlspecialchars($r['nome'])?>" required>
                        </div>
                    </div>

                    <div class="w3-row w3-section">
                <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-euro-sign"></i></div>
                <div class="w3-rest">
                    <label>Prezzo (€)</label>
                    <input class="w3-input w3-border w3-round" name="prezzo" type="number" step="0.01" placeholder="0.00" value="<?= $r['prezzo'] ?>" required>
                    </div>
                </div>

                    <div class="w3-row w3-section">
                <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-list"></i></div>
                <div class="w3-rest">
                    <label>Tipologia</label>
                    <select class="w3-select w3-border w3-round" name="id_categoria">
                        <option value="1" <?= $r['id_categoria'] == 1 ? 'selected' : '' ?>>Pizza</option>
                        <option value="2" <?= $r['id_categoria'] == 2 ? 'selected' : '' ?>>Bibita</option>
                        <option value="3" <?= $r['id_categoria'] == 3 ? 'selected' : '' ?>>Contorno</option>
                        <option value="4" <?= $r['id_categoria'] == 4 ? 'selected' : '' ?>>Dolce</option>
                    </select>
                </div>
            </div>
            
                    <div class="w3-row w3-section">
                <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-pen"></i></div>
                <div class="w3-rest">
                    <label>Descrizione</label>
                    <textarea class="w3-input w3-border w3-round" name="descrizione" placeholder="Descrizione del prodotto" rows="3" style="resize: vertical;" maxlength="200"><?= htmlspecialchars($r['descrizione'] ?? '') ?></textarea>
                </div>
            </div>
            
                    <div class="w3-row w3-section">
                <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-plus"></i></div>
                <div class="w3-rest">
                    <label>Ingredienti</label>
                    <input class="w3-input w3-border w3-round" type="text" name="ingredienti" id="ingredienti" placeholder="Inizia a digitare..." autocomplete="off" value="<?= htmlspecialchars($ingredienti_str) ?>">
                    <div id="suggerimentiIngredienti" class="w3-white w3-border"></div>
                </div>
            </div>
            
                    <div class="w3-row w3-section">
                <div class="w3-col w3-center" style="width:60px"><i class="w3-xxlarge fa-solid fa-triangle-exclamation"></i></div>
                <div class="w3-rest">
                    <label>Allergeni</label>
                    <input class="w3-input w3-border w3-round" type="text" name="allergeni" id="allergeni" placeholder="Inizia a digitare..." autocomplete="off" value="<?= htmlspecialchars($allergeni_str) ?>">
                    <div id="suggerimentiAllergeni" class="w3-white w3-border"></div>
                </div>
            </div>

                    <button class="w3-button w3-block w3-large w3-orange"><i class="fa-solid fa-square-check"></i> Modifica</button>
                    <a href="." class="w3-button w3-block w3-large w3-margin-bottom w3-light-gray"><i class="fa-solid fa-delete-left"></i> Torna all'elenco</a>
                </form>
            </div>

            <div class="w3-col w3-container l3 m1 w3-hide-small"></div>
        </div>
    <?php endif?>

<script>
// Ingredienti autocomplete
document.getElementById("ingredienti").addEventListener("keyup", function() {
    let valore = this.value;
    if(valore.length < 2){ document.getElementById("suggerimentiIngredienti").innerHTML = ""; return; }
    fetch("cerca_ingredienti.php?q=" + encodeURIComponent(valore))
        .then(res => res.text())
        .then(data => document.getElementById("suggerimentiIngredienti").innerHTML = data);
});
function scegliIngrediente(nome){
    let input = document.getElementById("ingredienti");
    if(input.value) input.value += ", ";
    input.value += nome;
    document.getElementById("suggerimentiIngredienti").innerHTML = "";
}

// Allergeni autocomplete
document.getElementById("allergeni").addEventListener("keyup", function() {
    let valore = this.value;
    if(valore.length < 2){ document.getElementById("suggerimentiAllergeni").innerHTML = ""; return; }
    fetch("cerca_allergeni.php?q=" + encodeURIComponent(valore))
        .then(res => res.text())
        .then(data => document.getElementById("suggerimentiAllergeni").innerHTML = data);
});
function scegliAllergene(nome){
    let input = document.getElementById("allergeni");
    if(input.value) input.value += ", ";
    input.value += nome;
    document.getElementById("suggerimentiAllergeni").innerHTML = "";
}
</script>

</body>
</html>



