# -*- coding: utf-8 -*-
import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
"""
================================================
SNBP Data Scraper - Portal Resmi SNPMB
================================================
Mengambil data dari API publik: https://snpmb.id
- 146 PTN (Universitas/Institut/Politeknik)
- Ribuan Prodi + historis 5 tahun (peminat, daya tampung)
- Memasukkan ke database MySQL: db_univ

Cara Pakai:
  1. Pastikan XAMPP (MySQL) sedang berjalan
  2. Buka Command Prompt / PowerShell
  3. Jalankan: python scraper/snbp_scraper.py

Author  : Auto-generated for projectpweb
Target  : MySQL db_univ @ localhost
================================================
"""

import requests
import mysql.connector
import time
import sys
from datetime import datetime

# ============================================================
# KONFIGURASI DATABASE (sesuaikan jika berbeda)
# ============================================================
DB_CONFIG = {
    "host":     "localhost",
    "user":     "root",
    "password": "",          # kosong = default XAMPP
    "database": "db_univ",
    "port":     3306,
    "charset":  "utf8mb4",
}

# ============================================================
# KONFIGURASI API SNPMB (publik, tidak perlu login)
# ============================================================
API_BASE       = "https://snpmb.id"
API_PTN_LIST   = f"{API_BASE}/proxy-ptn-sn.php"      # Daftar semua PTN
API_PRODI_LIST = f"{API_BASE}/proxy-prodi-sn.php"    # Prodi per PTN (?ptn=ID)

HEADERS = {
    "User-Agent":      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "Accept":          "application/json, text/plain, */*",
    "Referer":         "https://snpmb.id/snbp/daya-tampung-snbp",
    "Origin":          "https://snpmb.id",
}

# Delay antar request (detik) agar tidak membebani server
REQUEST_DELAY = 0.5

# ============================================================
# HELPER: Warna di terminal
# ============================================================
def clr(text, code): return f"\033[{code}m{text}\033[0m"
INFO    = lambda t: print(clr(f"[INFO]  {t}", "36"))
OK      = lambda t: print(clr(f"[OK]    {t}", "32"))
WARN    = lambda t: print(clr(f"[WARN]  {t}", "33"))
ERR     = lambda t: print(clr(f"[ERROR] {t}", "31"))
STEP    = lambda t: print(clr(f"\n{'='*55}\n  {t}\n{'='*55}", "35"))

# ============================================================
# LANGKAH 1: Koneksi ke MySQL
# ============================================================
def connect_db():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        OK(f"Terhubung ke database '{DB_CONFIG['database']}' di {DB_CONFIG['host']}")
        return conn, cursor
    except mysql.connector.Error as e:
        ERR(f"Gagal konek ke MySQL: {e}")
        ERR("Pastikan XAMPP/MySQL sudah berjalan!")
        sys.exit(1)

# ============================================================
# LANGKAH 2: Ambil data dari API
# ============================================================
def fetch_json(url, params=None, label=""):
    try:
        r = requests.get(url, params=params, headers=HEADERS, timeout=15)
        r.raise_for_status()
        data = r.json()
        return data
    except requests.exceptions.Timeout:
        WARN(f"Timeout saat mengambil {label}. Coba ulang...")
        time.sleep(2)
        return fetch_json(url, params, label)  # retry sekali
    except requests.exceptions.RequestException as e:
        ERR(f"Gagal fetch {label}: {e}")
        return None
    except ValueError:
        ERR(f"Response bukan JSON valid untuk {label}")
        return None

