<?php require 'connessione.php'; ?>

<?php
// Mantieni la selezione se il form viene ricaricato
$tipologia_selezionata = $_POST['tipologia'] ?? '';
$nome_val = $_POST['nome'] ?? '';
$prezzo_val = $_POST['prezzo'] ?? '';
$ingredienti_val = $_POST['ingredienti'] ?? '';
$allergeni_val = $_POST['allergeni'] ?? '';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prodotti</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,...">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .form-card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .w3-input, .w3-select { border-radius: 8px; padding: 10px; }
        .w3-button { border-radius: 8px; font-weight: bold; }
        #suggerimentiIngredienti, #suggerimentiAllergeni { max-height: 150px; overflow-y: auto; }
    </style>
</head>
<body class="w3-black">

<!-- Banner -->
<div class="w3-container w3-blue w3-xlarge w3-padding-16">
    <p class="w3-center " style="font-weight: 600; color: var(--primary)"><i class="fa fa-utensils"></i> Inserisci Prodotto</p>
</div>

<div class="w3-padding-16 w3-center">
    <div class="w3-content" style="max-width:900px;">


        <form action="inserisci.php" method="post" class="w3-container w3-card-2 w3-light-grey w3-text-blue w3-margin form-card w3-padding-24">

            <!-- Tipologia prodotto -->
            <h3>Tipologia prodotto</h3>
            <select name="tipologia" id="tipologia" class="w3-select w3-margin-bottom" onchange="mostraCampiIngredientiAllergeni()" required>
                <option value="">-- Seleziona --</option>
                <option value="pizza" <?= $tipologia_selezionata === 'pizza' ? 'selected' : '' ?>>Pizza</option>
                <option value="bibita" <?= $tipologia_selezionata === 'bibita' ? 'selected' : '' ?>>Bibita</option>
                <option value="dolce" <?= $tipologia_selezionata === 'dolce' ? 'selected' : '' ?>>Dolce</option>
                <option value="contorno" <?= $tipologia_selezionata === 'contorno' ? 'selected' : '' ?>>Contorno</option>
            </select>

            <!-- Nome prodotto -->
            <div class="w3-row w3-section">
                <div class="w3-col" style="width:60px">
                <i id="iconaProdotto" class="w3-xxlargefa-regular fa-circle-question"></i>
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
            <br>
            <!-- Campi ingredienti e allergeni -->
            <div id="campiIngredientiAllergeni" style="display:none; margin-top:10px;">
                <h3>Ingredienti</h3>
                <input class="w3-input w3-border" type="text" name="ingredienti" id="ingredienti" placeholder="Inizia a digitare..." autocomplete="off" value="<?= htmlspecialchars($ingredienti_val) ?>">
                <div id="suggerimentiIngredienti" class="w3-white w3-border"></div>
                <br>
                <h3>Allergeni</h3>
                <input class="w3-input w3-border" type="text" name="allergeni" id="allergeni" placeholder="Inizia a digitare..." autocomplete="off" value="<?= htmlspecialchars($allergeni_val) ?>">
                <div id="suggerimentiAllergeni" class="w3-white w3-border"></div>
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
    document.getElementById("campiIngredientiAllergeni").style.display =
        (tipo === "pizza" || tipo === "contorno" || tipo === "dolce") ? "block" : "none";
}

// Mostra i campi se già selezionato
window.addEventListener('DOMContentLoaded', (event) => {
    mostraCampiIngredientiAllergeni();
});

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
<script>
function mostraCampiIngredientiAllergeni() {
    let tipo = document.getElementById("tipologia").value.toLowerCase();

    // Mostra/Nasconde ingredienti e allergeni
    document.getElementById("campiIngredientiAllergeni").style.display =
        (tipo === "pizza" || tipo === "contorno" || tipo === "dolce") ? "block" : "none";

    // Cambia icona
    let icona = document.getElementById("iconaProdotto");

    switch(tipo){
        case "pizza":
            icona.className = "w3-xxlarge fa-solid fa-pizza-slice";
            break;
        case "bibita":
            icona.className = "w3-xxlarge fa-solid fa-glass-water";
            break;
        case "dolce":
            icona.className = "w3-xxlarge fa-solid fa-cake-candles";
            break;
        case "contorno":
            icona.className = "w3-xxlarge fa-solid fa-bowl-food";
            break;
        default:
            icona.className = "w3-xxlarge fa-regular fa-circle-question";
    }
}

// Mostra i campi e aggiorna l’icona se già selezionato
window.addEventListener('DOMContentLoaded', mostraCampiIngredientiAllergeni);
</script>
