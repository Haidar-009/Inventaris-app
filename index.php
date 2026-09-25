<?php
    session_start();
    include 'koneksi.php';

    // Cek apakah user sudah login. Jika belum, kembali ke login.php
    if(!isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }

    // Mengambil nama lengkap dari session 
    $nama_user = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : $_SESSION['username'];


    // CLASS OOP 
    class DashboardManager {
        private $db;

        public function __construct($koneksi) {
            $this->db = $koneksi;
        }

        // Method: Menghitung total seluruh stok
        public function getTotalStok() {
            $result = $this->db->query("SELECT SUM(Stok) AS total_stok FROM barang");
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total_stok'] ? $row['total_stok'] : 0;
            }
            return 0;
        }

        // Method: Menghitung total barang masuk (dari detail pendataan)
        public function getTotalMasuk() {
            $query = "SELECT SUM(d.Kuantitas) AS total_masuk 
                FROM detail_pendataan d 
                JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi 
                WHERE p.Jenis_transaksi IN ('Barang Masuk', 'Pemasukan')";

            $result = $this->db->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total_masuk'] ? $row['total_masuk'] : 0;
            }
        return 0;
        }

        // Method: Menghitung total barang keluar (dari detail pendataan)
        public function getTotalKeluar() {
            $query = "SELECT SUM(d.Kuantitas) AS total_keluar 
                    FROM detail_pendataan d 
                    JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi 
                    WHERE p.Jenis_transaksi IN ('Barang Keluar', 'Peminjaman')";
            $result = $this->db->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total_keluar'] ? $row['total_keluar'] : 0;
            }
            return 0;
        }

        // Method: Menghitung jumlah jenis barang yang perlu restok (< 5)
        public function getTotalRestok() {
            $result = $this->db->query("SELECT COUNT(*) AS restok FROM barang WHERE Stok < 5");
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['restok'] ? $row['restok'] : 0;
            }
            return 0;
        }

        // Method: Mengambil data riwayat transaksi terakhir (Return Array)
        public function getTransaksiTerakhir($limit = 4) {
            $data_transaksi = [];
            $query = "SELECT b.Nama_barang, b.Jenis_barang, b.Stok, p.Tanggal, d.Kuantitas, p.Jenis_transaksi 
                FROM detail_pendataan d 
                JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi 
                JOIN barang b ON d.id_barang = b.Id_barang 
                ORDER BY p.Tanggal DESC 
                LIMIT $limit";
        
            $result = $this->db->query($query);
        
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data_transaksi[] = $row;
                }
            }

            return $data_transaksi;
        }
    }


    $dashboard = new DashboardManager($koneksi);

    // Memasukkan hasil return dari method ke dalam variabel
    $total_stok         = $dashboard->getTotalStok();
    $total_masuk        = $dashboard->getTotalMasuk();
    $total_keluar       = $dashboard->getTotalKeluar();
    $total_restok       = $dashboard->getTotalRestok();
    $transaksi_terakhir = $dashboard->getTransaksiTerakhir(4); // Batasi 4 data

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Inventaris Barang</title>
    <!-- Import icon dari Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        /* Reset & Dasar */
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

        /* Sidebar */
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); 
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

        .nav-links a:hover, 
        .nav-links a.active { 
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

        /* Konten Utama */
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

        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
        }

        .header-right i { 
            font-size: 22px; 
            color: #555; 
            cursor: pointer; 
        }

        /* User Profile Badge */
        .user-profile { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            background-color: #f5f6f8; 
            padding: 8px 15px; 
            border-radius: 30px; 
            font-weight: 600; 
            font-size: 14px; 
            color: #333; 
        }

        .user-profile .avatar { 
            width: 30px; 
            height: 30px; 
            background-color: #191b20; 
            color: #fff; 
            border-radius: 50%; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 16px; 
        }

        /* Grid Bagian Atas (Statistik) */
        .top-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 30px; 
            margin-bottom: 40px; 
        }

        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
        }

        .section-header h3 { 
            font-size: 18px; 
            color: #111; 
        }

        /* Kartu Chart Utama */
        .chart-card { 
            background-color: #e6f0fa; 
            border-radius: 20px; 
            padding: 25px; 
            position: relative; 
        }

        .chart-card h2 { 
            font-size: 32px; 
            color: #111; 
            margin-bottom: 5px; 
        }

        .chart-card p { 
            color: #666; 
            font-size: 13px; 
        }

        .badge-dark { 
            position: absolute; 
            top: 25px; 
            right: 25px; 
            background-color: #191b20; 
            color: #fff; 
            padding: 5px 12px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: 600; 
        }

        .chart-placeholder { 
            margin-top: 20px; 
            height: 80px; 
            border-bottom: 2px dashed #b8cde0; 
            display: flex; 
            align-items: flex-end; 
            gap: 5px; 
        }

        /* Kartu Aset Kecil */
        .assets-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 15px; 
        }

        .asset-card { 
            border-radius: 20px; 
            padding: 20px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
        }

        .asset-card.purple { background-color: #e5dff2; }
        .asset-card.green  { background-color: #dcf2e3; }
        .asset-card.yellow { background-color: #faefdb; }

        .asset-card h4 { 
            font-size: 18px; 
            color: #111; 
            margin-bottom: 5px; 
        }

        .asset-card p { 
            font-size: 12px; 
            color: #666; 
        }

        .asset-icon-row { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 30px; 
        }

        .asset-icon { 
            width: 35px; 
            height: 35px; 
            background-color: #fff; 
            border-radius: 10px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 18px; 
            color: #111; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
        }

        .asset-trend { 
            font-size: 12px; 
            color: #888; 
        }

        /* Grid Bagian Bawah (List & Promo) */
        .bottom-grid { 
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 30px; 
        }

        /* List Transaksi */
        .list-header { 
            display: grid; 
            grid-template-columns: 2fr 1fr 1fr 1fr; 
            font-size: 12px; 
            color: #999; 
            margin-bottom: 15px; 
            padding: 0 10px; 
        }

        .list-item { 
            display: grid; 
            grid-template-columns: 2fr 1fr 1fr 1fr; 
            align-items: center; 
            padding: 12px 10px; 
            border-bottom: 1px solid #f0f0f0; 
            transition: 0.2s; 
        }

        .list-item:hover { 
            background-color: #f9fafc; 
            border-radius: 10px; 
        }

        .item-name { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }

        .item-icon-dark { 
            width: 40px; 
            height: 40px; 
            background-color: #191b20; 
            color: #fff; 
            border-radius: 12px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 20px; 
        }

        .item-name-text strong { 
            display: block; 
            font-size: 14px; 
            color: #111; 
        }

        .item-name-text span { 
            font-size: 12px; 
            color: #888; 
        }

        .item-val { 
            font-size: 14px; 
            color: #111; 
            font-weight: 500; 
        }

        /* Status Warna */
        .item-status.plus  { color: #2ecc71; }
        .item-status.minus { color: #e74c3c; }
        .item-status.blue  { color: #3498db; }

        /* Kartu Promo */
        .promo-card { 
            background-color: #191b20; 
            border-radius: 25px; 
            padding: 30px; 
            color: #fff; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
        }

        .promo-card h3 { 
            font-size: 24px; 
            margin-bottom: 15px; 
            line-height: 1.3; 
        }

        .promo-card h3 span { 
            border: 1px solid rgba(255,255,255,0.3); 
            padding: 2px 10px; 
            border-radius: 20px; 
            font-weight: normal; 
        }

        .promo-card p { 
            font-size: 13px; 
            color: #aaa; 
            margin-bottom: 25px; 
            line-height: 1.5; 
        }

        .promo-btn { 
            background-color: #e4f0fa; 
            color: #191b20;
            padding: 10px 20px; 
            border-radius: 15px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 13px; 
            display: inline-block; 
            text-align: center; 
            width: max-content; 
        }

    </style>
</head>
<body>

    <div class="app-wrapper">
        
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-layer'></i></div>
            <div class="nav-links">
                <a href="index.php" class="active" title="Beranda"><i class='bx bxs-grid-alt'></i></a>
                <a href="CRUD/pendataan.php" title="Pendataan Barang"><i class='bx bx-folder'></i></a>
                <a href="CRUD/read.php" title="Statistik & Data"><i class='bx bx-bar-chart-alt-2'></i></a>
                <a href="CRUD/update.php" title="Update Data"><i class='bx bx-cube'></i></a>
            </div>
            <a href="logout.php" class="logout" title="Logout" onclick="return confirm('Yakin ingin keluar?')">
                <i class='bx bx-log-out'></i>
            </a>
        </div>

        <!-- KONTEN UTAMA -->
        <div class="main-content">
            
            <div class="header">
                <h1>Overview</h1>
                <div class="header-right">
                    <i class='bx bx-search'></i>
                    <i class='bx bx-bell'></i>
                    <div class="user-profile">
                        <div class="avatar"><i class='bx bx-user'></i></div>
                        <span><?php echo $nama_user; ?> <i class='bx bx-chevron-down' style="font-size: 16px;"></i></span>
                    </div>
                </div>
            </div>

            <!-- Bagian Atas: Chart & Kartu Warna DINAMIS -->
            <div class="top-grid">
                
                <div>
                    <div class="section-header">
                        <h3>Ringkasan Stok</h3>
                    </div>

                    <!-- Mengambil Variabel PHP dari Object Method -->
                    <div class="chart-card">
                        <h2><?php echo $total_stok; ?> Unit</h2>
                        <p>Total Barang Tersedia</p>
                        <div class="badge-dark">Gudang Utama</div>
                        
                        <div class="chart-placeholder"></div>
                    </div>
                </div>

                <div>
                    <div class="section-header">
                        <h3>Status Aset (Riwayat)</h3>
                        <i class='bx bx-slider-alt' style="color: #666; font-size: 20px;"></i>
                    </div>
                    <!-- Grid 3 Kartu Pastel Dinamis -->
                    <div class="assets-grid">
                        <div class="asset-card purple">
                            <div>
                                <h4><?php echo $total_masuk; ?> Unit</h4>
                                <p>Barang Masuk</p>
                            </div>
                            <div class="asset-icon-row">
                                <div class="asset-icon"><i class='bx bx-download'></i></div>
                                <span class="asset-trend">+</span>
                            </div>
                        </div>

                        <div class="asset-card green">
                            <div>
                                <h4><?php echo $total_keluar; ?> Unit</h4>
                                <p>Barang Keluar</p>
                            </div>
                            <div class="asset-icon-row">
                                <div class="asset-icon"><i class='bx bx-upload'></i></div>
                                <span class="asset-trend">-</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bagian Bawah: Tabel List & Promo -->
            <div class="bottom-grid">
                
                <div>
                    <div class="section-header">
                        <h3>Transaksi Terakhir</h3>
                    </div>

                    <div class="list-header">
                        <span>Nama Barang</span>
                        <span>Trx / Kuantitas</span>
                        <span>Sisa Stok</span>
                        <span>Tgl Transaksi</span>
                    </div>

                    <?php
                    // Mengecek apakah array transaksi kosong atau tidak
                    if (empty($transaksi_terakhir)) {
                        echo "<div style='text-align: center; color: #999; margin-top: 20px;'>Belum ada riwayat transaksi.</div>";
                    } else {
                        // Menggunakan Foreach untuk membaca Array dari Object OOP
                        foreach ($transaksi_terakhir as $row) {
                            
                            // Format Tanggal
                            $tgl_format = date('d M Y', strtotime($row['Tanggal']));
                            
                            // Menentukan icon berdasarkan Jenis Transaksi
                            $icon = "bx-box"; // Default icon
                            if ($row['Jenis_transaksi'] == 'Barang Masuk' || $row['Jenis_transaksi'] == 'Pemasukan') {
                                $icon = "bx-down-arrow-alt"; // Icon panah ke bawah (masuk)
                            } else {
                                $icon = "bx-up-arrow-alt"; // Icon panah ke atas (keluar)
                            }

                            // Menentukan Warna Status Sisa Stok
                            $status_class = ($row['Stok'] < 5) ? 'minus' : 'plus';
                            $teks_status = ($row['Stok'] < 5) ? 'Hampir Habis' : 'Aman';
                    ?>
                    <!-- HTML untuk List Item Dinamis -->
                    <div class="list-item">
                        <div class="item-name">
                            <div class="item-icon-dark"><i class='bx <?php echo $icon; ?>'></i></div>
                            <div class="item-name-text">
                                <strong><?php echo $row['Nama_barang']; ?></strong>
                                <span><?php echo $row['Jenis_barang']; ?></span>
                            </div>
                        </div>
                        <div class="item-val item-status blue">
                            <?php echo $row['Jenis_transaksi']; ?> 
                            (<strong><?php echo $row['Kuantitas']; ?></strong>)
                        </div>
                        <div class="item-val item-status <?php echo $status_class; ?>">
                            <?php echo $row['Stok']; ?> Unit (<?php echo $teks_status; ?>)
                        </div>
                        <div class="item-val" style="color: #888;"><?php echo $tgl_format; ?></div>
                    </div>
                    <?php 
                        } // Akhir foreach loop
                    } // Akhir else
                    ?>

                </div>

                <!-- Promo / Info Card Hitam -->
                <div class="promo-card">
                    <div>
                        <h3>Kelola <span>Data</span> Inventaris dengan Mudah!</h3>
                        <p>Akses fitur pendataan, laporan, dan statistik dalam satu platform terpusat khusus untuk pergudangan.</p>
                    </div>
                    <a href="CRUD/pendataan.php" class="promo-btn">Mulai Pendataan</a>
                </div>

            </div>

        </div>
    </div>

</body>
</html>