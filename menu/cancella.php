<?php
require 'connessione.php';

// --- LOGICA CANCELLAZIONE ---
$msgErrore = "nessun errore";
$esito = false; // Variabile per tracciare se è andato tutto bene

try {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = 'DELETE FROM prodotto WHERE id_prodotto = :id';
        $stm = $pdo->prepare($sql);
        $stm->bindParam(":id", $_GET['id'], PDO::PARAM_INT);
        $stm->execute();
        $numRighe = $stm->rowCount();

        if ($numRighe == 0) {
            $msgErrore = 'Nessun dato è stato cancellato (ID non trovato).';
        } else {
            $esito = true; // Cancellazione avvenuta
        }
    } else {
        $msgErrore = "ID mancante o non valido.";
    }
} catch (PDOException $e) {
    $msgErrore = "Errore database: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminazione Prodotto | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        /* --- STILI COPIATI DA INDEX.PHP E ADATTATI --- */
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
            margin-bottom: 40px;
        }

        .hero-header h1 { margin: 0; font-size: 1.8rem; letter-spacing: 1px; }
        .hero-header p { margin: 8px 0 0 0; font-size: 0.9rem; opacity: 0.95; }

        /* --- CONTAINER --- */
        .container-central {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 16px;
            text-align: center;
        }

        /* --- CARD MESSAGGIO --- */
        .msg-card {
            background: white;
            border-radius: 12px;
            padding: 40px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            border: 1px solid #eee;
        }

        .icon-status { font-size: 4rem; margin-bottom: 20px; }
        .success-color { color: #2e7d32; }
        .error-color { color: #c62828; }

        .msg-title { font-size: 1.5rem; margin-bottom: 10px; color: #333; }
        .msg-detail { color: #666; margin-bottom: 30px; font-size: 1rem; }

        /* --- BOTTONE --- */
        .btn-action {
            display: inline-block;
            background: #2e7d32; 
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-family: 'Oswald', sans-serif;
            font-size: 1.1rem;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            background: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
        }

        .footer-info {
            text-align: center;
            font-size: 0.8rem;
            color: #aaa;
            padding: 40px 0;
        }
    </style>
</head>
<body>

    <div class="hero-header">
        <h1>Gestione Menu</h1>
        <p>Operazione di eliminazione</p>
    </div>

    <div class="container-central">
        
        <div class="msg-card">
            <?php if ($esito): ?>
                <div class="icon-status success-color">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="msg-title">Prodotto Eliminato</h2>
                <p class="msg-detail">Il prodotto è stato rimosso correttamente dal menu.</p>
            <?php else: ?>
                <div class="icon-status error-color">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h2 class="msg-title">Si è verificato un errore</h2>
                <p class="msg-detail">
                    <?= htmlspecialchars($msgErrore) ?>
                </p>
            <?php endif; ?>

            <a href="index.php" class="btn-action">
                <i class="fas fa-arrow-left"></i> TORNA ALL'ELENCO
            </a>
        </div>

    </div>

    <div class="footer-info">
        &copy; 2026 Gestione Menu Ristorante
    </div>

</body>
</html>