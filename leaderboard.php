<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$logged_user_id = $_SESSION['user_id'] ?? 1;

// Selected faculty filter
$selected_faculty = isset($_GET['faculty']) ? trim($_GET['faculty']) : '';

// Fetch all faculties for the filter buttons/dropdown
$faculties = [];
try {
    $faculties = $pdo->query("SELECT id, name, code, color_code FROM faculties ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $faculties = [];
}

// Build leaderboard query
$sql = "
    SELECT u.id, u.full_name, u.email, u.avatar_url, u.reputation_points, u.role, u.bio,
           f.name AS faculty_name, f.code AS faculty_code, f.color_code AS faculty_color,
           COUNT(DISTINCT m.id) AS total_files,
           COALESCE(SUM(m.upvotes_count), 0) AS total_votes,
           COALESCE(SUM(m.downloads_count), 0) AS total_downloads
    FROM users u
    LEFT JOIN faculties f ON u.faculty_id = f.id
    LEFT JOIN materials m ON u.id = m.user_id
";

$params = [];
if (!empty($selected_faculty)) {
    $sql .= " WHERE f.code = ? ";
    $params[] = $selected_faculty;
}

$sql .= " GROUP BY u.id ORDER BY u.reputation_points DESC, total_files DESC, total_votes DESC";

$ranked_users = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ranked_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $ranked_users = [];
}

// Find current user's rank
$my_rank = null;
foreach ($ranked_users as $idx => $r_user) {
    if ((int)$r_user['id'] === (int)$logged_user_id) {
        $my_rank = $idx + 1;
        break;
    }
}

// Podium split (Top 3)
$top1 = $ranked_users[0] ?? null;
$top2 = $ranked_users[1] ?? null;
$top3 = $ranked_users[2] ?? null;

