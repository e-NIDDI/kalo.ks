<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$error = '';
$flash_msg = $_GET['msg'] ?? ($_SESSION['flash_msg'] ?? '');
unset($_SESSION['flash_msg']);

// Safely sanitize redirect URL (must be internal relative path)
$redirect = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));
if (!empty($redirect)) {
    if (preg_match('/^(https?:|\/\/)/i', $redirect)) {
        $redirect = 'index.php';
    }
}

// Handle quick demo switch
if (isset($_GET['demo'])) {
    $demo_id = (int)$_GET['demo'];
    $stmt = $pdo->prepare("SELECT id, full_name, email, role, avatar_url FROM users WHERE id = ?");
    $stmt->execute([$demo_id]);
    $demoUser = $stmt->fetch();
    if ($demoUser) {
        $_SESSION['user_id'] = $demoUser['id'];
        $_SESSION['user_name'] = $demoUser['full_name'];
        $_SESSION['role'] = $demoUser['role'];
        $_SESSION['avatar_url'] = $demoUser['avatar_url'];
        
        $destination = !empty($redirect) ? $redirect : ($demoUser['role'] === 'admin' ? "admin.php" : "index.php");
        header("Location: " . $destination);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Ju lutem plotësoni të gjitha fushat.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Support bcrypt hash and demo strings
                $password_valid = password_verify($password, $user['password_hash']) 
                                  || ($password === 'password123')
                                  || ($password === 'password')
                                  || ($user['password_hash'] === 'hashed_password_' . $user['id']);

                if ($password_valid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['avatar_url'] = $user['avatar_url'];

                    $destination = !empty($redirect) ? $redirect : ($user['role'] === 'admin' ? "admin.php" : "profile.php");
                    header("Location: " . $destination);
                    exit;
                } else {
                    $error = "Fjalëkalimi është i pasaktë.";
                }
            } else {
                $error = "Nuk u gjet asnjë llogari me këtë email.";
            }
        } catch (PDOException $e) {
            $error = "Gabim gjatë kyçjes: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-md mx-auto px-4 py-12 w-full flex flex-col justify-center">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 shadow-2xl">
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-indigo-600/10 text-indigo-400 flex items-center justify-center mx-auto mb-3 text-2xl border border-indigo-500/20">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h1 class="text-2xl font-black text-white">Kyçu në Kalo.ks</h1>
            <p class="text-xs text-slate-400 mt-1">Qasuni në materialet, provimet dhe bisedat universitare</p>
        </div>

        <!-- Flash Notice (e.g. guest trying restricted action) -->
        <?php if (!empty($flash_msg)): ?>
            <div class="mb-4 p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-base shrink-0 text-amber-400"></i>
                <span class="leading-relaxed"><?= htmlspecialchars($flash_msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-base shrink-0 text-rose-400"></i>
                <span class="leading-relaxed"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" class="space-y-4">
            <?php if (!empty($redirect)): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Email Studentor</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="studenti@uni-pr.edu"
                       class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Fjalëkalimi</label>
                    <span class="text-[11px] text-slate-500">Demo: password123</span>
                </div>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition shadow-lg shadow-indigo-600/25 flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket text-xs"></i>
                <span>Kyçu</span>
            </button>
        </form>

        <!-- Quick Demo Switcher -->
        <div class="mt-6 pt-6 border-t border-slate-800 text-center">
            <span class="text-xs text-slate-400 block mb-3 font-medium">Kyçje e shpejtë për testim (Demo):</span>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <a href="login.php?demo=1<?= !empty($redirect) ? '&redirect=' . urlencode($redirect) : '' ?>" 
                   class="p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 border border-slate-700 text-slate-200 font-semibold transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-user text-indigo-400 text-xs"></i>
                    <span>Alice (Student)</span>
                </a>
                <a href="login.php?demo=4<?= !empty($redirect) ? '&redirect=' . urlencode($redirect) : '' ?>" 
                   class="p-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-semibold transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-rose-400 text-xs"></i>
                    <span>David (Admin)</span>
                </a>
            </div>
        </div>

        <p class="text-xs text-slate-400 text-center mt-6">
            Nuk keni ende llogari? <a href="signup.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" class="text-amber-400 font-bold hover:underline">Regjistrohu këtu</a>
        </p>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
