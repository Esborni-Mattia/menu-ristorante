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

// --- CONFIGURAZIONE PAGINAZIONE ---
$pag_numero = 0; $pag_voci = 30; $pag_offset = 0; $pag_totali = 0; $msgErrore = 'nessun errore';

try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql_count = 'SELECT count(*) FROM prodotto';
    $stm = $pdo->prepare($sql_count);
    $stm->execute();
    $num_record = $stm->fetchColumn();
    $pag_totali = ceil($num_record / $pag_voci);

    if (isset($_GET['pag']) && is_numeric($_GET['pag']) && intval($_GET['pag']) > 0) {
        $pag_numero = intval($_GET['pag']) - 1;
    }
    $pag_offset = $pag_numero * $pag_voci;

    $sql = 'SELECT p.id_prodotto, p.nome, p.prezzo, p.disponibile, c.nome AS categoria
            FROM prodotto p
            LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
            ORDER BY p.nome ASC LIMIT :voci OFFSET :offset';
    $stm = $pdo->prepare($sql);
    $stm->bindValue(':voci', $pag_voci, PDO::PARAM_INT);
    $stm->bindValue(':offset', $pag_offset, PDO::PARAM_INT);
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
    <title>Dashboard Menu | Admin</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:rgb(30, 94, 159);
            --accent: #10b981;
            --bg:rgb(19, 14, 10);
            --text-main: #1e293b;
            --text-muted:rgb(0, 0, 0);
        }

        body { 
            background-color: var(--bg); 
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
        }

        .header-admin {
            background-color: white;
            border-bottom: 1px solidrgb(240, 233, 226);
            padding: 20px 0;
            margin-bottom: 30px;
        }

        .container-lista { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
        
        /* Card Prodotto Professionale */
        .prodotto-row {
            background: white;
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solidrgb(188, 124, 15);
            transition: all 0.2s ease;
        }

        .prodotto-row:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .testi-prodotto { flex: 1; }

        .nome-titolo {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .categoria-sottotitolo {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
            display: block;
        }

        .prezzo-info {
            font-weight: 600;
            color: var(--primary);
            font-size: 1.05rem;
            padding: 0 30px;
            min-width: 120px;
            text-align: right;
        }

        /* Bottoni Minimal */
        .azioni-gruppo { display: flex; gap: 10px; }

        .btn-action {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
            background:rgba(33, 164, 230, 0.62);
            color: var(--text-muted);
            transition: all 0.2s;
            border: none;
        }

        .btn-action:hover {
            background: var(--primary);
            color: white;
        }

        .btn-edit:hover { background: #3b82f6; }
        .btn-delete:hover { background: #ef4444; }
        .btn-toggle-on { color: var(--accent); background: #ecfdf5; }
        .btn-toggle-on:hover { background: var(--accent); color: white; }

        .badge-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .w3-button.btn-primary {
            background-color: var(--primary) !important;
            color: white !important;
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
        }
    </style>
</head>
<body>

<div class="header-admin">
    <div class="container-lista w3-cell-row">
        <div class="w3-cell w3-cell-middle">
            <h2 class="w3-margin-0" style="font-weight: 600; color: var(--primary)">Gestione Menu</h2>
            <p class="w3-margin-0" style="color: var(--text-muted); font-size: 0.9rem;">
                Dashboard amministrativa &bull; <?= $num_record ?> piatti totali
            </p>
        </div>
        <div class="w3-cell w3-cell-middle w3-right-align">
            <a href="inserimento.php" class="w3-button btn-primary">
                <i class="fa fa-plus w3-small"></i> NUOVO PRODOTTO
            </a>
        </div>
    </div>
</div>

<div class="container-lista">
    <?php if ($msgErrore != 'nessun errore'): ?>
        <div class="w3-panel w3-red w3-round-large w3-small"><?= $msgErrore ?></div>
    <?php endif ?>

    <?php foreach ($ris as $r): ?>
        <div class="prodotto-row">
            
            <div class="testi-prodotto">
                <h3 class="nome-titolo">
                    <span class="badge-status" style="background-color: <?= $r['disponibile'] ? 'var(--accent)' : '#cbd5e1' ?>"></span>
                    <?= htmlspecialchars($r['nome']) ?>
                </h3>
                <span class="categoria-sottotitolo">
                    <?= htmlspecialchars($r['categoria'] ?? 'Non classificato') ?>
                </span>
            </div>

            <div class="prezzo-info">
                € <?= number_format($r['prezzo'], 2, ',', '.') ?>
            </div>

            <div class="azioni-gruppo">
                <a href="index.php?toggle=<?= $r['id_prodotto'] ?>" 
                   class="btn-action <?= $r['disponibile'] ? 'btn-toggle-on' : '' ?>" 
                   title="Visibilità">
                    <i class="fa <?= $r['disponibile'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                </a>

                <a href="modificaedit.php?id=<?= $r['id_prodotto'] ?>" class="btn-action btn-edit" title="Modifica">
                    <i class="fa fa-pen-to-square"></i>
                </a>
                
                <a href="cancellaconferma.php?id=<?= $r['id_prodotto'] ?>" class="btn-action btn-delete" title="Elimina">
                    <i class="fa fa-trash-can"></i>
                </a>
            </div>

        </div>
    <?php endforeach ?>

    <div class="w3-center w3-padding-48">
        <div class="w3-bar">
            <?php for ($i = 1; $i <= $pag_totali; $i++): ?>
                <a href="index.php?pag=<?= $i ?>" 
                   class="w3-bar-item w3-button w3-round-large <?= ($i == $pag_numero + 1) ? 'w3-dark-grey' : 'w3-white' ?> w3-margin-right"
                   style="border: 1px solid #e2e8f0">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</div>

</body>
</html>