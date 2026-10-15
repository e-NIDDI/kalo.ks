<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Logged-in user context
$logged_user_id = $_SESSION['user_id'] ?? 1;

// Profile being viewed (default to logged-in user, or inspect another student by ?id=...)
$view_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $logged_user_id;
if ($view_user_id <= 0) {
    $view_user_id = $logged_user_id;
}

// Fetch viewer info for authorization
$viewer = null;
try {
    $vStmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $vStmt->execute([$logged_user_id]);
    $viewer = $vStmt->fetch();
} catch (PDOException $e) {
    $viewer = ['id' => $logged_user_id, 'role' => 'student'];
}

$is_owner = ($logged_user_id === $view_user_id);
$is_admin = ($viewer && $viewer['role'] === 'admin');

// -------------------------------------------------------------
// POST ACTION: Update Profile
// -------------------------------------------------------------
$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!$is_owner && !$is_admin) {
        $flash_error = "Nuk keni leje për të modifikuar këtë profil.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $faculty_id = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
        $avatar_url = trim($_POST['avatar_url'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (empty($full_name)) {
            $flash_error = "Emri dhe mbiemri janë të detyrueshëm.";
        } else {
            try {
                $upStmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, faculty_id = ?, avatar_url = ?, bio = ? 
                    WHERE id = ?
                ");
                $upStmt->execute([$full_name, $faculty_id, $avatar_url, $bio, $view_user_id]);

                // Update session if editing own profile
                if ($is_owner) {
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['avatar_url'] = $avatar_url;
                }

                header("Location: profile.php" . ($view_user_id !== $logged_user_id ? "?id=" . $view_user_id . "&updated=1" : "?updated=1"));
                exit;
            } catch (PDOException $e) {
                $flash_error = "Gabim gjatë përditësimit: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------
// POST ACTION: Delete Material (from My Uploads)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_material') {
    $mat_id = (int)($_POST['material_id'] ?? 0);
    if ($mat_id > 0) {
        try {
            // Verify ownership or admin privileges
            $checkStmt = $pdo->prepare("SELECT user_id, file_path FROM materials WHERE id = ?");
            $checkStmt->execute([$mat_id]);
            $mat = $checkStmt->fetch();

            if ($mat && ($mat['user_id'] == $logged_user_id || $is_admin)) {
                // Delete cascading dependencies
                $pdo->prepare("DELETE FROM comments WHERE material_id = ?")->execute([$mat_id]);
                $pdo->prepare("DELETE FROM upvotes WHERE material_id = ?")->execute([$mat_id]);
                $pdo->prepare("DELETE FROM saved_materials WHERE material_id = ?")->execute([$mat_id]);
                $pdo->prepare("DELETE FROM materials WHERE id = ?")->execute([$mat_id]);

                // Delete physical file if present
                if (!empty($mat['file_path'])) {
                    $local_file = __DIR__ . '/' . ltrim($mat['file_path'], '/');
                    if (is_file($local_file)) {
                        @unlink($local_file);
                    }
                }

                header("Location: profile.php" . ($view_user_id !== $logged_user_id ? "?id=" . $view_user_id . "&deleted=1" : "?deleted=1"));
                exit;
            } else {
                $flash_error = "Nuk keni autorizim për të fshirë këtë material.";
            }
        } catch (PDOException $e) {
            $flash_error = "Dështoi fshirja e materialit: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------
// POST ACTION: Unsave Material (from Bookmarks)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unsave_material') {
    $mat_id = (int)($_POST['material_id'] ?? 0);
    if ($mat_id > 0 && $is_owner) {
        try {
            $delSave = $pdo->prepare("DELETE FROM saved_materials WHERE user_id = ? AND material_id = ?");
            $delSave->execute([$logged_user_id, $mat_id]);
            header("Location: profile.php?unsaved=1");
            exit;
        } catch (PDOException $e) {
            $flash_error = "Gabim gjatë heqjes nga koleksioni: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------
// Fetch Profile User Info
// -------------------------------------------------------------
$user = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, f.name AS faculty_name, f.code AS faculty_code, f.color_code AS faculty_color
        FROM users u
        LEFT JOIN faculties f ON u.faculty_id = f.id
        WHERE u.id = ?
    ");
    $stmt->execute([$view_user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Gabim në leximin e profilit: " . $e->getMessage());
}

if (!$user) {
    require_once __DIR__ . '/header.php';
    echo '<div class="max-w-4xl mx-auto px-6 py-20 text-center"><h2 class="text-2xl font-bold text-red-400">Përdoruesi nuk u gjet!</h2><a href="index.php" class="mt-4 inline-block text-amber-400 underline">Kthehu te Ballina</a></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// Determine Title Badge based on Reputation Points
$reputation = (int)$user['reputation_points'];
if ($reputation < 20) {
    $title_badge = [
        'text' => 'Student Fillestar 🐣',
        'class' => 'bg-slate-800 text-slate-300 border border-slate-700'
    ];
} elseif ($reputation < 100) {
    $title_badge = [
        'text' => 'Kalo-Master ⚡',
        'class' => 'bg-amber-500/20 text-amber-400 border border-amber-500/40 glow-amber'
    ];
} elseif ($reputation < 300) {
    $title_badge = [
        'text' => 'Profesor i Nderit 🎓',
        'class' => 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40'
    ];
} else {
    $title_badge = [
        'text' => 'Legjendë e SEMS ⭐',
        'class' => 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/40 glow-indigo'
    ];
}

// -------------------------------------------------------------
// Fetch User's Uploaded Materials
// -------------------------------------------------------------
$my_materials = [];
try {
    $matStmt = $pdo->prepare("
        SELECT m.*, c.name AS course_name, c.code AS course_code, f.name AS faculty_name, f.code AS faculty_code,
               (SELECT COUNT(*) FROM comments WHERE material_id = m.id) AS comment_count
        FROM materials m
        JOIN courses c ON m.course_id = c.id
        JOIN faculties f ON c.faculty_id = f.id
        WHERE m.user_id = ?
        ORDER BY m.created_at DESC
    ");
    $matStmt->execute([$view_user_id]);
    $my_materials = $matStmt->fetchAll();
} catch (PDOException $e) {
    $my_materials = [];
}

// Aggregate stats
$total_uploads = count($my_materials);
$total_downloads = array_sum(array_column($my_materials, 'downloads_count'));
$total_upvotes = array_sum(array_column($my_materials, 'upvotes_count'));

// -------------------------------------------------------------
// Fetch User's Saved Materials (Bookmarks)
// -------------------------------------------------------------
$saved_materials = [];
try {
    $savedStmt = $pdo->prepare("
        SELECT m.*, c.name AS course_name, c.code AS course_code, f.name AS faculty_name, f.code AS faculty_code,
               u.full_name AS author_name, u.avatar_url AS author_avatar,
               sm.created_at AS saved_at
        FROM saved_materials sm
        JOIN materials m ON sm.material_id = m.id
        JOIN courses c ON m.course_id = c.id
        JOIN faculties f ON c.faculty_id = f.id
        JOIN users u ON m.user_id = u.id
        WHERE sm.user_id = ?
        ORDER BY sm.created_at DESC
    ");
    $savedStmt->execute([$view_user_id]);
    $saved_materials = $savedStmt->fetchAll();
} catch (PDOException $e) {
    $saved_materials = [];
}

// -------------------------------------------------------------
// Fetch Faculties for Modal Dropdown
// -------------------------------------------------------------
$all_faculties = [];
try {
    $all_faculties = $pdo->query("SELECT id, name, code FROM faculties ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $all_faculties = [];
}

// Include session-guarded header
require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 py-8 w-full">
    <!-- Feedback Alerts -->
    <?php if (isset($_GET['updated'])): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fas fa-check-circle text-lg"></i> Profili u përditësua me sukses!</span>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">✕</button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fas fa-trash-can text-lg"></i> Materiali u fshi përgjithmonë nga databaza.</span>
            <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-white">✕</button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['unsaved'])): ?>
        <div class="mb-6 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fas fa-bookmark text-lg"></i> Materiali u hoq nga lista e të ruajturave.</span>
            <button onclick="this.parentElement.remove()" class="text-amber-400 hover:text-white">✕</button>
        </div>
    <?php endif; ?>
    <?php if (!empty($flash_error)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fas fa-circle-exclamation text-lg"></i> <?= htmlspecialchars($flash_error) ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-white">✕</button>
        </div>
    <?php endif; ?>

    <!-- Profile Hero Card -->
    <div class="relative bg-gradient-to-br from-slate-900 via-slate-900/90 to-slate-950 border border-slate-800 rounded-3xl p-6 sm:p-10 mb-8 shadow-2xl overflow-hidden">
        <!-- Background Ambient Glow -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row items-center md:items-start gap-6 sm:gap-8">
            <!-- Avatar with Border & Badge -->
            <div class="relative shrink-0">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=160&h=160&auto=format&fit=circle') ?>" 
                     alt="<?= htmlspecialchars($user['full_name']) ?>" 
                     class="w-28 h-28 sm:w-36 sm:h-36 rounded-3xl object-cover border-4 border-amber-400/40 shadow-xl shadow-amber-500/10">
                <span class="absolute -bottom-2 -right-2 w-9 h-9 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-black text-sm border-2 border-slate-900 shadow-md">
                    <i class="fas fa-award"></i>
                </span>
            </div>

            <!-- User Metadata & Bio -->
            <div class="flex-1 text-center md:text-left space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center justify-center md:justify-start gap-2">
                            <?= htmlspecialchars($user['full_name']) ?>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider bg-red-500/20 text-red-400 border border-red-500/30 rounded-lg">Admin</span>
                            <?php endif; ?>
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            <i class="fas fa-envelope mr-1 text-slate-500"></i> <?= htmlspecialchars($user['email']) ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-calendar-day mr-1 text-slate-500"></i> Anëtar që nga <?= date('M Y', strtotime($user['created_at'])) ?>
                        </p>
                    </div>

                    <!-- Action: Edit Profile Button -->
                    <?php if ($is_owner || $is_admin): ?>
                        <button onclick="openModal('edit-profile-modal')" 
                                class="inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-100 hover:text-amber-400 text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl border border-slate-700 transition-all shadow-md">
                            <i class="fas fa-user-pen"></i>
                            <span>Ndrysho Profilin</span>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Badges Row -->
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 pt-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold <?= $title_badge['class'] ?>">
                        <?= $title_badge['text'] ?>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-semibold bg-slate-800/80 text-slate-300 border border-slate-700/80">
                        <i class="fas fa-university text-amber-400"></i>
                        <span><?= htmlspecialchars($user['faculty_name'] ?? 'Universiteti i Prishtinës') ?> <?= !empty($user['faculty_code']) ? '(' . htmlspecialchars($user['faculty_code']) . ')' : '' ?></span>
                    </span>
                </div>

                <!-- Bio -->
                <div class="bg-slate-950/40 rounded-2xl p-4 border border-slate-800/60 text-slate-300 text-sm leading-relaxed max-w-3xl">
                    <p class="italic text-slate-300">
                        "<?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : 'Nuk ka vendosur ende një përshkrim personal.' ?>"
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
        <!-- Stat: Reputation -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-amber-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Reputacioni</span>
                <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm"><i class="fas fa-star"></i></span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-amber-400"><?= number_format($reputation) ?></div>
            <p class="text-[11px] text-slate-500 mt-1">Pikë të fituara nga komuniteti</p>
        </div>

        <!-- Stat: Total Uploads -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-indigo-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ngarkime</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-sm"><i class="fas fa-cloud-arrow-up"></i></span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-indigo-400"><?= number_format($total_uploads) ?></div>
            <p class="text-[11px] text-slate-500 mt-1">Dokumente & materiale të ndara</p>
        </div>

        <!-- Stat: Total Downloads -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-emerald-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Shkarkime</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-sm"><i class="fas fa-download"></i></span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-emerald-400"><?= number_format($total_downloads) ?></div>
            <p class="text-[11px] text-slate-500 mt-1">Herë të shkarkuara nga studentët</p>
        </div>

        <!-- Stat: Upvotes -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-rose-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Vota Pozitive</span>
                <span class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-sm"><i class="fas fa-thumbs-up"></i></span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-rose-400"><?= number_format($total_upvotes) ?></div>
            <p class="text-[11px] text-slate-500 mt-1">Pëlqime në zgjidhje & shënime</p>
        </div>
    </div>

    <!-- Tabs Navigation & Content -->
    <div class="bg-slate-900/50 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
        <!-- Tab Bar -->
        <div class="flex border-b border-slate-800 bg-slate-900/80 p-2 gap-2" role="tablist">
            <button id="tab-btn-uploads" onclick="switchProfileTab('uploads')" 
                    class="flex-1 py-3 px-4 rounded-2xl font-bold text-sm transition-all flex items-center justify-center gap-2 bg-slate-800 text-amber-400 shadow-sm">
                <i class="fas fa-folder-open"></i>
                <span>Materialet e Mia</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-500/20 text-amber-400"><?= count($my_materials) ?></span>
            </button>
            <button id="tab-btn-saved" onclick="switchProfileTab('saved')" 
                    class="flex-1 py-3 px-4 rounded-2xl font-bold text-sm transition-all flex items-center justify-center gap-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50">
                <i class="fas fa-bookmark"></i>
                <span>Koleksioni i Ruajtur</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-black bg-slate-800 text-slate-300"><?= count($saved_materials) ?></span>
            </button>
        </div>

        <!-- Tab 1: My Uploads -->
        <div id="tab-content-uploads" class="p-6">
            <?php if (empty($my_materials)): ?>
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 rounded-2xl bg-slate-800/80 text-slate-500 flex items-center justify-center mx-auto mb-4 text-2xl">
                        <i class="fas fa-cloud-arrow-up"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-200 mb-1">Nuk keni ngarkuar asnjë material ende</h3>
                    <p class="text-slate-400 text-sm max-w-md mx-auto mb-6">Ndani shënimet, afatet e vjetra ose zgjidhjet e ushtrimeve për të fituar pikë reputacioni!</p>
                    <button onclick="if(typeof openModal === 'function' && document.getElementById('upload-modal')){ openModal('upload-modal'); } else { window.location.href='index.php#ngarko'; }" 
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-5 py-3 rounded-xl transition-all shadow-lg shadow-indigo-600/30">
                        <i class="fas fa-plus"></i>
                        <span>Ngarko Materialin e Parë</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($my_materials as $mat): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition-all">
                            <!-- Left: File Info -->
                            <div class="flex items-start gap-4 flex-1 min-w-0">
                                <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center shrink-0 text-xl">
                                    <?php if (strtolower($mat['file_type']) === 'pdf'): ?>
                                        <i class="fas fa-file-pdf text-red-400"></i>
                                    <?php elseif (in_array(strtolower($mat['file_type']), ['zip', 'rar'])): ?>
                                        <i class="fas fa-file-zipper text-amber-400"></i>
                                    <?php else: ?>
                                        <i class="fas fa-file-lines text-indigo-400"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <a href="view.php?id=<?= $mat['id'] ?>" class="font-bold text-slate-100 hover:text-amber-400 transition-colors truncate text-base">
                                            <?= htmlspecialchars($mat['title']) ?>
                                        </a>
                                        <?php if ((int)$mat['is_verified'] === 1): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                                <i class="fas fa-shield-check"></i> Verifikuar
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                                <i class="fas fa-clock"></i> Në Pritje
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400">
                                        <span><i class="fas fa-university mr-1 text-slate-500"></i><?= htmlspecialchars($mat['faculty_code'] ?? 'UP') ?></span>
                                        <span><i class="fas fa-book-open mr-1 text-slate-500"></i><?= htmlspecialchars($mat['course_name']) ?></span>
                                        <span><i class="fas fa-database mr-1 text-slate-500"></i><?= strtoupper(htmlspecialchars($mat['file_type'])) ?> (<?= htmlspecialchars($mat['file_size']) ?>)</span>
                                        <span><i class="fas fa-calendar mr-1 text-slate-500"></i><?= date('d M Y', strtotime($mat['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Counters & Actions -->
                            <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-800">
                                <div class="flex items-center gap-3 text-xs text-slate-400 mr-2">
                                    <span title="Shikime"><i class="fas fa-eye text-slate-500 mr-1"></i><?= (int)$mat['views_count'] ?></span>
                                    <span title="Shkarkime"><i class="fas fa-download text-emerald-500 mr-1"></i><?= (int)$mat['downloads_count'] ?></span>
                                    <span title="Vota"><i class="fas fa-thumbs-up text-amber-400 mr-1"></i><?= (int)$mat['upvotes_count'] ?></span>
                                    <span title="Komente"><i class="fas fa-comment text-sky-400 mr-1"></i><?= (int)$mat['comment_count'] ?></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a href="view.php?id=<?= $mat['id'] ?>" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-colors">
                                        <i class="fas fa-eye mr-1"></i> Shiko
                                    </a>
                                    <?php if ($is_owner || $is_admin): ?>
                                        <form method="POST" onsubmit="return confirm('A jeni të sigurt që dëshironi ta fshini këtë material? Ky veprim nuk kthehet mbrapsht.');" class="inline">
                                            <input type="hidden" name="action" value="delete_material">
                                            <input type="hidden" name="material_id" value="<?= $mat['id'] ?>">
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-semibold transition-colors">
                                                <i class="fas fa-trash-can mr-1"></i> Fshij
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab 2: Saved Materials (Bookmarks) -->
        <div id="tab-content-saved" class="p-6 hidden">
            <?php if (empty($saved_materials)): ?>
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 rounded-2xl bg-slate-800/80 text-slate-500 flex items-center justify-center mx-auto mb-4 text-2xl">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-200 mb-1">Nuk keni asnjë material të ruajtur</h3>
                    <p class="text-slate-400 text-sm max-w-md mx-auto mb-6">Kur të gjeni skripte ose teste me vlerë në Ballinë, klikoni butonin "Ruaj" për t'i pasur gjithmonë gati këtu.</p>
                    <a href="index.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-5 py-3 rounded-xl transition-all shadow-lg shadow-indigo-600/30">
                        <i class="fas fa-magnifying-glass"></i>
                        <span>Eksploro Materialet</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($saved_materials as $saved): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition-all">
                            <!-- Left: File & Author Info -->
                            <div class="flex items-start gap-4 flex-1 min-w-0">
                                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 text-xl">
                                    <i class="fas fa-bookmark"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <a href="view.php?id=<?= $saved['id'] ?>" class="font-bold text-slate-100 hover:text-amber-400 transition-colors truncate text-base block">
                                        <?= htmlspecialchars($saved['title']) ?>
                                    </a>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400 mt-1">
                                        <span><i class="fas fa-user mr-1 text-slate-500"></i><?= htmlspecialchars($saved['author_name']) ?></span>
                                        <span><i class="fas fa-university mr-1 text-slate-500"></i><?= htmlspecialchars($saved['faculty_code'] ?? 'UP') ?></span>
                                        <span><i class="fas fa-book-open mr-1 text-slate-500"></i><?= htmlspecialchars($saved['course_name']) ?></span>
                                        <span><i class="fas fa-clock mr-1 text-slate-500"></i>Ruajtur më <?= date('d M Y', strtotime($saved['saved_at'])) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Counters & Actions -->
                            <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-800">
                                <div class="flex items-center gap-3 text-xs text-slate-400 mr-2">
                                    <span title="Shkarkime"><i class="fas fa-download text-emerald-500 mr-1"></i><?= (int)$saved['downloads_count'] ?></span>
                                    <span title="Vota"><i class="fas fa-thumbs-up text-amber-400 mr-1"></i><?= (int)$saved['upvotes_count'] ?></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a href="view.php?id=<?= $saved['id'] ?>" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-colors">
                                        <i class="fas fa-eye mr-1"></i> Shiko
                                    </a>
                                    <?php if ($is_owner): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="unsave_material">
                                            <input type="hidden" name="material_id" value="<?= $saved['id'] ?>">
                                            <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors" title="Hiq nga të ruajturat">
                                                <i class="fas fa-bookmark-slash mr-1"></i> Hiq
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Edit Profile Modal -->
<?php if ($is_owner || $is_admin): ?>
<div id="edit-profile-modal" class="modal hidden fixed inset-0 z-50 items-center justify-center p-4">
    <div class="modal-overlay fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"></div>
    <div class="relative w-full max-w-lg bg-slate-900 border border-slate-700 rounded-3xl shadow-2xl p-6 sm:p-8 z-10 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
            <div class="flex items-center gap-2">
                <i class="fas fa-user-gear text-amber-400 text-lg"></i>
                <h3 class="text-xl font-bold text-white">Ndrysho Profilin</h3>
            </div>
            <button onclick="closeModal('edit-profile-modal')" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors">✕</button>
        </div>

        <form method="POST" action="profile.php<?= $view_user_id !== $logged_user_id ? '?id=' . $view_user_id : '' ?>" class="space-y-4">
            <input type="hidden" name="action" value="update_profile">

            <!-- Full Name -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Emri dhe Mbiemri *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
            </div>

            <!-- Faculty Dropdown -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Fakulteti</label>
                <select name="faculty_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
                    <option value="">Zgjidh Fakultetin...</option>
                    <?php foreach ($all_faculties as $fac): ?>
                        <option value="<?= $fac['id'] ?>" <?= ((int)$user['faculty_id'] === (int)$fac['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fac['name']) ?> (<?= htmlspecialchars($fac['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Avatar URL -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">URL e Fotos së Profilit (Avatar)</label>
                <div class="flex gap-3">
                    <input type="url" name="avatar_url" id="modal-avatar-input" value="<?= htmlspecialchars($user['avatar_url']) ?>" placeholder="https://..."
                           class="flex-1 bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                           oninput="document.getElementById('modal-avatar-preview').src = this.value || 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=80&h=80&auto=format&fit=circle';">
                    <img id="modal-avatar-preview" src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=80&h=80&auto=format&fit=circle') ?>" 
                         alt="Preview" class="w-10 h-10 rounded-xl object-cover border border-slate-700 shrink-0">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Mund të përdorni foto nga Unsplash, GitHub ose link tjetër publik.</p>
            </div>

            <!-- Bio -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Bio / Përshkrimi Personal</label>
                <textarea name="bio" rows="3" placeholder="P.sh. Student i vitit të 3-të në Inxhinieri Kompjuterike..." 
                          class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeModal('edit-profile-modal')" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition-colors">
                    Anulo
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-sm font-bold transition-all shadow-lg shadow-amber-500/20">
                    Ruaj Ndryshimet
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Tab switcher for Profile
function switchProfileTab(tab) {
    const btnUploads = document.getElementById('tab-btn-uploads');
    const btnSaved = document.getElementById('tab-btn-saved');
    const contentUploads = document.getElementById('tab-content-uploads');
    const contentSaved = document.getElementById('tab-content-saved');

    if (tab === 'uploads') {
        btnUploads.classList.add('bg-slate-800', 'text-amber-400', 'shadow-sm');
        btnUploads.classList.remove('text-slate-400');
        btnSaved.classList.remove('bg-slate-800', 'text-amber-400', 'shadow-sm');
        btnSaved.classList.add('text-slate-400');

        contentUploads.classList.remove('hidden');
        contentSaved.classList.add('hidden');
    } else {
        btnSaved.classList.add('bg-slate-800', 'text-amber-400', 'shadow-sm');
        btnSaved.classList.remove('text-slate-400');
        btnUploads.classList.remove('bg-slate-800', 'text-amber-400', 'shadow-sm');
        btnUploads.classList.add('text-slate-400');

        contentSaved.classList.remove('hidden');
        contentUploads.classList.add('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
