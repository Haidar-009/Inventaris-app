<?php
    session_start();
    include '../koneksi.php';

    // Cek session login
    if(!isset($_SESSION['username'])) {
        header("Location: ../login.php");
        exit();
    }

    // ===== ROLE & KEAMANAN =====
    if(empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    function isAdmin() { return ($_SESSION['role'] ?? '') === 'admin'; }
    function csrf_ok() { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
    function e($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }

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
         */
        public function getAllBarang() {
            $data_barang = [];
            $query = "SELECT * FROM barang ORDER BY id_barang ASC";
        
            $result = $this->db->query($query);
        
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $data_barang[] = $row;
                }
            }
            return $data_barang;
        }

        /**
         * Method untuk menghapus data barang berdasarkan ID.
         * Mengembalikan nilai TRUE jika berhasil, FALSE jika gagal.
         */
        public function deleteBarang($id_barang) {
            // Mencegah SQL Injection
            $stmt = $this->db->prepare("DELETE FROM barang WHERE id_barang = ?");
            $stmt->bind_param("s", $id_barang);
            
            // query memakai prepared statement (di atas)
            
            try { return $stmt->execute(); } catch (mysqli_sql_exception $ex) { return false; }
        }
    }   
    // INSTANSIASI OBJECT & KONTROL LOGIKA
    $barangManager = new BarangManager($koneksi);

    // LOGIKA HAPUS DATA (Berjalan jika ada parameter '?hapus=...' di URL)
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
        // Hanya admin yang boleh menghapus (dicek di SERVER, bukan cuma menyembunyikan tombol)
        if(!isAdmin() || !csrf_ok()) { die('Akses ditolak.'); }
        $id_hapus = $_POST['hapus'];
        
        // Memanggil method deleteBarang
        $berhasil = $barangManager->deleteBarang($id_hapus);

        if($berhasil) {
            echo "<script>alert('Data barang berhasil dihapus!'); window.location.href = 'read.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data! Pastikan barang ini tidak sedang terikat dengan data transaksi.'); window.location.href = 'read.php';</script>";
        }
        exit(); // Hentikan script agar tidak memuat ulang seluruh halaman saat proses hapus
    }

    // Mengambil data untuk ditampilkan di tabel HTML
    $daftar_barang = $barangManager->getAllBarang(); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang - Inventaris</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* CSS Sama Persis dengan Sebelumnya */
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
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 8px; 
            color: #6b707c; 
            text-decoration: none; 
            transition: 0.3s; 
        }

        .nav-links a i { font-size: 24px; }

        .nav-links a span { 
            font-size: 12px; 
            font-weight: 500; 
        }

        .nav-links a:hover, .nav-links a.active { color: #ffffff; }

        .logout { 
            margin-top: auto; 
            color: #6b707c; 
            font-size: 24px; 
            text-decoration: none; 
            transition: 0.3s; 
        }

        .logout:hover { color: #ff4d4d; }

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

        .btn-tambah:hover { background-color: #333; }

        .table-container { 
            background: #fff; 
            border-radius: 15px; 
            border: 1px solid #eee; 
            overflow-x: auto; 
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

        tr:hover { background-color: #fdfdfd; }

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
        
        /* Menggunakan style btn-hapus yang sudah Anda buat */
        .btn-hapus { 
            color: #e74c3c; 
            background: #fdedec;
            padding: 6px 10px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-size: 14px; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
            font-weight: 600;
        }

        .btn-hapus:hover { background: #fadbd8; }

        .btn-hapus{border:none;cursor:pointer;}

        /* RESPONSIVE (HP & tablet kecil) */
        @media (max-width: 768px) {
            body { padding: 0; display: block; }

            .app-wrapper { 
                flex-direction: column-reverse; 
                height: auto; 
                min-height: 100vh; 
                border-radius: 0; 
                background: transparent; 
                box-shadow: none; 
            }

            .sidebar { 
                position: fixed; 
                bottom: 0; 
                left: 0; 
                right: 0; 
                z-index: 10; 
                width: 100%;
                height: 68px; 
                flex-direction: row; 
                justify-content: space-around; 
                padding: 0 12px; 
                background: #191b20; 
            }

            .sidebar .logo-box { display: none; }
            .nav-links { 
                flex-direction: row; 
                justify-content: space-around; 
                gap: 0; 
                flex: 1; 
            }

            .nav-links a { gap: 2px; }
            .nav-links a i { font-size: 22px; }
            .nav-links a span { font-size: 10px; }

            .logout {
                margin-top: 0; 
                margin-left: 8px; 
            }

            .main-content { 
                margin: 0; 
                border-radius: 0; 
                padding: 20px 16px 90px; 
                min-height: 100vh; 
                overflow: visible; 
            }

            .header h1 { font-size: 22px; }
        }

        /* Transisi fade in / fade out antar halaman (seamless) */
        /* Panel putih TETAP di tempat; hanya isinya yang memudar, jadi latar gelap tidak pernah terlihat */
        .main-content > * { animation: pageFadeIn 0.35s ease backwards; }
        .main-content.fade-out > * { animation: pageFadeOut 0.25s ease forwards; }
        @keyframes pageFadeIn  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pageFadeOut { from { opacity: 1; } to { opacity: 0; } }

        /* Sidebar: hanya ikon, meluncur (sliding) terbuka saat di-hover */
        .logout span { display: none; font-size: 12px; font-weight: 500; }
        @media (min-width: 769px) {
            .sidebar { position: relative; z-index: 20; }

            .sidebar::before { 
                content: ''; 
                position: absolute; 
                top: 0; 
                bottom: 0; 
                left: 0; 
                width: 100%; 
                background: #191b20; 
                border-radius: 35px 30px 30px 35px; 
                z-index: -1; 
                transition: width 0.4s ease, box-shadow 0.4s ease; 
            }

            .sidebar:hover::before { 
                width: 240px; 
                box-shadow: 6px 0 25px rgba(0,0,0,0.25); 
            }

            .nav-links a, .logout { 
                display: flex; 
                align-items: center; 
                flex-direction: row; 
                gap: 18px; 
                width: 100%; 
                padding-left: 38px; 
                white-space: nowrap; 
            }

            .logout span { display: inline; }
            .nav-links a span, .logout span { 
                opacity: 0; 
                transition: opacity 0.25s ease; 
            }

            .sidebar:hover .nav-links a span, .sidebar:hover .logout span { 
                opacity: 1; 
                transition-delay: 0.1s; 
            }
        }
        
    </style>
</head>
<body>

    <div class="app-wrapper">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-layer'></i></div>

            <div class="nav-links">
                <a href="../index.php" title="Beranda">
                    <i class='bx bxs-grid-alt'></i>
                    <span>Beranda</span>
                </a>
                <a href="pendataan.php" title="Pendataan Barang">
                    <i class='bx bx-folder'></i>
                    <span>Pendataan</span>
                </a>
                <a href="read.php" class="active" title="Statistik & Data">
                    <i class='bx bx-bar-chart-alt-2'></i>
                    <span>Master Barang</span>
                </a>
                <a href="update.php" title="Update Data">
                    <i class='bx bx-cube'></i>
                    <span>Update</span>
                </a>
                <?php if(isAdmin()): ?>
                <a href="tambah_user.php" title="Tambah User">
                    <i class='bx bx-user-plus'></i>
                    <span>Tambah User</span>
                </a>
                <?php endif; ?>
            </div>
            <a href="../logout.php" class="logout" title="Logout" data-logout data-no-fade>
                <i class='bx bx-log-out'></i>
                 <span>Logout</span>
            </a>
        </div>

        <!-- KONTEN UTAMA -->
        <div class="main-content">
            <div class="header">
                <h1>Data Master Barang</h1>
                <!-- bakal menambahkan tombol Tambah Data disini jika diperlukan :v -->
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
                            <?php if(isAdmin()): ?><th>Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Cek apakah data kosong atau tidak menggunakan fungsi empty()
                        if(empty($daftar_barang)) {
                            // Colspan diubah menjadi 7 karena ada tambahan kolom Aksi
                            echo "<tr><td colspan='" . (isAdmin() ? 7 : 6) . "' style='text-align:center; padding: 20px;'>Belum ada data barang.</td></tr>";
                        } else {
                            $no = 1;
                            
                            // perulangan FOREACH untuk membaca Array dari Object OOP
                            foreach($daftar_barang as $row) {
                                $stok_class = ($row['Stok'] < 5) ? 'badge-red' : 'badge-green';
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo e($row['id_barang']); ?></strong></td>
                            <td><?php echo e($row['Nama_barang']); ?></td>
                            <td><?php echo e($row['Jenis_barang']); ?></td>
                            <td><?php echo e($row['Sumber_dana']); ?></td>
                            <td><span class="badge <?php echo $stok_class; ?>"><?php echo e($row['Stok']); ?> Unit</span></td>
                            <?php if(isAdmin()): ?>
                            <td>
                                <form method="POST" onsubmit="return confirm('Hapus barang ini?')">
                                    <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf']; ?>">
                                    <input type="hidden" name="hapus" value="<?php echo e($row['id_barang']); ?>">
                                    <button type="submit" class="btn-hapus"><i class='bx bx-trash'></i> Hapus</button>
                                </form>
                            </td>
                            <?php endif; ?>
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
         <script>
        // Fade out isi halaman sebelum pindah (panel putih tetap, tidak jadi gelap)
        document.addEventListener('click', function (e) {
            var a = e.target.closest('a');
            if (!a || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#') return;
            if (a.target === '_blank' || a.hasAttribute('download') ||
                a.hasAttribute('onclick') || a.hasAttribute('data-no-fade')) return;
            if (a.origin !== location.origin) return;
            e.preventDefault();
            document.querySelector('.main-content').classList.add('fade-out');
            setTimeout(function () { location.href = a.href; }, 250);
        });
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) document.querySelector('.main-content').classList.remove('fade-out');
        });
    </script>
    <?php include '../logout.php'; ?>
</body>
</html>