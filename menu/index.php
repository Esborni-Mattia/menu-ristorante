<?php
require 'connessione.php';

// --- CONFIGURAZIONE PAGINAZIONE ---
$pag_numero = 0;
$pag_voci = 30; 
$pag_offset = 0;
$pag_totali = 0;
$msgErrore = 'nessun errore';

try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    
    // Conteggio totale record
    $sql_count = 'SELECT count(*) FROM prodotto';
    $stm = $pdo->prepare($sql_count);
    $stm->execute();
    $num_record = $stm->fetchColumn();

    $pag_totali = intdiv($num_record, $pag_voci);
    if (($num_record % $pag_voci) > 0) $pag_totali++;

    if (isset($_GET['pag']) && is_numeric($_GET['pag']) && intval($_GET['pag']) > 0) {
        $pag_numero = intval($_GET['pag']) - 1;
        if ($pag_numero >= $pag_totali) $pag_numero = $pag_totali - 1;
    }
    $pag_offset = $pag_numero * $pag_voci;

    // Lettura dati dinamica
    $sql = 'SELECT * FROM prodotto ORDER BY nome ASC LIMIT :voci OFFSET :offset';
    $stm = $pdo->prepare($sql);
    $stm->bindValue(':voci', $pag_voci, PDO::PARAM_INT);
    $stm->bindValue(':offset', $pag_offset, PDO::PARAM_INT);
    $stm->execute();
    
    $ris = $stm->fetchAll(PDO::FETCH_ASSOC);

    if (count($ris) == 0 && $num_record > 0) {
        $msgErrore = 'Nessun risultato in questa pagina.';
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
    <title>Gestione Menu Pizzeria</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .prezzo-cella { font-weight: bold; color: #2e7d32; white-space: nowrap; }
        .azione-btn { text-decoration: none; margin: 0 2px;}
        th { text-transform: capitalize; }
    </style>
</head>

<body class="w3-light-grey">

    <div class="w3-container w3-red w3-xlarge w3-padding-16 w3-card">
        <p class="w3-margin-0"><i class="fa fa-pizza-slice"></i> Gestione Menu Pizzeria</p>
    </div>

    <div class="w3-bar w3-white w3-border-bottom w3-padding-small">
        <a href="index.php" class="w3-bar-item w3-button w3-dark-grey w3-round"><i class="fa fa-home"></i> Home</a>
        <a href="inserimento.php" class="w3-bar-item w3-button w3-green w3-right w3-round">
            <i class="fa fa-plus-circle"></i> Aggiungi Prodotto
        </a>
    </div>

    <?php if ($msgErrore != 'nessun errore'): ?>
        <div class="w3-panel w3-yellow w3-card w3-margin">
            <p><?= $msgErrore ?></p>
        </div>
    <?php endif ?>

    <div class="w3-container w3-padding-16">
        
        <?php if ($msgErrore == 'nessun errore' && count($ris) > 0): 
            // PRENDO I NOMI DELLE COLONNE DALLA PRIMA RIGA
            $colonne = array_keys($ris[0]); 
        ?>
            <div class="w3-responsive w3-card-4 w3-white">
                <table class="w3-table-all w3-hoverable">
                    <thead>
                        <tr class="w3-dark-grey">
                            <?php foreach ($colonne as $colonna): ?>
                                <th><?= str_replace('_', ' ', $colonna) ?></th>
                            <?php endforeach; ?>
                            <th class="w3-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ris as $r): ?>
                            <tr>
                                <?php foreach ($colonne as $colonna): ?>
                                    <td>
                                        <?php 
                                        if ($colonna == 'prezzo') {
                                            // Formatto il prezzo se la colonna è quella
                                            echo '<span class="prezzo-cella">€ ' . number_format($r[$colonna], 2, ',', '.') . '</span>';
                                        } else {
                                            echo htmlspecialchars($r[$colonna]);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                
                                <td class="w3-center" style="white-space: nowrap;">
                                    <a href="modificaedit.php?id=<?= $r['id'] ?>" class="w3-button w3-tiny w3-blue w3-round azione-btn">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                    <a href="cancellaconferma.php?id=<?= $r['id'] ?>" 
                                       class="w3-button w3-tiny w3-red w3-round azione-btn">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>

            <div class="w3-center w3-padding-32">
                <div class="w3-bar w3-border w3-round w3-white">
                    <?php 
                    $prev = $pag_numero;
                    $next = $pag_numero + 2;
                    
                    if ($pag_numero > 0) echo "<a href='index.php?pag=$prev' class='w3-bar-item w3-button'>&laquo;</a>";
                    
                    for ($i = 1; $i <= $pag_totali; $i++) {
                        $active = ($i == $pag_numero + 1) ? 'w3-red' : '';
                        echo "<a href='index.php?pag=$i' class='w3-bar-item w3-button $active'>$i</a>";
                    }

                    if ($pag_numero < $pag_totali - 1) echo "<a href='index.php?pag=$next' class='w3-bar-item w3-button'>&raquo;</a>";
                    ?>
                </div>
            </div>

        <?php elseif ($num_record == 0): ?>
            <div class="w3-panel w3-pale-blue w3-leftbar w3-border-blue w3-padding-16">
                <h4>Menu vuoto</h4>
                <p>Usa il tasto in alto per aggiungere la prima pizza.</p>
            </div>
        <?php endif ?>
    </div>

</body>
</html>