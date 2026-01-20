<?php
require 'connessione.php';

// Ottengo i dati per la conferma della cancellazione.
try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $sql = 'SELECT * FROM prodotto where id_prodotto = :id';
    $stm = $pdo->prepare($sql);

    $stm->bindparam("id", $_GET['id']);
    $stm->execute();
    $numRighe = $stm->rowCount();

    if ($numRighe == 0) {
        $msgErrore = 'Dato non trovato.';
    } else {
        $ris = $stm->fetchAll(PDO::FETCH_ASSOC);
        $r = $ris[0];
        $msgErrore = "nessun errore";
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
    <title>Elimina Prodotto</title>
    <link rel="icon" type="image/x-icon"
        href="data:image/png;base64,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">
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
            max-width: 600px;
            margin: 0 auto;
            padding: 0 16px;
        }

        /* --- CARD --- */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            margin-bottom: 20px;
        }

        .product-details {
            background: #f9f9f9;
            border-left: 4px solid #b71c1c;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 24px;
        }

        .product-details p {
            margin: 8px 0;
            font-size: 0.95rem;
        }

        .product-details strong {
            color: #b71c1c;
            font-family: 'Oswald', sans-serif;
        }

        /* Pulsanti */
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-danger, .btn-cancel {
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

        .btn-danger {
            background: #c62828;
            color: white;
        }

        .btn-danger:hover {
            background: #8b0000;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        /* Messaggi */
        .error-message {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
            background: #ffebee;
            color: #c62828;
            font-weight: 500;
        }
    </style>
</head>

<body>

<!-- HERO HEADER -->
<div class="hero-header">
    <h1><i class="fas fa-trash-can"></i> Elimina Prodotto</h1>
    <p>Conferma l'eliminazione del piatto</p>
</div>

<div class="container-lista">

    <!-- Banner per l'errore -->
    <?php if ($msgErrore != 'nessun errore'):?>
        <div class="error-message">
            <strong>Errore!</strong> <?= htmlspecialchars($msgErrore) ?>
        </div>
    <?php endif?>

    <!-- Riepilogo di conferma -->
    <?php if ($msgErrore == 'nessun errore'):?>
        <div class="form-card">
            <h2 class="w3-center">Sei sicuro di voler eliminare?</h2>

            <div class="product-details">
                <p><strong>Nome:</strong> <?= htmlspecialchars($r['nome'])?></p>
                <p><strong>Descrizione:</strong> <?= htmlspecialchars($r['descrizione'] ?? 'N/A')?></p>
                <p><strong>Prezzo:</strong> €<?= number_format($r['prezzo'], 2, ',', '.')?></p>
                <p><strong>Disponibile:</strong> <?= ($r['disponibile'] ? '✓ Sì' : '✗ No')?></p>
            </div>

            <div class="btn-group">
                <a href="cancella.php?id=<?= $r['id_prodotto']?>" class="btn-danger"><i class="fas fa-trash-can"></i> Elimina</a>
                <a href="index.php" class="btn-cancel"><i class="fas fa-arrow-left"></i> Annulla</a>
            </div>
        </div>
    <?php endif?>
</div>

</body>
</html>
