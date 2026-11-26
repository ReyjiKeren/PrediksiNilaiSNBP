# -*- coding: utf-8 -*-
import sys, io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
"""
Fix: Ambil data prodi untuk 8 PTN yang sudah ada di database
(UI, UGM, ITB, IPB, UNAIR, ITS, UNPAD, UNDIP)
Menggunakan id_univ yang sudah ada, bukan dari API.
"""

import requests
import mysql.connector
import time

DB_CONFIG = {
    "host": "localhost", "user": "root",
    "password": "", "database": "db_univ", "port": 3306, "charset": "utf8mb4"
}

API_BASE = "https://snpmb.id"
API_PTN  = f"{API_BASE}/proxy-ptn-sn.php"
API_PRODI = f"{API_BASE}/proxy-prodi-sn.php"
HEADERS  = {"User-Agent": "Mozilla/5.0", "Referer": "https://snpmb.id/"}

# Mapping: kode_univ di DB -> id_univ di DB
EXISTING_PTN = {
    "1321": 1,   # UI
    "1361": 2,   # UGM
    "1332": 8,   # ITB
    "1341": 9,   # IPB
    "1381": 10,  # UNAIR
    "1382": 11,  # ITS
    "1333": 12,  # UNPAD
    "1355": 16,  # UNDIP
}

def get_or_create_master_prodi(cursor, nama_prodi):
    cursor.execute("SELECT id_master_prodi FROM tb_master_prodi WHERE nama_prodi = %s", (nama_prodi,))
    row = cursor.fetchone()
    if row:
        return row["id_master_prodi"]
    cursor.execute("INSERT INTO tb_master_prodi (nama_prodi) VALUES (%s)", (nama_prodi,))
    return cursor.lastrowid

def get_id_prodi(cursor, id_univ, kode_prodi):
    cursor.execute(
        "SELECT id_prodi FROM tb_prodi_univ WHERE id_univ = %s AND kode_prodi = %s",
        (int(id_univ), kode_prodi)
    )
    row = cursor.fetchone()
    return row["id_prodi"] if row else None

def main():
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    print("[OK] Terhubung ke database")

    # Ambil daftar PTN dari API
    print("[INFO] Mengambil daftar PTN dari API...")
    r = requests.get(API_PTN, headers=HEADERS, timeout=15)
    all_ptn = r.json()

    # Filter hanya 8 PTN yang gagal
    target_ptn = []
    for ptn in all_ptn:
        kode = str(ptn.get("kode_ptn", ""))[:4]
        if kode in EXISTING_PTN:
            target_ptn.append(ptn)

    print(f"[INFO] Ditemukan {len(target_ptn)} PTN yang perlu diperbaiki\n")

    total_prodi = 0
    total_hist = 0

    for ptn in target_ptn:
        id_api = ptn["id_ptn"]
        kode = str(ptn.get("kode_ptn", ""))[:4]
        id_db = EXISTING_PTN[kode]
        nama = ptn.get("nama", "?")

        print(f"  {nama} (API id={id_api} -> DB id={id_db})")

        # Update info universitas
        jenis = "PTN Vokasi" if ptn.get("is_vokasi") == 1 else ("PTKIN" if ptn.get("is_ptkin") == 1 else "PTN Akademik")
        lokasi = "-"
        if ptn.get("provinsi") and len(ptn["provinsi"]) > 0:
            prov = ptn["provinsi"][0]
            lokasi = f"{prov.get('nama_kota','')}, {prov.get('nama_prov1','')}".strip(", ")
        website = ptn.get("web", "-") or "-"

        cursor.execute("""
            UPDATE tb_universitas SET jenis_ptn=%s, lokasi=%s, website=%s WHERE id_univ=%s
        """, (jenis, lokasi, website, id_db))
        conn.commit()

        # Ambil prodi
        r2 = requests.get(API_PRODI, params={"ptn": id_api}, headers=HEADERS, timeout=15)
        prodi_list = r2.json()
        time.sleep(0.5)

        if not prodi_list:
            print(f"    [WARN] Tidak ada prodi")
            continue

        print(f"    {len(prodi_list)} prodi ditemukan")

        for prodi in prodi_list:
            nama_prodi = prodi.get("nama", "").strip()
            kode_prodi = prodi.get("kode_prodi", "")
            jenjang = prodi.get("jenjang", "S1")
            daya_tampung = int(prodi.get("daya_tampung_snbp", 0) or 0)
            portofolio_raw = prodi.get("nama_portofolio", "Tidak Ada") or "Tidak Ada"

            if "wajib" in portofolio_raw.lower():
                portofolio = "Wajib"
            elif "opsional" in portofolio_raw.lower():
                portofolio = "Opsional"
            else:
                portofolio = "Tidak Ada"

            if jenjang not in ["D3", "D4", "S1", "S2", "S3"]:
                jenjang = "S1"

            id_master = get_or_create_master_prodi(cursor, nama_prodi)

            # Insert/update tb_prodi_univ dengan ON DUPLICATE KEY UPDATE
            cursor.execute("""
                INSERT INTO tb_prodi_univ (id_univ, id_master_prodi, kode_prodi, jenjang, daya_tampung, nilai_minimal, portofolio)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    kode_prodi   = VALUES(kode_prodi),
                    daya_tampung = VALUES(daya_tampung),
                    portofolio   = VALUES(portofolio)
            """, (id_db, id_master, kode_prodi, jenjang, daya_tampung, 0.00, portofolio))
            
            # Dapatkan id_prodi
            id_prodi = get_id_prodi(cursor, id_db, kode_prodi)

            conn.commit()

            # Historis
            history = prodi.get("history_daya_tampung", [])
            for h in history:
                tahun = int(h.get("tahun", 0))
                peminat = int(h.get("peminat", 0) or 0)
                dt = int(h.get("daya_tampung", 0) or 0)
                if tahun == 0:
                    continue
                keketatan = round(peminat / dt, 2) if dt > 0 else 0.00
                cursor.execute("""
                    INSERT INTO tb_historis (id_prodi, tahun, peminat, daya_tampung, keketatan)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE peminat=VALUES(peminat), daya_tampung=VALUES(daya_tampung), keketatan=VALUES(keketatan)
                """, (id_prodi, tahun, peminat, dt, keketatan))
                total_hist += 1

            conn.commit()
            total_prodi += 1

        print(f"    [OK] Selesai!")

    print(f"\n{'='*50}")
    print(f"  SELESAI!")
    print(f"  Prodi ditambahkan/diupdate: {total_prodi}")
    print(f"  Data historis: {total_hist}")
    print(f"{'='*50}")

    cursor.close()
    conn.close()

if __name__ == "__main__":
    main()
