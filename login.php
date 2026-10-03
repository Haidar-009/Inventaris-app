<?php
    session_start();
    include 'koneksi.php';

    // MEMBUAT CLASS 
    class Auth {
        // Property (Variabel di dalam class) dengan visibilitas private (Encapsulation)
        private $db;

        // Constructor: Method yang otomatis dijalankan saat object dibuat
        public function __construct($koneksi) {
            $this->db = $koneksi;
        }

        // Method untuk mengecek sesi (Jika sudah login, tidak boleh ke halaman login lagi)
        public function checkSession() {
            if(isset($_SESSION['username'])) {
                // Cek role-nya apa, lalu arahkan ke jalan yang benar
                if($_SESSION['role'] == 'wakasek') {
                    header("Location: wakasek.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            }
        }

        // Method untuk memproses login
        public function prosesLogin($username, $password) {
            // Keamanan: Mencegah SQL Injection
            $username = mysqli_real_escape_string($this->db, trim($username));
            $password = mysqli_real_escape_string($this->db, trim($password));

            // Cek tabel 'user'
            $query = "SELECT * FROM user WHERE Username='$username' AND Password='$password'";
            $cek_user = mysqli_query($this->db, $query);
        
            if(mysqli_num_rows($cek_user) > 0) {
                $data = mysqli_fetch_assoc($cek_user);
                
                // Mengambil role langsung dari database ($data['Role'])
                $this->setSesi($data['Username'], $data['Nama_lengkap'], $data['Role']);
                return true;
            } 
        
            // Jika tidak ditemukan
            return false;
        }

        // Method private untuk mendaftarkan session
        private function setSesi($username, $nama, $role) {
            $_SESSION['username'] = $username;
            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['role'] = $role;
        }   
    }


    // MENGGUNAKAN OBJECT (INSTANSIASI OOP)

    // Membuat objek baru dari class Auth dan memasukkan koneksi database
    $auth = new Auth($koneksi);

    // Memanggil method untuk mengecek session
    $auth->checkSession();

    // Variabel untuk pesan error dan menyimpan username agar tidak perlu diketik ulang
    $pesan_error = '';
    $username_lama = '';

    // Jika tombol login ditekan di form HTML
    if(isset($_POST['login'])) {
        $username_lama = trim($_POST['username']);

        // Menjalankan method prosesLogin dan menyimpan hasilnya (true/false)
        $status_login = $auth->prosesLogin($_POST['username'], $_POST['password']);
    
        // Menentukan tindakan berdasarkan hasil dari method class
        if($status_login) {
            // CEK ROLE DISINI SEBELUM PINDAH HALAMAN
            if($_SESSION['role'] == 'wakasek') {
                header("Location: wakasek.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            // Tidak ada alert() dan tidak redirect: cukup isi pesan, tampil di dalam kartu
            $pesan_error = 'Username atau password salah. Silakan coba lagi.';
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Inventaris</title>
    <!-- icon dari Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'toronto', Tahoma, Geneva, Verdana, sans-serif; 
        }

        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            background: #0f172a; 
            background-image: url('img/image.png'); 
            background-size: cover; 
            background-position: center; 
        }
        
        .wrapper { 
            width: 420px; 
            background: rgba(255, 255, 255, 0.1); 
            border: 1px solid rgba(255, 255, 255, 0.2); 
            backdrop-filter: blur(15px); 
            -webkit-backdrop-filter: blur(15px); 
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3); 
            color: #fff; 
            border-radius: 15px; 
            padding: 40px 30px; 
        }

        .wrapper h1 { 
            font-size: 36px; 
            text-align: center; 
            margin-bottom: 30px; 
        }

        /* ===== PESAN ERROR DI DALAM KARTU ===== */
        .alert-error {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(239, 68, 68, 0.25);
            border: 1px solid rgba(252, 165, 165, 0.7);
            color: #fff1f2;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            animation: geser 0.4s ease;
        }

        .alert-error i {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Efek bergetar singkat supaya pengguna sadar ada error */
        @keyframes geser {
            0%, 100% { transform: translateX(0); }
            20%      { transform: translateX(-8px); }
            40%      { transform: translateX(8px); }
            60%      { transform: translateX(-5px); }
            80%      { transform: translateX(5px); }
        }

        /* Input yang bermasalah diberi border merah lembut */
        .input-box.error input {
            border-color: rgba(252, 165, 165, 0.9);
        }

        .wrapper .input-box { 
            position: relative; 
            width: 100%; 
            height: 50px; 
            margin-bottom: 20px; 
        }

        .input-box input { 
            width: 100%; 
            height: 100%; 
            background: transparent; 
            border: 1px solid rgba(255, 255, 255, 0.5); 
            outline: none; 
            border-radius: 40px; 
            font-size: 16px; 
            color: #fff; 
            padding: 20px 45px 20px 20px; 
        }

        .input-box input:focus {
            border-color: #fff;
        }

        .input-box input::placeholder { 
            color: rgba(255, 255, 255, 0.7);
        }

        .input-box i { 
            position: absolute; 
            right: 20px; 
            top: 50%; 
            transform: translateY(-50%); 
            font-size: 20px; 
            color: #fff; 
        }

        .wrapper .btn { 
            width: 100%; 
            height: 45px; 
            background: #fff; 
            border: none; 
            outline: none; 
            border-radius: 40px; 
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); 
            cursor: pointer; 
            font-size: 16px; 
            color: #333; 
            font-weight: bold; 
            transition: .3s; 
            margin-bottom: 20px;
        }

        .wrapper .btn:hover { 
            background: #e4e4e4; 
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <form action="" method="POST">
            <h1>Login</h1>

            <?php if($pesan_error != '') { ?>
                <div class="alert-error" role="alert">
                    <i class='bx bxs-error-circle'></i>
                    <span><?php echo htmlspecialchars($pesan_error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php } ?>
            
            <div class="input-box <?php echo $pesan_error != '' ? 'error' : ''; ?>">
                <input type="text" name="username" placeholder="Username"
                       value="<?php echo htmlspecialchars($username_lama, ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo $pesan_error == '' ? 'autofocus' : ''; ?> required>
                <i class='bx bxs-user'></i>
            </div>
            
            <div class="input-box <?php echo $pesan_error != '' ? 'error' : ''; ?>">
                <input type="password" name="password" placeholder="Password"
                       <?php echo $pesan_error != '' ? 'autofocus' : ''; ?> required>
                <i class='bx bxs-lock-alt'></i>
            </div>
            
            <button type="submit" name="login" class="btn">Login</button>
            <marquee behavior="" direction="left"><p>Haidar Saefullah ©2026</p></marquee>
            
        </form>
    </div>

</body>
</html>