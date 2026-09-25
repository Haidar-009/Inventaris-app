<?php
session_start();
include '../koneksi.php';

// Validasi Akses: Pastikan pengguna sudah login
if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/**
 * Class BarangUpdater
 * 
 * Class ini bertanggung jawab untuk menangani proses pembaruan (update) 
 * data barang, mulai dari mengambil data untuk form hingga mengeksekusi 
 * perubahan ke database.
 */
class BarangUpdater {
    
    /**
     * @var mysqli Menyimpan koneksi database
     */
    private $db;

    /**
     * Constructor Class
     * 
     * @param mysqli $koneksi Objek koneksi database dari file koneksi.php
     */
    public function __construct($koneksi) {
        $this->db = $koneksi;
    }

    /**
     * Mengambil seluruh data barang untuk ditampilkan di tabel utama.
     * 
     * @return array Array multidimensi berisi daftar barang.
     */
    public function getAllBarang() {
        $data = [];
        $query = "SELECT * FROM barang ORDER BY Id_barang ASC";
        $result = $this->db->query($query);
        
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }

    /**
     * Mengambil detail satu barang berdasarkan ID untuk form edit.
     * 
     * @param string $id_barang ID Barang yang ingin dicari (contoh: 'BRG1001')
     * @return array|null Mengembalikan array data barang, atau null jika tidak ditemukan.
     */
    public function getBarangById($id_barang) {
        // Mencegah SQL Injection dari parameter URL
        $id_aman = $this->db->real_escape_string($id_barang);
        $query = "SELECT * FROM barang WHERE Id_barang = '$id_aman'";
        $result = $this->db->query($query);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    /**
     * Mengeksekusi proses pembaruan data barang ke database.
     * 
     * @param string $id ID Barang (sebagai patokan, tidak diubah)
     * @param string $nama Nama Barang yang baru
     * @param string $jenis Jenis/Kategori Barang yang baru
     * @param string $sumber Sumber Dana yang baru
     * @param int $stok Jumlah stok yang baru
     * @return bool True jika update berhasil, False jika gagal.
     */
    public function updateData($id, $nama, $jenis, $sumber, $stok) {
        // Membersihkan inputan agar aman dari serangan peretas
        $id     = $this->db->real_escape_string(trim($id));
        $nama   = $this->db->real_escape_string(trim($nama));
        $jenis  = $this->db->real_escape_string(trim($jenis));
        $sumber = $this->db->real_escape_string(trim($sumber));
        
        // Memastikan stok selalu terbaca sebagai angka (Integer)
        $stok   = (int)$stok; 

        $query = "UPDATE barang SET 
                    Nama_barang = '$nama', 
                    Jenis_barang = '$jenis', 
                    Sumber_dana = '$sumber', 
                    Stok = $stok 
                  WHERE Id_barang = '$id'";
        
        return $this->db->query($query); 
    }
}

// INSTANSIASI & LOGIKA KONTROL HALAMAN (CONTROLLER)

$updater = new BarangUpdater($koneksi);

// Variabel untuk mengontrol tampilan (Tabel vs Form Edit)
$mode_edit = false; 
$data_edit = null;

/**
 * LOGIKA 1: PROSES SIMPAN UPDATE
 * Berjalan jika user menekan tombol "Simpan Perubahan Data" di Form Edit.
 */
if (isset($_POST['simpan_update'])) {
    
    $is_success = $updater->updateData(
        $_POST['id_barang'],
        $_POST['nama_barang'],
        $_POST['jenis_barang'],
        $_POST['sumber_dana'],
        $_POST['stok']
    );

    if ($is_success) {
        echo "<script>alert('Data barang berhasil diperbarui!'); window.location.href = 'update.php';</script>";
        exit();
    } else {
        echo "<script>alert('Gagal memperbarui data! Periksa koneksi Anda.');</script>";
    }
}

/**
 * LOGIKA 2: MENAMPILKAN FORM EDIT
 * Berjalan jika terdapat parameter "?id=..." pada URL.
 */
if (isset($_GET['id'])) {
    
    $mode_edit = true; 
    $data_edit = $updater->getBarangById($_GET['id']);
    
    // Validasi jika pengguna memasukkan ID palsu/acak di URL
    if (!$data_edit) {
        echo "<script>alert('Data tidak valid atau tidak ditemukan!'); window.location.href = 'update.php';</script>";
        exit();
    }
    
} 
/**
 * LOGIKA 3: MENAMPILKAN TABEL DATA (DEFAULT)
 * Berjalan jika tidak ada aksi POST atau parameter GET.
 */
else {
    $daftar_barang = $updater->getAllBarang();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Data - Inventaris</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === CSS DASAR & LAYOUT === */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #dce4ed; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .app-wrapper { width: 100%; max-width: 1350px; height: 92vh; background-color: #191b20; border-radius: 35px; display: flex; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); }
        
        /* === SIDEBAR === */
        .sidebar { width: 100px; display: flex; flex-direction: column; align-items: center; padding: 30px 0; color: #fff; }
        .sidebar .logo-box { width: 50px; height: 50px; background-color: #faeed4; color: #191b20; border-radius: 15px; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: bold; margin-bottom: 50px; }
        .nav-links { display: flex; flex-direction: column; gap: 35px; width: 100%; align-items: center; }
        .nav-links a { color: #6b707c; font-size: 22px; text-decoration: none; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: #ffffff; }
        .logout { margin-top: auto; color: #6b707c; font-size: 24px; text-decoration: none; transition: 0.3s; }
        .logout:hover { color: #ff4d4d; }
        
        /* === KONTEN UTAMA === */
        .main-content { flex: 1; background-color: #ffffff; border-radius: 30px; margin: 15px 15px 15px 0; padding: 40px 50px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; color: #111; }
        .header p { color: #666; font-size: 14px; margin-top: 5px; }

        /* === TABEL DATA === */
        .table-container { background: #fff; border-radius: 15px; border: 1px solid #eee; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8f9fa; color: #555; padding: 15px; font-size: 14px; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 15px; font-size: 14px; color: #333; border-bottom: 1px solid #eee; }
        tr:hover { background-color: #fdfdfd; }
        
        /* === BADGE & TOMBOL === */
        .badge { padding: 5px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .badge-green { background-color: #dcf2e3; color: #2ecc71; }
        .badge-red { background-color: #fde8e8; color: #e74c3c; }
        .btn-edit { background: #191b20; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-edit:hover { background: #333; }
        .btn-kembali { background: #e4f0fa; color: #191b20; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: 600; display: inline-block; margin-bottom: 20px;}

        /* === DESAIN FORM (MODE EDIT) === */
        .form-container { background-color: #f9fafc; border-radius: 20px; padding: 30px; max-width: 700px; border: 1px solid #eee; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333; }
        .form-control { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid #ccc; font-size: 14px; color: #333; background-color: #fff; outline: none; transition: 0.3s; }
        .form-control:focus { border-color: #191b20; box-shadow: 0 0 0 3px rgba(25, 27, 32, 0.1); }
        .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; color: #666; }
        .btn-submit { background-color: #191b20; color: #fff; padding: 12px 25px; border: none; border-radius: 10px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; margin-top: 10px;}
        .btn-submit:hover { background-color: #333; }
    </style>
</head>
<body>

    <div class="app-wrapper">
        <!-- === NAVIGASI SIDEBAR === -->
        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-layer'></i></div>
            <div class="nav-links">
                <a href="../index.php" title="Beranda"><i class='bx bxs-grid-alt'></i></a>
                <a href="read.php" title="Data Barang"><i class='bx bx-folder'></i></a> 
                <a href="pendataan.php" title="Pendataan Barang"><i class='bx bx-transfer'></i></a>
                <a href="update.php" class="active" title="Update Data"><i class='bx bx-cube'></i></a>
            </div>
            <a href="../logout.php" class="logout" title="Logout" onclick="return confirm('Yakin ingin keluar?')"><i class='bx bx-log-out'></i></a>
        </div>

        <!-- === KONTEN UTAMA === -->
        <div class="main-content">
            
            <?php 
            /* 
             * BLOK TAMPILAN 1: MODE FORM EDIT 
             * Ditampilkan hanya jika variabel $mode_edit bernilai True
             */
            if ($mode_edit): 
            ?>
                <!-- Tombol Kembali ke Tabel -->
                <a href="update.php" class="btn-kembali"><i class='bx bx-left-arrow-alt'></i> Kembali ke Daftar</a>
                
                <div class="header" style="margin-bottom: 20px;">
                    <div>
                        <h1>Edit Data Aset</h1>
                        <p>Perbarui informasi detail barang pada sistem.</p>
                    </div>
                </div>

                <div class="form-container">
                    <form action="" method="POST">
                        
                        <!-- Input tersembunyi/Readonly untuk Primary Key -->
                        <div class="form-group">
                            <label>ID Barang (Tidak dapat diubah)</label>
                            <input type="text" name="id_barang" class="form-control" value="<?php echo $data_edit['id_barang']; ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Barang / Aset</label>
                            <input type="text" name="nama_barang" class="form-control" value="<?php echo $data_edit['Nama_barang']; ?>" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Kategori / Jenis Barang</label>
                                <select name="jenis_barang" class="form-control" required>
                                    <option value="Elektronik" <?php if($data_edit['Jenis_barang'] == 'Elektronik') echo 'selected'; ?>>Elektronik & Kelistrikan</option>
                                    <option value="Furniture" <?php if($data_edit['Jenis_barang'] == 'Furniture') echo 'selected'; ?>>Furniture & Meubelair</option>
                                    <option value="ATK" <?php if($data_edit['Jenis_barang'] == 'ATK') echo 'selected'; ?>>ATK / Alat Tulis</option>
                                    <option value="Peralatan Olahraga" <?php if($data_edit['Jenis_barang'] == 'Peralatan Olahraga') echo 'selected'; ?>>Peralatan Olahraga</option>
                                    <option value="Lainnya" <?php if($data_edit['Jenis_barang'] == 'Lainnya') echo 'selected'; ?>>Lainnya</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Sumber Dana</label>
                                <select name="sumber_dana" class="form-control" required>
                                    <option value="BOS" <?php if($data_edit['Sumber_dana'] == 'BOS') echo 'selected'; ?>>Dana BOS</option>
                                    <option value="Hibah" <?php if($data_edit['Sumber_dana'] == 'Hibah') echo 'selected'; ?>>Hibah / Bantuan</option>
                                    <option value="Komite" <?php if($data_edit['Sumber_dana'] == 'Komite') echo 'selected'; ?>>Dana Komite</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="width: 40%;">
                            <label>Stok Tersedia</label>
                            <input type="number" name="stok" class="form-control" min="0" value="<?php echo $data_edit['Stok']; ?>" required>
                        </div>

                        <button type="submit" name="simpan_update" class="btn-submit">Simpan Perubahan Data</button>
                    </form>
                </div>

            <?php 
            /* 
             * BLOK TAMPILAN 2: MODE TABEL DATA (DEFAULT)
             * Ditampilkan jika tidak ada aksi Edit yang sedang dilakukan
             */
            else: 
            ?>
                <div class="header">
                    <div>
                        <h1>Update / Perbarui Data</h1>
                        <p>Pilih barang yang ingin Anda perbarui datanya dari tabel di bawah.</p>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Barang</th>
                                <th>Nama Barang</th>
                                <th>Jenis</th>
                                <th>Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Validasi jika data dari database kosong
                            if (empty($daftar_barang)) {
                                echo "<tr><td colspan='5' style='text-align:center; padding: 20px;'>Belum ada data barang.</td></tr>";
                            } else {
                                // Ekstraksi array data barang
                                foreach ($daftar_barang as $row) {
                                    $stok_class = ($row['Stok'] < 5) ? 'badge-red' : 'badge-green';
                            ?>
                            <tr>
                                <td><strong><?php echo $row['id_barang']; ?></strong></td>
                                <td><?php echo $row['Nama_barang']; ?></td>
                                <td><?php echo $row['Jenis_barang']; ?></td>
                                <td><span class="badge <?php echo $stok_class; ?>"><?php echo $row['Stok']; ?> Unit</span></td>
                                <td>
                                    <!-- Tombol Edit: Mengirim ID melalui URL (Method GET) -->
                                    <a href="update.php?id=<?php echo $row['id_barang']; ?>" class="btn-edit">
                                        <i class='bx bx-edit'></i> Edit Data
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                } 
                            } 
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>