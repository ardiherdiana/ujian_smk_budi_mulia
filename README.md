# Ujian SMK Budi Mulia

Modul **Ujian** dari Classroom (`../landing_page`) sebagai aplikasi Laravel mandiri. Produksi:
`https://ujian.ardiherdiana.com` (VPS, `/var/www/ujian`; langkah deploy dan cara menyamakan akun ada di
`C:\projects\web\DEPLOY_SERVER.md` bagian "Ujian SMK Budi Mulia").
Laravel 13 + Inertia 3 + React 19 + Tailwind 4 + shadcn (`base-nova`), versi paket dan design system sama dengan
`landing_page`.

## Isi

Sama dengan ujian di Classroom: ruang kelas, buat ujian (terbit ke beberapa kelas sekaligus), token akses, editor soal
satu halaman (PG, PG kompleks kategori/MCMA, essay), impor soal .docx/PDF + template Word, gambar soal (disk privat),
pengerjaan siswa dengan simpan otomatis dan timer, acak soal, koreksi essay, ekspor nilai Excel multi-kelas, rekap nilai
per kelas + ekspor, daftar ujian dan kalender lintas kelas, komentar, serta ganti sandi di Profil.

Bedanya dengan Classroom:

- Tidak ada pengumuman, materi, tugas, Menu Utama, data induk (rombel, siswa, mapel, tahun ajaran, data guru), dan
  buat/ubah/arsip/hapus ruang kelas. Semua data itu **disalin dari Classroom**.
- Ujian boleh diberi lampiran berkas/tautan (di Classroom form ujian tidak punya lampiran).
- URL tanpa awalan `/classroom`: `/kelas`, `/post/{id}`, `/ujian`, `/kalender`, `/profil`.
- Kode tetap di namespace/folder `Classroom` (`App\Models\Classroom`, `resources/js/pages/classroom`) supaya mudah
  dibandingkan dengan sumbernya.

## Pasang

Butuh MySQL lokal (Laragon) dengan DB Classroom `budi_mulia` sudah terisi.

```bash
composer install
npm install --ignore-scripts
cp .env.example .env          # isi DB_PASSWORD dan CLASSROOM_DB_PASSWORD
php artisan key:generate
# buat DB `ujian` dan `ujian_testing` (utf8mb4_unicode_ci) bila belum ada
php artisan migrate
php artisan db:seed           # salin akun + ruang kelas dari Classroom
npm run build
php artisan serve             # http://localhost:8000
```

Masuk memakai username dan sandi Classroom.

## Menyamakan data dengan Classroom

`php artisan db:seed` (`SalinClassroomSeeder`) membaca koneksi `classroom` (`CLASSROOM_DB_*`, bawaan `budi_mulia`) dan
boleh dijalankan ulang kapan saja:

- Menyalin akun ADMIN/KEPSEK/GURU/SISWA (id dan hash sandi sama), tahun ajaran, mapel, rombel, siswa, dan ruang kelas.
  Tenaga kependidikan tidak ikut.
- Classroom adalah sumber kebenaran: nama, peran, status aktif, dan **sandi ditimpa**. Sandi yang diganti lewat Profil
  di aplikasi ini kembali ke sandi Classroom setelah seeder dijalankan lagi.
- Baris yang sudah dihapus di Classroom ikut dihapus bila tidak punya data ujian; bila masih punya, barisnya dibiarkan
  dan akunnya dinonaktifkan.
- Data ujian (ujian, soal, hasil, komentar, lampiran) tidak pernah disentuh.

## Perintah

```bash
composer test            # PHPUnit ke MySQL `ujian_testing`
./vendor/bin/pint        # format PHP
npm run lint             # ESLint
npm run build            # tsc -b lalu vite build
php artisan schedule:work   # opsional: menutup percobaan yang waktunya habis tiap menit
```

Tanpa penjadwal tetap aman: percobaan yang kedaluwarsa ditutup saat halaman ujian dibuka.