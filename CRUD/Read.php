<?php
    session_start();
    include '../koneksi.php';

    // Cek session login
    if(!isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }

    // CLASS OOP
    class BarangManager {
        private $db;

        // Constructor menerima koneksi database
        public function __construct($koneksi) {
            $this->db = $koneksi;
        }

    /**
     * Method untuk mengambil semua data barang.
     * Mengembalikan nilai berupa Array.
     * (Sangat mudah untuk dilakukan Unit Testing secara terpisah)
     */

        public function getAllBarang() {
            $data_barang = [];
            $query = "SELECT * FROM barang ORDER BY id_barang ASC";
        
            // Menggunakan gaya OOP MySQLi (karena koneksi.php sudah OOP)
            $result = $this->db->query($query);
        
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $data_barang[] = $row;
            }
        }
        
        return $data_barang;
        }
    }   

    // INSTANSIASI OBJECT & PEMANGGILAN METHOD
    $barangManager = new BarangManager($koneksi);
    $daftar_barang = $barangManager->getAllBarang(); // $daftar_barang sekarang berisi Array
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang - Inventaris</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }

        body { 
            background-color: #dce4ed; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
        }

        .app-wrapper { 
            width: 100%; 
            max-width: 1350px; 
            height: 92vh; 
            background-color: #191b20; 
            border-radius: 35px; 
            display: flex; 
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); 
        }

        .sidebar { 
            width: 100px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 30px 0; 
            color: #fff; 
        }

        .sidebar .logo-box { 
            width: 50px; 
            height: 50px; 
            background-color: #faeed4; 
            color: #191b20; 
            border-radius: 15px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 50px; 
        }

        .nav-links { 
            display: flex; 
            flex-direction: column; 
            gap: 35px; 
            width: 100%; 
            align-items: center; 
        }

        .nav-links a { 
            color: #6b707c; 
            font-size: 22px; 
            text-decoration: none; 
            transition: 0.3s; 
        }

        .nav-links a:hover, .nav-links a.active { 
            color: #ffffff; 
        }

        .logout { 
            margin-top: auto; 
            color: #6b707c; 
            font-size: 24px; 
            text-decoration: none; 
            transition: 0.3s; 
        }

        .logout:hover { 
            color: #ff4d4d; 
        }

        .main-content { 
            flex: 1; 
            background-color: #ffffff; 
            border-radius: 30px; 
            margin: 15px 15px 15px 0; 
            padding: 40px 50px; 
            overflow-y: auto; 
        }

        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
        }

        .header h1 { 
            font-size: 28px; 
            color: #111; 
        }

        .btn-tambah { 
            background-color: #191b20;
            color: #fff; 
            padding: 10px 20px; 
            border-radius: 10px; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            transition: 0.3s; 
        }

        .btn-tambah:hover { 
            background-color: #333; 
        }

        .table-container { 
            background: #fff; 
            border-radius: 15px; 
            border: 1px solid #eee; 
            overflow: hidden; 
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
        }

        th { 
            background-color: #f8f9fa; 
            color: #555; 
            padding: 15px; 
            font-size: 14px; 
            font-weight: 600; 
            border-bottom: 2px solid #eee; 
        }

        td { 
            padding: 15px; 
            font-size: 14px; 
            color: #333; 
            border-bottom: 1px solid #eee; 
        }

        tr:hover { 
            background-color: #fdfdfd; 
        }

        .badge {
            padding: 5px 10px; 
            border-radius: 8px; 
            font-size: 12px; 
            font-weight: 600; 
        }

        .badge-green { 
            background-color: #dcf2e3; 
            color: #2ecc71; 
        }

        .badge-red { 
            background-color: #fde8e8; 
            color: #e74c3c; 
        }

        .action-btns { 
            display: flex; 
            gap: 10px; 
        }

        .btn-edit { 
            color: #3498db; 
            background: #ebf5fb; 
            padding: 6px 10px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-size: 16px; 
        }

        .btn-hapus { 
            color: #e74c3c; 
            background: #fdedec; 
            padding: 6px 10px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-size: 16px; 
        }

        .btn-edit:hover { 
            background: #d6eaf8; 
        }

        .btn-hapus:hover { 
            background: #fadbd8; 
        }

    </style>
</head>
<body>

    <div class="app-wrapper">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-layer'></i></div>
            <div class="nav-links">
                <a href="../index.php" title="Beranda"><i class='bx bxs-grid-alt'></i></a>
                <a href="read.php" class="active" title="Data Barang"><i class='bx bx-folder'></i></a> 
                <a href="pendataan.php" title="Pendataan Barang"><i class='bx bx-transfer'></i></a>
                <a href="update.php" title="Update Data"><i class='bx bx-cube'></i></a>
            </div>
            <a href="../logout.php" class="logout" title="Logout" onclick="return confirm('Yakin ingin keluar?')"><i class='bx bx-log-out'></i></a>
        </div>

        <!-- KONTEN UTAMA -->
        <div class="main-content">
            <div class="header">
                <h1>Data Master Barang</h1>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Barang</th>
                            <th>Nama Barang</th>
                            <th>Jenis Barang</th>
                            <th>Sumber Dana</th>
                            <th>Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Cek apakah data kosong atau tidak menggunakan fungsi empty()
                        if(empty($daftar_barang)) {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>Belum ada data barang.</td></tr>";
                        } else {
                            $no = 1;
                            
                            // perulangan FOREACH untuk membaca Array dari Object OOP
                            foreach($daftar_barang as $row) {
                                $stok_class = ($row['Stok'] < 5) ? 'badge-red' : 'badge-green';
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo $row['id_barang']; ?></strong></td>
                            <td><?php echo $row['Nama_barang']; ?></td>
                            <td><?php echo $row['Jenis_barang']; ?></td>
                            <td><?php echo $row['Sumber_dana']; ?></td>
                            <td><span class="badge <?php echo $stok_class; ?>"><?php echo $row['Stok']; ?> Unit</span></td>
                        </tr>
                        <?php 
                            } 
                        } // Penutup Else
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</body>
</html>