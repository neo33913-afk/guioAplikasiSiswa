// =====================================================
// NOTIFIKASI (TOAST) & KONFIRMASI
// =====================================================

// Tampilkan notifikasi. tipe: success | error | warning | info
function showToast(pesan, tipe = "info", durasi = 3500) {
    let wadah = document.getElementById("toast-container");

    if (!wadah) {
        wadah = document.createElement("div");
        wadah.id = "toast-container";
        document.body.appendChild(wadah);
    }

    const ikon = { success: "✓", error: "✕", warning: "!", info: "i" };

    const toast = document.createElement("div");
    toast.className = "toast toast-" + tipe;
    toast.setAttribute("role", tipe === "error" ? "alert" : "status");

    const icon = document.createElement("span");
    icon.className = "toast-icon";
    icon.textContent = ikon[tipe] || ikon.info;

    const teks = document.createElement("span");
    teks.className = "toast-text";
    teks.textContent = pesan;

    const tutup = document.createElement("button");
    tutup.type = "button";
    tutup.className = "toast-close";
    tutup.setAttribute("aria-label", "Tutup");
    tutup.textContent = "×";

    toast.append(icon, teks, tutup);
    wadah.appendChild(toast);

    let timer;

    const hapus = () => {
        clearTimeout(timer);
        toast.classList.add("hide");
        setTimeout(() => toast.remove(), 300);
    };

    tutup.addEventListener("click", hapus);
    timer = setTimeout(hapus, durasi);
}


// Kotak konfirmasi pengganti confirm(). Mengembalikan Promise<boolean>
function konfirmasi({ judul, pesan, tombol = "Ya", tipe = "primary" }) {
    return new Promise(resolve => {
        const overlay = document.createElement("div");
        overlay.className = "modal-overlay";

        overlay.innerHTML = `
            <div class="modal" role="dialog" aria-modal="true">
                <h3 class="modal-title"></h3>
                <p class="modal-text"></p>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" data-aksi="batal">Batal</button>
                    <button type="button" class="btn ${tipe === "danger" ? "btn-danger-solid" : "btn-primary"}" data-aksi="ya"></button>
                </div>
            </div>
        `;

        overlay.querySelector(".modal-title").textContent = judul;
        overlay.querySelector(".modal-text").textContent = pesan;

        const tombolYa = overlay.querySelector('[data-aksi="ya"]');
        tombolYa.textContent = tombol;

        const selesai = hasil => {
            document.removeEventListener("keydown", onKey);
            overlay.remove();
            resolve(hasil);
        };

        const onKey = event => {
            if (event.key === "Escape") {
                selesai(false);
            }
        };

        overlay.addEventListener("click", event => {
            if (event.target === overlay || event.target.dataset.aksi === "batal") {
                selesai(false);
            } else if (event.target.dataset.aksi === "ya") {
                selesai(true);
            }
        });

        document.addEventListener("keydown", onKey);
        document.body.appendChild(overlay);
        tombolYa.focus();
    });
}


// =====================================================
// HALAMAN LOGIN
// =====================================================

// Tampilkan / sembunyikan password
function togglePassword() {
    const input = document.getElementById("password");

    input.type = input.type === "password" ? "text" : "password";
}


// =====================================================
// HALAMAN APLIKASI (DATA SISWA)
// Data disimpan di JSON online lewat api.php
// =====================================================

const API_URL = "../api.php";

let siswa = [];
let editId = null;