require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 py-8 w-full">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400 mb-2">
                <i class="fas fa-ranking-star"></i> Tabela e Nderit e Studentëve
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                Renditja e Studentëve (Leaderboard)
            </h1>
            <p class="text-slate-400 text-sm mt-1 max-w-2xl">
                Renditja bazohet në pikët e reputacionit, numrin e materialeve cilësore të ndara dhe vlerësimet nga kolegët studentë në të gjithë universitetin.
            </p>
        </div>

        <!-- Faculty Filter Form -->
        <form method="GET" action="leaderboard.php" class="flex flex-wrap items-center gap-2 shrink-0">
            <div class="relative">
                <select name="faculty" onchange="this.form.submit()" 
                        class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 pr-9">
                    <option value="">Të gjitha Fakultetet (Gjithsej)</option>
                    <?php foreach ($faculties as $fac): ?>
                        <option value="<?= htmlspecialchars($fac['code']) ?>" <?= $selected_faculty === $fac['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fac['name']) ?> (<?= htmlspecialchars($fac['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 text-xs">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
            <?php if (!empty($selected_faculty)): ?>
                <a href="leaderboard.php" class="px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition-colors" title="Pastro filtrin">
                    <i class="fas fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Current User's Standing Card (if logged in & ranked) -->
    <?php if ($my_rank !== null): ?>
        <div class="mb-8 p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-slate-900/80 to-indigo-500/10 border border-amber-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-500 text-slate-950 font-black text-lg flex items-center justify-center shrink-0 shadow-lg shadow-amber-500/20">
                    #<?= $my_rank ?>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Pozita juaj aktuale në këtë listë</h3>
                    <p class="text-xs text-slate-400">Vazhdoni të ngarkoni skripte & zgjidhje për t'u ngjitur në podium!</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="profile.php" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold transition-all shadow-md">
                    Profili Im
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top 3 Podium Display -->
    <?php if (!empty($ranked_users)): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 items-end">
            <!-- 2nd Place (Silver) -->
            <div class="order-2 md:order-1 relative bg-slate-900/60 backdrop-blur-md border border-slate-700/80 rounded-3xl p-6 sm:p-8 text-center hover:border-slate-500 transition-all hover:-translate-y-1">
                <div class="w-12 h-12 rounded-2xl bg-slate-700 text-slate-200 font-black text-xl flex items-center justify-center mx-auto mb-4 border border-slate-500 shadow-md">
                    <i class="fas fa-medal text-slate-300"></i>
                </div>
                <?php if ($top2): ?>
                    <div class="relative w-24 h-24 mx-auto mb-3">
                        <img src="<?= htmlspecialchars($top2['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=120&h=120&auto=format&fit=circle') ?>" 
                             alt="<?= htmlspecialchars($top2['full_name']) ?>" 
                             class="w-24 h-24 rounded-2xl object-cover border-4 border-slate-400 shadow-lg">
                        <span class="absolute -bottom-2 -right-2 px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-700 text-slate-200 border border-slate-500">#2</span>
                    </div>
                    <a href="profile.php?id=<?= $top2['id'] ?>" class="text-lg font-bold text-white hover:text-amber-400 transition-colors block truncate">
                        <?= htmlspecialchars($top2['full_name']) ?>
                    </a>
                    <span class="inline-block text-xs text-slate-400 mb-3"><?= htmlspecialchars($top2['faculty_name'] ?? 'Universiteti i Prishtinës') ?></span>
                    <div class="p-3 bg-slate-950/60 rounded-2xl border border-slate-800 grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <span class="block text-slate-400 text-[10px]">Pikë</span>
                            <span class="font-extrabold text-amber-400 text-sm"><?= number_format((int)$top2['reputation_points']) ?></span>
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px]">Fajlla</span>
                            <span class="font-bold text-slate-200 text-sm"><?= (int)$top2['total_files'] ?></span>
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px]">Vota</span>
                            <span class="font-bold text-slate-200 text-sm"><?= (int)$top2['total_votes'] ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 text-xs py-8">Vend i lirë</p>
                <?php endif; ?>
            </div>

            <!-- 1st Place (Gold / Champion) -->
            <div class="order-1 md:order-2 relative bg-gradient-to-b from-amber-500/10 via-slate-900 to-slate-900 border-2 border-amber-400/80 rounded-3xl p-6 sm:p-10 text-center shadow-2xl shadow-amber-500/10 hover:-translate-y-2 transition-all">
                <!-- Floating Crown Badge -->
                <div class="absolute -top-6 left-1/2 -translate-x-1/2 px-4 py-1.5 rounded-full bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950 font-black text-xs uppercase tracking-wider flex items-center gap-1.5 shadow-lg shadow-amber-500/30">
                    <i class="fas fa-crown"></i> Kampioni
                </div>

                <?php if ($top1): ?>
                    <div class="relative w-28 h-28 mx-auto mb-4 mt-2">
                        <img src="<?= htmlspecialchars($top1['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=160&h=160&auto=format&fit=circle') ?>" 
                             alt="<?= htmlspecialchars($top1['full_name']) ?>" 
                             class="w-28 h-28 rounded-2xl object-cover border-4 border-amber-400 shadow-xl shadow-amber-500/20">
                        <span class="absolute -bottom-2 -right-2 px-2.5 py-1 rounded-full text-xs font-black bg-amber-400 text-slate-950 border border-slate-950 shadow">#1</span>
                    </div>
                    <a href="profile.php?id=<?= $top1['id'] ?>" class="text-xl font-extrabold text-white hover:text-amber-400 transition-colors block truncate">
                        <?= htmlspecialchars($top1['full_name']) ?>
                    </a>
                    <span class="inline-block text-xs font-medium text-amber-400/90 mb-4"><?= htmlspecialchars($top1['faculty_name'] ?? 'Universiteti i Prishtinës') ?></span>
                    <div class="p-4 bg-slate-950/80 rounded-2xl border border-amber-500/20 grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <span class="block text-slate-400 text-[10px]">Pikë Reputacioni</span>
                            <span class="font-black text-amber-400 text-base"><?= number_format((int)$top1['reputation_points']) ?></span>
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px]">Materiale</span>
                            <span class="font-extrabold text-white text-base"><?= (int)$top1['total_files'] ?></span>
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px]">Vota Pozitive</span>
                            <span class="font-extrabold text-white text-base"><?= (int)$top1['total_votes'] ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 text-xs py-8">Vend i lirë</p>
                <?php endif; ?>
            </div>

            <!-- 3rd Place (Bronze) -->
            <div class="order-3 relative bg-slate-900/60 backdrop-blur-md border border-amber-900/50 rounded-3xl p-6 sm:p-8 text-center hover:border-amber-800 transition-all hover:-translate-y-1">
                <div class="w-12 h-12 rounded-2xl bg-amber-900/40 text-amber-500 font-black text-xl flex items-center justify-center mx-auto mb-4 border border-amber-700/50 shadow-md">
                    <i class="fas fa-award text-amber-600"></i>
                </div>
                <?php if ($top3): ?>
                    <div class="relative w-24 h-24 mx-auto mb-3">
                        <img src="<?= htmlspecialchars($top3['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=120&h=120&auto=format&fit=circle') ?>" 
                             alt="<?= htmlspecialchars($top3['full_name']) ?>" 
                             class="w-24 h-24 rounded-2xl object-cover border-4 border-amber-700/70 shadow-lg">
                        <span class="absolute -bottom-2 -right-2 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-900/80 text-amber-300 border border-amber-700">#3</span>
                    </div>
                    <a href="profile.php?id=<?= $top3['id'] ?>" class="text-lg font-bold text-white hover:text-amber-400 transition-colors block truncate">
                        <?= htmlspecialchars($top3['full_name']) ?>
                    </a>
                    <span class="inline-block text-xs text-slate-400 mb-3"><?= htmlspecialchars($top3['faculty_name'] ?? 'Universiteti i Prishtinës') ?></span>
                    <div class="p-3 bg-slate-950/60 rounded-2xl border border-slate-800 grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <span class="block text-slate-400 text-[10px]">Pikë</span>
                            <span class="font-extrabold text-amber-400 text-sm"><?= number_format((int)$top3['reputation_points']) ?></span>
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px]">Fajlla</span>
                            <span class="font-bold text-slate-200 text-sm"><?= (int)$top3['total_files'] ?></span>
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px]">Vota</span>
                            <span class="font-bold text-slate-200 text-sm"><?= (int)$top3['total_votes'] ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 text-xs py-8">Vend i lirë</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Full Leaderboard Table Section -->
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
        <div class="p-5 sm:p-6 border-b border-slate-800 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-list-ol text-amber-400"></i>
                    <span>Tabela e Plotë e Renditjes</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Të gjithë studentët sipas kontributeve në Kalo.ks</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-300">
                <?= count($ranked_users) ?> Studentë
            </span>
        </div>

        <!-- Responsive Horizontal Scrolling Container -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap min-w-[750px]">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/40 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-4 px-6 text-center w-20">Rendi</th>
                        <th class="py-4 px-6">Studenti</th>
                        <th class="py-4 px-6">Fakulteti</th>
                        <th class="py-4 px-6 text-center">Materiale</th>
                        <th class="py-4 px-6 text-center">Shkarkime</th>
                        <th class="py-4 px-6 text-center">Vota</th>
                        <th class="py-4 px-6 text-center">Reputacioni</th>
                        <th class="py-4 px-6 text-right">Veprim</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-sm">
                    <?php if (empty($ranked_users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-16 text-slate-400">
                                <i class="fas fa-user-slash text-4xl text-slate-600 mb-3 block"></i>
                                Nuk u gjet asnjë student me këto kritere filtrimi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ranked_users as $index => $u): 
                            $rank = $index + 1;
                            $is_current = ((int)$u['id'] === (int)$logged_user_id);
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors <?= $is_current ? 'bg-amber-500/5' : '' ?>">
                                <!-- Rank Badge -->
                                <td class="py-4 px-6 text-center">
                                    <?php if ($rank === 1): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-amber-400 text-slate-950 font-black text-sm shadow-md shadow-amber-500/20">
                                            1
                                        </span>
                                    <?php elseif ($rank === 2): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-400 text-slate-950 font-black text-sm">
                                            2
                                        </span>
                                    <?php elseif ($rank === 3): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-amber-700 text-amber-100 font-black text-sm">
                                            3
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-800 text-slate-400 font-bold text-xs">
                                            <?= $rank ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Student Details -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= htmlspecialchars($u['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=60&h=60&auto=format&fit=circle') ?>" 
                                             alt="<?= htmlspecialchars($u['full_name']) ?>" 
                                             class="w-10 h-10 rounded-xl object-cover border border-slate-700">
                                        <div class="min-w-0">
                                            <a href="profile.php?id=<?= $u['id'] ?>" class="font-bold text-slate-100 hover:text-amber-400 transition-colors flex items-center gap-1.5 truncate">
                                                <?= htmlspecialchars($u['full_name']) ?>
                                                <?php if ($is_current): ?>
                                                    <span class="text-[10px] font-bold text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded-md">(Ti)</span>
                                                <?php endif; ?>
                                                <?php if ($u['role'] === 'admin'): ?>
                                                    <span class="text-[10px] uppercase font-bold text-red-400 bg-red-500/10 px-1.5 py-0.5 rounded-md">Admin</span>
                                                <?php endif; ?>
                                            </a>
                                            <span class="text-xs text-slate-400 block truncate"><?= htmlspecialchars($u['email']) ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Faculty -->
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                        <i class="fas fa-university text-amber-400 text-[10px]"></i>
                                        <span><?= htmlspecialchars($u['faculty_code'] ?? 'UP') ?></span>
                                    </span>
                                </td>

                                <!-- Total Files -->
                                <td class="py-4 px-6 text-center">
                                    <span class="font-bold text-slate-200"><?= number_format((int)$u['total_files']) ?></span>
                                </td>

                                <!-- Total Downloads -->
                                <td class="py-4 px-6 text-center">
                                    <span class="font-semibold text-emerald-400"><?= number_format((int)$u['total_downloads']) ?></span>
                                </td>

                                <!-- Total Upvotes -->
                                <td class="py-4 px-6 text-center">
                                    <span class="font-semibold text-rose-400"><?= number_format((int)$u['total_votes']) ?></span>
                                </td>

                                <!-- Reputation Points -->
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-black bg-amber-500/10 border border-amber-500/30 text-amber-400">
                                        <i class="fas fa-star text-[10px]"></i>
                                        <?= number_format((int)$u['reputation_points']) ?>
                                    </span>
                                </td>

                                <!-- Action Link -->
                                <td class="py-4 px-6 text-right">
                                    <a href="profile.php?id=<?= $u['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold transition-colors">
                                        <span>Shiko</span>
                                        <i class="fas fa-chevron-right text-[10px]"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
