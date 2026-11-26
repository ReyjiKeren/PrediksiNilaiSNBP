# -*- coding: utf-8 -*-
import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
"""
Test cepat: ambil data 3 PTN pertama saja
Gunakan ini untuk memastikan koneksi DB dan API berfungsi sebelum menjalankan scraper penuh
"""
import requests
import mysql.connector
import json

DB_CONFIG = {
    "host": "localhost", "user": "root",
    "password": "", "database": "db_univ", "port": 3306
}

API_PTN   = "https://snpmb.id/proxy-ptn-sn.php"
API_PRODI = "https://snpmb.id/proxy-prodi-sn.php"
HEADERS   = {"User-Agent": "Mozilla/5.0", "Referer": "https://snpmb.id/"}

print("=" * 50)
print("  TEST KONEKSI - SNBP Scraper")
print("=" * 50)

# Test 1: Koneksi DB
print("\n[1] Test koneksi MySQL...")
try:
    conn = mysql.connector.connect(**DB_CONFIG)
    print("    [OK] MySQL OK - database 'db_univ' terhubung")
    conn.close()
except Exception as e:
    print(f"    [GAGAL] {e}")
    print("    --> Pastikan XAMPP/MySQL sudah dijalankan!")
    exit(1)

# Test 2: API PTN
print("\n[2] Test API daftar PTN...")
try:
    r = requests.get(API_PTN, headers=HEADERS, timeout=10)
    ptn_list = r.json()
    print(f"    [OK] API OK - {len(ptn_list)} PTN tersedia")
    print(f"    Contoh PTN pertama: {ptn_list[0].get('nama')} (id={ptn_list[0].get('id_ptn')})")
except Exception as e:
    print(f"    [GAGAL] {e}")
    exit(1)

# Test 3: API Prodi (ambil 1 PTN saja)
print("\n[3] Test API prodi (PTN pertama)...")
try:
    id_test = ptn_list[0]["id_ptn"]
    r2 = requests.get(API_PRODI, params={"ptn": id_test}, headers=HEADERS, timeout=10)
    prodi_list = r2.json()
    print(f"    [OK] API Prodi OK - {len(prodi_list)} prodi untuk PTN id={id_test}")
    if prodi_list:
        p = prodi_list[0]
        print(f"    Contoh prodi: {p.get('nama')} [{p.get('jenjang')}]")
        hist = p.get("history_daya_tampung", [])
        print(f"    Historis tersedia: {len(hist)} tahun")
        if hist:
            print(f"    Data terbaru: Tahun {hist[-1].get('tahun')} | "
                  f"Peminat: {hist[-1].get('peminat')} | "
                  f"Daya Tampung: {hist[-1].get('daya_tampung')}")
except Exception as e:
    print(f"    [GAGAL] {e}")
    exit(1)

print("\n" + "=" * 50)
print("  [SUKSES] SEMUA TEST BERHASIL!")
print("  Anda siap menjalankan scraper penuh.")
print("  Jalankan: python scraper/snbp_scraper.py")
print("=" * 50)
