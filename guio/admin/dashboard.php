<?php
session_start();

// Hanya user yang sudah login boleh membuka halaman ini
if (!isset($_SESSION["user_id"])) {
    $_SESSION["flash"] = [
        "type"  => "warning",
        "pesan" => "Silakan login terlebih dahulu.",
    ];
    header("Location: ../login.php");
    exit;
}

$namaUser = $_SESSION["nama"] ?? "Admin";

// Token CSRF untuk permintaan ke api.php
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Notifikasi sekali tampil (mis. berhasil masuk)
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Data Siswa</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
    <link rel="stylesheet" href="../asset/css/style.css">
</head>

<body<?php if ($flash): ?>
      data-flash-type="<?= htmlspecialchars($flash["type"]) ?>"
      data-flash-msg="<?= htmlspecialchars($flash["pesan"]) ?>"<?php endif; ?>>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="logo">
            <div class="logo-icon">🎓</div>
            <h2>Aplikasi<br>Data Siswa</h2>
        </div>

        <div class="menu">
            <button class="active" onclick="showPage('daftar', this)">
                🏠 <span>Daftar Siswa</span>
            </button>

            <button onclick="showPage('tambah', this)">
                ➕ <span>Tambah Siswa</span>
            </button>

            <button onclick="logout()">
                🚪 <span>Logout</span>
            </button>
        </div>

    </aside>


    <!-- MAIN -->
    <main class="main">

        <!-- HEADER -->
        <header class="header">
            <div class="admin">
                <div class="admin-icon"><?= htmlspecialchars(strtoupper(substr($namaUser, 0, 1))) ?></div>
                <span><?= htmlspecialchars($namaUser) ?></span>
                <span>⌄</span>
            </div>
        </header>


        <section class="content">

            <!-- ================= DAFTAR SISWA ================= -->
            <div id="page-daftar" class="page active">

                <div class="page-title">
                    <div>
                        <h1>Daftar Siswa</h1>
                        <p>Kelola data siswa dengan mudah dan cepat.</p>
                    </div>

                    <button class="btn btn-primary" onclick="showPage('tambah')">
                        + Tambah Siswa
                    </button>
                </div>

                <div class="card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>NIS</th>
                                    <th>Kelas</th>
                                    <th>Jenis Kelamin</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody id="tableSiswa"></tbody>
                        </table>
                    </div>
                </div>

            </div>


            <!-- ================= TAMBAH SISWA ================= -->
            <div id="page-tambah" class="page">

                <div class="page-title">
                    <div>
                        <h1 id="formTitle">Tambah Data Siswa</h1>
                        <p>Silakan isi data siswa dengan lengkap.</p>
                    </div>
                </div>

                <div class="card form-card">

                    <form id="formSiswa">

                        <div class="form-group">
                            <label>Nama</label>
                            <input type="text" id="nama" placeholder="Masukkan nama siswa" required>
                        </div>

                        <div class="form-group">
                            <label>NIS</label>
                            <input type="text" id="nis" placeholder="Masukkan NIS" required>
                        </div>

                        <div class="form-group">
                            <label>Kelas</label>
                            <select id="kelas" required>
                                <option value="">Pilih kelas</option>
                                <option>X RPL 1</option>
                                <option>X RPL 2</option>
                                <option>XI RPL 1</option>
                                <option>XI RPL 2</option>
                                <option>XII RPL 1</option>
                                <option>XII RPL 2</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Jenis Kelamin</label>
                            <div class="radio-group">
                                <label class="radio-item">
                                    <input type="radio" name="gender" value="Laki-laki" required>
                                    Laki-laki
                                </label>

                                <label class="radio-item">
                                    <input type="radio" name="gender" value="Perempuan">
                                    Perempuan
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Hobi</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="hobi" value="Gaming">
                                    Gaming
                                </label>

                                <label class="checkbox-item">
                                    <input type="checkbox" name="hobi" value="Membaca">
                                    Membaca
                                </label>

                                <label class="checkbox-item">
                                    <input type="checkbox" name="hobi" value="Olahraga">
                                    Olahraga
                                </label>

                                <label class="checkbox-item">
                                    <input type="checkbox" name="hobi" value="Musik">
                                    Musik
                                </label>

                                <label class="checkbox-item">
                                    <input type="checkbox" name="hobi" value="Coding">
                                    Coding
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea id="alamat" placeholder="Masukkan alamat lengkap siswa..." required></textarea>
                        </div>

                        <div class="form-buttons">
                            <button type="submit" class="btn btn-primary">💾 Simpan</button>

                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                ↻ Reset
                            </button>

                            <button type="button" class="btn btn-secondary" onclick="showPage('daftar')">
                                Kembali
                            </button>
                        </div>

                    </form>

                </div>

            </div>


            <!-- ================= DETAIL SISWA ================= -->
            <div id="page-detail" class="page">

                <div class="page-title">
                    <div>
                        <h1>Detail Data Siswa</h1>
                        <p>Informasi lengkap data siswa.</p>
                    </div>
                </div>

                <div class="card detail-card">

                    <div class="detail-row">
                        <div class="detail-label">Nama</div>
                        <div>:</div>
                        <div class="detail-value" id="detailNama"></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">NIS</div>
                        <div>:</div>
                        <div class="detail-value" id="detailNis"></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Kelas</div>
                        <div>:</div>
                        <div class="detail-value" id="detailKelas"></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Jenis Kelamin</div>
                        <div>:</div>
                        <div class="detail-value" id="detailGender"></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Hobi</div>
                        <div>:</div>
                        <div class="detail-value" id="detailHobi"></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Alamat</div>
                        <div>:</div>
                        <div class="detail-value" id="detailAlamat"></div>
                    </div>

                    <div class="detail-buttons">
                        <button class="btn btn-primary" onclick="showPage('daftar')">
                            ← Kembali
                        </button>
                    </div>

                </div>

            </div>

        </section>

    </main>

</div>

<script src="../asset/js/script.js"></script>

</body>
</html>