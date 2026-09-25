<?php
session_start();
include '../koneksi.php';

if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username_aktif = $_SESSION['username'];
$query_petugas = mysqli_query($koneksi, "SELECT * FROM pegawai WHERE Username='$username_aktif'");
$data_petugas = mysqli_fetch_assoc($query_petugas);

$id_petugas_otomatis = isset($data_petugas['id_petugas']) ? $data_petugas['id_petugas'] : 0;
$nama_petugas_aktif = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : $username_aktif;

date_default_timezone_set('Asia/Jakarta');
$tanggal_hari_ini = date('Y-m-d H:i:s'); 
$tanggal_tampil = date('d F Y'); 


// PROSES SIMPAN DATA

if(isset($_POST['simpan_transaksi'])) {
    $jenis_transaksi = $_POST['jenis_transaksi'];
    $kuantitas = $_POST['kuantitas'];
    $id_transaksi_baru = "TRX" . rand(10000, 99999); 
    $id_detail_baru = "DET" . rand(10000, 99999);
    $nama_penerima = '-'; 
    $id_barang = '';

    // LOGIKA 1: PEMINJAMAN, PEMASUKAN, & BARANG KELUAR (Pakai barang yang sudah ada)
    if($jenis_transaksi == 'Peminjaman' || $jenis_transaksi == 'Pemasukan' || $jenis_transaksi == 'Barang Keluar') {
        
        // Nama penerima hanya diisi jika Peminjaman/Pemasukan
        if($jenis_transaksi == 'Peminjaman' || $jenis_transaksi == 'Pemasukan') {
            $nama_penerima = $_POST['nama_penerima'];
        }
        
        $id_barang = $_POST['id_barang_tersedia'];
        
        // Simpan ke Transaksi Induk
        $query_trx = "INSERT INTO pendataan_barang (Id_Transaksi, Id_petugas, Tanggal, Nama_penerima, Jenis_transaksi) 
                      VALUES ('$id_transaksi_baru', '$id_petugas_otomatis', '$tanggal_hari_ini', '$nama_penerima', '$jenis_transaksi')";
        
        if(mysqli_query($koneksi, $query_trx)){
            // Simpan ke Transaksi Detail
            $query_detail = "INSERT INTO detail_pendataan (Id_detail, Id_Transaksi, id_barang, Kuantitas) 
                             VALUES ('$id_detail_baru', '$id_transaksi_baru', '$id_barang', '$kuantitas')";
            mysqli_query($koneksi, $query_detail);
            
            // Logika Update Stok:
            // Peminjaman & Barang Keluar (Rusak) = Stok Berkurang
            if($jenis_transaksi == 'Peminjaman' || $jenis_transaksi == 'Barang Keluar') {
                mysqli_query($koneksi, "UPDATE barang SET Stok = Stok - $kuantitas WHERE Id_barang = '$id_barang'");
            } 
            // Pemasukan (Pengembalian) = Stok Bertambah
            else if ($jenis_transaksi == 'Pemasukan') {
                mysqli_query($koneksi, "UPDATE barang SET Stok = Stok + $kuantitas WHERE Id_barang = '$id_barang'");
            }
            
            echo "<script>alert('Transaksi $jenis_transaksi berhasil disimpan!'); window.location.href = '../index.php';</script>";
        }

    // LOGIKA 2: KHUSUS BARANG MASUK (Beli/Hibah Barang Baru)
    } else if ($jenis_transaksi == 'Barang Masuk') {
        $nama_barang_baru = $_POST['nama_barang_input'];
        $jenis_barang_kategori = $_POST['jenis_kategori'];
        $sumber_dana = isset($_POST['sumber_dana']) ? $_POST['sumber_dana'] : 'Lainnya';
        
        // Generate ID Barang Baru
        $id_barang = "BRG" . rand(1000, 9999);

        // DAFTARKAN BARANG KE TABEL MASTER DULU (Ini yang mencegah error Foreign Key)
        $query_barang_baru = "INSERT INTO barang (Id_barang, Nama_barang, Stok, Jenis_barang, Sumber_dana) 
                              VALUES ('$id_barang', '$nama_barang_baru', '$kuantitas', '$jenis_barang_kategori', '$sumber_dana')";
        mysqli_query($koneksi, $query_barang_baru);

        // Catat di Transaksi Induk
        $query_trx = "INSERT INTO pendataan_barang (Id_Transaksi, Id_petugas, Tanggal, Nama_penerima, Jenis_transaksi) 
                      VALUES ('$id_transaksi_baru', '$id_petugas_otomatis', '$tanggal_hari_ini', '-', '$jenis_transaksi')";
        
        if(mysqli_query($koneksi, $query_trx)){
            // Catat di Transaksi Detail
            $query_detail = "INSERT INTO detail_pendataan (Id_detail, Id_Transaksi, id_barang, Kuantitas) 
                             VALUES ('$id_detail_baru', '$id_transaksi_baru', '$id_barang', '$kuantitas')";
            mysqli_query($koneksi, $query_detail);
            
            echo "<script>alert('Pencatatan Barang Masuk (Baru) berhasil disimpan!'); window.location.href = '../index.php';</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendataan Transaksi - Inventaris</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* CSS Dasar */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #dce4ed; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .app-wrapper { width: 100%; max-width: 1350px; height: 92vh; background-color: #191b20; border-radius: 35px; display: flex; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); }
        .sidebar { width: 100px; display: flex; flex-direction: column; align-items: center; padding: 30px 0; color: #fff; }
        .sidebar .logo-box { width: 50px; height: 50px; background-color: #faeed4; color: #191b20; border-radius: 15px; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: bold; margin-bottom: 50px; }
        .nav-links { display: flex; flex-direction: column; gap: 35px; width: 100%; align-items: center; }
        .nav-links a { color: #6b707c; font-size: 22px; text-decoration: none; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: #ffffff; }
        .logout { margin-top: auto; color: #6b707c; font-size: 24px; text-decoration: none; transition: 0.3s; }
        .logout:hover { color: #ff4d4d; }
        .main-content { flex: 1; background-color: #ffffff; border-radius: 30px; margin: 15px 15px 15px 0; padding: 40px 50px; overflow-y: auto; }
        .header { margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px; }
        .header h1 { font-size: 28px; color: #111; }
        .header p { color: #666; font-size: 14px; margin-top: 5px; }

        /* Desain Form */
        .form-container { background-color: #f9fafc; border-radius: 20px; padding: 30px; max-width: 800px; border: 1px solid #eee; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333; }
        .form-control { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid #ccc; font-size: 14px; color: #333; background-color: #fff; outline: none; transition: 0.3s; }
        .form-control:focus { border-color: #191b20; box-shadow: 0 0 0 3px rgba(25, 27, 32, 0.1); }
        .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; color: #666; }
        
        .btn-submit { background-color: #191b20; color: #fff; padding: 12px 25px; border: none; border-radius: 10px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.3s; margin-top: 10px; width: 100%;}
        .btn-submit:hover { background-color: #333; }

        /* Pengaturan Tampilan Berdasarkan JavaScript */
        #form_penerima, #grup_barang_baru { display: none; }
        #grup_barang_tersedia { display: block; }
    </style>
</head>
<body>

    <div class="app-wrapper">
        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-layer'></i></div>
            <div class="nav-links">
                <a href="../index.php" title="Overview"><i class='bx bxs-grid-alt'></i></a>
                <a href="pendataan.php" class="active" title="Pendataan Barang"><i class='bx bx-folder'></i></a>
                <a href="read.php" title="Statistik & Data"><i class='bx bx-bar-chart-alt-2'></i></a>
                <a href="update.php" title="Update Data"><i class='bx bx-cube'></i></a>
            </div>
            <a href="../logout.php" class="logout" title="Logout" onclick="return confirm('Yakin ingin keluar?')"><i class='bx bx-log-out'></i></a>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Pendataan / Transaksi Barang</h1>
                <p>Catat pergerakan barang masuk, keluar, maupun peminjaman.</p>
            </div>

            <div class="form-container">
                <form action="" method="POST">
                    
                    <!-- INFO OTOMATIS -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Petugas Bertugas</label>
                            <input type="text" class="form-control" value="<?php echo $nama_petugas_aktif; ?> (Otomatis)" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Transaksi</label>
                            <input type="text" class="form-control" value="<?php echo $tanggal_tampil; ?>" readonly>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px dashed #ccc; margin: 10px 0 25px 0;">

                    <!-- INPUT JENIS TRANSAKSI -->
                    <div class="form-group">
                        <label>Pilih Jenis Transaksi</label>
                        <select name="jenis_transaksi" id="jenis_transaksi" class="form-control" onchange="ubahTampilanForm()" required>
                            <option value="" disabled selected>-- Pilih Jenis --</option>
                            <option value="Peminjaman">1. Peminjaman</option>
                            <option value="Pemasukan">2. Pemasukan (Pengembalian)</option>
                            <option value="Barang Masuk">3. Barang Masuk (Hibah / Beli)</option>
                            <option value="Barang Keluar">4. Barang Keluar (Rusak / Afkir)</option>
                        </select>
                    </div>

                    <!-- NAMA PENERIMA (Dinamic) -->
                    <div class="form-group" id="form_penerima">
                        <label>Nama Penerima / Peminjam</label>
                        <input type="text" name="nama_penerima" id="input_penerima" class="form-control" placeholder="Masukkan nama pihak terkait...">
                    </div>

                    <hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0 20px 0;">

                    <!-- OPSI 1: JIKA PEMINJAMAN/PEMASUKAN (PILIH BARANG TERSEDIA) -->
                    <div id="grup_barang_tersedia">
                        <div class="form-group">
                            <label>Pilih Barang (Dari Database)</label>
                            <select name="id_barang_tersedia" id="input_barang_tersedia" class="form-control">
                                <option value="" disabled selected>-- Pilih Barang Tersedia --</option>
                                <?php
                                $q_barang = mysqli_query($koneksi, "SELECT * FROM barang");
                                while($brg = mysqli_fetch_assoc($q_barang)){
                                    echo "<option value='".$brg['id_barang']."'>".$brg['id_barang']." - ".$brg['Nama_barang']." (Stok: ".$brg['Stok'].")</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- OPSI 2: JIKA BARANG MASUK/KELUAR (INPUT DETAIL BARANG) -->
                    <div id="grup_barang_baru">
                        <div class="form-group">
                            <label>Nama Barang / Aset</label>
                            <input type="text" name="nama_barang_input" id="input_nama_barang" class="form-control" placeholder="Masukkan nama barang...">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Kategori / Jenis Barang</label>
                                <select name="jenis_kategori" id="input_kategori" class="form-control">
                                    <option value="" disabled selected>-- Pilih Kategori --</option>
                                    <option value="Elektronik">Elektronik & Kelistrikan</option>
                                    <option value="Furniture">Furniture & Meubelair</option>
                                    <option value="ATK">ATK / Alat Tulis</option>
                                    <option value="Peralatan Olahraga">Peralatan Olahraga</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Sumber Dana</label>
                                <select name="sumber_dana" id="input_dana" class="form-control">
                                    <option value="" disabled selected>-- Sumber Pendanaan --</option>
                                    <option value="BOS">Dana BOS</option>
                                    <option value="Hibah">Hibah / Bantuan</option>
                                    <option value="Komite">Dana Komite</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Kuantitas selalu tampil di semua jenis transaksi -->
                    <div class="form-group" style="width: 30%;">
                        <label>Kuantitas / Jumlah</label>
                        <input type="number" name="kuantitas" class="form-control" min="1" placeholder="Misal: 5" required>
                    </div>

                    <button type="submit" name="simpan_transaksi" class="btn-submit">Simpan Transaksi Pendataan</button>
                    
                </form>
            </div>

        </div>
    </div>

   <!-- JAVASCRIPT UNTUK MERUBAH TAMPILAN FORM -->
    <script>
        function ubahTampilanForm() {
            var jenis = document.getElementById("jenis_transaksi").value;
            
            var divPenerima = document.getElementById("form_penerima");
            var divTersedia = document.getElementById("grup_barang_tersedia");
            var divBarangBaru = document.getElementById("grup_barang_baru");

            var inpPenerima = document.getElementById("input_penerima");
            var inpTersedia = document.getElementById("input_barang_tersedia");
            var inpNamaBarang = document.getElementById("input_nama_barang");
            var inpKategori = document.getElementById("input_kategori");
            var inpDana = document.getElementById("input_dana");

            // KONDISI 1: PEMINJAMAN ATAU PEMASUKAN
            if(jenis === "Peminjaman" || jenis === "Pemasukan") {
                divPenerima.style.display = "block"; // Munculkan Penerima
                divTersedia.style.display = "block"; // Munculkan Dropdown Barang
                divBarangBaru.style.display = "none";
                
                inpPenerima.setAttribute("required", "true"); 
                inpTersedia.setAttribute("required", "true"); 
                
                inpNamaBarang.removeAttribute("required");
                inpKategori.removeAttribute("required");
                inpDana.removeAttribute("required");
            } 
            // KONDISI 2: BARANG KELUAR (Rusak/Afkir) -> Sama dengan di atas, tapi TANPA Penerima
            else if(jenis === "Barang Keluar") {
                divPenerima.style.display = "none"; // Sembunyikan Penerima
                divTersedia.style.display = "block"; // Munculkan Dropdown Barang (Pilih barang yang rusak)
                divBarangBaru.style.display = "none";
                
                inpPenerima.removeAttribute("required");
                inpTersedia.setAttribute("required", "true"); 
                
                inpNamaBarang.removeAttribute("required");
                inpKategori.removeAttribute("required");
                inpDana.removeAttribute("required");
            }
            // KONDISI 3: BARANG MASUK (Baru beli/hibah)
            else if(jenis === "Barang Masuk") {
                divPenerima.style.display = "none";
                divTersedia.style.display = "none"; // Sembunyikan Dropdown Barang
                divBarangBaru.style.display = "block"; // Munculkan form input detail baru
                
                inpPenerima.removeAttribute("required");
                inpTersedia.removeAttribute("required");

                inpNamaBarang.setAttribute("required", "true"); 
                inpKategori.setAttribute("required", "true"); 
                inpDana.setAttribute("required", "true"); 
            }
        }
        
        window.onload = ubahTampilanForm;
    </script>

</body>
</html>