<?php
/**
 * Database Initializer & Migration Tool for Kalo.ks
 * Can be run from CLI (php init.php) or directly via browser (http://localhost/init.php)
 */

require_once __DIR__ . '/db.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="sq" class="dark"><head><meta charset="UTF-8"><title>Instalimi i Kalo.ks</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-950 text-slate-100 p-8 font-sans"><div class="max-w-2xl mx-auto bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl">';
    echo '<h1 class="text-2xl font-black text-amber-400 mb-4 flex items-center gap-2"><span class="text-3xl">⚡</span> Instalimi i Kalo.ks Baza e të Dhënave</h1>';
}

function logMsg($msg, $is_cli) {
    if ($is_cli) {
        echo "[Kalo.ks] " . $msg . "\n";
    } else {
        echo '<p class="text-xs text-slate-300 font-mono py-1 border-b border-slate-800/60"><span class="text-emerald-400">✓</span> ' . htmlspecialchars($msg) . '</p>';
    }
}

try {
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Skedari schema.sql nuk u gjet!");
    }

    $sql = file_get_contents($schemaFile);
    
    // Execute multiple SQL statements
    $pdo->exec($sql);
    logMsg("U ekzekutua me sukses skema e plotë prej 9 tabelash (schema.sql).", $is_cli);
    logMsg("U regjistruan 5 fakultete zyrtare të Universitetit të Prishtinës (FIEK, FNA, FE, FM, FK).", $is_cli);
    logMsg("U inicializuan 10 lëndë kryesore universitare.", $is_cli);
    logMsg("U krijuan 4 përdorues demo (fjalëkalimi për të gjithë: password123).", $is_cli);
    logMsg("U ngarkuan 8 materiale studimore reale, komente, vota dhe mesazhe bisede.", $is_cli);

    if (!$is_cli) {
        echo '<div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between">';
        echo '<span class="text-sm text-emerald-400 font-bold">Instalimi u krye me sukses!</span>';
        echo '<a href="index.php" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-sm transition-all shadow-lg shadow-amber-500/20">Shko te Ballina →</a>';
        echo '</div>';
    } else {
        echo "\n[Kalo.ks] Sukses! Baza e të dhënave kaloks u instalua me të gjitha të dhënat fillestare.\n";
    }

} catch (Exception $e) {
    if ($is_cli) {
        echo "[Kalo.ks ERROR] " . $e->getMessage() . "\n";
    } else {
        echo '<div class="mt-4 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-mono">';
        echo 'Gabim gjatë ekzekutimit: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
}

if (!$is_cli) {
    echo '</div></body></html>';
}
