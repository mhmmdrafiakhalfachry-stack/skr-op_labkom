-- ============================================
-- DATABASE: laboratorium_komputer
-- Sistem Manajemen Laboratorium Komputer v2
-- ============================================

DROP DATABASE IF EXISTS laboratorium_komputer;
CREATE DATABASE laboratorium_komputer;
USE laboratorium_komputer;

-- ============================================
-- Tabel Users (Admin & Guru)
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nip VARCHAR(30) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','guru') NOT NULL DEFAULT 'guru',
    email VARCHAR(100),
    no_hp VARCHAR(20),
    foto VARCHAR(100),
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Tabel Laboratorium (Multiple Labs)
-- ============================================
CREATE TABLE laboratorium (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lab VARCHAR(100) NOT NULL,
    lokasi VARCHAR(100),
    kapasitas INT DEFAULT 30,
    jumlah_pc INT DEFAULT 0,
    fasilitas TEXT,
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Tabel Komputer/PC
-- ============================================
CREATE TABLE komputer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    no_pc VARCHAR(20) NOT NULL,
    nama_pc VARCHAR(50) NOT NULL,
    merk VARCHAR(50),
    spesifikasi TEXT,
    kondisi ENUM('baik','rusak_ringan','rusak_berat','perbaikan') DEFAULT 'baik',
    lokasi VARCHAR(50),
    tahun_pengadaan YEAR,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_lab_pc (lab_id, no_pc),
    FOREIGN KEY (lab_id) REFERENCES laboratorium(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Tabel Jadwal Praktum (Recurring Schedule)
-- ============================================
CREATE TABLE jadwal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    mata_pelajaran VARCHAR(100) NOT NULL,
    kelas VARCHAR(30) NOT NULL,
    guru_id INT,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES laboratorium(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- Tabel Pengajuan/Peminjaman Lab
-- ============================================
CREATE TABLE peminjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    guru_id INT NOT NULL,
    kelas VARCHAR(30),
    mata_pelajaran VARCHAR(100),
    jumlah_siswa INT,
    keperluan TEXT,
    tujuan TEXT,
    status ENUM('pending','diterima','ditolak','berlangsung','tidak_terlaksana','selesai') DEFAULT 'pending',
    alasan_penolakan TEXT,
    rekomendasi_jadwal TEXT,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES laboratorium(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Tabel Check-In
-- ============================================
CREATE TABLE check_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peminjaman_id INT NOT NULL,
    guru_id INT NOT NULL,
    waktu_check_in DATETIME NOT NULL,
    waktu_check_out DATETIME NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_peminjaman (peminjaman_id),
    FOREIGN KEY (peminjaman_id) REFERENCES peminjaman(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Tabel Penggunaan Lab (Activity Log)
-- ============================================
CREATE TABLE penggunaan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peminjaman_id INT,
    lab_id INT,
    tanggal DATE NOT NULL,
    komputer_id INT,
    no_pc VARCHAR(20),
    pengguna VARCHAR(100),
    kelas VARCHAR(30),
    jam_mulai TIME,
    jam_selesai TIME,
    kegiatan TEXT,
    kondisi_awal ENUM('baik','rusak') DEFAULT 'baik',
    kondisi_akhir ENUM('baik','rusak') DEFAULT 'baik',
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (peminjaman_id) REFERENCES peminjaman(id) ON DELETE SET NULL,
    FOREIGN KEY (lab_id) REFERENCES laboratorium(id) ON DELETE SET NULL,
    FOREIGN KEY (komputer_id) REFERENCES komputer(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- Tabel Rule-Based System
-- ============================================
CREATE TABLE rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_rule VARCHAR(50) NOT NULL UNIQUE,
    nama_rule VARCHAR(150) NOT NULL,
    kondisi TEXT NOT NULL COMMENT 'Kondisi IF',
    aksi TEXT NOT NULL COMMENT 'Aksi THEN',
    deskripsi TEXT,
    prioritas INT DEFAULT 0,
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Tabel Laporan Kerusakan
-- ============================================
CREATE TABLE laporan_kerusakan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    komputer_id INT NOT NULL,
    pelapor_id INT NOT NULL,
    tanggal DATE NOT NULL,
    deskripsi TEXT NOT NULL,
    tingkat_kerusakan ENUM('ringan','sedang','berat') DEFAULT 'ringan',
    status ENUM('belum_ditangani','diproses','selesai') DEFAULT 'belum_ditangani',
    tindakan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (komputer_id) REFERENCES komputer(id) ON DELETE CASCADE,
    FOREIGN KEY (pelapor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Tabel Maintenance
-- ============================================
CREATE TABLE maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    jenis VARCHAR(50) NOT NULL DEFAULT 'rutin',
    deskripsi TEXT,
    tanggal_mulai DATE NOT NULL,
    jam_mulai TIME DEFAULT '08:00:00',
    jam_selesai TIME DEFAULT '12:00:00',
    status ENUM('dijadwalkan','berlangsung','selesai','dibatalkan') DEFAULT 'dijadwalkan',
    catatan TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES laboratorium(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- Tabel Notice (Pengumuman ke Guru)
-- ============================================
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL = broadcast ke semua guru',
    jenis VARCHAR(50) NOT NULL DEFAULT 'info',
    ref_id INT NULL COMMENT 'ID maintenance terkait',
    judul VARCHAR(255) NOT NULL,
    pesan TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_jenis_ref (jenis, ref_id)
) ENGINE=InnoDB;

CREATE TABLE notice_reads (
    notice_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notice_id, user_id)
) ENGINE=InnoDB;

-- ============================================
-- Seed Data: Default Rules (IF-THEN)
-- ============================================
INSERT INTO rules (kode_rule, nama_rule, kondisi, aksi, deskripsi, prioritas) VALUES
('R001', 'Cek Bentrok Jadwal',
 'IF jadwal bentrok dengan penggunaan yang sudah diterima pada lab dan waktu yang sama',
 'THEN pengajuan ditolak dan sistem menampilkan rekomendasi jadwal kosong',
 'Sistem memeriksa apakah ada pengajuan penggunaan lab yang bentrok dengan jadwal yang sudah diterima. Jika bentrok, pengajuan otomatis ditolak dan ditampilkan rekomendasi waktu kosong.', 1),

('R002', 'Jadwal Tersedia',
 'IF jadwal tersedia (tidak bentrok) pada lab dan waktu yang diminta',
 'THEN pengajuan otomatis diterima',
 'Jika tidak ada konflik jadwal, sistem otomatis menerima pengajuan tanpa persetujuan manual.', 2),

('R003', 'Tidak Check-In',
 'IF guru tidak melakukan check-in pada waktu penggunaan laboratorium',
 'THEN status penggunaan menjadi Tidak Terlaksana',
 'Sistem secara otomatis mengubah status menjadi Tidak Terlaksana jika guru tidak check-in pada rentang waktu yang dijadwalkan.', 3),

('R004', 'Rekomendasi Jadwal',
 'IF jadwal bentrok pada lab yang diminta',
 'THEN sistem menampilkan rekomendasi jadwal kosong yang masih tersedia pada lab lain atau waktu lain',
 'Ketika pengajuan ditolak karena bentrok, sistem memberikan alternatif jadwal kosong.', 4),

('R005', 'Lab Sedang Digunakan',
 'IF laboratorium sedang digunakan pada waktu tertentu (status berlangsung)',
 'THEN laboratorium tidak dapat dipilih oleh pengguna lain pada waktu yang sama',
 'Mencegah double-booking pada lab yang sedang aktif digunakan.', 5),

('R006', 'Waktu Penggunaan Selesai',
 'IF waktu penggunaan telah selesai (melewati jam_selesai)',
 'THEN status penggunaan berubah menjadi Selesai',
 'Sistem otomatis mengubah status menjadi Selesai ketika waktu penggunaan telah berakhir.', 6);

-- ============================================
-- Seed Data: Laboratorium Default
-- ============================================
INSERT INTO laboratorium (nama_lab, lokasi, kapasitas, jumlah_pc, status) VALUES
('Lab Komputer 1', 'Gedung A Lt.2', 30, 30, 'aktif'),
('Lab Komputer 2', 'Gedung A Lt.3', 25, 25, 'aktif');
