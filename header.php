<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Resolve current user strictly without artificial fallbacks
$logged_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$navbar_user = null;

if ($logged_user_id) {
    try {
        $userStmt = $pdo->prepare("SELECT id, full_name, email, role, reputation_points, avatar_url FROM users WHERE id = ?");
        $userStmt->execute([$logged_user_id]);
        $navbar_user = $userStmt->fetch() ?: null;
        if (!$navbar_user) {
            // User ID in session doesn't exist in DB - invalidate session user
            unset($_SESSION['user_id']);
            $logged_user_id = null;
        }
    } catch (PDOException $e) {
        $navbar_user = null;
        $logged_user_id = null;
    }
}

// Fetch unread notifications ONLY if user is authenticated
$nav_notifications = [];
$unread_count = 0;
if ($navbar_user) {
    try {
        $notifStmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $notifStmt->execute([$navbar_user['id']]);
        $nav_notifications = $notifStmt->fetchAll();
        
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unreadStmt->execute([$navbar_user['id']]);
        $unread_count = (int)$unreadStmt->fetchColumn();
    } catch (PDOException $e) {
        $nav_notifications = [];
        $unread_count = 0;
    }
}

// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="sq" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalo.ks - Platforma Studentore e Provimeve</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Fonts & Guaranteed FontAwesome 6 Free CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glow-amber { box-shadow: 0 0 25px rgba(251, 191, 36, 0.35); }
        .glow-indigo { box-shadow: 0 0 25px rgba(99, 102, 241, 0.45); }
        @keyframes pulse-glow { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #020617; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased selection:bg-amber-500 selection:text-slate-950">

<!-- Sticky Navbar -->
<nav class="sticky top-0 z-50 backdrop-blur-md bg-slate-900/90 border-b border-slate-800/80 w-full transition-all">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between gap-4">
        <!-- Brand & Badge -->
        <a href="index.php" class="flex items-center gap-3 group shrink-0">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-300 flex items-center justify-center text-slate-950 font-black text-xl shadow-lg shadow-amber-500/20 group-hover:scale-105 transition-transform">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="text-xl font-black tracking-tight text-white">Kalo<span class="text-amber-400">.ks</span></span>
                    <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-full">UP</span>
                </div>
                <p class="text-[10px] text-slate-400 font-medium -mt-1 hidden sm:block">Hubi Studentor i Kosovës</p>
            </div>
        </a>

        <!-- Global Search -->
        <form action="index.php" method="GET" class="relative hidden md:flex items-center flex-1 max-w-xs lg:max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 text-slate-400 text-sm"></i>
            <input type="text" name="search" placeholder="Kërko provime, skripte, lëndë..." 
                   class="bg-slate-800/80 border border-slate-700/80 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-400 w-full focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
        </form>

        <!-- Navigation Links (Desktop) -->
        <div class="hidden lg:flex items-center gap-1 xl:gap-2">
            <a href="index.php" class="px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors <?= $current_page === 'index.php' ? 'text-amber-400 bg-amber-400/10' : 'text-slate-300 hover:text-amber-400 hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-layer-group mr-1.5 text-xs"></i> Ballina
            </a>
            <a href="chat.php" class="px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors <?= $current_page === 'chat.php' ? 'text-amber-400 bg-amber-400/10' : 'text-slate-300 hover:text-amber-400 hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-comments mr-1.5 text-xs"></i> Chat Dhomat
            </a>
            <a href="leaderboard.php" class="px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors <?= $current_page === 'leaderboard.php' ? 'text-amber-400 bg-amber-400/10' : 'text-slate-300 hover:text-amber-400 hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-trophy mr-1.5 text-xs"></i> Renditja
            </a>
            <?php if ($navbar_user && $navbar_user['role'] === 'admin'): ?>
            <a href="admin.php" class="px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors <?= $current_page === 'admin.php' ? 'text-rose-400 bg-rose-400/10' : 'text-rose-400/80 hover:text-rose-400 hover:bg-rose-500/10' ?>">
                <i class="fa-solid fa-shield-halved mr-1.5 text-xs"></i> Admin
            </a>
            <?php endif; ?>
        </div>

        <!-- Right Side: Reputation, Notifications, Actions, & Profile -->
        <div class="flex items-center gap-2 sm:gap-3">
            <?php if ($navbar_user): ?>
                <!-- Reputation Pill -->
                <a href="profile.php" title="Pikët tua të reputacionit" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400 hover:bg-amber-500/20 transition-all">
                    <i class="fa-solid fa-star text-amber-400"></i>
                    <span><?= number_format((int)$navbar_user['reputation_points']) ?> <span class="hidden xl:inline text-[10px] text-amber-400/70 font-normal">Pikë</span></span>
                </a>

                <!-- Notification Bell -->
                <div class="relative">
                    <button id="notif-btn" type="button" class="relative p-2.5 text-slate-300 hover:text-amber-400 hover:bg-slate-800 rounded-xl transition-colors" aria-label="Njoftimet">
                        <i class="fa-solid fa-bell text-lg"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="absolute top-1 right-1 bg-amber-500 text-slate-950 font-black text-[10px] rounded-full w-4 h-4 flex items-center justify-center animate-pulse">
                                <?= $unread_count ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div id="notif-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-4 z-50">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                            <span class="text-xs font-bold text-slate-200 uppercase tracking-wider">Njoftimet</span>
                            <span class="text-[11px] text-amber-400 font-semibold"><?= $unread_count ?> të reja</span>
                        </div>
                        <div class="space-y-2.5 py-3 max-h-64 overflow-y-auto">
                            <?php if (empty($nav_notifications)): ?>
                                <p class="text-xs text-slate-500 text-center py-4">Nuk keni njoftime të reja.</p>
                            <?php else: ?>
                                <?php foreach ($nav_notifications as $notif): ?>
                                    <div class="p-2.5 rounded-xl bg-slate-800/40 border border-slate-800 text-xs text-slate-300 hover:bg-slate-800/70 transition-colors">
                                        <p class="leading-relaxed"><?= htmlspecialchars($notif['message']) ?></p>
                                        <span class="text-[10px] text-slate-500 mt-1 block"><?= date('d M, H:i', strtotime($notif['created_at'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Upload Button (authenticated: opens modal) -->
                <button onclick="if(typeof openModal === 'function' && document.getElementById('upload-modal')){ openModal('upload-modal'); } else { window.location.href='index.php#ngarko'; }" 
                        class="hidden sm:inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-600/25 transition">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Ngarko</span>
                </button>

                <!-- User Profile Dropdown -->
                <div class="relative">
                    <button id="profile-dropdown-btn" type="button" class="flex items-center gap-2 p-1 rounded-xl hover:bg-slate-800 transition-colors">
                        <img src="<?= htmlspecialchars($navbar_user['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=80&h=80&auto=format&fit=crop&q=80') ?>" 
                             alt="<?= htmlspecialchars($navbar_user['full_name']) ?>" 
                             class="w-9 h-9 rounded-full object-cover border-2 border-slate-700">
                        <i class="fa-solid fa-chevron-down text-slate-400 text-xs hidden sm:block"></i>
                    </button>
                    <div id="profile-dropdown-menu" class="hidden absolute right-0 mt-3 w-56 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-2 z-50">
                        <div class="px-3 py-2 border-b border-slate-800 mb-1">
                            <p class="text-sm font-bold text-slate-100 truncate"><?= htmlspecialchars($navbar_user['full_name']) ?></p>
                            <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($navbar_user['email']) ?></p>
                        </div>
                        <a href="profile.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-amber-400 hover:bg-slate-800/60 rounded-xl transition-colors">
                            <i class="fa-solid fa-circle-user w-4 text-center"></i> Profili Im
                        </a>
                        <a href="chat.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-amber-400 hover:bg-slate-800/60 rounded-xl transition-colors">
                            <i class="fa-solid fa-comments w-4 text-center"></i> Chat me Studentë
                        </a>
                        <a href="leaderboard.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-amber-400 hover:bg-slate-800/60 rounded-xl transition-colors">
                            <i class="fa-solid fa-trophy w-4 text-center"></i> Renditja
                        </a>
                        <?php if ($navbar_user['role'] === 'admin'): ?>
                        <a href="admin.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-rose-400 hover:bg-rose-500/10 rounded-xl transition-colors">
                            <i class="fa-solid fa-shield-halved w-4 text-center"></i> Paneli Admin
                        </a>
                        <?php endif; ?>
                        <div class="border-t border-slate-800 my-1"></div>
                        <a href="logout.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-rose-400 hover:bg-rose-500/10 rounded-xl transition-colors">
                            <i class="fa-solid fa-right-from-bracket w-4 text-center"></i> Dil (Logout)
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Unauthenticated / Guest View -->
                <a href="login.php?msg=<?= urlencode('Ju lutem kyçuni për të ngarkuar materiale.') ?>&redirect=index.php" 
                   class="hidden sm:inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-600/25 transition">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Ngarko</span>
                </a>
                <a href="login.php" class="px-3.5 py-2 text-xs sm:text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 rounded-xl transition">
                    <i class="fa-solid fa-right-to-bracket mr-1 text-xs sm:inline hidden"></i> Kyçu
                </a>
                <a href="signup.php" class="px-3.5 py-2 text-xs sm:text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/25 transition">
                    <i class="fa-solid fa-user-plus mr-1 text-xs sm:inline hidden"></i> Regjistrohu
                </a>
            <?php endif; ?>

            <!-- Mobile Hamburger Button -->
            <button id="mobile-menu-btn" type="button" class="lg:hidden p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition" aria-label="Menyja">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div id="mobile-nav-drawer" class="hidden lg:hidden border-t border-slate-800 bg-slate-900/95 px-4 py-4 space-y-2">
        <form action="index.php" method="GET" class="relative mb-3">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
            <input type="text" name="search" placeholder="Kërko materiale..." 
                   class="bg-slate-800 border border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-400 w-full focus:outline-none focus:border-indigo-500">
        </form>
        <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $current_page === 'index.php' ? 'text-amber-400 bg-amber-400/10' : 'text-slate-300 hover:bg-slate-800' ?>">
            <i class="fa-solid fa-layer-group w-5 text-center"></i> Ballina e Materialeve
        </a>
        <a href="chat.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $current_page === 'chat.php' ? 'text-amber-400 bg-amber-400/10' : 'text-slate-300 hover:bg-slate-800' ?>">
            <i class="fa-solid fa-comments w-5 text-center"></i> Live Chat Fakultetet
        </a>
        <a href="leaderboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $current_page === 'leaderboard.php' ? 'text-amber-400 bg-amber-400/10' : 'text-slate-300 hover:bg-slate-800' ?>">
            <i class="fa-solid fa-trophy w-5 text-center"></i> Leaderboard
        </a>

        <?php if ($navbar_user): ?>
            <a href="profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $current_page === 'profile.php' ? 'text-amber-400 bg-amber-400/10' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-circle-user w-5 text-center"></i> Profili Im
            </a>
            <?php if ($navbar_user['role'] === 'admin'): ?>
            <a href="admin.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-rose-400 hover:bg-rose-500/10">
                <i class="fa-solid fa-shield-halved w-5 text-center"></i> Admin Panel
            </a>
            <?php endif; ?>
            <div class="border-t border-slate-800 my-2"></div>
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-rose-400 hover:bg-rose-500/10">
                <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> Dil (Logout)
            </a>
        <?php else: ?>
            <div class="border-t border-slate-800 my-2"></div>
            <a href="login.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:bg-slate-800">
                <i class="fa-solid fa-right-to-bracket w-5 text-center"></i> Kyçu
            </a>
            <a href="signup.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-indigo-400 hover:bg-slate-800">
                <i class="fa-solid fa-user-plus w-5 text-center"></i> Regjistrohu
            </a>
        <?php endif; ?>
    </div>
</nav>

<script>
// Header Dropdowns and Mobile Navigation
document.addEventListener('DOMContentLoaded', function() {
    const notifBtn = document.getElementById('notif-btn');
    const notifDropdown = document.getElementById('notif-dropdown');
    const profileBtn = document.getElementById('profile-dropdown-btn');
    const profileMenu = document.getElementById('profile-dropdown-menu');
    const mobileBtn = document.getElementById('mobile-menu-btn');
    const mobileDrawer = document.getElementById('mobile-nav-drawer');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (profileMenu) profileMenu.classList.add('hidden');
            notifDropdown.classList.toggle('hidden');
        });
    }

    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (notifDropdown) notifDropdown.classList.add('hidden');
            profileMenu.classList.toggle('hidden');
        });
    }

    if (mobileBtn && mobileDrawer) {
        mobileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileDrawer.classList.toggle('hidden');
        });
    }

    document.addEventListener('click', function(e) {
        if (notifDropdown && !notifDropdown.contains(e.target) && e.target !== notifBtn) {
            notifDropdown.classList.add('hidden');
        }
        if (profileMenu && !profileMenu.contains(e.target) && e.target !== profileBtn) {
            profileMenu.classList.add('hidden');
        }
    });
});
</script>