// Cegah XSS: data siswa disimpan di server, jadi harus di-escape saat ditampilkan
function escapeHtml(teks) {
    const peta = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };

    return String(teks).replace(/[&<>"']/g, huruf => peta[huruf]);
}


// Kirim permintaan ke api.php (tanpa body = ambil data, dengan body = ubah data)
async function apiRequest(body = null) {
    const opsi = {
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
    };

    if (body) {
        opsi.method = "POST";
        opsi.headers["Content-Type"] = "application/json";
        opsi.headers["X-CSRF-Token"] =
            document.querySelector('meta[name="csrf-token"]')?.content || "";
        opsi.body = JSON.stringify(body);
    }

    let respon;

    try {
        respon = await fetch(API_URL, opsi);
    } catch (error) {
        throw new Error("Tidak dapat terhubung ke server.");
    }

    let hasil = null;
    let teks = "";

    try {
        teks = await respon.text();
        hasil = JSON.parse(teks);
    } catch (error) {
        console.error("Respons dari " + API_URL + " bukan JSON (HTTP " + respon.status + "):", teks);
    }

    // Sesi habis: index.php akan mengarahkan ke login dengan notifikasi
    if (respon.status === 401) {
        window.location.href = "dashboard.php";
        throw new Error(hasil?.pesan || "Sesi berakhir.");
    }

    // Server tidak membalas JSON: biasanya api.php tidak ditemukan / ada error PHP
    if (hasil === null) {
        if (respon.status === 404) {
            throw new Error("api.php tidak ditemukan (HTTP 404). Pastikan api.php ada di folder utama (sejajar dengan login.php).");
        }

        throw new Error("Respons server bukan JSON (HTTP " + respon.status + "). Buka api.php langsung di browser untuk melihat error PHP-nya.");
    }

    if (!respon.ok || !hasil.ok) {
        throw new Error(hasil.pesan || "Terjadi kesalahan pada server (HTTP " + respon.status + ").");
    }

    return hasil;
}


// Ambil data siswa dari JSON online
async function muatData() {
    tampilkanPesanTabel("Memuat data siswa...");

    try {
        const hasil = await apiRequest();

        siswa = hasil.data;
        tampilkanData();
    } catch (error) {
        tampilkanPesanTabel("Gagal memuat data siswa.");
        showToast(error.message, "error");
    }
}


function tampilkanPesanTabel(pesan) {
    document.getElementById("tableSiswa").innerHTML = `
        <tr>
            <td colspan="6" class="empty">${escapeHtml(pesan)}</td>
        </tr>
    `;
}


function cariSiswa(id) {
    return siswa.find(item => item.id === id);
}


// TAMPILKAN DATA
function tampilkanData() {
    const table = document.getElementById("tableSiswa");

    if (siswa.length === 0) {
        tampilkanPesanTabel("Belum ada data siswa.");
        return;
    }

    table.innerHTML = siswa.map((data, index) => `
        <tr>
            <td>${index + 1}</td>
            <td><strong>${escapeHtml(data.nama)}</strong></td>
            <td>${escapeHtml(data.nis)}</td>
            <td><span class="badge">${escapeHtml(data.kelas)}</span></td>
            <td>${escapeHtml(data.gender)}</td>
            <td>
                <div class="action">
                    <button class="btn btn-primary btn-small" onclick="lihatDetail('${escapeHtml(data.id)}')">
                        Detail
                    </button>
                    <button class="btn btn-secondary btn-small" onclick="editSiswa('${escapeHtml(data.id)}')">
                        Edit
                    </button>
                    <button class="btn btn-danger btn-small" onclick="hapusSiswa('${escapeHtml(data.id)}')">
                        Hapus
                    </button>
                </div>
            </td>
        </tr>
    `).join("");
}


// NAVIGASI HALAMAN
function showPage(page, button = null) {
    document.querySelectorAll(".page").forEach(item => {
        item.classList.remove("active");
    });

    document.getElementById("page-" + page).classList.add("active");

    if (button) {
        document.querySelectorAll(".menu button").forEach(item => {
            item.classList.remove("active");
        });

        button.classList.add("active");
    }

    if (page === "tambah") {
        document.getElementById("formTitle").innerText =
            editId === null ? "Tambah Data Siswa" : "Edit Data Siswa";
    }
}


// SUBMIT FORM
async function simpanSiswa(event) {
    event.preventDefault();

    const nama = document.getElementById("nama").value.trim();
    const nis = document.getElementById("nis").value.trim();
    const kelas = document.getElementById("kelas").value;
    const gender = document.querySelector('input[name="gender"]:checked')?.value;

    const hobi = Array.from(
        document.querySelectorAll('input[name="hobi"]:checked')
    ).map(item => item.value);

    const alamat = document.getElementById("alamat").value.trim();

    // Validasi
    if (!nama || !nis || !kelas || !gender || !alamat) {
        showToast("Mohon lengkapi semua data siswa.", "warning");
        return;
    }

    const nisSudahAda = siswa.some(
        item => item.nis === nis && item.id !== editId
    );

    if (nisSudahAda) {
        showToast("NIS " + nis + " sudah terdaftar.", "error");
        return;
    }

    const sedangEdit = editId !== null;
    const tombol = formSiswa.querySelector('button[type="submit"]');
    const labelAsli = tombol.textContent;

    tombol.disabled = true;
    tombol.textContent = "Menyimpan...";

    try {
        const hasil = await apiRequest({
            aksi: sedangEdit ? "ubah" : "tambah",
            id: editId,
            data: { nama, nis, kelas, gender, hobi, alamat }
        });

        siswa = hasil.data;

        showToast(
            sedangEdit
                ? "Data siswa berhasil diperbarui."
                : "Data siswa berhasil ditambahkan.",
            "success"
        );

        tampilkanData();
        resetForm(false);
        showPage("daftar");
    } catch (error) {
        showToast(error.message, "error");
    } finally {
        tombol.disabled = false;
        tombol.textContent = labelAsli;
    }
}


// RESET FORM
function resetForm(beriNotif = true) {
    document.getElementById("formSiswa").reset();

    editId = null;

    document.getElementById("formTitle").innerText = "Tambah Data Siswa";

    if (beriNotif) {
        showToast("Form telah dikosongkan.", "info", 2000);
    }
}


// DETAIL
function lihatDetail(id) {
    const data = cariSiswa(id);

    if (!data) {
        return;
    }

    document.getElementById("detailNama").innerText = data.nama;
    document.getElementById("detailNis").innerText = data.nis;
    document.getElementById("detailKelas").innerText = data.kelas;
    document.getElementById("detailGender").innerText = data.gender;
    document.getElementById("detailHobi").innerText =
        data.hobi.length > 0 ? data.hobi.join(", ") : "-";
    document.getElementById("detailAlamat").innerText = data.alamat;

    showPage("detail");
}


// EDIT
function editSiswa(id) {
    const data = cariSiswa(id);

    if (!data) {
        return;
    }

    editId = id;

    document.getElementById("nama").value = data.nama;
    document.getElementById("nis").value = data.nis;
    document.getElementById("kelas").value = data.kelas;

    document.querySelectorAll('input[name="gender"]').forEach(item => {
        item.checked = item.value === data.gender;
    });

    document.querySelectorAll('input[name="hobi"]').forEach(item => {
        item.checked = data.hobi.includes(item.value);
    });

    document.getElementById("alamat").value = data.alamat;

    document.getElementById("formTitle").innerText = "Edit Data Siswa";

    showPage("tambah");
}


// HAPUS
async function hapusSiswa(id) {
    const data = cariSiswa(id);

    if (!data) {
        return;
    }

    const yakin = await konfirmasi({
        judul: "Hapus Data Siswa",
        pesan: "Apakah Anda yakin ingin menghapus data " + data.nama + "? Tindakan ini tidak dapat dibatalkan.",
        tombol: "Ya, Hapus",
        tipe: "danger"
    });

    if (!yakin) {
        return;
    }

    try {
        const hasil = await apiRequest({ aksi: "hapus", id });

        siswa = hasil.data;

        tampilkanData();

        showToast("Data " + data.nama + " berhasil dihapus.", "success");
    } catch (error) {
        showToast(error.message, "error");
    }
}


// LOGOUT
async function logout() {
    const yakin = await konfirmasi({
        judul: "Logout",
        pesan: "Apakah Anda yakin ingin keluar dari aplikasi?",
        tombol: "Ya, Logout"
    });

    if (yakin) {
        // Notifikasi "berhasil logout" ditampilkan di halaman login
        window.location.href = "logout.php";
    }
}


// =====================================================
// INISIALISASI (berlaku untuk semua halaman)
// =====================================================

// Notifikasi dari server (mis. berhasil masuk / berhasil logout)
const pesanServer = document.body.dataset.flashMsg;

if (pesanServer) {
    showToast(pesanServer, document.body.dataset.flashType || "info", 4000);
}

// Khusus halaman aplikasi
const formSiswa = document.getElementById("formSiswa");

if (formSiswa) {
    formSiswa.addEventListener("submit", simpanSiswa);
    muatData();
}