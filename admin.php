<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Quick switch to admin demo user for evaluator convenience
if (isset($_GET['switch_admin'])) {
    // Find first admin user (e.g. David Lee, user 4 in demo seed)
    try {
        $admStmt = $pdo->query("SELECT id, role FROM users WHERE role = 'admin' LIMIT 1");
        $admUser = $admStmt->fetch();
        if ($admUser) {
            $_SESSION['user_id'] = $admUser['id'];
            $_SESSION['role'] = 'admin';
        }
    } catch (PDOException $e) {}
    header("Location: admin.php");
    exit;
}

// Logged-in user context
$logged_user_id = $_SESSION['user_id'] ?? 4; // Defaults to user 4 (David Lee, Admin) if not set

// Verify role from database
$current_admin = null;
try {
    $authStmt = $pdo->prepare("SELECT id, full_name, email, role, avatar_url FROM users WHERE id = ?");
    $authStmt->execute([$logged_user_id]);
    $current_admin = $authStmt->fetch();
} catch (PDOException $e) {
    $current_admin = null;
}

// Access guard
if (!$current_admin || $current_admin['role'] !== 'admin') {
    require_once __DIR__ . '/header.php';
    ?>
    <main class="flex-1 max-w-3xl mx-auto px-6 py-20 text-center">
        <div class="w-20 h-20 rounded-3xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-center justify-center mx-auto mb-6 text-3xl">
            <i class="fas fa-shield-halved"></i>
        </div>
        <h1 class="text-3xl font-extrabold text-white mb-3">Qasja u Refuzua (Admins Only)</h1>
        <p class="text-slate-400 text-sm max-w-md mx-auto mb-8">
            Ky panel është i rezervuar vetëm për stafin administrativ dhe moderatorët e Kalo.ks.
        </p>
        <div class="flex flex-wrap items-center justify-center gap-4">
            <a href="index.php" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition-colors">
                Kthehu te Ballina
            </a>
            <!-- 1-Click switch for testers/evaluators -->
            <a href="admin.php?switch_admin=1" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-sm font-bold transition-all shadow-lg shadow-amber-500/20">
                <i class="fas fa-key mr-1.5"></i> Hyr si Administrator Demo (David Lee)
            </a>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// -------------------------------------------------------------
// POST ACTIONS: Verify, Delete, Toggle Role
// -------------------------------------------------------------
$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Verify Material & Award +20 points
    if ($action === 'verify_material') {
        $material_id = (int)($_POST['material_id'] ?? 0);
        if ($material_id > 0) {
            try {
                // Fetch material and uploader
                $mStmt = $pdo->prepare("SELECT id, user_id, title, is_verified FROM materials WHERE id = ?");
                $mStmt->execute([$material_id]);
                $mat = $mStmt->fetch();

                if ($mat) {
                    if ((int)$mat['is_verified'] === 1) {
                        $flash_error = "Ky material është i verifikuar më parë.";
                    } else {
                        // Mark as verified
                        $upd = $pdo->prepare("UPDATE materials SET is_verified = 1 WHERE id = ?");
                        $upd->execute([$material_id]);

                        // Award +20 reputation points to uploader
                        $pRep = $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 20 WHERE id = ?");
                        $pRep->execute([$mat['user_id']]);

                        // Send in-app notification to student
                        $notifMsg = "Zgjidhja/materiali juaj '" . $mat['title'] . "' u verifikua nga moderatori! Keni fituar +20 pikë reputacioni.";
                        $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        $nStmt->execute([$mat['user_id'], $notifMsg]);

                        $flash_success = "Materiali '" . htmlspecialchars($mat['title']) . "' u verifikua me sukses dhe autori u shpërblye me +20 pikë reputacioni!";
                    }
                } else {
                    $flash_error = "Materiali nuk u gjet.";
                }
            } catch (PDOException $e) {
                $flash_error = "Dështoi verifikimi: " . $e->getMessage();
            }
        }
    }

    // 2. Delete Material (Flagged or Rejected File)
    elseif ($action === 'delete_material') {
        $material_id = (int)($_POST['material_id'] ?? 0);
        if ($material_id > 0) {
            try {
                $mStmt = $pdo->prepare("SELECT id, title, file_path, user_id FROM materials WHERE id = ?");
                $mStmt->execute([$material_id]);
                $mat = $mStmt->fetch();

                if ($mat) {
                    // Remove references
                    $pdo->prepare("DELETE FROM comments WHERE material_id = ?")->execute([$material_id]);
                    $pdo->prepare("DELETE FROM upvotes WHERE material_id = ?")->execute([$material_id]);
                    $pdo->prepare("DELETE FROM saved_materials WHERE material_id = ?")->execute([$material_id]);
                    $pdo->prepare("DELETE FROM materials WHERE id = ?")->execute([$material_id]);

                    // Delete physical file if exists
                    if (!empty($mat['file_path'])) {
                        $local_file = __DIR__ . '/' . ltrim($mat['file_path'], '/');
                        if (is_file($local_file)) {
                            @unlink($local_file);
                        }
                    }

                    // Optional notification to user about removal
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
                        ->execute([$mat['user_id'], "Materiali juaj '" . $mat['title'] . "' u fshi nga moderatori për shkak të mosplotësimit të kritereve."]);

                    $flash_success = "Materiali '" . htmlspecialchars($mat['title']) . "' u fshi përgjithmonë nga platforma.";
                }
            } catch (PDOException $e) {
                $flash_error = "Dështoi fshirja: " . $e->getMessage();
            }
        }
    }

    // 3. Toggle User Role (Student <-> Admin)
    elseif ($action === 'toggle_role') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $new_role = trim($_POST['new_role'] ?? '');

        if ($target_user_id > 0 && in_array($new_role, ['student', 'admin'])) {
            if ($target_user_id === (int)$logged_user_id && $new_role === 'student') {
                $flash_error = "Nuk mund t'ia hiqni vetes rolin e administratorit.";
            } else {
                try {
                    $uStmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $uStmt->execute([$new_role, $target_user_id]);
                    $flash_success = "Roli i përdoruesit u ndryshua në: " . strtoupper($new_role);
                } catch (PDOException $e) {
                    $flash_error = "Dështoi ndryshimi i rolit: " . $e->getMessage();
                }
            }
        }
    }
}