# ============================================================
# LANGKAH 3: Insert/Update Universitas ke tb_universitas
# ============================================================
def upsert_universitas(cursor, ptn):
    """
    Mapping field API → tb_universitas:
    id_ptn      → id_univ
    kode_ptn    → kode_univ
    nama        → nama_univ
    is_vokasi   → jenis_ptn (PTN Vokasi / PTN Akademik / PTKIN)
    provinsi    → lokasi
    web         → website
    """
    # Tentukan jenis PTN
    if ptn.get("is_vokasi") == 1:
        jenis = "PTN Vokasi"
    elif ptn.get("is_ptkin") == 1:
        jenis = "PTKIN"
    else:
        jenis = "PTN Akademik"

    # Lokasi dari field provinsi
    lokasi = "-"
    if ptn.get("provinsi") and len(ptn["provinsi"]) > 0:
        prov = ptn["provinsi"][0]
        kota = prov.get("nama_kota", "")
        nama_prov = prov.get("nama_prov1", "")
        lokasi = f"{kota}, {nama_prov}".strip(", ")

    # Kode PTN hanya 4 digit pertama
    kode_univ = str(ptn.get("kode_ptn", ""))[:4]
    website   = ptn.get("web", "-") or "-"

    sql = """
        INSERT INTO tb_universitas (id_univ, kode_univ, nama_univ, jenis_ptn, lokasi, website)
        VALUES (%s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            nama_univ  = VALUES(nama_univ),
            jenis_ptn  = VALUES(jenis_ptn),
            lokasi     = VALUES(lokasi),
            website    = VALUES(website)
    """
    cursor.execute(sql, (
        int(ptn["id_ptn"]),
        kode_univ,
        ptn.get("nama", ""),
        jenis,
        lokasi,
        website,
    ))

# ============================================================
# LANGKAH 4: Insert/Update Prodi ke tb_master_prodi & tb_prodi_univ
# ============================================================
def get_or_create_master_prodi(cursor, nama_prodi):
    """Cek apakah nama prodi sudah ada di tb_master_prodi, jika belum buat baru."""
    cursor.execute("SELECT id_master_prodi FROM tb_master_prodi WHERE nama_prodi = %s", (nama_prodi,))
    row = cursor.fetchone()
    if row:
        return row["id_master_prodi"]
    # Insert baru
    cursor.execute("INSERT INTO tb_master_prodi (nama_prodi) VALUES (%s)", (nama_prodi,))
    return cursor.lastrowid

def upsert_prodi_univ(cursor, id_ptn, prodi):
    """
    Mapping field API → tb_prodi_univ:
    kode_prodi       → kode_prodi
    jenjang          → jenjang
    daya_tampung_snbp→ daya_tampung
    nama_portofolio  → portofolio
    """
    nama_prodi    = prodi.get("nama", "").strip()
    kode_prodi    = prodi.get("kode_prodi", "")
    jenjang       = prodi.get("jenjang", "S1")
    daya_tampung  = int(prodi.get("daya_tampung_snbp", 0) or 0)
    portofolio_raw = prodi.get("nama_portofolio", "Tidak Ada") or "Tidak Ada"

    # Normalize portofolio ke ENUM yang ada di DB
    if "wajib" in portofolio_raw.lower():
        portofolio = "Wajib"
    elif "opsional" in portofolio_raw.lower():
        portofolio = "Opsional"
    else:
        portofolio = "Tidak Ada"

    # Normalize jenjang
    jenjang_valid = ["D3", "D4", "S1", "S2", "S3"]
    if jenjang not in jenjang_valid:
        jenjang = "S1"

    # Cari/buat master prodi
    id_master = get_or_create_master_prodi(cursor, nama_prodi)

    # Insert/update tb_prodi_univ
    sql = """
        INSERT INTO tb_prodi_univ
            (id_univ, id_master_prodi, kode_prodi, jenjang, daya_tampung, nilai_minimal, portofolio)
        VALUES (%s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            daya_tampung = VALUES(daya_tampung),
            portofolio   = VALUES(portofolio)
    """
    cursor.execute(sql, (
        int(id_ptn),
        id_master,
        kode_prodi,
        jenjang,
        daya_tampung,
        0.00,         # nilai_minimal default 0, bisa diupdate manual
        portofolio,
    ))
    return cursor.lastrowid if cursor.lastrowid else None

# ============================================================
# LANGKAH 5: Insert historis ke tb_historis
# ============================================================
def upsert_historis(cursor, id_prodi, history_list):
    """Insert data historis peminat & daya tampung per tahun."""
    if not history_list or not id_prodi:
        return 0

    count = 0
    for h in history_list:
        tahun        = int(h.get("tahun", 0))
        peminat      = int(h.get("peminat", 0) or 0)
        daya_tampung = int(h.get("daya_tampung", 0) or 0)

        if tahun == 0:
            continue

        # Keketatan = peminat / daya_tampung (rasio)
        if daya_tampung > 0:
            keketatan = round(peminat / daya_tampung, 2)
        else:
            keketatan = 0.00

        sql = """
            INSERT INTO tb_historis (id_prodi, tahun, peminat, daya_tampung, keketatan)
            VALUES (%s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                peminat      = VALUES(peminat),
                daya_tampung = VALUES(daya_tampung),
                keketatan    = VALUES(keketatan)
        """
        cursor.execute(sql, (id_prodi, tahun, peminat, daya_tampung, keketatan))
        count += 1

    return count

