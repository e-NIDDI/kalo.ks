<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$logged_user_id = $_SESSION['user_id'] ?? 1;

// -------------------------------------------------------------
// Built-in AJAX Handler for Chat (Self-contained & lightweight)
// -------------------------------------------------------------
if (isset($_REQUEST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'] ?? '';

    // AJAX ACTION: Fetch Messages
    if ($action === 'fetch') {
        $faculty_id = (int)($_GET['faculty_id'] ?? 0);
        $last_id = (int)($_GET['last_id'] ?? 0);

        if ($faculty_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID e pavlefshme e fakultetit.']);
            exit;
        }

        try {
            if ($last_id > 0) {
                // Incremental fetch (only newer messages)
                $stmt = $pdo->prepare("
                    SELECT m.id, m.faculty_id, m.user_id, m.message, m.created_at,
                           u.full_name, u.avatar_url, u.role, u.reputation_points
                    FROM live_chat_messages m
                    JOIN users u ON m.user_id = u.id
                    WHERE m.faculty_id = ? AND m.id > ?
                    ORDER BY m.created_at ASC
                    LIMIT 100
                ");
                $stmt->execute([$faculty_id, $last_id]);
            } else {
                // Initial load: get last 50 messages
                $stmt = $pdo->prepare("
                    SELECT * FROM (
                        SELECT m.id, m.faculty_id, m.user_id, m.message, m.created_at,
                               u.full_name, u.avatar_url, u.role, u.reputation_points
                        FROM live_chat_messages m
                        JOIN users u ON m.user_id = u.id
                        WHERE m.faculty_id = ?
                        ORDER BY m.created_at DESC
                        LIMIT 50
                    ) AS sub ORDER BY created_at ASC
                ");
                $stmt->execute([$faculty_id]);
            }

            $messages = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'current_user_id' => (int)$logged_user_id
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // AJAX ACTION: Send Message
    elseif ($action === 'send') {
        $faculty_id = (int)($_POST['faculty_id'] ?? 0);
        $message_text = trim($_POST['message'] ?? '');

        if ($faculty_id <= 0 || $message_text === '') {
            echo json_encode(['success' => false, 'error' => 'Mesazhi nuk mund të jetë i zbrazët.']);
            exit;
        }

        // Limit message length to 1500 chars
        if (mb_strlen($message_text) > 1500) {
            $message_text = mb_substr($message_text, 0, 1500);
        }

        try {
            $insert = $pdo->prepare("
                INSERT INTO live_chat_messages (faculty_id, user_id, message, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $insert->execute([$faculty_id, $logged_user_id, $message_text]);
            $new_id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message_id' => $new_id
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Veprim i panjohur.']);
    exit;
}

// -------------------------------------------------------------
// Fetch Faculties with Live Message Counts
// -------------------------------------------------------------
$faculties = [];
try {
    $facStmt = $pdo->query("
        SELECT f.id, f.name, f.code, f.icon, f.color_code,
               COUNT(m.id) AS message_count
        FROM faculties f
        LEFT JOIN live_chat_messages m ON f.id = m.faculty_id
        GROUP BY f.id
        ORDER BY f.id ASC
    ");
    $faculties = $facStmt->fetchAll();
} catch (PDOException $e) {
    $faculties = [];
}

// Determine active faculty
$active_faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : ($faculties[0]['id'] ?? 1);
$active_faculty = null;
foreach ($faculties as $fac) {
    if ((int)$fac['id'] === $active_faculty_id) {
        $active_faculty = $fac;
        break;
    }
}
if (!$active_faculty && !empty($faculties)) {
    $active_faculty = $faculties[0];
    $active_faculty_id = (int)$active_faculty['id'];
}

// Initial messages for server-side rendering
$initial_messages = [];
try {
    $imStmt = $pdo->prepare("
        SELECT * FROM (
            SELECT m.id, m.faculty_id, m.user_id, m.message, m.created_at,
                   u.full_name, u.avatar_url, u.role, u.reputation_points
            FROM live_chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.faculty_id = ?
            ORDER BY m.created_at DESC
            LIMIT 50
        ) AS sub ORDER BY created_at ASC
    ");
    $imStmt->execute([$active_faculty_id]);
    $initial_messages = $imStmt->fetchAll();
} catch (PDOException $e) {
    $initial_messages = [];
}

require_once __DIR__ . '/header.php';
?>

<main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 py-6 w-full flex flex-col">
    <!-- Chat Container with Fixed Height -->
    <div class="bg-slate-900/70 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col md:flex-row h-[75vh] min-h-[550px]">
        
        <!-- ========================================================= -->
        <!-- Left: Faculty Sidebar -->
        <!-- ========================================================= -->
        <aside class="w-full md:w-80 bg-slate-950/70 border-b md:border-b-0 md:border-r border-slate-800 flex flex-col shrink-0">
            <!-- Sidebar Header -->
            <div class="p-4 sm:p-5 border-b border-slate-800/80 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-base">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div>
                        <h2 class="font-extrabold text-sm sm:text-base text-white">Dhomat e Fakulteteve</h2>
                        <p class="text-[11px] text-slate-400">Diskutime studentore live</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live
                </span>
            </div>

            <!-- Faculty Room List (Scrollable on desktop, horizontal scroll on mobile) -->
            <div class="flex-1 overflow-x-auto md:overflow-y-auto p-3 flex md:flex-col gap-2">
                <?php foreach ($faculties as $fac): 
                    $isActive = ((int)$fac['id'] === $active_faculty_id);
                ?>
                    <button type="button" 
                            onclick="selectFacultyRoom(<?= $fac['id'] ?>, '<?= htmlspecialchars($fac['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($fac['code'], ENT_QUOTES) ?>')"
                            id="faculty-btn-<?= $fac['id'] ?>"
                            class="faculty-room-btn flex items-center justify-between gap-3 px-4 py-3 rounded-2xl text-left transition-all shrink-0 md:shrink w-auto md:w-full border <?= $isActive ? 'bg-slate-800 border-amber-500/50 shadow-md text-amber-400' : 'bg-slate-900/40 hover:bg-slate-800/60 border-slate-800/80 text-slate-300' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-8 h-8 rounded-xl bg-slate-800 text-slate-300 flex items-center justify-center font-bold text-xs shrink-0 border border-slate-700">
                                <?= htmlspecialchars($fac['code']) ?>
                            </span>
                            <div class="min-w-0">
                                <span class="font-bold text-xs sm:text-sm text-slate-100 block truncate"><?= htmlspecialchars($fac['name']) ?></span>
                                <span class="text-[10px] text-slate-400 block -mt-0.5">Kanali zyrtar i bisedës</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-800 text-slate-400 border border-slate-700/60 shrink-0">
                            <i class="fas fa-message text-[8px] mr-1 text-amber-400"></i>
                            <span id="fac-count-<?= $fac['id'] ?>"><?= (int)$fac['message_count'] ?></span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Sidebar Footer Tip -->
            <div class="hidden md:block p-3.5 border-t border-slate-800/80 bg-slate-950/40 text-[11px] text-slate-400 text-center">
                <i class="fas fa-shield-cat text-amber-400 mr-1"></i> Ruani etikën dhe respektoni kolegët.
            </div>
        </aside>

        <!-- ========================================================= -->
        <!-- Right: Active Chat Area -->
        <!-- ========================================================= -->
        <section class="flex-1 flex flex-col bg-slate-950/40 min-w-0">
            <!-- Room Header -->
            <div class="px-5 py-4 border-b border-slate-800/80 bg-slate-900/60 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-amber-300/10 border border-amber-500/30 text-amber-400 flex items-center justify-center text-lg shrink-0">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 id="active-faculty-title" class="font-extrabold text-base text-white truncate">
                                <?= htmlspecialchars($active_faculty['name'] ?? 'Fakulteti') ?>
                            </h3>
                            <span id="active-faculty-code" class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-500/10 border border-amber-500/30 text-amber-400 shrink-0">
                                <?= htmlspecialchars($active_faculty['code'] ?? 'UP') ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span>Sinkronizim automatik çdo 2 sekonda</span>
                        </p>
                    </div>
                </div>

                <!-- Chat Room Action Buttons -->
                <div class="flex items-center gap-2">
                    <button onclick="pollMessages(true)" class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-800 rounded-xl transition-colors text-xs" title="Rifresko mesazhet">
                        <i class="fas fa-arrows-rotate" id="refresh-icon"></i>
                    </button>
                    <a href="index.php?fakulteti=<?= urlencode($active_faculty['code'] ?? '') ?>" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                        <i class="fas fa-folder-open text-amber-400"></i>
                        <span>Materialet e Lëndëve</span>
                    </a>
                </div>
            </div>

            <!-- Message Feed Container -->
            <div id="chat-messages-container" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
                <!-- Initial Messages rendered by PHP for instant load -->
                <?php if (empty($initial_messages)): ?>
                    <div id="no-messages-placeholder" class="text-center py-20 text-slate-500">
                        <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center mx-auto mb-3 text-2xl text-slate-600">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <p class="font-semibold text-slate-300 text-sm">Nuk ka mesazhe në këtë dhomë ende.</p>
                        <p class="text-xs text-slate-500 mt-1">Bëhuni i pari që filloni bisedën me kolegët tuaj!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($initial_messages as $msg): 
                        $is_me = ((int)$msg['user_id'] === (int)$logged_user_id);
                        $msgTime = date('H:i', strtotime($msg['created_at']));
                    ?>
                        <div class="flex items-end gap-2.5 <?= $is_me ? 'justify-end' : 'justify-start' ?> message-item" data-id="<?= $msg['id'] ?>">
                            <?php if (!$is_me): ?>
                                <img src="<?= htmlspecialchars($msg['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=40&h=40&auto=format&fit=circle') ?>" 
                                     alt="" class="w-8 h-8 rounded-full object-cover border border-slate-700 shrink-0 mb-1">
                            <?php endif; ?>

                            <div class="max-w-[80%] sm:max-w-md">
                                <?php if (!$is_me): ?>
                                    <div class="flex items-center gap-1.5 mb-1 pl-1">
                                        <a href="profile.php?id=<?= $msg['user_id'] ?>" class="text-xs font-bold text-slate-300 hover:text-amber-400 transition-colors">
                                            <?= htmlspecialchars($msg['full_name']) ?>
                                        </a>
                                        <?php if ($msg['role'] === 'admin'): ?>
                                            <span class="text-[9px] uppercase font-bold text-red-400 bg-red-500/10 px-1 rounded">Admin</span>
                                        <?php endif; ?>
                                        <span class="text-[10px] text-amber-400/80 flex items-center gap-0.5">
                                            <i class="fas fa-star text-[8px]"></i> <?= (int)$msg['reputation_points'] ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed shadow-md break-words <?= $is_me ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-slate-800 text-slate-200 border border-slate-700/80 rounded-bl-none' ?>">
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                </div>

                                <div class="text-[10px] text-slate-500 mt-1 px-1 <?= $is_me ? 'text-right' : 'text-left' ?>">
                                    <?= $msgTime ?>
                                </div>
                            </div>

                            <?php if ($is_me): ?>
                                <img src="<?= htmlspecialchars($_SESSION['avatar_url'] ?? 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=40&q=80') ?>" 
                                     alt="" class="w-8 h-8 rounded-full object-cover border border-indigo-400/50 shrink-0 mb-1">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input Form -->
            <div class="p-4 border-t border-slate-800/80 bg-slate-900/80">
                <form id="chat-input-form" onsubmit="handleSendMessage(event)" class="flex items-center gap-2">
                    <input type="text" id="chat-message-input" 
                           placeholder="Shkruaj një mesazh për kolegët e <?= htmlspecialchars($active_faculty['code'] ?? 'fakultetit') ?>..." 
                           autocomplete="off" required
                           class="flex-1 bg-slate-800/90 border border-slate-700 rounded-2xl px-4 py-3 text-sm text-slate-100 placeholder-slate-400 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                    
                    <button type="submit" id="chat-send-btn" 
                            class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded-2xl font-bold text-sm transition-all shadow-md shadow-indigo-600/30 flex items-center gap-1.5 shrink-0">
                        <span>Dërgo</span>
                        <i class="fas fa-paper-plane text-xs"></i>
                    </button>
                </form>
            </div>
        </section>
    </div>
</main>

<script>
// State management
let activeFacultyId = <?= (int)$active_faculty_id ?>;
const currentUserId = <?= (int)$logged_user_id ?>;
let lastMessageId = 0;
let pollingTimer = null;
const POLLING_INTERVAL_MS = 2000; // 2 seconds lightweight poll

// Helper: Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    // Find highest message ID in DOM
    updateLastMessageIdFromDOM();
    scrollToBottom(false);

    // Start 2-second polling
    startPolling();
});

function updateLastMessageIdFromDOM() {
    const items = document.querySelectorAll('.message-item');
    items.forEach(el => {
        const id = parseInt(el.getAttribute('data-id'), 10);
        if (id > lastMessageId) {
            lastMessageId = id;
        }
    });
}

// Switch active faculty room
function selectFacultyRoom(facultyId, facultyName, facultyCode) {
    if (activeFacultyId === facultyId) return;

    activeFacultyId = facultyId;
    lastMessageId = 0;

    // Update UI headers
    document.getElementById('active-faculty-title').textContent = facultyName;
    document.getElementById('active-faculty-code').textContent = facultyCode;
    document.getElementById('chat-message-input').placeholder = `Shkruaj një mesazh për kolegët e ${facultyCode}...`;

    // Highlight button in sidebar
    document.querySelectorAll('.faculty-room-btn').forEach(btn => {
        btn.classList.remove('bg-slate-800', 'border-amber-500/50', 'text-amber-400');
        btn.classList.add('bg-slate-900/40', 'border-slate-800/80', 'text-slate-300');
    });
    const activeBtn = document.getElementById(`faculty-btn-${facultyId}`);
    if (activeBtn) {
        activeBtn.classList.add('bg-slate-800', 'border-amber-500/50', 'text-amber-400');
        activeBtn.classList.remove('bg-slate-900/40', 'border-slate-800/80', 'text-slate-300');
    }

    // Clear feed & show loading indicator
    const container = document.getElementById('chat-messages-container');
    container.innerHTML = `
        <div class="text-center py-20 text-slate-500">
            <i class="fas fa-circle-notch fa-spin text-2xl text-amber-400 mb-2"></i>
            <p class="text-xs">Duke ngarkuar bisedën...</p>
        </div>
    `;

    // Fetch initial messages for new room
    pollMessages(true);
}

// Start recurring 2-second AJAX polling
function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(() => {
        pollMessages(false);
    }, POLLING_INTERVAL_MS);
}

// Poll messages from server
function pollMessages(isFullRefresh = false) {
    const fetchLastId = isFullRefresh ? 0 : lastMessageId;
    const url = `chat.php?ajax=1&action=fetch&faculty_id=${activeFacultyId}&last_id=${fetchLastId}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                if (isFullRefresh) {
                    renderFullMessageList(data.messages);
                } else if (data.messages.length > 0) {
                    appendNewMessages(data.messages);
                }
            }
        })
        .catch(err => {
            // Silently handle polling errors without breaking UI
            console.warn('Chat poll warning:', err);
        });
}

// Render entire message list (e.g. after switching rooms)
function renderFullMessageList(messages) {
    const container = document.getElementById('chat-messages-container');
    if (messages.length === 0) {
        container.innerHTML = `
            <div id="no-messages-placeholder" class="text-center py-20 text-slate-500">
                <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center mx-auto mb-3 text-2xl text-slate-600">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <p class="font-semibold text-slate-300 text-sm">Nuk ka mesazhe në këtë dhomë ende.</p>
                <p class="text-xs text-slate-500 mt-1">Bëhuni i pari që filloni bisedën me kolegët tuaj!</p>
            </div>
        `;
        lastMessageId = 0;
        return;
    }

    container.innerHTML = '';
    messages.forEach(msg => {
        container.appendChild(createMessageElement(msg));
        if (parseInt(msg.id, 10) > lastMessageId) {
            lastMessageId = parseInt(msg.id, 10);
        }
    });

    scrollToBottom(false);
}

// Append newly arrived messages to existing feed
function appendNewMessages(messages) {
    const container = document.getElementById('chat-messages-container');
    const placeholder = document.getElementById('no-messages-placeholder');
    if (placeholder) placeholder.remove();

    const wasNearBottom = isNearBottom(container);

    messages.forEach(msg => {
        const msgId = parseInt(msg.id, 10);
        // Avoid duplicate message appending
        if (msgId > lastMessageId) {
            container.appendChild(createMessageElement(msg));
            lastMessageId = msgId;
        }
    });

    // Update room counter in sidebar
    const counter = document.getElementById(`fac-count-${activeFacultyId}`);
    if (counter) {
        counter.textContent = parseInt(counter.textContent || 0, 10) + messages.length;
    }

    // Scroll to bottom if user was already near the bottom
    if (wasNearBottom) {
        scrollToBottom(true);
    }
}

// Create message DOM element with full XSS escaping
function createMessageElement(msg) {
    const isMe = (parseInt(msg.user_id, 10) === currentUserId);
    const timeStr = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const avatarUrl = msg.avatar_url || 'https://images.unsplash.com/photo-1534528741775-53994a6488df?w=40&h=40&auto=format&fit=circle';

    const wrapper = document.createElement('div');
    wrapper.className = `flex items-end gap-2.5 ${isMe ? 'justify-end' : 'justify-start'} message-item`;
    wrapper.setAttribute('data-id', msg.id);

    let html = '';

    if (!isMe) {
        html += `<img src="${escapeHtml(avatarUrl)}" alt="" class="w-8 h-8 rounded-full object-cover border border-slate-700 shrink-0 mb-1">`;
    }

    html += `<div class="max-w-[80%] sm:max-w-md">`;

    if (!isMe) {
        html += `
            <div class="flex items-center gap-1.5 mb-1 pl-1">
                <a href="profile.php?id=${msg.user_id}" class="text-xs font-bold text-slate-300 hover:text-amber-400 transition-colors">
                    ${escapeHtml(msg.full_name)}
                </a>
                ${msg.role === 'admin' ? '<span class="text-[9px] uppercase font-bold text-red-400 bg-red-500/10 px-1 rounded">Admin</span>' : ''}
                <span class="text-[10px] text-amber-400/80 flex items-center gap-0.5">
                    <i class="fas fa-star text-[8px]"></i> ${parseInt(msg.reputation_points, 10) || 0}
                </span>
            </div>
        `;
    }

    html += `
        <div class="p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed shadow-md break-words ${isMe ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-slate-800 text-slate-200 border border-slate-700/80 rounded-bl-none'}">
            ${escapeHtml(msg.message).replace(/\n/g, '<br>')}
        </div>
        <div class="text-[10px] text-slate-500 mt-1 px-1 ${isMe ? 'text-right' : 'text-left'}">
            ${timeStr}
        </div>
    </div>`;

    if (isMe) {
        html += `<img src="${escapeHtml(avatarUrl)}" alt="" class="w-8 h-8 rounded-full object-cover border border-indigo-400/50 shrink-0 mb-1">`;
    }

    wrapper.innerHTML = html;
    return wrapper;
}

// Handle sending a new message via AJAX
function handleSendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('chat-message-input');
    const sendBtn = document.getElementById('chat-send-btn');
    const message = input.value.trim();

    if (!message || !activeFacultyId) return;

    // Disable input momentarily to avoid double-posts
    input.disabled = true;
    sendBtn.disabled = true;

    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'send');
    formData.append('faculty_id', activeFacultyId);
    formData.append('message', message);

    fetch('chat.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        input.disabled = false;
        sendBtn.disabled = false;

        if (data.success) {
            input.value = '';
            input.focus();
            // Immediate fetch to show the new message
            pollMessages(false);
            scrollToBottom(true);
        } else {
            alert('Dështoi dërgimi: ' + (data.error || 'Gabim i panjohur'));
        }
    })
    .catch(err => {
        input.disabled = false;
        sendBtn.disabled = false;
        alert('Gabim në rrjet: ' + err.message);
    });
}

// Scroll feed helpers
function scrollToBottom(smooth = false) {
    const container = document.getElementById('chat-messages-container');
    if (container) {
        container.scrollTo({
            top: container.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }
}

function isNearBottom(container) {
    const threshold = 120; // pixels from bottom
    return (container.scrollHeight - container.scrollTop - container.clientHeight) < threshold;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
