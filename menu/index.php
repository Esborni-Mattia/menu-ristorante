<?php
require 'connessione.php';

// ---- TOGGLE DISPONIBILITÀ ----
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    try {
        $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $id = (int)$_GET['toggle'];
        $sqlToggle = "UPDATE prodotto SET disponibile = IF(disponibile = 1, 0, 1) WHERE id_prodotto = :id";
        $stmtToggle = $pdo->prepare($sqlToggle);
        $stmtToggle->execute([':id' => $id]);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $msgErrore = "Errore toggle: " . $e->getMessage();
    }
}

// --- CARICAMENTO CATEGORIE ---
$categorie = [];
try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ordino le categorie: prima quelle che iniziano con "Pizza", poi le altre
    $sql_cat = "SELECT id_categoria, nome FROM categoria 
                ORDER BY CASE WHEN nome LIKE 'Pizza%' THEN 0 ELSE 1 END, nome ASC";
    $stm_cat = $pdo->prepare($sql_cat);
    $stm_cat->execute();
    $categorie = $stm_cat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $msgErrore = "Errore categorie: " . $e->getMessage();
}

// --- CONFIGURAZIONE PAGINAZIONE ---
$pag_numero = 0; $pag_voci = 10; $pag_offset = 0; $pag_totali = 0; $msgErrore = 'nessun errore';
$categoria_filtro = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
$ricerca = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Costruzione query di conteggio con filtri
    $where_conditions = [];
    $params = [];
    
    if ($categoria_filtro) {
        $where_conditions[] = 'p.id_categoria = :cat';
        $params[':cat'] = $categoria_filtro;
    }
    
    if ($ricerca) {
        $ricerca_param = '%' . $ricerca . '%';
        $where_conditions[] = '(p.nome LIKE :ricerca OR p.descrizione LIKE :ricerca)';
        $params[':ricerca'] = $ricerca_param;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql_count = 'SELECT count(*) FROM prodotto p ' . $where_clause;
    $stm = $pdo->prepare($sql_count);
    $stm->execute($params);
    
    $num_record = $stm->fetchColumn();
    $pag_totali = ceil($num_record / $pag_voci);

    if (isset($_GET['pag']) && is_numeric($_GET['pag']) && intval($_GET['pag']) > 0) {
        $pag_numero = intval($_GET['pag']) - 1;
    }
    $pag_offset = $pag_numero * $pag_voci;

    // Recupero prodotti con nome categoria e disponibilità
    if ($categoria_filtro || $ricerca) {
        $sql = 'SELECT p.id_prodotto, p.nome, p.prezzo, p.disponibile, c.nome AS categoria
                FROM prodotto p
                LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
                ' . $where_clause . '
                ORDER BY p.nome ASC LIMIT :voci OFFSET :offset';
        $stm = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stm->bindValue($key, $value);
        }
        $stm->bindValue(':voci', $pag_voci, PDO::PARAM_INT);
        $stm->bindValue(':offset', $pag_offset, PDO::PARAM_INT);
    } else {
        // Query generica: se non filtro, ordino comunque per categoria (prima pizze) e poi nome
        $sql = "SELECT p.id_prodotto, p.nome, p.prezzo, p.disponibile, c.nome AS categoria
                FROM prodotto p
                LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
                ORDER BY CASE WHEN c.nome LIKE 'Pizza%' THEN 0 ELSE 1 END, c.nome ASC, p.nome ASC 
                LIMIT :voci OFFSET :offset";
        $stm = $pdo->prepare($sql);
        $stm->bindValue(':voci', $pag_voci, PDO::PARAM_INT);
        $stm->bindValue(':offset', $pag_offset, PDO::PARAM_INT);
    }
    
    $stm->execute();
    $ris = $stm->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $msgErrore = "Errore: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Menu | Admin</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
        }

        /* --- MENU DI NAVIGAZIONE --- */
        .scroll-nav-container {
            background: #faf9f6;
            padding: 10px 0;
            margin-bottom: 15px;
        }

        .scroll-nav {
            display: flex;
            align-items: center;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            padding: 5px 10px;
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

        /* --- BOTTONE NUOVO PRODOTTO GRANDE --- */
        .btn-add-big-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .btn-add-big {
            display: inline-block;
            background: #2e7d32; /* Verde per differenziare */
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-family: 'Oswald', sans-serif;
            font-size: 1.2rem;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
            transition: all 0.3s ease;
            width: 100%;
            max-width: 300px;
        }

        .btn-add-big:hover {
            background: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
        }

        .btn-add-big i { margin-right: 10px; }

        /* --- BARRA DI RICERCA --- */
        .search-container {
            background: #faf9f6;
            padding: 15px 0;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }

        .search-box input {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #b71c1c;
            box-shadow: 0 0 8px rgba(183, 28, 28, 0.2);
        }

        .search-box button {
            padding: 12px 30px;
            background: #b71c1c;
            color: white;
            border: none;
            border-radius: 8px;
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-box button:hover {
            background: #8b0000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(183, 28, 28, 0.3);
        }

        .search-box .reset-btn {
            background: #757575;
            padding: 12px 20px;
        }

        .search-box .reset-btn:hover {
            background: #424242;
        }

        @media (max-width: 700px) {
            .search-box {
                flex-direction: column;
            }
            .search-box input,
            .search-box button {
                width: 100%;
            }
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
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .product-card:active { transform: scale(0.98); }

        .prod-info { flex: 1; }

        .prod-nome { font-size: 1.15rem; font-weight: 600; color: #222; margin: 0 0 8px 0; }
        .prod-desc { font-size: 0.9rem; color: #777; margin-bottom: 6px; line-height: 1.4; display: block; }

        /* Prezzo */
        .prezzo-container { text-align: right; display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-start; padding-left: 20px; }
        .prezzo-tag { 
            font-size: 1.2rem; 
            color: #b71c1c; 
            font-weight: 600; 
            margin: 0;
        }

        /* Azioni Admin */
        .azioni-admin {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            align-items: center;
        }

        .btn-admin {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .btn-toggle {
            background: #f0f0f0;
            color: #666;
        }

        .btn-toggle.active {
            background: #ecfdf5;
            color: #10b981;
        }

        .btn-toggle:hover { background: #d0d0d0; }
        .btn-toggle.active:hover { background: #10b981; color: white; }

        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-edit:hover { background: #1976d2; color: white; }

        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
        }

        .btn-delete:hover { background: #d32f2f; color: white; }

        /* Badge disponibilità */
        .badge-status {
            display: inline-block;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            margin-top: 6px;
            font-weight: 600;
        }

        .badge-available {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .badge-unavailable {
            background: #ef9a9a;
            color: #c62828;
        }

        /* Footer */
        .footer-info {
            text-align: center;
            font-size: 0.8rem;
            color: #aaa;
            padding: 30px 0 150px 0;
        }

        /* Paginazione */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 30px 0;
            flex-wrap: wrap;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #faf9f6;
            border-top: 2px solid #eee;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
            z-index: 999;
        }

        /* Spazio in fondo al body per evitare che il contenuto sia coperto dalla paginazione fissa */
        body.has-pagination {
            padding-bottom: 120px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #666;
            transition: 0.2s;
            min-width: 36px;
            text-align: center;
        }

        .pagination a:hover {
            background: #b71c1c;
            color: white;
            border-color: #b71c1c;
        }

        .pagination .active {
            background: #b71c1c;
            color: white;
            border-color: #b71c1c;
        }

        /* Messaggi errore */
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }

        /* --- TASTO AIUTO FLUTTUANTE (NUOVO) --- */
        .btn-help-float {
            position: fixed;
            bottom: 90px; /* Sopra la paginazione */
            right: 25px;
            width: 55px;
            height: 55px;
            background-color: #0288d1; /* Colore Azzurro Info */
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 1000;
            text-decoration: none;
            font-size: 1.4rem;
            transition: all 0.3s ease;
        }

        .btn-help-float:hover {
            background-color: #0277bd;
            transform: scale(1.1) rotate(10deg);
        }
    </style>
</head>
<body class="has-pagination">

<div class="hero-header">
    <h1>Gestione Menu</h1>
    <p>Dashboard amministrativa &bull; <?= $num_record ?> piatti totali</p>
</div>

<div class="container-lista">
    
    <div class="scroll-nav-container">
        <div class="scroll-nav">
            <a href="index.php" class="nav-chip <?= !$categoria_filtro ? 'active' : '' ?>">
                <i class="fas fa-list"></i> TUTTI
            </a>
            <?php foreach ($categorie as $cat): ?>
                <a href="index.php?cat=<?= $cat['id_categoria'] ?><?= $ricerca ? '&search=' . urlencode($ricerca) : '' ?>" 
                   class="nav-chip <?= ($categoria_filtro == $cat['id_categoria']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['nome']) ?>
                </a>
            <?php endforeach ?>
        </div>
    </div>

    <div class="btn-add-big-container">
        <a href="inserimento.php" class="btn-add-big">
            <i class="fas fa-plus-circle"></i> AGGIUNGI PRODOTTO
        </a>
    </div>

    <!-- BARRA DI RICERCA -->
    <div class="search-container">
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Cerca per nome, descrizione, ingredienti, allergeni..." 
                   value="<?= htmlspecialchars($ricerca) ?>">
            <?php if ($categoria_filtro): ?>
                <input type="hidden" name="cat" value="<?= $categoria_filtro ?>">
            <?php endif; ?>
            <button type="submit">
                <i class="fas fa-search"></i> Cerca
            </button>
            <?php if ($ricerca || $categoria_filtro): ?>
                <a href="index.php" class="reset-btn" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-times"></i> Ripristina
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($msgErrore != 'nessun errore'): ?>
        <div class="error-message"><?= htmlspecialchars($msgErrore) ?></div>
    <?php endif ?>

    <div>
        <?php foreach ($ris as $r): ?>
            <div class="product-card">
                <div class="prod-info">
                    <h3 class="prod-nome">
                        <?= htmlspecialchars($r['nome']) ?>
                    </h3>
                    <span class="prod-desc">
                        <?= htmlspecialchars($r['categoria'] ?? 'Non classificato') ?>
                    </span>
                    <div class="badge-status <?= $r['disponibile'] ? 'badge-available' : 'badge-unavailable' ?>">
                        <?= $r['disponibile'] ? '✓ DISPONIBILE' : '✗ NON DISPONIBILE' ?>
                    </div>
                    <div class="azioni-admin">
                        <a href="index.php?toggle=<?= $r['id_prodotto'] ?>" 
                           class="btn-admin btn-toggle <?= $r['disponibile'] ? 'active' : '' ?>" 
                           title="Cambia disponibilità">
                            <i class="fa <?= $r['disponibile'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                        </a>
                        <a href="modifica.php?id=<?= $r['id_prodotto'] ?>" class="btn-admin btn-edit" title="Modifica">
                            <i class="fa fa-pen-to-square"></i>
                        </a>
                        <a href="cancellaconferma.php?id=<?= $r['id_prodotto'] ?>" class="btn-admin btn-delete" title="Elimina">
                            <i class="fa fa-trash-can"></i>
                        </a>
                    </div>
                </div>
                <div class="prezzo-container">
                    <p class="prezzo-tag">€ <?= number_format($r['prezzo'], 2, ',', '.') ?></p>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $pag_totali; $i++): ?>
            <a href="index.php?pag=<?= $i ?><?= $categoria_filtro ? '&cat=' . $categoria_filtro : '' ?><?= $ricerca ? '&search=' . urlencode($ricerca) : '' ?>"
               class="<?= ($i == $pag_numero + 1) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>

<a href="paginaHelp.html" class="btn-help-float" title="Guida e Aiuto">
    <i class="fas fa-info"></i>
</a>

<div class="footer-info">
    &copy; 2026 Gestione Menu Ristorante
</div>

</body>
</html>