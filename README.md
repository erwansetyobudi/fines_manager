# Fines Manager Plugin for SLiMS

**Nama Plugin:** Fines Manager  
**Author:** Erwan Setyo Budi  
**Deskripsi:** Plugin ini digunakan untuk **melihat, menambah, mengedit, dan menghapus data denda pemustaka** dalam SLiMS. Dilengkapi dengan tampilan datagrid dan formulir input yang fleksibel.

---

## 📦 Cara Install

1. Unduh repository ini atau klik tombol `Code > Download ZIP`.
2. Ekstrak folder hasil unduhan.
3. Pindahkan folder `fines_manager` ke direktori `plugins/` pada instalasi SLiMS Anda.
4. Login ke SLiMS sebagai **super admin**.
5. Masuk ke menu **Sistem > Plugins**.
6. Cari **Fines Manager** dan **geser tombol aktivasi ke kanan** untuk mengaktifkan plugin.

---

## ⚙️ Catatan Teknis

- Plugin ini akan **menambahkan dua kolom baru** ke tabel `fines`:
  - `input_date` (`DATE`) — Tanggal data denda dibuat.
  - `last_update` (`DATE`) — Tanggal data terakhir diperbarui.
  
Pastikan Anda memiliki akses database dan hak istimewa untuk mengubah struktur tabel sebelum menggunakan plugin ini.

---

## ✅ Fitur

- Tambah denda baru berdasarkan kode eksemplar.
- Edit denda yang sudah ada.
- Hapus satu atau beberapa denda sekaligus.
- Tampilkan datagrid denda dengan pencarian berdasarkan nama anggota, ID, atau keterangan.
- Penyimpanan otomatis keterangan denda berdasarkan kode eksemplar (`Overdue fines for item [kode]`).

---

## 📌 Kompatibilitas

- SLiMS 9 Bulian dan versi di atasnya

---

## 🙏 Kontribusi

Silakan fork dan pull request jika Anda ingin menyumbangkan perbaikan atau fitur tambahan.

---
## ScreeShoot
<img width="1342" height="636" alt="image" src="https://github.com/user-attachments/assets/3123e7a7-77de-4aa0-a492-b6e3c1a4e65c" />
<img width="1350" height="641" alt="image" src="https://github.com/user-attachments/assets/69409b2f-f623-4531-a1f4-2e4ce320b931" />
<img width="1362" height="642" alt="image" src="https://github.com/user-attachments/assets/e2fa003a-5e77-4b25-a161-52a728d33b10" />



MIT License © Erwan Setyo Budi
