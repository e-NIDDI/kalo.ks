<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 py-8 w-full">
    <!-- Platform Hero / Title Banner -->
    <div class="mb-8">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400 mb-2">
            <i class="fa-solid fa-graduation-cap"></i> Universiteti i Prishtinës "Hasan Prishtina"
        </div>
        <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
            Qendra e Provimeve & Materialeve Studentore
        </h1>
        <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl">
            Gjeni afate të zgjidhura provimesh, raporte laboratori dhe shënime leksionesh të ndara nga studentët më të dalluar.
        </p>
    </div>

    <!-- Stats Cards Component -->
    <?php require_once __DIR__ . '/components/stats.php'; ?>

    <?php
    // Get filter parameters
    $selected_fakulteti = isset($_GET['fakulteti']) ? trim($_GET['fakulteti']) : '';
    $selected_lenda = isset($_GET['lenda']) ? trim($_GET['lenda']) : '';
    $selected_renditja = isset($_GET['renditja']) ? trim($_GET['renditja']) : 'te_rejat';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build WHERE conditions
    $conditions = [];
    $params = [];

    if (!empty($selected_fakulteti)) {
        $conditions[] = "f.code = ?";
        $params[] = $selected_fakulteti;
    }

    if (!empty($selected_lenda)) {
        $conditions[] = "c.code = ?";
        $params[] = $selected_lenda;
    }

    if (!empty($search)) {
        $conditions[] = "(m.title LIKE ? OR m.description LIKE ? OR c.professor_name LIKE ? OR c.name LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Build ORDER BY clause
    switch ($selected_renditja) {
        case 'te_votuarat':
            $order_by = "ORDER BY m.upvotes_count DESC, m.created_at DESC";
            break;
        case 'te_shkarkuarat':
            $order_by = "ORDER BY m.downloads_count DESC, m.created_at DESC";
            break;
        case 'te_rejat':
        default:
            $order_by = "ORDER BY m.created_at DESC";
            break;
    }

    // Get faculties for dropdown
    $faculties_stmt = $pdo->query("SELECT code, name FROM faculties ORDER BY name ASC");
    $faculties = $faculties_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get courses for dropdown (filtered by selected faculty or all)
    if (!empty($selected_fakulteti)) {
        $courses_stmt = $pdo->prepare("
            SELECT c.code, c.name 
            FROM courses c 
            JOIN faculties f ON c.faculty_id = f.id 
            WHERE f.code = ? 
            ORDER BY c.name ASC
        ");
        $courses_stmt->execute([$selected_fakulteti]);
    } else {
        $courses_stmt = $pdo->query("SELECT code, name FROM courses ORDER BY name ASC");
    }
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get materials with filters
    $sql = "
        SELECT m.*, 
               f.name AS faculty_name, f.code AS faculty_code,
               c.name AS course_name, c.code AS course_code, c.professor_name,
               u.full_name AS author_name, u.avatar_url AS author_avatar, u.reputation_points AS author_reputation,
               (SELECT COUNT(*) FROM comments WHERE material_id = m.id) AS comment_count
        FROM materials m
        JOIN courses c ON m.course_id = c.id
        JOIN faculties f ON c.faculty_id = f.id
        JOIN users u ON m.user_id = u.id
        {$where_clause}
        {$order_by}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // User's saved and upvoted materials for visual state (only for logged-in users)
    $user_saved_ids = [];
    $user_upvoted_ids = [];
    if (!empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        try {
            $sStmt = $pdo->prepare("SELECT material_id FROM saved_materials WHERE user_id = ?");
            $sStmt->execute([$uid]);
            $user_saved_ids = $sStmt->fetchAll(PDO::FETCH_COLUMN);

            $uStmt = $pdo->prepare("SELECT material_id FROM upvotes WHERE user_id = ?");
            $uStmt->execute([$uid]);
            $user_upvoted_ids = $uStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {}
    }
    ?>

    <!-- Filter & Search Controls Bar -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 mb-8 shadow-xl">
        <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <!-- Fakulteti -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Fakulteti</label>
                <div class="relative">
                    <select name="fakulteti" onchange="this.form.submit()" 
                            class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 appearance-none pr-9">
                        <option value="">Të gjitha fakultetet</option>
                        <?php foreach ($faculties as $f): ?>
                            <option value="<?= htmlspecialchars($f['code']) ?>" <?= $selected_fakulteti === $f['code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 text-xs">
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Lënda -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Lënda</label>
                <div class="relative">
                    <select name="lenda" onchange="this.form.submit()" 
                            class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 appearance-none pr-9">
                        <option value="">Të gjitha lëndët</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= htmlspecialchars($c['code']) ?>" <?= $selected_lenda === $c['code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 text-xs">
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Renditja -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Renditja</label>
                <div class="relative">
                    <select name="renditja" onchange="this.form.submit()" 
                            class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500 appearance-none pr-9">
                        <option value="te_rejat" <?= $selected_renditja === 'te_rejat' ? 'selected' : '' ?>>Më të Rejat</option>
                        <option value="te_votuarat" <?= $selected_renditja === 'te_votuarat' ? 'selected' : '' ?>>Më të Votuarat</option>
                        <option value="te_shkarkuarat" <?= $selected_renditja === 'te_shkarkuarat' ? 'selected' : '' ?>>Më të Shkarkuarat</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 text-xs">
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Kërko në material</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Titulli ose profesori..." 
                           class="w-full bg-slate-800 border border-slate-700 text-slate-200 rounded-xl p-3 pl-9 text-sm focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" 
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm p-3 rounded-xl transition shadow-lg shadow-indigo-600/25 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-filter text-xs"></i>
                    <span>Filtro</span>
                </button>
                <?php if (!empty($selected_fakulteti) || !empty($selected_lenda) || !empty($search) || $selected_renditja !== 'te_rejat'): ?>
                    <a href="index.php" class="p-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 transition text-sm flex items-center justify-center" title="Pastro të gjithë filtrat">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Feed Header: Count & Active Filters -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-amber-400"></i>
            <span>Materialet Mësimore</span>
        </h2>
        <span class="px-3.5 py-1 rounded-full text-xs font-bold bg-slate-900 border border-slate-800 text-slate-400">
            <?= count($materials) ?> Gjithsej
        </span>
    </div>

    <!-- Materials Grid (Strictly grid-cols-1 sm:grid-cols-2 lg:grid-cols-3) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        <?php if (empty($materials)): ?>
            <div class="col-span-full bg-slate-900 border border-slate-800 rounded-3xl p-12 text-center shadow-xl">
                <div class="w-16 h-16 rounded-2xl bg-slate-800/80 text-slate-500 flex items-center justify-center mx-auto mb-4 text-2xl border border-slate-700/60">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-1">Nuk u gjet asnjë material</h3>
                <p class="text-xs sm:text-sm text-slate-400 max-w-md mx-auto mb-6">
                    Nuk ka materiale që përputhen me kriteret e zgjedhura të filtrimit. Provoni të pastroni filtrat ose ngarkoni materialin e parë!
                </p>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <a href="index.php" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition border border-slate-700">
                        Pastro Filtrat
                    </a>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <button onclick="openModal('upload-modal')" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-lg shadow-indigo-600/25">
                            + Ngarko Material
                        </button>
                    <?php else: ?>
                        <a href="login.php?msg=<?= urlencode('Ju lutem kyçuni për të ngarkuar materiale.') ?>&redirect=index.php" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-lg shadow-indigo-600/25">
                            + Ngarko Material
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($materials as $material): 
                $matId = (int)$material['id'];
                $isSaved = in_array($matId, $user_saved_ids);
                $isUpvoted = in_array($matId, $user_upvoted_ids);
            ?>
                <!-- Material Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 flex flex-col justify-between hover:border-slate-700 transition shadow-xl shadow-black/10 hover:-translate-y-1 group">
                    <div>
                        <!-- Badges Row -->
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30 rounded-full">
                                <?= htmlspecialchars($material['faculty_code'] ?? 'UP') ?>
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-semibold bg-slate-800 text-slate-300 border border-slate-700 rounded-full truncate max-w-[140px]">
                                <?= htmlspecialchars($material['course_name']) ?>
                            </span>
                            <?php if ((int)$material['is_verified'] === 1): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-full ml-auto">
                                    <i class="fa-solid fa-circle-check"></i> Verifikuar
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Title -->
                        <h3 class="text-lg font-bold text-white mb-2 line-clamp-2 group-hover:text-amber-400 transition-colors">
                            <a href="view.php?id=<?= $material['id'] ?>">
                                <?= htmlspecialchars($material['title']) ?>
                            </a>
                        </h3>

                        <!-- Description Excerpt -->
                        <p class="text-slate-400 text-xs mb-4 line-clamp-2 leading-relaxed">
                            <?= !empty($material['description']) ? htmlspecialchars($material['description']) : 'Nuk ka përshkrim shtesë për këtë material mësimor.' ?>
                        </p>

                        <!-- File Format & Size Pills -->
                        <div class="flex items-center gap-2 mb-4 text-[11px] text-slate-400">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-xl bg-slate-800/80 border border-slate-700/60 font-semibold">
                                <i class="fa-solid fa-file-lines mr-1.5 text-indigo-400"></i>
                                <?= strtoupper(htmlspecialchars($material['file_type'])) ?>
                            </span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-xl bg-slate-800/80 border border-slate-700/60">
                                <i class="fa-solid fa-hard-drive mr-1.5 text-slate-500"></i>
                                <?= htmlspecialchars($material['file_size']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Author & Footer Action Section -->
                    <div class="pt-4 border-t border-slate-800/80 mt-2 space-y-4">
                        <!-- Author Metadata -->
                        <div class="flex items-center justify-between gap-2">
                            <a href="profile.php?id=<?= $material['user_id'] ?>" class="flex items-center gap-2.5 group/auth min-w-0">
                                <img src="<?= htmlspecialchars($material['author_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=80&h=80&auto=format&fit=crop&q=80') ?>" 
                                     alt="" class="w-8 h-8 rounded-full object-cover border border-slate-700 shrink-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-200 group-hover/auth:text-amber-400 transition-colors truncate">
                                        <?= htmlspecialchars($material['author_name']) ?>
                                    </p>
                                    <p class="text-[10px] text-amber-400 flex items-center gap-1">
                                        <i class="fa-solid fa-star text-[8px]"></i>
                                        <span><?= (int)$material['author_reputation'] ?> Pts</span>
                                    </p>
                                </div>
                            </a>

                            <!-- Card Mini Metrics -->
                            <div class="flex items-center gap-3 text-xs text-slate-400 shrink-0">
                                <span title="Shikime"><i class="fa-solid fa-eye text-slate-500 mr-1"></i><?= (int)$material['views_count'] ?></span>
                                <span title="Shkarkime"><i class="fa-solid fa-download text-emerald-500 mr-1"></i><?= (int)$material['downloads_count'] ?></span>
                                <span title="Komente"><i class="fa-solid fa-comment text-sky-400 mr-1"></i><?= (int)$material['comment_count'] ?></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2">
                            <!-- Upvote Button -->
                            <button onclick="cardUpvote(<?= $material['id'] ?>, this)" 
                                    class="px-3.5 py-2 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 <?= $isUpvoted ? 'bg-amber-500/20 text-amber-400 border-amber-500/40' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' ?>"
                                    title="Pëlqe materialin">
                                <i class="fa-solid fa-thumbs-up text-xs"></i>
                                <span class="upvote-num"><?= (int)$material['upvotes_count'] ?></span>
                            </button>

                            <!-- View Link -->
                            <a href="view.php?id=<?= $material['id'] ?>" 
                               class="flex-1 text-center py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition shadow-md shadow-indigo-600/20 flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-eye text-xs"></i>
                                <span>Shiko</span>
                            </a>

                            <!-- Bookmark / Save Button -->
                            <button onclick="cardSave(<?= $material['id'] ?>, this)" 
                                    class="p-2.5 rounded-xl border text-xs font-bold transition <?= $isSaved ? 'bg-indigo-500/20 text-indigo-400 border-indigo-500/40' : 'bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border-slate-700' ?>"
                                    title="<?= $isSaved ? 'Hiq nga të ruajturat' : 'Ruaj në koleksion' ?>">
                                <i class="fa-solid fa-bookmark text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
// Card Upvote toggle helper with guest redirection
function cardUpvote(materialId, btn) {
    fetch('upvote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'material_id=' + materialId
    })
    .then(r => r.json())
    .then(data => {
        if (data.require_login) {
            window.location.href = data.redirect || 'login.php';
            return;
        }
        if (data.success) {
            const numEl = btn.querySelector('.upvote-num');
            if (numEl) numEl.textContent = data.count;

            if (data.upvoted) {
                btn.classList.add('bg-amber-500/20', 'text-amber-400', 'border-amber-500/40');
                btn.classList.remove('bg-slate-800', 'text-slate-300', 'border-slate-700');
                if (typeof showToast === 'function') showToast('Faleminderit për votën tuaj!', 'success');
            } else {
                btn.classList.remove('bg-amber-500/20', 'text-amber-400', 'border-amber-500/40');
                btn.classList.add('bg-slate-800', 'text-slate-300', 'border-slate-700');
                if (typeof showToast === 'function') showToast('Vota u hoq.', 'info');
            }
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'Dështoi votimi.', 'error');
        }
    })
    .catch(err => {
        if (typeof showToast === 'function') showToast('Gabim në rrjet: ' + err.message, 'error');
    });
}

// Card Bookmark toggle helper with guest redirection
function cardSave(materialId, btn) {
    fetch('save_material.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'material_id=' + materialId
    })
    .then(r => r.json())
    .then(data => {
        if (data.require_login) {
            window.location.href = data.redirect || 'login.php';
            return;
        }
        if (data.success) {
            if (data.saved) {
                btn.classList.add('bg-indigo-500/20', 'text-indigo-400', 'border-indigo-500/40');
                btn.classList.remove('bg-slate-800', 'text-slate-400', 'border-slate-700');
                if (typeof showToast === 'function') showToast('U ruajt në koleksion!', 'success');
            } else {
                btn.classList.remove('bg-indigo-500/20', 'text-indigo-400', 'border-indigo-500/40');
                btn.classList.add('bg-slate-800', 'text-slate-400', 'border-slate-700');
                if (typeof showToast === 'function') showToast('U hoq nga koleksioni.', 'info');
            }
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'Dështoi ruajtja.', 'error');
        }
    })
    .catch(err => {
        if (typeof showToast === 'function') showToast('Gabim në rrjet: ' + err.message, 'error');
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
