<?php
    require 'phpqrcode/qrlib.php';
    $text = "https://127.0.0.1/esercizi/menu_utente/interfaccia_utente.php";
    
    // Indica dove collocare le immagini generate.
    $path = 'qrimages/';
    // Genera un nome unico per ogni immagine basato su microtime.
    $file = $path.uniqid().".png";
  
    // Robustezza agli errori ('L', 'M', 'H').
    $ecc = 'M';
    $pixel_Size = 8;
    $frame_Size = 8;

    // Genera il QR Code e lo memorizza nella cartella indicata.
    QRcode::png($text, $file, $ecc, $pixel_Size, $frame_Size);

    // Mostra l'immagine del QR code.
    echo "<img src='".$file."'>";
?>