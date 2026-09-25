<?php
    $host = "localhost";
    $user = "root"; 
    $pass = "";
    $db   = "pergudangan"; 

    // Membuat objek baru
    $koneksi = new mysqli($host, $user, $pass, $db);

    // Mengecek koneksi menggunakan property (->)
    if ($koneksi->connect_error) {
        die("Koneksi gagal: " . $koneksi->connect_error);
    }
?>