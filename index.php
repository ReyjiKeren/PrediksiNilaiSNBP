<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Jurusan - Prediksi Nilai SNBP</title>
    <link rel="shortcut icon" href="./img/logo.png" type="image/png">
    <link href="./src/output.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>

    <section class="relative min-h-screen flex items-center justify-center bg-cover bg-center bg-no-repeat"
        style="background-image: url('./img/bgtb.png');">

        <div class="absolute inset-0 bg-black/60"></div>

        <div class="relative z-10 w-full max-w-2xl mx-4 bg-white rounded-2xl p-6 sm:p-10 shadow-2xl">

            <h1 class="text-center text-gray-800 text-2xl sm:text-3xl font-bold mb-6 sm:mb-10 tracking-wide">
                Pilih Jurusan Anda
            </h1>

            <div class="flex flex-wrap justify-center gap-4 sm:gap-6">

                <!-- Link diarahkan ke catat_viewer.php agar aktivitas tercatat di database -->
                <a href="catat_viewer.php?jurusan=RPL" class="block group shrink-0">
                    <div class="w-28 h-28 sm:w-32 sm:h-32 bg-orange-500 rounded-2xl flex flex-col items-center justify-center shadow-lg transform group-hover:scale-105 transition-all duration-300" style="width: 8rem; height: 8rem; min-width: 8rem; min-height: 8rem;">
                        <span class="text-white text-2xl sm:text-3xl font-bold">RPL</span>
                    </div>
                </a>

                <a href="catat_viewer.php?jurusan=TKR" class="block group shrink-0">
                    <div class="w-28 h-28 sm:w-32 sm:h-32 bg-red-500 rounded-2xl flex flex-col items-center justify-center shadow-lg transform group-hover:scale-105 transition-all duration-300" style="width: 8rem; height: 8rem; min-width: 8rem; min-height: 8rem;">
                        <span class="text-white text-2xl sm:text-3xl font-bold">TKR</span>
                    </div>
                </a>

                <a href="catat_viewer.php?jurusan=TITL" class="block group shrink-0">
                    <div class="w-28 h-28 sm:w-32 sm:h-32 bg-blue-500 rounded-2xl flex flex-col items-center justify-center shadow-lg transform group-hover:scale-105 transition-all duration-300" style="width: 8rem; height: 8rem; min-width: 8rem; min-height: 8rem;">
                        <span class="text-white text-2xl sm:text-3xl font-bold">TITL</span>
                    </div>
                </a>

                <a href="catat_viewer.php?jurusan=TAV" class="block group shrink-0">
                    <div class="w-28 h-28 sm:w-32 sm:h-32 bg-yellow-400 rounded-2xl flex flex-col items-center justify-center shadow-lg transform group-hover:scale-105 transition-all duration-300" style="width: 8rem; height: 8rem; min-width: 8rem; min-height: 8rem;">
                        <span class="text-white text-2xl sm:text-3xl font-bold text-gray-900">TAV</span>
                    </div>
                </a>

            </div>

        </div>

    </section>

</body>
</html>
