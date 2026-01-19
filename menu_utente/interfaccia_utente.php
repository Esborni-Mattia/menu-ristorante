<?php
require 'connessione.php';

try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    
    // QUERY AGGIORNATA PER LA TUA STRUTTURA
    // Recupera gli allergeni passando attraverso gli ingredienti
    $sql = "SELECT 
                p.id_prodotto, 
                p.nome, 
                p.prezzo, 
                p.descrizione, 
                c.nome AS categoria,
                -- Unisco i nomi degli ingredienti
                GROUP_CONCAT(DISTINCT i.nome SEPARATOR ', ') AS ingredienti,
                -- Unisco i nomi degli allergeni trovati tramite gli ingredienti
                GROUP_CONCAT(DISTINCT a.nome SEPARATOR ', ') AS allergeni
            FROM prodotto p
            JOIN categoria c ON p.id_categoria = c.id_categoria
            -- Collegamento Prodotto -> Ingredienti
            LEFT JOIN prodotto_ingrediente pi ON p.id_prodotto = pi.id_prodotto
            LEFT JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
            -- Collegamento Ingrediente -> Allergeni (LA TUA STRUTTURA)
            LEFT JOIN ingrediente_allergene ia ON i.id_ingrediente = ia.id_ingrediente
            LEFT JOIN allergene a ON ia.id_allergene = a.id_allergene
            
            WHERE p.disponibile = 1
            GROUP BY p.id_prodotto
            -- Ordina: Prima Pizze, poi Contorni, Bibite, Dolci
            ORDER BY FIELD(c.nome, 'Pizza', 'Contorno', 'Bibita', 'Dolce'), p.nome";

    $stm = $pdo->prepare($sql);
    $stm->execute();
    $prodotti = $stm->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Errore nel caricamento del menu: " . $e->getMessage());
}

// Funzione per le icone (Assicurati che i nomi 'case' corrispondano ai nomi nel DB categoria)
function getIconaCategoria($cat) {
    $cat = strtolower(trim($cat));
    if (strpos($cat, 'pizza') !== false) return 'fa-pizza-slice';
    if (strpos($cat, 'bibita') !== false) return 'fa-wine-bottle';
    if (strpos($cat, 'dolce') !== false) return 'fa-ice-cream';
    if (strpos($cat, 'contorno') !== false) return 'fa-carrot';
    return 'fa-utensils';
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Digitale</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .prezzo { font-weight: bold; color: #d32f2f; font-size: 1.2em; }
        
        /* Stile etichetta allergeni */
        .allergeni-box { 
            font-size: 0.85em; 
            color: #856404; 
            background-color: #fff3cd; 
            border: 1px solid #ffeeba;
            padding: 4px 8px; 
            border-radius: 4px; 
            display: inline-block; 
            margin-top: 8px; 
        }
        
        .ingr-text { color: #666; font-style: italic; font-size: 0.95em; margin-top: 4px;}
        
        /* Header appiccicoso (Sticky) */
        .cat-header { 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
            border-bottom: 1px solid #ddd;
        }
        
        /* Card Prodotto */
        .product-card {
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px !important;
            border-left: 5px solid transparent;
        }
        
        /* Bordi colorati per categoria (opzionale) */
        .cat-pizza .product-card { border-left-color: #ff9800; }
        .cat-bibita .product-card { border-left-color: #2196F3; }
        
    </style>
</head>
<body>

    <div class="w3-container w3-red w3-center w3-padding-24">
        <h1 class="w3-xxlarge" style="margin:0; font-weight:bold"><i class="fa fa-utensils"></i> Pizzeria Amato</h1>
        <p class="w3-medium w3-opacity">Scansiona, Scegli, Gusta</p>
    </div>

    <div class="w3-content" style="max-width: 800px; padding-bottom: 60px;">
        
        <?php 
        $categoriaCorrente = ""; 
        
        if(count($prodotti) > 0):
            foreach ($prodotti as $p): 
                // Controllo cambio categoria
                if ($p['categoria'] != $categoriaCorrente): 
                    $categoriaCorrente = $p['categoria'];
                    // Creo una classe CSS basata sul nome categoria per stili personalizzati
                    $cssCat = "cat-" . strtolower(str_replace(' ', '', $categoriaCorrente));
            ?>
                <div class="w3-container w3-white w3-padding-16 w3-margin-top cat-header w3-text-dark-grey">
                    <h2 style="margin:0; font-size:22px">
                        <i class="fa <?= getIconaCategoria($categoriaCorrente) ?> w3-text-red"></i> 
                        <b><?= ucfirst($categoriaCorrente) ?></b>
                    </h2> 
                </div>
            <?php endif; ?>

            <div class="w3-container">
                <div class="w3-white w3-card product-card">
                    <div class="w3-row">
                        <div class="w3-col s9">
                            <div class="w3-large w3-text-black" style="font-weight: 600;">
                                <?= htmlspecialchars($p['nome']) ?>
                            </div>
                            
                            <?php if(!empty($p['descrizione'])): ?>
                                <div class="w3-small w3-text-grey" style="margin-bottom:4px">
                                    Descrizione: <?= htmlspecialchars($p['descrizione']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($p['ingredienti'])): ?>
                                <div class="ingr-text">
                                    Ingredienti: <?= htmlspecialchars($p['ingredienti']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($p['allergeni'])): ?>
                                <div class="allergeni-box">
                                    <i class="fa fa-triangle-exclamation"></i> 
                                    Contiene: <b><?= htmlspecialchars($p['allergeni']) ?></b>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="w3-col s3 w3-right-align">
                            <span class="prezzo">€ <?= number_format($p['prezzo'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php endforeach; ?>
        <?php else: ?>
            <div class="w3-panel w3-pale-yellow w3-leftbar w3-border-yellow w3-margin-top">
                <h3>Menu in aggiornamento</h3>
                <p>Nessun prodotto disponibile al momento.</p>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>