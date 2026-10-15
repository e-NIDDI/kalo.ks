<?php
require_once __DIR__ . '/../db.php';

$total_materiale = 0;
$verified = 0;
$total_downloads = 0;
$total_reputation = 0;

try {
    $total_materiale = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
    $verified = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE is_verified = 1")->fetchColumn();
    $total_downloads = (int)$pdo->query("SELECT COALESCE(SUM(downloads_count), 0) FROM materials")->fetchColumn();
    $total_reputation = (int)$pdo->query("SELECT COALESCE(SUM(reputation_points), 0) FROM users")->fetchColumn();
} catch (PDOException $e) {
    // Graceful fallback if tables are empty
}
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Materiale -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-slate-700 transition shadow-xl shadow-black/20">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-400 text-xl">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Materiale</span>
        </div>
        <h3 class="text-3xl font-black text-white mb-1"><?= number_format($total_materiale) ?></h3>
        <p class="text-xs text-slate-400">Skripte & afate të ngarkuara</p>
    </div>

    <!-- Zgjidhje të Verifikuara -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-slate-700 transition shadow-xl shadow-black/20">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Të Verifikuara</span>
        </div>
        <h3 class="text-3xl font-black text-emerald-400 mb-1"><?= number_format($verified) ?></h3>
        <p class="text-xs text-slate-400">Zgjidhje zyrtarisht të miratuara</p>
    </div>

    <!-- Total Shkarkime -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-slate-700 transition shadow-xl shadow-black/20">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 text-xl">
                <i class="fa-solid fa-download"></i>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Shkarkime</span>
        </div>
        <h3 class="text-3xl font-black text-white mb-1"><?= number_format($total_downloads) ?></h3>
        <p class="text-xs text-slate-400">Dokumente të shkarkuara nga studentët</p>
    </div>

    <!-- Pikët e Reputacionit -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-slate-700 transition shadow-xl shadow-black/20">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-400 text-xl">
                <i class="fa-solid fa-star"></i>
            </div>
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Reputacion</span>
        </div>
        <h3 class="text-3xl font-black text-amber-400 mb-1"><?= number_format($total_reputation) ?></h3>
        <p class="text-xs text-slate-400">Pikë të akumuluara në komunitet</p>
    </div>
</div>
