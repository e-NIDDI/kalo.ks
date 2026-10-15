<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$error = '';
$redirect = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));
if (!empty($redirect)) {
    if (preg_match('/^(https?:|\/\/)/i', $redirect)) {
        $redirect = 'index.php';
    }
}

$faculties = [];
try {
    $faculties = $pdo->query("SELECT id, name, code FROM faculties ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $faculty_id = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Ju lutem plotësoni të gjitha fushat e detyrueshme.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresa e emailit nuk është e vlefshme.";
    } elseif (strlen($password) < 6) {
        $error = "Fjalëkalimi duhet të ketë të paktën 6 karaktere.";
    } else {
        try {
            // Check if email already exists
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = "Një llogari me këtë email ekziston tashmë.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $avatar = 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=160&h=160&auto=format&fit=crop&q=80';

                $insert = $pdo->prepare("
                    INSERT INTO users (full_name, email, password_hash, role, faculty_id, reputation_points, avatar_url, bio, created_at)
                    VALUES (?, ?, ?, 'student', ?, 10, ?, 'Student i ri në platformën Kalo.ks', NOW())
                ");
                $insert->execute([$full_name, $email, $hash, $faculty_id, $avatar]);
                $new_user_id = (int)$pdo->lastInsertId();

                // Auto login
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['role'] = 'student';
                $_SESSION['avatar_url'] = $avatar;

                // Welcome notification
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
                    ->execute([$new_user_id, "Mirëseerdhët në Kalo.ks! Keni fituar 10 pikë fillestare reputacioni."]);

                $destination = !empty($redirect) ? $redirect : "profile.php";
                header("Location: " . $destination);
                exit;
            }
        } catch (PDOException $e) {
            $error = "Gabim gjatë regjistrimit: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-md mx-auto px-4 py-12 w-full flex flex-col justify-center">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 shadow-2xl">
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-indigo-600/10 text-indigo-400 flex items-center justify-center mx-auto mb-3 text-2xl border border-indigo-500/20">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h1 class="text-2xl font-black text-white">Regjistrohu në Kalo.ks</h1>
            <p class="text-xs text-slate-400 mt-1">Bashkohuni me komunitetin studentor universitar (+10 pikë fillestare)</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-base shrink-0 text-rose-400"></i>
                <span class="leading-relaxed"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="signup.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" class="space-y-4">
            <?php if (!empty($redirect)): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Emri dhe Mbiemri *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required placeholder="Filan Fisteku"
                       class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Email Studentor *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="filan@uni-pr.edu"
                       class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Fakulteti</label>
                <select name="faculty_id" class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 transition">
                    <option value="">Zgjidhni Fakultetin Tuaj...</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= (isset($_POST['faculty_id']) && (int)$_POST['faculty_id'] === (int)$f['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Fjalëkalimi (min. 6 karaktere) *</label>
                <input type="password" name="password" required placeholder="••••••••" minlength="6"
                       class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition shadow-lg shadow-indigo-600/25 flex items-center justify-center gap-2">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Krijo Llogarinë</span>
            </button>
        </form>

        <p class="text-xs text-slate-400 text-center mt-6">
            Keni tashmë llogari? <a href="login.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" class="text-amber-400 font-bold hover:underline">Kyçu këtu</a>
        </p>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