# ============================================================
# LANGKAH 6: Cari id_prodi di database setelah insert
# ============================================================
def get_id_prodi(cursor, id_ptn, kode_prodi):
    cursor.execute(
        "SELECT id_prodi FROM tb_prodi_univ WHERE id_univ = %s AND kode_prodi = %s",
        (int(id_ptn), kode_prodi)
    )
    row = cursor.fetchone()
    return row["id_prodi"] if row else None

# ============================================================
# MAIN: Orchestrator
# ============================================================
def main():
    start_time = datetime.now()
    STEP("SNBP Data Scraper - Portal Resmi SNPMB")
    print(f"  Mulai    : {start_time.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"  Target DB: {DB_CONFIG['database']} @ {DB_CONFIG['host']}")

    # --- Koneksi DB ---
    conn, cursor = connect_db()

    # --- Ambil daftar PTN ---
    STEP("Mengambil daftar PTN dari API...")
    ptn_list = fetch_json(API_PTN_LIST, label="daftar PTN")
    if not ptn_list:
        ERR("Gagal mendapatkan daftar PTN. Periksa koneksi internet.")
        sys.exit(1)

    total_ptn = len(ptn_list)
    OK(f"Ditemukan {total_ptn} PTN")

    # --- Loop tiap PTN ---
    STEP("Memproses data tiap PTN...")
    total_prodi    = 0
    total_historis = 0
    gagal          = []

    for i, ptn in enumerate(ptn_list, 1):
        id_ptn    = ptn.get("id_ptn")
        nama_ptn  = ptn.get("nama", "?")

        print(f"\n[{i:3}/{total_ptn}] {nama_ptn} (id={id_ptn})")

        try:
            # 3. Insert universitas
            upsert_universitas(cursor, ptn)
            conn.commit()

            # 4. Ambil daftar prodi
            prodi_list = fetch_json(API_PRODI_LIST, params={"ptn": id_ptn}, label=f"prodi PTN {id_ptn}")
            time.sleep(REQUEST_DELAY)

            if not prodi_list:
                WARN(f"  Tidak ada data prodi untuk {nama_ptn}")
                gagal.append(nama_ptn)
                continue

            print(f"       {len(prodi_list)} prodi ditemukan")

            for prodi in prodi_list:
                # 4. Insert prodi
                upsert_prodi_univ(cursor, id_ptn, prodi)
                conn.commit()

                # 6. Cari id_prodi yang baru di-insert
                kode_prodi = prodi.get("kode_prodi", "")
                id_prodi = get_id_prodi(cursor, id_ptn, kode_prodi)

                # 5. Insert historis
                history = prodi.get("history_daya_tampung", [])
                if id_prodi and history:
                    cnt = upsert_historis(cursor, id_prodi, history)
                    total_historis += cnt

                total_prodi += 1

            OK(f"  ✓ Selesai: {len(prodi_list)} prodi")

        except Exception as e:
            ERR(f"  Error pada {nama_ptn}: {e}")
            conn.rollback()
            gagal.append(nama_ptn)
            continue

    # --- Selesai ---
    end_time = datetime.now()
    durasi   = (end_time - start_time).seconds

    STEP("SELESAI!")
    print(f"  Total PTN diproses : {total_ptn - len(gagal)}/{total_ptn}")
    print(f"  Total Prodi        : {total_prodi:,}")
    print(f"  Total Data Historis: {total_historis:,}")
    print(f"  PTN Gagal          : {len(gagal)}")
    if gagal:
        for g in gagal:
            WARN(f"    - {g}")
    print(f"  Durasi             : {durasi} detik")
    print(f"  Selesai pada       : {end_time.strftime('%Y-%m-%d %H:%M:%S')}")
    OK("\nData berhasil dimasukkan ke database db_univ!")

    cursor.close()
    conn.close()

if __name__ == "__main__":
    main()
