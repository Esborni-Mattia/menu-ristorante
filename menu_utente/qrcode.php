<?php
    // Includi la libreria
    require 'phpqrcode/qrlib.php';

    // -----------------------------------------------------------
    // RILEVAMENTO AUTOMATICO URL
    // -----------------------------------------------------------
    
    // 1. Rileva Protocollo (http o https)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    
    // 2. Rileva Host (es. 127.0.0.1, localhost, o 192.168.x.x)
    $host = $_SERVER['HTTP_HOST'];
    
    // 3. Rileva il percorso della cartella in cui si trova questo file
    $path_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Correzione per Windows (sostituisce backslash con slash)
    $path_dir = str_replace('\\', '/', $path_dir);
    
    // Rimuove eventuale slash finale per pulizia
    $path_dir = rtrim($path_dir, '/');

    // COSTRUZIONE LINK FINALE
    // Aggiunge la sottocartella dove si trova l'interfaccia utente
    $text = $protocol . $host . $path_dir . "/interfaccia_utente.php";

    // -----------------------------------------------------------
    
    // Cartella di destinazione per le immagini
    $path = 'qrimages/';
    
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    $filename = uniqid() . ".png";
    $file = $path . $filename;
  
    // Parametri QR
    $ecc = 'M';
    $pixel_Size = 10;
    $frame_Size = 2;

    QRcode::png($text, $file, $ecc, $pixel_Size, $frame_Size);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Menu</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body {
            /* Sfondo "tovaglia" o texture leggera per dare contesto */
            background-color: #f1f1f1;
            background-image: radial-gradient(#ddd 1px, transparent 1px);
            background-size: 20px 20px;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-card {
            max-width: 400px;
            width: 100%;
        }
        /* Stile specifico per la stampa */
        @media print {
            body { background: none; display: block; }
            .no-print { display: none; }
            .qr-card { 
                margin: 0 auto; 
                border: 1px solid #ccc; 
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="w3-content qr-card w3-card-4 w3-white w3-round-large w3-animate-zoom">
        
        <!-- Header della Card -->
        <div class="w3-container w3-red w3-center w3-padding-16 w3-round-large" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            <h1 class="w3-xlarge" style="margin:0; font-weight: bold; text-transform: uppercase;">
                <i class="fa fa-pizza-slice"></i> Pizzeria BER
            </h1>
            <p class="w3-small w3-opacity">Il gusto della tradizione</p>
        </div>

        <!-- Corpo Centrale -->
        <div class="w3-container w3-center w3-padding-32">
            
            <p class="w3-large w3-text-dark-grey" style="font-weight: 600;">
                Il nostro Menu Digitale
            </p>
            
            <p class="w3-text-grey">
                Inquadra il codice con la fotocamera<br>del tuo smartphone per ordinare.
            </p>

            <!-- Immagine QR -->
            <div class="w3-padding-16">
                <img src="<?= $file ?>" alt="Menu QR Code" class="w3-image w3-border w3-padding" style="border-radius: 8px; width: 220px; height: 220px;">
            </div>
            
            <!-- Mostra il link generato per debug (opzionale, utile per verificare) -->
            <div class="w3-small w3-text-grey w3-margin-bottom" style="word-break: break-all;">
                <!-- Link: <?= htmlspecialchars($text) ?> -->
            </div>

            <!-- Info aggiuntive -->
            <div class="w3-panel w3-light-grey w3-round w3-padding w3-margin-top w3-small">
                <i class="fa fa-wifi w3-text-blue"></i> Wi-Fi Clienti: <b>BER_Guest</b>
            </div>

        </div>

        <!-- Footer / Azioni -->
        <div class="w3-container w3-border-top w3-light-grey w3-padding w3-center w3-round-large" style="border-top-left-radius: 0; border-top-right-radius: 0;">
            <button onclick="window.print()" class="w3-button w3-white w3-border w3-border-grey w3-round w3-hover-red no-print">
                <i class="fa fa-print"></i> Stampa cavaliere da tavolo
            </button>
        </div>

    </div>

</body>
</html>