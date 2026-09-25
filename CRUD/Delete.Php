<?php 
    include 'koneksi.php';

    if(isset($_GET['hapus'])) {
        $id_barang = $_GET['hapus'];
    
        $query_hapus = "DELETE FROM barang WHERE Id_barang='$id_barang'";
    
        if(mysqli_query($koneksi, $query_hapus)){
            echo "Data berhasil dihapus!";
        }
    }
?>