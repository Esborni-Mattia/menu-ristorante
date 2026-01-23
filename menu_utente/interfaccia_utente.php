<?php
require 'connessione.php';

// --- LOGICA DI RICERCA ---
$ricerca = isset($_GET['search']) ? trim($_GET['search']) : '';
$params = [];

try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Inizio costruzione Query
    $sql = "SELECT 
                p.id_prodotto, 
                p.nome, 
                p.prezzo, 
                p.descrizione, 
                c.nome AS categoria,
                GROUP_CONCAT(DISTINCT i.nome SEPARATOR ', ') AS ingredienti,
                GROUP_CONCAT(DISTINCT a.nome SEPARATOR ', ') AS allergeni
            FROM prodotto p
            JOIN categoria c ON p.id_categoria = c.id_categoria
            LEFT JOIN prodotto_ingrediente pi ON p.id_prodotto = pi.id_prodotto
            LEFT JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
            LEFT JOIN ingrediente_allergene ia ON i.id_ingrediente = ia.id_ingrediente
            LEFT JOIN allergene a ON ia.id_allergene = a.id_allergene
            
            WHERE p.disponibile = 1";

    // Se c'è una ricerca, aggiungo i filtri
    if ($ricerca) {
        $sql .= " AND (
            p.nome LIKE :ricerca OR 
            p.descrizione LIKE :ricerca OR 
            i.nome LIKE :ricerca OR 
            a.nome LIKE :ricerca
        )";
        $params[':ricerca'] = '%' . $ricerca . '%';
    }

    // Continuo con raggruppamento e ordinamento
    $sql .= " GROUP BY p.id_prodotto
              ORDER BY FIELD(c.nome, 
                'Pizza Classica', 'Pizza Gustosa', 'Pizza Speciale', 
                'Contorno', 
                'Bevanda analcolica', 'Bevanda alcolica', 'Acqua', 
                'Dolce'
              ), p.nome";

    $stm = $pdo->prepare($sql);
    $stm->execute($params);
    $prodotti = $stm->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Errore: " . $e->getMessage());
}

// Funzione Icone
function getIconaCategoria($cat) {
    $cat = strtolower(trim($cat));
    if (strpos($cat, 'pizza') !== false) return 'fa-pizza-slice';
    if (strpos($cat, 'acqua') !== false) return 'fa-bottle-water';
    if (strpos($cat, 'analcolica') !== false) return 'fa-glass-water';
    if (strpos($cat, 'alcolica') !== false) return 'fa-wine-glass';
    if (strpos($cat, 'dolce') !== false) return 'fa-cake-candles';
    if (strpos($cat, 'contorno') !== false) return 'fa-bowl-food';
    return 'fa-utensils';
}

