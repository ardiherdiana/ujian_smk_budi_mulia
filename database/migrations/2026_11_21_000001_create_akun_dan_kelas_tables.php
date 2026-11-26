<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun dan data induk, disalin dari skema Classroom (landing_page, DB `budi_mulia`) per 21 Nov 2026. DDL mentah seperti di landing_page agar
 * collation, default, dan nama kunci sama persis.
 *
 * Bedanya dengan sumber: `users` tanpa kolom warisan situs lama (`password_plain`, `nama_file`, `path_file`),
 * `profil_akun` tanpa kolom milik modul lain (`jabatan`, `absensi_user_id`, `guruStaffId`) dan perannya hanya peran
 * Classroom (ADMIN|KEPSEK|GURU|SISWA).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE `users` (
              `id` int NOT NULL AUTO_INCREMENT,
              `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
              `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
              `role` enum('admin','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user',
              PRIMARY KEY (`id`),
              UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `profil_akun` (
              `id` int NOT NULL,
              `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `role` enum('ADMIN','KEPSEK','GURU','SISWA') COLLATE utf8mb4_unicode_ci NOT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT '1',
              `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `profil_akun_username_key` (`username`),
              CONSTRAINT `profil_akun_id_fkey` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `tahun_ajaran` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `nama` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `semester` enum('GANJIL','GENAP') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `aktif` tinyint(1) NOT NULL DEFAULT '0',
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `tahun_ajaran_nama_semester_key` (`nama`,`semester`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `rombel` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `nama` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `tingkat` enum('X','XI','XII') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `jurusan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `waliKelasId` int DEFAULT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `rombel_nama_key` (`nama`),
              KEY `rombel_waliKelasId_fkey` (`waliKelasId`),
              CONSTRAINT `rombel_waliKelasId_fkey` FOREIGN KEY (`waliKelasId`) REFERENCES `profil_akun` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `mapel` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `mapel_nama_key` (`nama`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `siswa` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `userId` int NOT NULL,
              `rombelId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `nis` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `siswa_userId_key` (`userId`),
              UNIQUE KEY `siswa_nis_key` (`nis`),
              KEY `siswa_rombelId_fkey` (`rombelId`),
              CONSTRAINT `siswa_rombelId_fkey` FOREIGN KEY (`rombelId`) REFERENCES `rombel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `siswa_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `profil_akun` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE `kelas` (
              `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `rombelId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `mapelId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `guruId` int NOT NULL,
              `tahunAjaranId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `deskripsi` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `diarsipkan` tinyint(1) NOT NULL DEFAULT '0',
              `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updatedAt` datetime(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `kelas_rombel_mapel_guru_tahun_key` (`rombelId`,`mapelId`,`guruId`,`tahunAjaranId`),
              KEY `kelas_mapelId_fkey` (`mapelId`),
              KEY `kelas_guruId_fkey` (`guruId`),
              KEY `kelas_tahunAjaranId_fkey` (`tahunAjaranId`),
              CONSTRAINT `kelas_guruId_fkey` FOREIGN KEY (`guruId`) REFERENCES `profil_akun` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `kelas_mapelId_fkey` FOREIGN KEY (`mapelId`) REFERENCES `mapel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `kelas_rombelId_fkey` FOREIGN KEY (`rombelId`) REFERENCES `rombel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `kelas_tahunAjaranId_fkey` FOREIGN KEY (`tahunAjaranId`) REFERENCES `tahun_ajaran` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(): void
    {
        foreach (['kelas', 'siswa', 'mapel', 'rombel', 'tahun_ajaran', 'profil_akun', 'users'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
