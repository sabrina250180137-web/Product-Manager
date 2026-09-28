<?php
session_start();
require_once 'koneksi.php';

// Generate Token CSRF jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$edit_product = null;

// Handle Edit Fetch (GET ID)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $edit_product = $stmt->fetch();
}

// Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF Token");
    }

    $action = $_POST['action'] ?? '';

    // Action: DELETE (Menggunakan POST + CSRF)
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['flash'] = "Produk berhasil dihapus!";
        header("Location: index.php");
        exit;
    }

    // Action: CREATE / UPDATE
    if ($action === 'save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'], FILTER_VALIDATE_INT);

        // Validasi Sesuai Syarat Tugas
        if (strlen($name) < 3) {
            $errors[] = "Nama produk minimal 3 karakter.";
        }

        // Cek Keunikan Nama Produk
        if ($name !== '') {
            if ($id) {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND id != ?");
                $stmt->execute([$name, $id]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
                $stmt->execute([$name]);
            }
            if ($stmt->fetch()) {
                $errors[] = "Nama produk sudah digunakan / harus unik.";
            }
        }

        if (empty($category)) {
            $errors[] = "Kategori tidak boleh kosong.";
        }

        if ($price === false || $price <= 0) {
            $errors[] = "Harga harus lebih dari 0 (> 0).";
        }

        if ($stock === false || $stock < 0) {
            $errors[] = "Stok harus berupa angka tak negatif (≥ 0).";
        }

        // Simpan jika tidak ada error
        if (empty($errors)) {
            if ($id) {
                // UPDATE query
                $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, stock = ? WHERE id = ?");
                $stmt->execute([$name, $category, $price, $stock, $id]);
                $_SESSION['flash'] = "Produk berhasil diperbarui!";
            } else {
                // INSERT query
                $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $category, $price, $stock]);
                $_SESSION['flash'] = "Produk berhasil ditambahkan!";
            }

            // Redirect (PRG Pattern) untuk menghindari submit ganda saat refresh
            header("Location: index.php");
            exit;
        }
    }
}

// Fetch Semua Produk (Read)
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC");
$stmt->execute();
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Manager</title>
    <!-- CSS Responsive Frame/Layout (CDN Tailwind CSS) -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-6">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-indigo-400">Aplikasi Product Manager</h1>

        <!-- Flash Message -->
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="p-4 mb-6 bg-green-800 text-green-200 rounded-lg">
                <?= htmlspecialchars($_SESSION['flash'], ENT_QUOTES, 'UTF-8'); ?>
                <?php unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="p-4 mb-6 bg-red-800 text-red-200 rounded-lg">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Form Create / Update -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg mb-10 border border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-indigo-300">
                <?= $edit_product ? 'Edit Produk ID #' . htmlspecialchars($edit_product['id'], ENT_QUOTES, 'UTF-8') : 'Tambah Produk Baru'; ?>
            </h2>
            <form action="index.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="id" value="<?= $edit_product['id']; ?>">
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium mb-1">Nama Produk (≥ 3 Karakter, Unik):</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit_product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Kategori:</label>
                    <input type="text" name="category" value="<?= htmlspecialchars($edit_product['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Harga (> 0):</label>
                    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($edit_product['price'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Stok (≥ 0):</label>
                    <input type="number" name="stock" value="<?= htmlspecialchars($edit_product['stock'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="md:col-span-2 flex gap-3 mt-2">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition">
                        <?= $edit_product ? 'Perbarui Produk' : 'Tambah Produk'; ?>
                    </button>
                    <?php if ($edit_product): ?>
                        <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition">Batal Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Read: Card Responsif -->
        <h2 class="text-2xl font-semibold mb-4 text-gray-200">Daftar Produk</h2>
        <?php if (empty($products)): ?>
            <p class="text-gray-400">Belum ada produk yang tersimpan.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($products as $product): ?>
                    <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow flex flex-col justify-between">
                        <div>
                            <!-- Syarat Output: htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8") -->
                            <h3 class="text-xl font-bold text-indigo-400 mb-1">
                                <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <span class="inline-block bg-gray-700 text-gray-300 text-xs px-2 py-1 rounded mb-3">
                                <?= htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <div class="space-y-1 text-sm text-gray-300">
                                <p><span class="font-semibold">Harga:</span> Rp <?= number_format($product['price'], 2, ',', '.'); ?></p>
                                <p><span class="font-semibold">Stok:</span> <?= htmlspecialchars($product['stock'], ENT_QUOTES, 'UTF-8'); ?> unit</p>
                            </div>
                        </div>

                        <!-- Action Buttons (Update & Delete) -->
                        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-700">
                            <!-- Update/Edit Trigger -->
                            <a href="index.php?action=edit&id=<?= $product['id']; ?>" 
                               class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold py-1.5 px-3 rounded transition">
                                Edit
                            </a>

                            <!-- Delete Trigger (POST + CSRF) -->
                            <form action="index.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $product['id']; ?>">
                                <button type="submit" 
                                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold py-1.5 px-3 rounded transition">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>