// Estrazione lista categorie unica per il menu di navigazione
$listaCategorie = [];
foreach ($prodotti as $p) {
    if (!in_array($p['categoria'], $listaCategorie)) {
        $listaCategorie[] = $p['categoria'];
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Digitale</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=">
    
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <style>
        /* --- STILI GENERALI --- */
        body { 
            background-color: #faf9f6; 
            font-family: 'Roboto', sans-serif; 
            color: #333;
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

        /* --- SEARCH BOX UTENTE --- */
        .search-box-user {
            margin: 0 16px 20px 16px;
            display: flex;
            gap: 8px;
        }
        
        .search-box-user input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            outline: none;
            transition: 0.3s;
            font-family: 'Roboto', sans-serif;
        }

        .search-box-user input:focus {
            border-color: #b71c1c;
            box-shadow: 0 0 5px rgba(183, 28, 28, 0.2);
        }

        .search-box-user button {
            background: #b71c1c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            cursor: pointer;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reset-search {
            background: #757575 !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            color: white;
            flex-shrink: 0;
        }

        /* --- MENU DI NAVIGAZIONE --- */
        .scroll-nav-container {
            position: sticky;
            top: 0;
            background: #faf9f6;
            z-index: 1000;
            padding: 10px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .scroll-nav {
            display: flex;
            align-items: center;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            padding: 0 10px;
            scrollbar-width: none; 
            -ms-overflow-style: none;
        }
        .scroll-nav::-webkit-scrollbar { display: none; }
        
        .scroll-nav::after {
            content: '';
            min-width: 20px;
            height: 1px;
        }

        /* STILE TASTI NAVIGAZIONE */
        .nav-chip {
            display: inline-block;
            flex: 0 0 auto; 
            background: white;
            border: 1px solid #ddd;
            border-radius: 25px;
            padding: 8px 16px;
            margin-right: 8px;
            font-size: 14px;
            color: #555;
            text-decoration: none;
            transition: 0.3s;
            font-family: 'Oswald', sans-serif;
            letter-spacing: 0.5px;
        }
        .nav-chip:hover, .nav-chip.active {
            background: #b71c1c;
            color: white;
            border-color: #b71c1c;
        }

        @media (min-width: 700px) {
            .scroll-nav {
                flex-wrap: wrap;       
                justify-content: center; 
                overflow-x: visible;   
                white-space: normal;
            }
            .scroll-nav::after { display: none; } 
            .nav-chip { margin-bottom: 8px; }      
            .search-box-user { max-width: 500px; margin: 0 auto 20px auto; }
        }

        /* --- INTESTAZIONE CATEGORIA --- */
        .cat-title {
            margin-top: 30px;
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 4px solid #b71c1c;
            color: #2c3e50;
            scroll-margin-top: 140px; 
        }

        /* --- CARD PRODOTTO --- */
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            border: 1px solid #eee;
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        .product-card:active { transform: scale(0.98); } 

        .prod-nome { font-size: 1.15rem; font-weight: 600; color: #222; }
        .prod-desc { font-size: 0.9rem; color: #777; margin-bottom: 6px; line-height: 1.4; }
        .prod-ingr { font-size: 0.85rem; color: #555; font-style: italic; }

        /* Prezzo */
        .prezzo-container { text-align: right; display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-start; }
        .prezzo-tag { 
            font-size: 1.2rem; 
            color: #b71c1c; 
            font-weight: 600; 
        }

        /* Allergeni */
        .allergeni-pill {
            display: inline-block;
            font-size: 0.75rem;
            background-color: #fff3e0; 
            color: #e65100;
            padding: 2px 8px;
            border-radius: 4px;
            margin-top: 8px;
            font-weight: 500;
        }

        /* Footer */
        .footer-info {
            text-align: center;
            font-size: 0.8rem;
            color: #aaa;
            padding: 30px 0;
        }
    </style>
</head>
<body>

    <div class="hero-header">
        <h1 style="margin:0; font-size: 28px;"><i class="fa fa-pizza-slice"></i> Pizzeria BER</h1>
        <p style="margin:5px 0 0 0; opacity: 0.9; font-weight: 300;">Menu Digitale</p>
    </div>

    <div class="w3-content" style="max-width: 800px;">

        <form method="GET" action="interfaccia_utente.php" class="search-box-user">
            <input type="text" name="search" placeholder="Cerca piatto, ingrediente, allergene..." 
                   value="<?= htmlspecialchars($ricerca) ?>">
            <button type="submit">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($ricerca): ?>
                <a href="interfaccia_utente.php" class="reset-search" title="Rimuovi filtri">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </form>

        <?php if (!empty($listaCategorie)): ?>
        <div class="scroll-nav-container">
            <div class="scroll-nav">
                <?php foreach($listaCategorie as $catName): 
                    $anchorLink = "#cat-" . strtolower(str_replace(' ', '', $catName));
                ?>
                    <a href="<?= $anchorLink ?>" class="nav-chip">
                        <?= ucfirst($catName) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div style="padding: 0 16px;">
            <?php 
            $categoriaCorrente = ""; 
            
            if(count($prodotti) > 0):
                foreach ($prodotti as $p): 
                    // Controllo cambio categoria
                    if ($p['categoria'] != $categoriaCorrente): 
                        $categoriaCorrente = $p['categoria'];
                        $anchorId = "cat-" . strtolower(str_replace(' ', '', $categoriaCorrente));
                ?>
                    <div id="<?= $anchorId ?>" class="cat-title">
                        <h2 style="margin:0; font-size: 22px;">
                            <i class="fa <?= getIconaCategoria($categoriaCorrente) ?> w3-text-red" style="margin-right:8px; font-size: 0.9em;"></i>
                            <?= ucfirst($categoriaCorrente) ?>
                        </h2>
                    </div>
                <?php endif; ?>

                <div class="product-card">
                    <div class="w3-row">
                        <div class="w3-col s9">
                            <div class="prod-nome"><?= htmlspecialchars($p['nome']) ?></div>
                            
                            <?php if(!empty($p['descrizione'])): ?>
                                <div class="prod-desc"><?= htmlspecialchars($p['descrizione']) ?></div>
                            <?php endif; ?>

                            <?php if(!empty($p['ingredienti'])): ?>
                                <div class="prod-ingr">Ingredienti: <?= htmlspecialchars($p['ingredienti']) ?></div>
                            <?php endif; ?>

                            <?php if(!empty($p['allergeni'])): ?>
                                <div class="allergeni-pill">
                                    <i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($p['allergeni']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="w3-col s3 prezzo-container">
                            <div class="prezzo-tag"><?= number_format($p['prezzo'], 2) ?>€</div>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="w3-panel w3-pale-red w3-leftbar w3-border-red w3-margin-top w3-round" style="padding: 20px; text-align: center;">
                    <h3><i class="fa fa-search"></i> Nessun risultato</h3>
                    <p>Non abbiamo trovato nulla che corrisponda a "<strong><?= htmlspecialchars($ricerca) ?></strong>".</p>
                    <a href="interfaccia_utente.php" class="w3-button w3-red w3-round-large" style="margin-top:10px;">Vedi tutto il menu</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-info">
            &copy; <?= date("Y") ?> Pizzeria BER<br>
            Coperto e servizio inclusi
        </div>

    </div>

    <script>
        const navLinks = document.querySelectorAll('.nav-chip');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                navLinks.forEach(n => n.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>