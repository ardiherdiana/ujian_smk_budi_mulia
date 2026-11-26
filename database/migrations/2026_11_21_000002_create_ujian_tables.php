<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel modul ujian, disalin dari skema Classroom (landing_page) per 21 Nov 2026, termasuk perubahan migrasi
 * lanjutannya (format soal TKA, grupId multi-kelas, token akses, gambar soal).
 *
 * Bedanya dengan sumber: `classroom_posts.tipe` hanya `UJIAN` (pengumuman, materi, dan tugas tidak ada di aplikasi ini)
 * dan `lampiran` tanpa `pengumpulanId` (tidak ada tabel pengumpulan tugas). Kolom tugas di `classroom_posts`
 * (`deadline`, `poinMaks`, `izinTerlambat`) dibiarkan supaya kode dan bentuk data tetap sama dengan Classroom.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE `classroom_posts` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `kelasId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `grupId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tipe` enum('UJIAN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `judul` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `isi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
              `deadline` datetime(3) DEFAULT NULL,
              `poinMaks` smallint unsigned DEFAULT NULL,
              `izinTerlambat` tinyint(1) NOT NULL DEFAULT '1',
              `durasiMenit` smallint unsigned DEFAULT NULL,
              `mulaiAt` datetime(3) DEFAULT NULL,
              `selesaiAt` datetime(3) DEFAULT NULL,
              `acakSoal` tinyint(1) NOT NULL DEFAULT '0',
              `tampilkanNilai` tinyint(1) NOT NULL DEFAULT '1',
              `tokenAkses` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `dibuatOleh` int NOT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `posts_kelasId_createdAt_idx` (`kelasId`,`createdAt`),
              KEY `posts_dibuatOleh_fkey` (`dibuatOleh`),
              KEY `posts_grupId_idx` (`grupId`),
              CONSTRAINT `posts_dibuatOleh_fkey` FOREIGN KEY (`dibuatOleh`) REFERENCES `profil_akun` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `posts_kelasId_fkey` FOREIGN KEY (`kelasId`) REFERENCES `kelas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `lampiran` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `postId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tipe` enum('FILE','LINK') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `ukuran` int unsigned DEFAULT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `lampiran_postId_fkey` (`postId`),
              CONSTRAINT `lampiran_postId_fkey` FOREIGN KEY (`postId`) REFERENCES `classroom_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `komentar` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `postId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `userId` int NOT NULL,
              `isi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `komentar_postId_createdAt_idx` (`postId`,`createdAt`),
              KEY `komentar_userId_fkey` (`userId`),
              CONSTRAINT `komentar_postId_fkey` FOREIGN KEY (`postId`) REFERENCES `classroom_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `komentar_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `profil_akun` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `soal` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `postId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `urut` smallint unsigned NOT NULL,
              `tipe` enum('PG','PGK_KATEGORI','PGK_MCMA','ESSAY') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `teks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `gambar` json DEFAULT NULL,
              `kompetensi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
              `subKompetensi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
              `poin` decimal(5,2) NOT NULL DEFAULT '1.00',
              `opsi` json DEFAULT NULL,
              `opsiGambar` json DEFAULT NULL,
              `kunci` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `soal_postId_urut_idx` (`postId`,`urut`),
              CONSTRAINT `soal_postId_fkey` FOREIGN KEY (`postId`) REFERENCES `classroom_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `percobaan` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `postId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `siswaId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `mulaiAt` datetime(3) NOT NULL,
              `batasAt` datetime(3) NOT NULL,
              `selesaiAt` datetime(3) DEFAULT NULL,
              `skorOtomatis` decimal(6,2) NOT NULL DEFAULT '0.00',
              `skorManual` decimal(6,2) DEFAULT NULL,
              `status` enum('BERJALAN','SELESAI','DINILAI') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BERJALAN',
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `percobaan_postId_siswaId_key` (`postId`,`siswaId`),
              KEY `percobaan_siswaId_fkey` (`siswaId`),
              CONSTRAINT `percobaan_postId_fkey` FOREIGN KEY (`postId`) REFERENCES `classroom_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `percobaan_siswaId_fkey` FOREIGN KEY (`siswaId`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `jawaban` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `percobaanId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `soalId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `jawaban` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
              `skor` decimal(5,2) DEFAULT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `jawaban_percobaanId_soalId_key` (`percobaanId`,`soalId`),
              KEY `jawaban_soalId_fkey` (`soalId`),
              CONSTRAINT `jawaban_percobaanId_fkey` FOREIGN KEY (`percobaanId`) REFERENCES `percobaan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `jawaban_soalId_fkey` FOREIGN KEY (`soalId`) REFERENCES `soal` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(): void
    {
        foreach (['jawaban', 'percobaan', 'soal', 'komentar', 'lampiran', 'classroom_posts'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
