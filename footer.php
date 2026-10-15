<?php 
require_once __DIR__ . '/db.php';

$total_users = 0;
$total_materials = 0;
$total_courses = 0;
$total_faculties = 0;
$all_faculties_modal = [];
$all_courses_modal = [];

try {
    $total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_materials = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
    $total_courses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $total_faculties = (int)$pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn();

    $all_faculties_modal = $pdo->query("SELECT id, name, code FROM faculties ORDER BY name ASC")->fetchAll();
    $all_courses_modal = $pdo->query("SELECT id, faculty_id, name, code FROM courses ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    // Suppress error if tables not yet populated
}
?>
<footer class="mt-auto bg-slate-950 text-slate-100 py-12 border-t border-slate-800/80">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- About -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-graduation-cap text-amber-400 text-lg"></i>
                <h3 class="text-amber-400 font-extrabold text-base tracking-wide">Kalo.ks</h3>
            </div>
            <p class="text-slate-400 text-sm leading-relaxed">
                Platformë e hapur bashkëpunimi për studentët e universiteteve në Kosovë. Shkëmbeni zgjidhje provimesh, raporte laboratori dhe skripte studimore.
            </p>
        </div>

        <!-- Quick Links by Faculty -->
        <div>
            <h3 class="text-slate-200 font-bold mb-3 text-sm tracking-wider uppercase">Fakultetet Kryesore</h3>
            <ul class="space-y-2 text-slate-400 text-sm">
                <li><a href="index.php?fakulteti=FIEK" class="hover:text-amber-400 transition-colors"><i class="fas fa-microchip w-4 text-xs text-amber-400/70 mr-1"></i> FIEK - Inxhinieri Elektrike & Kompjuterike</a></li>
                <li><a href="index.php?fakulteti=FNA" class="hover:text-amber-400 transition-colors"><i class="fas fa-flask w-4 text-xs text-teal-400/70 mr-1"></i> FNA - Shkenca Natyrore & Matematikë</a></li>
                <li><a href="index.php?fakulteti=FE" class="hover:text-amber-400 transition-colors"><i class="fas fa-chart-line w-4 text-xs text-emerald-400/70 mr-1"></i> FE - Fakulteti Ekonomik</a></li>
                <li><a href="index.php?fakulteti=FM" class="hover:text-amber-400 transition-colors"><i class="fas fa-stethoscope w-4 text-xs text-blue-400/70 mr-1"></i> FM - Fakulteti i Mjekësisë</a></li>
                <li><a href="index.php?fakulteti=FK" class="hover:text-amber-400 transition-colors"><i class="fas fa-palette w-4 text-xs text-pink-400/70 mr-1"></i> FK - Fakulteti i Arteve</a></li>
            </ul>
        </div>

        <!-- Live Stats -->
        <div>
            <h3 class="text-slate-200 font-bold mb-3 text-sm tracking-wider uppercase">Statistika Live</h3>
            <div class="space-y-2 text-sm">
                <p class="text-slate-400 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-400 flex items-center justify-center text-xs"><i class="fas fa-users"></i></span>
                    <span><strong class="text-slate-200"><?= number_format($total_users) ?></strong> Studentë Aktivë</span>
                </p>
                <p class="text-slate-400 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs"><i class="fas fa-file-lines"></i></span>
                    <span><strong class="text-slate-200"><?= number_format($total_materials) ?></strong> Materiale Mësimore</span>
                </p>
                <p class="text-slate-400 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-xs"><i class="fas fa-book-bookmark"></i></span>
                    <span><strong class="text-slate-200"><?= number_format($total_courses) ?></strong> Lëndë Universitare</span>
                </p>
            </div>
        </div>

        <!-- Copyright & Kosovo Pride -->
        <div>
            <h3 class="text-slate-200 font-bold mb-3 text-sm tracking-wider uppercase">Për Komunitetin</h3>
            <p class="text-slate-400 text-xs mb-3 leading-relaxed">
                Krijuar nga studentët, për studentët. Ndërtuar me teknologji të lehtë dhe të shpejtë për performancë maksimale.
            </p>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400">
                <i class="fas fa-heart text-amber-400 text-xs"></i> Ndërtuar për Studentët në Kosovë 🇽🇰
            </span>
            <p class="text-slate-500 text-xs mt-4">© <?= date('Y') ?> Kalo.ks. Të gjitha të drejtat të rezervuara.</p>
        </div>
    </div>
</footer>

<!-- Global Upload Material Modal (Accessible from any page) -->
<div id="upload-modal" class="modal hidden fixed inset-0 z-50 items-center justify-center p-4">
    <div class="modal-overlay fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"></div>
    <div class="relative w-full max-w-xl bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-8 z-10 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600/10 text-indigo-400 flex items-center justify-center text-lg">
                    <i class="fas fa-cloud-arrow-up"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">Ngarko Material Mësimor</h2>
                    <p class="text-xs text-slate-400">Fito +15 pikë reputacioni për çdo kontribut</p>
                </div>
            </div>
            <button onclick="closeModal('upload-modal')" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors">✕</button>
        </div>

        <form action="upload.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <!-- Fakulteti -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Fakulteti *</label>
                <select name="fakulteti" id="global-upload-fakulteti" required onchange="filterModalCourses(this.value)"
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
                    <option value="">Zgjidh Fakultetin...</option>
                    <?php foreach ($all_faculties_modal as $fac): ?>
                        <option value="<?= htmlspecialchars($fac['code']) ?>" data-id="<?= $fac['id'] ?>">
                            <?= htmlspecialchars($fac['name']) ?> (<?= htmlspecialchars($fac['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Lënda -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Lënda *</label>
                <select name="lenda" id="global-upload-lenda" required
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
                    <option value="">Zgjidhni më parë fakultetin...</option>
                </select>
            </div>

            <!-- Titulli -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Titulli i Materialit *</label>
                <input type="text" name="title" required placeholder="P.sh. Afati i Qershorit 2024 - Zgjidhje të Plota"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
            </div>

            <!-- Përshkrimi -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Përshkrimi i Detajuar</label>
                <textarea name="description" rows="3" placeholder="Përshkruani materialin, çfarë pyetjesh përmban, ushtrimet kryesore..."
                          class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400"></textarea>
            </div>

            <!-- File Type & File Input -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Formati</label>
                    <select name="file_type" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
                        <option value="pdf">PDF (.pdf)</option>
                        <option value="zip">ZIP Arkivë (.zip)</option>
                        <option value="png">Imazh / Foto (.png, .jpg)</option>
                        <option value="docx">Word (.docx, .doc)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Skedari (max 50MB) *</label>
                    <input type="file" name="file" required
                           class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 file:cursor-pointer cursor-pointer">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeModal('upload-modal')" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition-colors">
                    Anulo
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold transition-all shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                    <i class="fas fa-cloud-arrow-up"></i>
                    <span>Ngarko Skedarin</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Global Vanilla JS Helpers & Course Filtering -->
<script>
    "use strict";

    // All courses data for dynamic dropdown filtering
    const globalModalCourses = <?= json_encode($all_courses_modal) ?>;

    function filterModalCourses(facultyCode) {
        const select = document.getElementById('global-upload-lenda');
        const facSelect = document.getElementById('global-upload-fakulteti');
        if (!select || !facSelect) return;

        const selectedOption = facSelect.options[facSelect.selectedIndex];
        const facultyId = selectedOption ? parseInt(selectedOption.getAttribute('data-id'), 10) : null;

        select.innerHTML = '<option value="">Zgjidh Lëndën...</option>';

        if (!facultyId) return;

        const filtered = globalModalCourses.filter(c => parseInt(c.faculty_id, 10) === facultyId);
        if (filtered.length === 0) {
            select.innerHTML += '<option value="">Nuk ka lëndë të regjistruara për këtë fakultet</option>';
            return;
        }

        filtered.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.code;
            opt.textContent = `${c.name} (${c.code})`;
            select.appendChild(opt);
        });
    }

    // Modal trigger helper
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    }

    // Toast notification helper
    function showToast(message, type = 'info') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-24 right-6 z-50 flex flex-col gap-3 pointer-events-none';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        const bgClass = type === 'success' ? 'bg-emerald-600/90 border-emerald-500/50 text-white' : 
                       (type === 'error' ? 'bg-red-600/90 border-red-500/50 text-white' : 'bg-slate-800/90 border-slate-700 text-slate-100');
        toast.className = `pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-2xl border backdrop-blur-md transition-all duration-300 transform translate-x-4 opacity-0 ${bgClass}`;
        toast.innerHTML = `
            <span class="text-sm font-medium">${message}</span>
            <button class="ml-auto text-white/70 hover:text-white" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(toast);
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-4', 'opacity-0');
        });
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-x-4');
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    }

    // Modal background click & Escape listener
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) closeModal(modal.id);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal:not(.hidden)');
            openModals.forEach(modal => closeModal(modal.id));
        }
    });
</script>
</body>
</html>
