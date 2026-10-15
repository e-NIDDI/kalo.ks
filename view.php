<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$logged_user_id = $_SESSION['user_id'] ?? 1;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID e pavlefshme e materialit.");
}

// Session-deduplicated view counter
if (!isset($_SESSION['viewed_materials']) || !is_array($_SESSION['viewed_materials'])) {
    $_SESSION['viewed_materials'] = [];
}
if (!in_array($id, $_SESSION['viewed_materials'])) {
    $stmt = $pdo->prepare("UPDATE materials SET views_count = views_count + 1 WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['viewed_materials'][] = $id;
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_text'])) {
    $comment_text = trim($_POST['comment_text']);
    if ($comment_text !== '') {
        $insert = $pdo->prepare("INSERT INTO comments (material_id, user_id, comment_text, created_at) VALUES (?, ?, ?, NOW())");
        $insert->execute([$id, $logged_user_id, $comment_text]);
        header("Location: view.php?id=" . $id . "#komentet");
        exit;
    }
}

// Fetch material with joins
$sql = "
    SELECT m.*, 
           f.name AS faculty_name, f.code AS faculty_code,
           c.name AS course_name, c.code AS course_code, c.professor_name,
           u.id AS user_id, u.full_name AS author_name, u.avatar_url AS author_avatar, 
           u.reputation_points AS author_reputation, u.role AS author_role,
           (SELECT COUNT(*) FROM comments WHERE material_id = m.id) AS comment_count
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    JOIN faculties f ON c.faculty_id = f.id
    JOIN users u ON m.user_id = u.id
    WHERE m.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    require_once __DIR__ . '/header.php';
    echo '<div class="max-w-4xl mx-auto px-6 py-20 text-center"><h2 class="text-2xl font-bold text-red-400">Materiali nuk u gjet!</h2><a href="index.php" class="mt-4 inline-block text-amber-400 underline">Kthehu te Ballina</a></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// Check if current user has saved or upvoted
$is_saved = false;
$is_upvoted = false;
try {
    $sCheck = $pdo->prepare("SELECT id FROM saved_materials WHERE user_id = ? AND material_id = ?");
    $sCheck->execute([$logged_user_id, $id]);
    $is_saved = (bool)$sCheck->fetch();

    $uCheck = $pdo->prepare("SELECT id FROM upvotes WHERE user_id = ? AND material_id = ?");
    $uCheck->execute([$logged_user_id, $id]);
    $is_upvoted = (bool)$uCheck->fetch();
} catch (PDOException $e) {}

// Fetch comments
$comments_stmt = $pdo->prepare("
    SELECT c.*, u.full_name AS commenter_name, u.avatar_url AS commenter_avatar, u.reputation_points AS commenter_reputation
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.material_id = ?
    ORDER BY c.created_at DESC
");
$comments_stmt->execute([$id]);
$comments = $comments_stmt->fetchAll();

// Similar materials (same course)
$similar_stmt = $pdo->prepare("
    SELECT m.id, m.title, m.file_type, m.file_size, m.created_at,
           u.full_name AS author_name
    FROM materials m
    JOIN users u ON m.user_id = u.id
    WHERE m.course_id = ? AND m.id != ?
    ORDER BY m.created_at DESC
    LIMIT 4
");
$similar_stmt->execute([$material['course_id'], $id]);
$similar = $similar_stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 py-8 w-full">
    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Left Column (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Header Info -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="px-3 py-1 text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30 rounded-full">
                        <?= htmlspecialchars($material['faculty_name']) ?>
                    </span>
                    <span class="px-3 py-1 text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700 rounded-full">
                        <?= htmlspecialchars($material['course_name']) ?>
                    </span>
                    <?php if (!empty($material['professor_name'])): ?>
                        <span class="px-3 py-1 text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded-full">
                            Prof: <?= htmlspecialchars($material['professor_name']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ((int)$material['is_verified'] === 1): ?>
                        <span class="px-3 py-1 text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 rounded-full flex items-center gap-1">
                            <i class="fas fa-shield-check"></i> Zgjidhje e Verifikuar
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-white leading-tight mb-3">
                    <?= htmlspecialchars($material['title']) ?>
                </h1>

                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                    <span><i class="fas fa-calendar mr-1 text-slate-500"></i><?= date('d M Y', strtotime($material['created_at'])) ?></span>
                    <span><i class="fas fa-eye mr-1 text-slate-500"></i><?= number_format((int)$material['views_count']) ?> shikime</span>
                    <span><i class="fas fa-download mr-1 text-emerald-500"></i><?= number_format((int)$material['downloads_count']) ?> shkarkime</span>
                    <span><i class="fas fa-thumbs-up mr-1 text-amber-400"></i><?= number_format((int)$material['upvotes_count']) ?> vota</span>
                </div>
            </div>

            <!-- Document Previewer -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 bg-slate-950/40 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                        <i class="fas fa-magnifying-glass text-amber-400"></i> Pamje Paraprake e Dokumentit
                    </span>
                    <span class="text-xs text-slate-400 font-mono"><?= strtoupper(htmlspecialchars($material['file_type'])) ?> (<?= htmlspecialchars($material['file_size']) ?>)</span>
                </div>

                <?php 
                $fType = strtolower($material['file_type']);
                $fPath = htmlspecialchars($material['file_path']);
                ?>
                <?php if ($fType === 'pdf'): ?>
                    <iframe src="<?= $fPath ?>" class="w-full h-[520px] bg-slate-950 border-0" title="Pamja e Dokumentit"></iframe>
                <?php elseif (in_array($fType, ['png', 'jpg', 'jpeg', 'gif', 'webp'])): ?>
                    <div class="p-4 flex items-center justify-center bg-slate-950">
                        <img src="<?= $fPath ?>" alt="Preview" class="max-h-[520px] object-contain rounded-xl">
                    </div>
                <?php else: ?>
                    <div class="p-16 text-center bg-slate-950/50">
                        <div class="w-16 h-16 rounded-2xl bg-slate-800 text-amber-400 flex items-center justify-center mx-auto mb-4 text-2xl border border-slate-700">
                            <i class="fas fa-file-zipper"></i>
                        </div>
                        <h3 class="text-base font-bold text-white mb-1">Skedar <?= strtoupper(htmlspecialchars($material['file_type'])) ?></h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mb-4">Shikimi i drejtpërdrejtë nuk mbështetet për këtë format skedari. Shkarkoni skedarin për ta hapur në pajisjen tuaj.</p>
                        <a href="download.php?id=<?= $material['id'] ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-md">
                            <i class="fas fa-download"></i> Shkarko Menjëherë
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
                <h2 class="text-base sm:text-lg font-bold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-align-left text-amber-400"></i> Përshkrimi i Materialit
                </h2>
                <div class="text-slate-300 text-sm leading-relaxed whitespace-pre-line bg-slate-950/40 p-4 rounded-2xl border border-slate-800/80">
                    <?= !empty($material['description']) ? htmlspecialchars($material['description']) : 'Nuk ka përshkrim shtesë për këtë material.' ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div id="komentet" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
                <h2 class="text-base sm:text-lg font-bold text-white mb-4 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="fas fa-comments text-sky-400"></i>
                        <span>Diskutimet & Pyetjet</span>
                    </span>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-400"><?= count($comments) ?></span>
                </h2>

                <!-- Comment Form -->
                <form method="POST" action="view.php?id=<?= $id ?>" class="mb-6">
                    <div class="relative">
                        <textarea name="comment_text" rows="3" required placeholder="Keni ndonjë pyetje ose falënderim për këtë material? Shkruani komentin tuaj..."
                                  class="w-full bg-slate-800/80 border border-slate-700 rounded-2xl p-4 text-sm text-slate-100 placeholder-slate-400 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 mb-3"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-md">
                            <i class="fas fa-paper-plane"></i>
                            <span>Posto Komentin</span>
                        </button>
                    </div>
                </form>

                <!-- Comments List -->
                <div class="space-y-4">
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-10 text-slate-500 bg-slate-950/30 rounded-2xl border border-slate-800/50">
                            <i class="fas fa-comment-slash text-2xl mb-2 text-slate-600 block"></i>
                            <p class="text-xs">Nuk ka asnjë koment ende. Jini i pari që komentoni!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $c): ?>
                            <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-slate-950/40 border border-slate-800/80">
                                <img src="<?= htmlspecialchars($c['commenter_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=40&h=40&auto=format&fit=circle') ?>" 
                                     alt="" class="w-9 h-9 rounded-xl object-cover border border-slate-700 shrink-0">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <div class="flex items-center gap-2 truncate">
                                            <span class="font-bold text-xs sm:text-sm text-white truncate"><?= htmlspecialchars($c['commenter_name']) ?></span>
                                            <span class="text-[10px] text-amber-400 font-semibold flex items-center gap-0.5">
                                                <i class="fas fa-star text-[8px]"></i> <?= (int)$c['commenter_reputation'] ?>
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-slate-500 shrink-0"><?= date('d M Y, H:i', strtotime($c['created_at'])) ?></span>
                                    </div>
                                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed break-words"><?= nl2br(htmlspecialchars($c['comment_text'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column (1/3) -->
        <div class="space-y-6">
            <!-- Action Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl text-center">
                <a href="download.php?id=<?= $material['id'] ?>" 
                   class="w-full inline-flex items-center justify-center gap-2 py-4 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-black text-sm tracking-wide uppercase transition-all shadow-xl shadow-indigo-600/30 hover:shadow-indigo-600/50 mb-4">
                    <i class="fas fa-cloud-arrow-down text-base"></i>
                    <span>Shkarko Skedarin</span>
                </a>

                <div class="grid grid-cols-2 gap-3">
                    <!-- Upvote Button -->
                    <button onclick="toggleUpvote(<?= $material['id'] ?>)" id="upvote-btn" 
                            class="py-3 px-4 rounded-2xl border transition-all flex items-center justify-center gap-2 font-bold text-xs <?= $is_upvoted ? 'bg-amber-500/20 text-amber-400 border-amber-500/40' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' ?>">
                        <i class="fas fa-thumbs-up" id="upvote-icon"></i>
                        <span id="upvote-count"><?= (int)$material['upvotes_count'] ?></span>
                    </button>

                    <!-- Save / Bookmark Button -->
                    <button onclick="toggleSave(<?= $material['id'] ?>)" id="save-btn" 
                            class="py-3 px-4 rounded-2xl border transition-all flex items-center justify-center gap-2 font-bold text-xs <?= $is_saved ? 'bg-indigo-500/20 text-indigo-400 border-indigo-500/40' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' ?>">
                        <i class="fas fa-bookmark" id="save-icon"></i>
                        <span id="save-label"><?= $is_saved ? 'Ruajtur' : 'Ruaj' ?></span>
                    </button>
                </div>
            </div>

            <!-- Author Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl text-center">
                <div class="relative w-20 h-20 mx-auto mb-3">
                    <img src="<?= htmlspecialchars($material['author_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=80&h=80&auto=format&fit=circle') ?>" 
                         alt="" class="w-20 h-20 rounded-2xl object-cover border-2 border-amber-400/50 shadow-md">
                    <span class="absolute -bottom-2 -right-2 w-7 h-7 rounded-lg bg-amber-500 text-slate-950 font-black text-xs flex items-center justify-center">
                        <i class="fas fa-star"></i>
                    </span>
                </div>
                <h3 class="font-extrabold text-base text-white"><?= htmlspecialchars($material['author_name']) ?></h3>
                <p class="text-xs text-amber-400 font-bold mt-0.5"><?= number_format((int)$material['author_reputation']) ?> Pikë Reputacioni</p>
                <div class="mt-3 pt-3 border-t border-slate-800 flex justify-center">
                    <a href="profile.php?id=<?= $material['user_id'] ?>" class="text-xs text-slate-400 hover:text-white transition-colors flex items-center gap-1 font-semibold">
                        <span>Shiko Profilin e Plotë</span>
                        <i class="fas fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- Similar Materials -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
                <h3 class="font-bold text-sm uppercase tracking-wider text-slate-300 mb-4 flex items-center gap-2">
                    <i class="fas fa-book-bookmark text-amber-400"></i> Materiale të Ngjashme
                </h3>
                <div class="space-y-3">
                    <?php if (empty($similar)): ?>
                        <p class="text-xs text-slate-500 py-3">Nuk ka materiale të tjera për këtë lëndë.</p>
                    <?php else: ?>
                        <?php foreach ($similar as $sim): ?>
                            <a href="view.php?id=<?= $sim['id'] ?>" class="block p-3 rounded-2xl bg-slate-950/40 border border-slate-800/80 hover:border-slate-700 transition-colors">
                                <h4 class="font-bold text-xs text-slate-200 line-clamp-1 hover:text-amber-400 transition-colors">
                                    <?= htmlspecialchars($sim['title']) ?>
                                </h4>
                                <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1">
                                    <span><?= strtoupper(htmlspecialchars($sim['file_type'])) ?> • <?= htmlspecialchars($sim['file_size']) ?></span>
                                    <span><?= htmlspecialchars($sim['author_name']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// AJAX Upvote toggle
function toggleUpvote(materialId) {
    fetch('upvote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'material_id=' + materialId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('upvote-count').textContent = data.count;
            const btn = document.getElementById('upvote-btn');
            if (data.upvoted) {
                btn.classList.add('bg-amber-500/20', 'text-amber-400', 'border-amber-500/40');
                btn.classList.remove('bg-slate-800', 'text-slate-300', 'border-slate-700');
                showToast('Faleminderit për votën tuaj!', 'success');
            } else {
                btn.classList.remove('bg-amber-500/20', 'text-amber-400', 'border-amber-500/40');
                btn.classList.add('bg-slate-800', 'text-slate-300', 'border-slate-700');
                showToast('Vota u hoq.', 'info');
            }
        } else {
            showToast(data.error || 'Dështoi votimi.', 'error');
        }
    })
    .catch(err => showToast('Gabim në rrjet: ' + err.message, 'error'));
}

// AJAX Bookmark toggle
function toggleSave(materialId) {
    fetch('save_material.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'material_id=' + materialId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('save-btn');
            const label = document.getElementById('save-label');
            if (data.saved) {
                btn.classList.add('bg-indigo-500/20', 'text-indigo-400', 'border-indigo-500/40');
                btn.classList.remove('bg-slate-800', 'text-slate-300', 'border-slate-700');
                label.textContent = 'Ruajtur';
                showToast('Materiali u ruajt në koleksion!', 'success');
            } else {
                btn.classList.remove('bg-indigo-500/20', 'text-indigo-400', 'border-indigo-500/40');
                btn.classList.add('bg-slate-800', 'text-slate-300', 'border-slate-700');
                label.textContent = 'Ruaj';
                showToast('Materiali u hoq nga koleksioni.', 'info');
            }
        } else {
            showToast(data.error || 'Dështoi ruajtja.', 'error');
        }
    })
    .catch(err => showToast('Gabim në rrjet: ' + err.message, 'error'));
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
