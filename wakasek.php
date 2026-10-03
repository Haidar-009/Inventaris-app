<?php
    session_start();
    include 'koneksi.php';

    // Validasi Akses: Hanya untuk role wakasek
    if(!isset($_SESSION['username']) || $_SESSION['role'] != 'wakasek') {
        echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href = 'login.php';</script>";
        exit();
    }

    $nama_user = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : $_SESSION['username'];

    /**
     * Class Wakasek
     * (Read-Only)
     */
    class WakasekManager {
        private $db;

        public function __construct($koneksi) {
            $this->db = $koneksi;
        }

        public function getTotalKondisiBaik() {
            $result = $this->db->query("SELECT SUM(Stok) AS total FROM barang");
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total'] ? $row['total'] : 0;
            }
            return 0;
        }

        public function getTotalDipinjam() {
            $query = "SELECT SUM(d.Kuantitas) AS total 
                    FROM detail_pendataan d 
                    JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi 
                    WHERE p.Jenis_transaksi = 'Peminjaman'";
            $result = $this->db->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total'] ? $row['total'] : 0;
            }
            return 0;
        }

        public function getTotalRusak() {
            $query = "SELECT SUM(d.Kuantitas) AS total 
                    FROM detail_pendataan d 
                    JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi 
                    WHERE p.Jenis_transaksi = 'Barang Keluar'";
            $result = $this->db->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total'] ? $row['total'] : 0;
            }
            return 0;
        }

        public function getLaporanTransaksi() {
            $data = [];
            $query = "SELECT b.Nama_barang, b.Jenis_barang, p.Tanggal, d.Kuantitas, p.Jenis_transaksi, p.Nama_penerima 
                    FROM detail_pendataan d 
                    JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi 
                    JOIN barang b ON d.id_barang = b.id_barang 
                    ORDER BY p.Tanggal DESC";
            $result = $this->db->query($query);
        
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            return $data;
        }

        public function getLaporanBarang() {
            $data = [];
            $query = "SELECT * FROM barang ORDER BY id_barang ASC";
            $result = $this->db->query($query);
        
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            return $data;
        }
    }

    $wakasek = new WakasekManager($koneksi);
    $total_baik     = $wakasek->getTotalKondisiBaik();
    $total_dipinjam = $wakasek->getTotalDipinjam();
    $total_rusak    = $wakasek->getTotalRusak();
    $lap_transaksi  = $wakasek->getLaporanTransaksi();
    $lap_barang     = $wakasek->getLaporanBarang();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Pengawas - Inventaris</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #dce4ed; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .app-wrapper { width: 100%; max-width: 1350px; height: 92vh; background-color: #191b20; border-radius: 35px; display: flex; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); overflow: hidden; }
        
        .sidebar { width: 100px; display: flex; flex-direction: column; align-items: center; padding: 30px 0; color: #fff; background-color: #191b20; }
        .sidebar .logo-box { width: 50px; height: 50px; background-color: #faeed4; color: #191b20; border-radius: 15px; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: bold; margin-bottom: 50px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .nav-links { display: flex; flex-direction: column; gap: 35px; width: 100%; align-items: center; }
        .nav-links button { background: none; border: none; color: #6b707c; font-size: 24px; cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .nav-links button span { font-size: 12px; font-weight: 500; }
        .nav-links button:hover, .nav-links button.active { color: #ffffff; }
        .logout { margin-top: auto; color: #6b707c; font-size: 24px; text-decoration: none; transition: 0.3s; display: flex; flex-direction: column; align-items: center; gap: 8px;}
        .logout span { font-size: 12px; font-weight: 500; }
        .logout:hover { color: #ff4d4d; }
        
        .main-content { flex: 1; background-color: #ffffff; border-radius: 30px; margin: 15px 15px 15px 0; padding: 40px 50px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; color: #111; }
        .user-profile { display: flex; align-items: center; gap: 10px; background-color: #f5f6f8; padding: 8px 15px; border-radius: 30px; font-weight: 600; font-size: 14px; color: #333; border: 1px solid #eee; }
        .user-profile .avatar { width: 30px; height: 30px; background-color: #191b20; color: #fff; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 16px; }
        
        .assets-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .asset-card { border-radius: 20px; padding: 25px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; }
        .asset-card h4 { font-size: 32px; color: #111; margin-bottom: 5px; z-index: 2; }
        .asset-card p { font-size: 14px; color: #555; z-index: 2; font-weight: 600; }
        .asset-icon-bg { position: absolute; right: -10px; bottom: -20px; font-size: 100px; opacity: 0.1; z-index: 1; }
        .card-baik { background-color: #e6f0fa; }
        .card-pinjam { background-color: #faefdb; }
        .card-rusak { background-color: #fde8e8; }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .section-header h3 { font-size: 20px; color: #111; }
        .btn-cetak { background-color: #191b20; color: #fff; padding: 8px 15px; border-radius: 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; transition: 0.3s; }
        .btn-cetak:hover { background-color: #333; }

        table { width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 30px; font-size: 13px; }
        th { background-color: #f8f9fa; color: #555; padding: 12px; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; color: #333; border-bottom: 1px solid #eee; }
        tr:nth-child(even) { background-color: #fdfdfd; }
        
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .b-masuk { background-color: #dcf2e3; color: #2ecc71; }
        .b-keluar { background-color: #fde8e8; color: #e74c3c; }
        .b-pinjam { background-color: #faefdb; color: #f39c12; }

        /* Tampilan Tab yang disembunyikan secara default */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        @media print {
            body { background-color: #fff; padding: 0; }
            .app-wrapper { box-shadow: none; border-radius: 0; height: auto; display: block; }
            .sidebar { display: none !important; }
            .main-content { margin: 0; padding: 0; border-radius: 0; overflow-y: visible; }
            .btn-cetak, .user-profile, .assets-grid, .header { display: none !important; }
            .section-header { border-bottom: 2px solid #000; margin-top: 0; }
            table { border: 1px solid #000; }
            th, td { border: 1px solid #ddd; }
            .tab-content { display: block !important; } /* Tampilkan semua saat di-print */
            #area-laporan-barang { page-break-before: always; }
        }
    </style>
</head>
<body>

    <div class="app-wrapper">
        
        <!-- SIDEBAR WAKASEK DENGAN NAVIGASI TAB -->
        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-check-shield'></i></div>
            <div class="nav-links">
                <!-- Tombol Navigasi JS -->
                <button class="active" onclick="bukaTab('dashboard')" id="btn-dashboard">
                    <i class='bx bxs-dashboard'></i>
                    <span>Utama</span>
                </button>
                <button onclick="bukaTab('laporan')" id="btn-laporan">
                    <i class='bx bx-file'></i>
                    <span>Stok</span>
                </button>
            </div>
            <a href="../logout.php" class="logout" title="Logout" data-logout data-no-fade>
                <i class='bx bx-log-out'></i>
                 <span>Logout</span>
            </a>
        </div>

        <!-- KONTEN UTAMA WAKASEK -->
        <div class="main-content">
            
            <div class="header">
                <div>
                    <h1>Dashboard Pengawasan</h1>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">Sistem Pemantauan Aset & Inventaris Sekolah</p>
                </div>
                <div class="user-profile">
                    <div class="avatar"><i class='bx bx-user-pin'></i></div>
                    <span><?php echo $nama_user; ?> <strong style="color: #3498db;">(Wakasek)</strong></span>
                </div>
            </div>

            <!-- TAB 1: DASHBOARD UTAMA (TRANSAKSI) -->
            <div id="dashboard" class="tab-content active">
                <div class="assets-grid">
                    <div class="asset-card card-baik">
                        <h4><?php echo $total_baik; ?> Unit</h4>
                        <p>Total Barang Kondisi Baik (Tersedia)</p>
                        <i class='bx bx-check-circle asset-icon-bg'></i>
                    </div>
                    <div class="asset-card card-pinjam">
                        <h4 style="color: #d35400;"><?php echo $total_dipinjam; ?> Unit</h4>
                        <p>Barang Sedang Dipinjam</p>
                        <i class='bx bx-time-five asset-icon-bg'></i>
                    </div>
                    <div class="asset-card card-rusak">
                        <h4 style="color: #c0392b;"><?php echo $total_rusak; ?> Unit</h4>
                        <p>Barang Keluar / Kondisi Rusak</p>
                        <i class='bx bx-error-circle asset-icon-bg'></i>
                    </div>
                </div>

                <div class="section-header">
                    <h3>Laporan Histori Transaksi</h3>
                    <button class="btn-cetak" onclick="window.print()"><i class='bx bx-printer'></i> Cetak Laporan</button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama Barang</th>
                            <th>Jenis Transaksi</th>
                            <th>Jml</th>
                            <th>Penerima/Peminjam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if(empty($lap_transaksi)) {
                            echo "<tr><td colspan='5' style='text-align:center;'>Belum ada histori transaksi.</td></tr>";
                        } else {
                            foreach($lap_transaksi as $trx) {
                                $tgl = date('d M Y', strtotime($trx['Tanggal']));
                                $badge = 'b-masuk';
                                if($trx['Jenis_transaksi'] == 'Barang Keluar') $badge = 'b-keluar';
                                if($trx['Jenis_transaksi'] == 'Peminjaman') $badge = 'b-pinjam';
                        ?>
                        <tr>
                            <td><?php echo $tgl; ?></td>
                            <td><strong><?php echo $trx['Nama_barang']; ?></strong> <br><small style="color: #888;"><?php echo $trx['Jenis_barang']; ?></small></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo $trx['Jenis_transaksi']; ?></span></td>
                            <td><?php echo $trx['Kuantitas']; ?></td>
                            <td><?php echo $trx['Nama_penerima']; ?></td>
                        </tr>
                        <?php } } ?>
                    </tbody>
                </table>
            </div>


            <!-- TAB 2: LAPORAN STOK BARANG -->
            <div id="laporan" class="tab-content">
                <div class="section-header" style="margin-top: 0;">
                    <h3>Laporan Stok Kondisi Baik (Tersedia)</h3>
                    <button class="btn-cetak" onclick="window.print()"><i class='bx bx-printer'></i> Cetak Laporan</button>
                </div>

                <table id="area-laporan-barang">
                    <thead>
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Aset</th>
                            <th>Kategori</th>
                            <th>Sumber Dana</th>
                            <th>Stok Tersedia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if(empty($lap_barang)) {
                            echo "<tr><td colspan='5' style='text-align:center;'>Tidak ada data barang.</td></tr>";
                        } else {
                            foreach($lap_barang as $brg) {
                        ?>
                        <tr>
                            <td><?php echo $brg['id_barang']; ?></td>
                            <td><strong><?php echo $brg['Nama_barang']; ?></strong></td>
                            <td><?php echo $brg['Jenis_barang']; ?></td>
                            <td><?php echo $brg['Sumber_dana']; ?></td>
                            <td><strong><?php echo $brg['Stok']; ?> Unit</strong></td>
                        </tr>
                        <?php } } ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- SCRIPT UNTUK PINDAH TAB -->
    <script>
        function bukaTab(namaTab) {
            // Sembunyikan semua konten tab
            var konten = document.getElementsByClassName("tab-content");
            for (var i = 0; i < konten.length; i++) {
                konten[i].style.display = "none";
            }

            // Hapus class 'active' dari semua tombol sidebar
            document.getElementById("btn-dashboard").classList.remove("active");
            document.getElementById("btn-laporan").classList.remove("active");

            // Tampilkan konten yang dipilih dan tandai tombolnya
            document.getElementById(namaTab).style.display = "block";
            document.getElementById("btn-" + namaTab).classList.add("active");
        }
    </script>
    <?php include 'logout.php'; ?>
</body>
</html>