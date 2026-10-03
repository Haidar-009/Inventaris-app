<?php
/*
 * logout.php (gabungan: proses logout + pop-up konfirmasi)
 *
 * 1. Dibuka langsung (klik "Ya, Keluar" -> logout.php): menghancurkan sesi lalu ke login.
 * 2. Di-include oleh halaman lain: hanya menampilkan pop-up konfirmasi.
 *
 * Cara pakai di setiap halaman:
 *   - Link logout:  <a href="logout.php" class="logout" data-logout data-no-fade> ... </a>
 *   - Sebelum </body>:  <?php include 'logout.php'; ?>
 *     (di folder CRUD/ pakai: href="../logout.php" dan include '../logout.php';)
 */

    // Mode file ini dibuka langsung lewat URL -> proses logout
    if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
        session_start();
        $_SESSION = [];
        session_destroy();
        header("Location: login.php?logout=1");
        exit();
    }

// Mode 2: file ini di-include -> tampilkan pop-up (kode di bawah)
?>

<style>
    .lo-overlay {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 17, 21, 0.55);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        opacity: 0;
        visibility: hidden;
        transition: opacity .25s ease, visibility .25s ease;
    }

    .lo-overlay.show { 
        opacity: 1; 
        visibility: visible; 
    }

    .lo-box {
        width: 100%;
        max-width: 380px;
        background: #191b20;
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        padding: 32px 28px 24px;
        text-align: center;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
        transform: translateY(16px) scale(.96);
        transition: transform .3s cubic-bezier(.2, .9, .3, 1.2);
    }

    .lo-overlay.show .lo-box { 
        transform: none; 
    }

    .lo-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 77, 77, 0.12);
        color: #ff4d4d;
        font-size: 30px;
    }

    .lo-box h3 { 
        font-size: 20px; 
        margin-bottom: 8px; 
    }

    .lo-box p  { 
        font-size: 14px; 
        line-height: 1.5; 
        color: #9aa0ad; 
        margin-bottom: 24px; 
    }

    .lo-actions { 
        display: flex; 
        gap: 12px; 
    }

    .lo-actions button {
        flex: 1;
        height: 44px;
        border: none;
        border-radius: 14px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background .2s ease, opacity .2s ease;
    }

    .lo-batal { 
        background: rgba(255, 255, 255, 0.08); 
        color: #fff; 
    }

    .lo-batal:hover { 
        background: rgba(255, 255, 255, 0.14); 
    }

    .lo-ya { 
        background: #ff4d4d; 
        color: #fff; 
    }

    .lo-ya:hover { 
        background: #e63e3e; 
    }

    .lo-ya:disabled { 
        opacity: .7; 
        cursor: wait; 
    }

    .lo-actions button:focus-visible { 
        outline: 2px solid #fff; 
        outline-offset: 2px; 
    }

    @media (prefers-reduced-motion: reduce) {
        .lo-overlay, .lo-box { transition: none; }
    }
</style>

<div class="lo-overlay" id="loOverlay" role="dialog" aria-modal="true"
     aria-labelledby="loJudul" aria-describedby="loIsi" aria-hidden="true">

    <div class="lo-box">

        <div class="lo-icon"><i class='bx bx-log-out'></i></div>
        <h3 id="loJudul">Keluar dari akun?</h3>
        <p id="loIsi">Anda akan keluar dari Sistem Inventaris. Pastikan semua perubahan sudah tersimpan.</p>

        <div class="lo-actions">
            <button type="button" class="lo-batal" id="loBatal">Batal</button>
            <button type="button" class="lo-ya" id="loYa">Ya, Keluar</button>
        </div>

    </div>
</div>

<script>
    (function () {
        var overlay = document.getElementById('loOverlay');
        var btnBatal = document.getElementById('loBatal');
        var btnYa = document.getElementById('loYa');
        var tujuan = 'logout.php';
        var pemicu = null;

        function buka(href, el) {
            tujuan = href || tujuan;
            pemicu = el;
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
            btnBatal.focus();
        }

        function tutup(kembalikanFokus) {
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
            if (kembalikanFokus !== false && pemicu) pemicu.focus();
        }

        // Klik pada link logout -> tampilkan pop-up, bukan langsung pindah halaman
        document.addEventListener('click', function (e) {
            var a = e.target.closest('[data-logout]');
            if (!a) return;
            e.preventDefault();
            buka(a.getAttribute('href'), a);
        });

        // Klik area gelap di luar kartu = batal
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) tutup();
        });
        btnBatal.addEventListener('click', function () { tutup(); });

        btnYa.addEventListener('click', function () {
            btnYa.disabled = true;
            btnYa.textContent = 'Keluar...';
            window.location.href = tujuan;
        });

        // Keyboard: Esc menutup, Tab berputar di antara dua tombol
        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'Escape') { tutup(); return; }
            if (e.key === 'Tab') {
                if (e.shiftKey && document.activeElement === btnBatal) { e.preventDefault(); btnYa.focus(); }
                else if (!e.shiftKey && document.activeElement === btnYa) { e.preventDefault(); btnBatal.focus(); }
            }
        });

        // Jika pengguna menekan tombol Back dan halaman dipulihkan dari cache
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                btnYa.disabled = false;
                btnYa.textContent = 'Ya, Keluar';
                tutup(false);
            }
        });
    })();
</script>