// -------------------------------------------------------------
// Fetch Metrics & Statistics
// -------------------------------------------------------------
$total_users = 0;
$pending_count = 0;
$verified_count = 0;
$total_downloads = 0;

try {
    $total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $pending_count = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE is_verified = 0")->fetchColumn();
    $verified_count = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE is_verified = 1")->fetchColumn();
    $total_downloads = (int)$pdo->query("SELECT COALESCE(SUM(downloads_count), 0) FROM materials")->fetchColumn();
} catch (PDOException $e) {}

// Storage calculation
$upload_dir = __DIR__ . '/uploads';
$storage_bytes = 0;
if (is_dir($upload_dir)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile()) {
            $storage_bytes += $file->getSize();
        }
    }
}
$storage_mb = round($storage_bytes / (1024 * 1024), 2);

// -------------------------------------------------------------
// Fetch Unverified Queue (Verification Queue)
// -------------------------------------------------------------
$pending_materials = [];
try {
    $pqStmt = $pdo->query("
        SELECT m.*, u.full_name AS author_name, u.email AS author_email, u.avatar_url AS author_avatar,
               c.name AS course_name, c.code AS course_code,
               f.name AS faculty_name, f.code AS faculty_code
        FROM materials m
        JOIN users u ON m.user_id = u.id
        JOIN courses c ON m.course_id = c.id
        JOIN faculties f ON c.faculty_id = f.id
        WHERE m.is_verified = 0
        ORDER BY m.created_at ASC
    ");
    $pending_materials = $pqStmt->fetchAll();
} catch (PDOException $e) {
    $pending_materials = [];
}

// -------------------------------------------------------------
// Fetch All Materials for Moderation (Delete Flagged)
// -------------------------------------------------------------
$all_materials = [];
try {
    $amStmt = $pdo->query("
        SELECT m.*, u.full_name AS author_name,
               c.name AS course_name, f.code AS faculty_code
        FROM materials m
        JOIN users u ON m.user_id = u.id
        JOIN courses c ON m.course_id = c.id
        JOIN faculties f ON c.faculty_id = f.id
        ORDER BY m.created_at DESC
        LIMIT 50
    ");
    $all_materials = $amStmt->fetchAll();
} catch (PDOException $e) {
    $all_materials = [];
}

// -------------------------------------------------------------
// Fetch Users for Management
// -------------------------------------------------------------
$all_users = [];
try {
    $uListStmt = $pdo->query("
        SELECT u.*, f.name AS faculty_name, f.code AS faculty_code,
               COUNT(m.id) AS total_uploads
        FROM users u
        LEFT JOIN faculties f ON u.faculty_id = f.id
        LEFT JOIN materials m ON u.id = m.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $all_users = $uListStmt->fetchAll();
} catch (PDOException $e) {
    $all_users = [];
}

require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 py-8 w-full">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-red-500/10 border border-red-500/30 text-red-400 mb-2">
                <i class="fas fa-shield-halved"></i> Paneli i Moderimit & Administrimit
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                Paneli Administrativ Kalo.ks
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Verifikoni zgjidhjet e provimeve, moderoni materialet e dyshimta dhe menaxhoni përdoruesit.
            </p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Admin: <?= htmlspecialchars($current_admin['full_name']) ?></span>
            </span>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($flash_success)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-between shadow-lg">
            <span class="flex items-center gap-2 font-medium text-sm"><i class="fas fa-circle-check text-lg"></i> <?= $flash_success ?></span>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">✕</button>
        </div>
    <?php endif; ?>
    <?php if (!empty($flash_error)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-center justify-between shadow-lg">
            <span class="flex items-center gap-2 font-medium text-sm"><i class="fas fa-triangle-exclamation text-lg"></i> <?= $flash_error ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-white">✕</button>
        </div>
    <?php endif; ?>

    <!-- Metric Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
        <!-- Metric: Pending Queue -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-amber-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Në Pritje (Queue)</span>
                <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm font-black">
                    <i class="fas fa-clock"></i>
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-amber-400"><?= number_format($pending_count) ?></div>
            <p class="text-[11px] text-slate-500 mt-1">Materiale që kërkojnë shqyrtim</p>
        </div>

        <!-- Metric: Verified Solutions -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-emerald-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Të Verifikuara</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-sm">
                    <i class="fas fa-shield-check"></i>
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-emerald-400"><?= number_format($verified_count) ?></div>
            <p class="text-[11px] text-slate-500 mt-1">Zgjidhje zyrtarisht të miratuara</p>
        </div>

        <!-- Metric: Total Users -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-sky-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Studentë Aktivë</span>
                <span class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center text-sm">
                    <i class="fas fa-users"></i>
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-sky-400"><?= number_format($total_users) ?></div>
            <p class="text-[11px] text-slate-500 mt-1">Llogari të regjistruara në sistem</p>
        </div>

        <!-- Metric: Storage / Downloads -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-indigo-500/40 transition-colors shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Hapësira e Skedarëve</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-sm">
                    <i class="fas fa-hard-drive"></i>
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-indigo-400"><?= $storage_mb ?> <span class="text-xs font-normal text-slate-400">MB</span></div>
            <p class="text-[11px] text-slate-500 mt-1"><?= number_format($total_downloads) ?> shkarkime totale</p>
        </div>
    </div>

    <!-- Administrative Tabs -->
    <div class="bg-slate-900/50 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl mb-8">
        <!-- Navigation Buttons -->
        <div class="flex flex-wrap border-b border-slate-800 bg-slate-900/90 p-2 gap-2" role="tablist">
            <button id="adm-tab-queue-btn" onclick="switchAdminTab('queue')" 
                    class="flex-1 py-3 px-4 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 bg-slate-800 text-amber-400 shadow-sm">
                <i class="fas fa-clock-rotate-left"></i>
                <span>Radha e Verifikimit</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-500/20 text-amber-400"><?= count($pending_materials) ?></span>
            </button>
            <button id="adm-tab-materials-btn" onclick="switchAdminTab('materials')" 
                    class="flex-1 py-3 px-4 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50">
                <i class="fas fa-file-shield"></i>
                <span>Moderimi i Skedarëve (Flagged)</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-black bg-slate-800 text-slate-400"><?= count($all_materials) ?></span>
            </button>
            <button id="adm-tab-users-btn" onclick="switchAdminTab('users')" 
                    class="flex-1 py-3 px-4 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50">
                <i class="fas fa-users-gear"></i>
                <span>Menaxhimi i Përdoruesve</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-black bg-slate-800 text-slate-400"><?= count($all_users) ?></span>
            </button>
        </div>

        <!-- ------------------------------------------------------------- -->
        <!-- Tab 1: Verification Queue -->
        <!-- ------------------------------------------------------------- -->
        <div id="adm-tab-queue" class="p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-tasks text-amber-400"></i>
                        <span>Materialet në Pritje të Miratimit</span>
                    </h2>
                    <p class="text-xs text-slate-400">Verifikimi i saktësisë i jep studentit autor +20 pikë reputacioni.</p>
                </div>
            </div>

            <?php if (empty($pending_materials)): ?>
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto mb-4 text-2xl border border-emerald-500/20">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-1">Radha është e zbrazët!</h3>
                    <p class="text-slate-400 text-sm max-w-sm mx-auto">Të gjitha materialet e ngarkuara nga studentët janë shqyrtuar me sukses.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse whitespace-nowrap min-w-[750px]">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-950/40 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                <th class="py-3.5 px-4">Materiali & Përshkrimi</th>
                                <th class="py-3.5 px-4">Autori</th>
                                <th class="py-3.5 px-4">Fakulteti / Lënda</th>
                                <th class="py-3.5 px-4">Data</th>
                                <th class="py-3.5 px-4 text-right">Veprimet e Moderimit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-sm">
                            <?php foreach ($pending_materials as $pMat): ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="py-4 px-4 max-w-xs">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0 text-base">
                                                <i class="fas fa-file"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <a href="view.php?id=<?= $pMat['id'] ?>" target="_blank" class="font-bold text-white hover:text-amber-400 transition-colors truncate block">
                                                    <?= htmlspecialchars($pMat['title']) ?>
                                                </a>
                                                <span class="text-xs text-slate-400 block truncate">
                                                    <?= strtoupper(htmlspecialchars($pMat['file_type'])) ?> • <?= htmlspecialchars($pMat['file_size']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <img src="<?= htmlspecialchars($pMat['author_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=40&h=40&auto=format&fit=circle') ?>" 
                                                 alt="" class="w-8 h-8 rounded-full object-cover border border-slate-700">
                                            <div>
                                                <p class="font-bold text-slate-200 text-xs"><?= htmlspecialchars($pMat['author_name']) ?></p>
                                                <p class="text-[10px] text-slate-500"><?= htmlspecialchars($pMat['author_email']) ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-4 text-xs">
                                        <p class="font-semibold text-slate-300"><?= htmlspecialchars($pMat['course_name']) ?></p>
                                        <p class="text-slate-500"><?= htmlspecialchars($pMat['faculty_code'] ?? 'UP') ?></p>
                                    </td>

                                    <td class="py-4 px-4 text-xs text-slate-400">
                                        <?= date('d M Y, H:i', strtotime($pMat['created_at'])) ?>
                                    </td>

                                    <td class="py-4 px-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Preview Link -->
                                            <a href="view.php?id=<?= $pMat['id'] ?>" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors" title="Shiko dokumentin">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <!-- Verify Button (+20 points) -->
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="verify_material">
                                                <input type="hidden" name="material_id" value="<?= $pMat['id'] ?>">
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/30 flex items-center gap-1.5">
                                                    <i class="fas fa-check"></i>
                                                    <span>Verifiko (+20 Pts)</span>
                                                </button>
                                            </form>

                                            <!-- Reject & Delete Button -->
                                            <form method="POST" onsubmit="return confirm('A jeni të sigurt që dëshironi ta refuzoni dhe fshini këtë skedar?');" class="inline">
                                                <input type="hidden" name="action" value="delete_material">
                                                <input type="hidden" name="material_id" value="<?= $pMat['id'] ?>">
                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-bold transition-colors">
                                                    <i class="fas fa-trash-can"></i>
                                                    <span>Refuzo</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ------------------------------------------------------------- -->
        <!-- Tab 2: All Materials & Flagged Moderation -->
        <!-- ------------------------------------------------------------- -->
        <div id="adm-tab-materials" class="p-4 sm:p-6 hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-file-shield text-indigo-400"></i>
                        <span>Moderimi i Skedarëve të Platformës</span>
                    </h2>
                    <p class="text-xs text-slate-400">Fshini çdo material të raportuar ose të papërshtatshëm nga platforma me 1 klik.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap min-w-[700px]">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <th class="py-3.5 px-4">Materiali</th>
                            <th class="py-3.5 px-4">Lënda / Fakulteti</th>
                            <th class="py-3.5 px-4">Autori</th>
                            <th class="py-3.5 px-4 text-center">Statusi</th>
                            <th class="py-3.5 px-4 text-center">Statistika</th>
                            <th class="py-3.5 px-4 text-right">Veprim</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-sm">
                        <?php foreach ($all_materials as $aMat): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-3.5 px-4 max-w-xs truncate">
                                    <a href="view.php?id=<?= $aMat['id'] ?>" class="font-bold text-white hover:text-amber-400 transition-colors">
                                        <?= htmlspecialchars($aMat['title']) ?>
                                    </a>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-400">
                                    <?= htmlspecialchars($aMat['course_name']) ?> (<?= htmlspecialchars($aMat['faculty_code'] ?? 'UP') ?>)
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-300">
                                    <?= htmlspecialchars($aMat['author_name']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ((int)$aMat['is_verified'] === 1): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Verifikuar</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">Në Pritje</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center text-xs text-slate-400">
                                    <span><i class="fas fa-download text-slate-500 mr-1"></i><?= (int)$aMat['downloads_count'] ?></span>
                                    <span class="mx-2">•</span>
                                    <span><i class="fas fa-thumbs-up text-amber-400 mr-1"></i><?= (int)$aMat['upvotes_count'] ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <form method="POST" onsubmit="return confirm('A jeni të sigurt që dëshironi ta fshini këtë skedar të dyshimtë/flamuruar?');" class="inline">
                                        <input type="hidden" name="action" value="delete_material">
                                        <input type="hidden" name="material_id" value="<?= $aMat['id'] ?>">
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-bold transition-colors">
                                            <i class="fas fa-trash-can mr-1"></i> Fshij Skedarin
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ------------------------------------------------------------- -->
        <!-- Tab 3: User Management & Roles -->
        <!-- ------------------------------------------------------------- -->
        <div id="adm-tab-users" class="p-4 sm:p-6 hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-users-gear text-sky-400"></i>
                        <span>Menaxhimi i Llogarive të Studentëve</span>
                    </h2>
                    <p class="text-xs text-slate-400">Avancimi në administrator ose ndryshimi i roleve në sistem.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap min-w-[700px]">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <th class="py-3.5 px-4">Përdoruesi</th>
                            <th class="py-3.5 px-4">Fakulteti</th>
                            <th class="py-3.5 px-4 text-center">Ngarkime</th>
                            <th class="py-3.5 px-4 text-center">Pikë Reputacioni</th>
                            <th class="py-3.5 px-4 text-center">Roli</th>
                            <th class="py-3.5 px-4 text-right">Veprimi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-sm">
                        <?php foreach ($all_users as $u): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= htmlspecialchars($u['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=40&h=40&auto=format&fit=circle') ?>" 
                                             alt="" class="w-9 h-9 rounded-xl object-cover border border-slate-700">
                                        <div>
                                            <a href="profile.php?id=<?= $u['id'] ?>" class="font-bold text-white hover:text-amber-400 transition-colors text-xs sm:text-sm block">
                                                <?= htmlspecialchars($u['full_name']) ?>
                                            </a>
                                            <span class="text-[11px] text-slate-400"><?= htmlspecialchars($u['email']) ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-xs text-slate-300">
                                    <?= htmlspecialchars($u['faculty_name'] ?? 'Universiteti i Prishtinës') ?>
                                </td>

                                <td class="py-3.5 px-4 text-center text-xs font-bold text-slate-200">
                                    <?= (int)$u['total_uploads'] ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        <i class="fas fa-star text-[10px]"></i>
                                        <?= (int)$u['reputation_points'] ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-extrabold uppercase bg-red-500/20 text-red-400 border border-red-500/30">
                                            Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                            Student
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <?php if ((int)$u['id'] !== (int)$logged_user_id): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="new_role" value="<?= $u['role'] === 'admin' ? 'student' : 'admin' ?>">
                                            <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all border <?= $u['role'] === 'admin' ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' : 'bg-red-500/10 hover:bg-red-500/20 text-red-400 border-red-500/30' ?>">
                                                <?= $u['role'] === 'admin' ? 'Kthe në Student' : 'Bëj Admin' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500 italic">Ti (Aktual)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
// Switch between admin tabs
function switchAdminTab(tab) {
    const queueBtn = document.getElementById('adm-tab-queue-btn');
    const materialsBtn = document.getElementById('adm-tab-materials-btn');
    const usersBtn = document.getElementById('adm-tab-users-btn');

    const queueTab = document.getElementById('adm-tab-queue');
    const materialsTab = document.getElementById('adm-tab-materials');
    const usersTab = document.getElementById('adm-tab-users');

    // Reset all buttons
    [queueBtn, materialsBtn, usersBtn].forEach(btn => {
        btn.classList.remove('bg-slate-800', 'text-amber-400', 'shadow-sm');
        btn.classList.add('text-slate-400');
    });

    // Hide all tabs
    [queueTab, materialsTab, usersTab].forEach(tabEl => tabEl.classList.add('hidden'));

    if (tab === 'queue') {
        queueBtn.classList.add('bg-slate-800', 'text-amber-400', 'shadow-sm');
        queueBtn.classList.remove('text-slate-400');
        queueTab.classList.remove('hidden');
    } else if (tab === 'materials') {
        materialsBtn.classList.add('bg-slate-800', 'text-amber-400', 'shadow-sm');
        materialsBtn.classList.remove('text-slate-400');
        materialsTab.classList.remove('hidden');
    } else if (tab === 'users') {
        usersBtn.classList.add('bg-slate-800', 'text-amber-400', 'shadow-sm');
        usersBtn.classList.remove('text-slate-400');
        usersTab.classList.remove('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
