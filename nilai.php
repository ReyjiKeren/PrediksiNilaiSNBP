<?php

$jurusan = $_GET['jurusan'] ?? '';

$jurusan_valid = ['RPL', 'TKR', 'TITL', 'TAV'];

if (empty($jurusan) || !in_array($jurusan, $jurusan_valid)) {
    header("Location: index.php");
    exit;
}

$errors = [];
$nama = trim($_POST['nama_siswa'] ?? '');
$nilai = [
    'semester1' => '',
    'semester2' => '',
    'semester3' => '',
    'semester4' => '',
    'semester5' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($nama)) {
        $errors['nama_siswa'] = 'Nama tidak boleh kosong.';
    }

    foreach ($nilai as $key => $val) {
        $input = trim($_POST[$key] ?? '');
        
        // Batasi maksimal 4 karakter jika dikirim melebihinya
        if (strlen($input) > 4) {
            $input = substr($input, 0, 4);
        }

        $nilai[$key] = $input;

        if ($input === '') {
            $errors[$key] = 'Nilai tidak boleh kosong.';
        } elseif (!is_numeric($input)) {
            $errors[$key] = 'Nilai harus berupa angka.';
        } elseif ((float) $input < 0 || (float) $input > 100) {
            $errors[$key] = 'Nilai harus antara 0 dan 100.';
        }
    }

    if (empty($errors)) {
        $total = (float) $nilai['semester1'] +
            (float) $nilai['semester2'] +
            (float) $nilai['semester3'] +
            (float) $nilai['semester4'] +
            (float) $nilai['semester5'];

        $rata_rata = $total / 5;

        if ($jurusan === 'RPL') {
            $acuan = 87;
        } else {
            $acuan = 85;
        }

        header("Location: hasil.php?jurusan=" . urlencode($jurusan) . "&rata_rata=" . urlencode($rata_rata) . "&acuan=" . urlencode($acuan) . "&nama=" . urlencode($nama));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai Semester - Prediksi SNBP</title>
    <link rel="shortcut icon" href="./img/logo.png" type="image/png">
    <link href="./src/output.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .form-group-item {
            margin-bottom: 1rem !important;
        }

        .btn-green-submit {
            background-color: #22c55e !important;
            color: #ffffff !important;
        }

        .btn-green-submit:hover {
            background-color: #16a34a !important;
        }

        .input-error-box {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .text-error-msg {
            color: #dc2626 !important;
        }
    </style>
</head>

<body>
    <section class="relative min-h-screen flex items-center justify-center bg-cover bg-center bg-no-repeat py-12"
        style="background-image: url('./img/bgtb.png');">

        <div class="absolute inset-0 bg-black/60"></div>

        <div
            class="relative z-10 w-full max-w-2xl mx-4 bg-white rounded-2xl p-6 md:p-8 shadow-2xl">

            <h1 class="text-center text-gray-800 text-3xl font-bold mb-2">Masukkan Nilai Anda</h1>
            <p class="text-center text-gray-600 text-sm mb-6">
                Jurusan Terpilih: <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full font-bold"><?= htmlspecialchars($jurusan) ?></span>
            </p>

            <form action="nilai.php?jurusan=<?= urlencode($jurusan) ?>" method="POST">

                <!-- Input Nama Siswa -->
                <div class="mb-4 form-group-item">
                    <label class="block text-gray-700 text-base font-semibold mb-1.5">Nama Lengkap</label>
                    <input type="text"
                           name="nama_siswa"
                           id="input_nama"
                           placeholder="Masukkan nama lengkap Anda"
                           value="<?= htmlspecialchars($nama ?? '') ?>"
                           autocomplete="off"
                           class="w-full h-11 px-4 text-base rounded-xl border text-gray-800 placeholder-gray-400 outline-none transition
                                  <?= isset($errors['nama_siswa'])
                                      ? 'border-red-500 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 input-error-box'
                                      : 'border-gray-300 bg-gray-50 focus:bg-white focus:border-green-500 focus:ring-2 focus:ring-green-500/20' ?>">
                    <?php if (isset($errors['nama_siswa'])): ?>
                        <p class="text-red-600 text-error-msg text-sm font-medium mt-2 flex items-center gap-1.5">
                            <span>⚠️</span> <?= htmlspecialchars($errors['nama_siswa']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php
                $semesters = [
                    'semester1' => 'Semester 1',
                    'semester2' => 'Semester 2',
                    'semester3' => 'Semester 3',
                    'semester4' => 'Semester 4',
                    'semester5' => 'Semester 5',
                ];

                foreach ($semesters as $name => $label):
                    $hasError = isset($errors[$name]);
                    ?>

                    <div class="mb-4 form-group-item">
                        <label class="block text-gray-700 text-base font-semibold mb-1.5">
                            <?= htmlspecialchars($label) ?>
                        </label>

                        <input type="number"
                            maxlength="4"
                            oninput="if(this.value.length > 4) this.value = this.value.slice(0, 4);"
                            name="<?= $name ?>"
                            placeholder="Masukkan nilai rata-rata <?= $label ?>"
                            step="0.01"
                            value="<?= htmlspecialchars($nilai[$name]) ?>"
                            class="w-full h-11 px-4 text-base rounded-xl border text-gray-800 placeholder-gray-400 outline-none transition
                               <?= $hasError
                                   ? 'border-red-500 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 input-error-box'
                                   : 'border-gray-300 bg-gray-50 focus:bg-white focus:border-green-500 focus:ring-2 focus:ring-green-500/20' ?>">

                        <?php if ($hasError): ?>
                            <p class="text-red-600 text-error-msg text-sm font-medium mt-2 flex items-center gap-1.5">
                                <span>⚠️</span> <?= htmlspecialchars($errors[$name]) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>

                <div class="mt-5">
                    <button type="submit"
                        class="w-full h-12 rounded-xl bg-green-500 hover:bg-green-600 active:bg-green-700 text-white text-base font-bold shadow-lg shadow-green-500/30 transition cursor-pointer btn-green-submit">
                        Prediksi Nilai
                    </button>
                </div>

            </form>

        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const namaInput = document.getElementById('input_nama');
            const inputs = [namaInput, ...Array.from(document.querySelectorAll('form input[name^="semester"]'))];
            inputs.forEach((input, index) => {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                            inputs[index + 1].select();
                        } else {
                            this.form.submit();
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>