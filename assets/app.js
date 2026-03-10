/* ╔══════════════════════════════════════════════════════════════════╗
   ║  Oráculo Namek v2.0 — Client-side Application                  ║
   ║  Features: Markdown, dark mode, search, export, auto-resize    ║
   ╚══════════════════════════════════════════════════════════════════╝ */

'use strict';

/* ── State ─────────────────────────────────────────────────────────── */
const state = {
    user: null,
    conversations: [],
    activeConversationId: null,
    models: [],
    selectedModel: '',
    sending: false,
    searchQuery: '',
};

/* ── DOM references ────────────────────────────────────────────────── */
const el = {
    authView: document.getElementById('authView'),
    chatView: document.getElementById('chatView'),
    loginForm: document.getElementById('loginForm'),
    registerForm: document.getElementById('registerForm'),
    showLogin: document.getElementById('showLogin'),
    showRegister: document.getElementById('showRegister'),
    userBadge: document.getElementById('userBadge'),
    userEmail: document.getElementById('userEmail'),
    userAvatar: document.getElementById('userAvatar'),
    logoutBtn: document.getElementById('logoutBtn'),
    themeToggle: document.getElementById('themeToggle'),
    searchConversations: document.getElementById('searchConversations'),
    newConversationBtn: document.getElementById('newConversationBtn'),
    conversationList: document.getElementById('conversationList'),
    conversationTitle: document.getElementById('conversationTitle'),
    conversationHint: document.getElementById('conversationHint'),
    renameConversationBtn: document.getElementById('renameConversationBtn'),
    deleteConversationBtn: document.getElementById('deleteConversationBtn'),
    exportConversationBtn: document.getElementById('exportConversationBtn'),
    modelSelect: document.getElementById('modelSelect'),
    messages: document.getElementById('messages'),
    messageForm: document.getElementById('messageForm'),
    messageInput: document.getElementById('messageInput'),
    sendBtn: document.getElementById('sendBtn'),
    charCount: document.getElementById('charCount'),
    typingIndicator: document.getElementById('typingIndicator'),
    statsLabel: document.getElementById('statsLabel'),
    toast: document.getElementById('toast'),
};

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  UTILITIES                                                      ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
}

function showToast(text, duration = 2800) {
    el.toast.textContent = text;
    el.toast.classList.remove('hidden');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => el.toast.classList.add('hidden'), duration);
}

function timeAgo(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'ahora';
    if (diff < 3600) return `hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `hace ${Math.floor(diff / 3600)} h`;
    if (diff < 604800) return `hace ${Math.floor(diff / 86400)} d`;
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
}

/* ── Simple Markdown → HTML parser ─────────────────────────────────── */
function renderMarkdown(text) {
    if (!text) return '';

    let html = escapeHtml(text);

    // Code blocks: ```lang\n...\n```
    html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (_match, lang, code) => {
        const label = lang || 'código';
        const id = 'cb_' + Math.random().toString(36).slice(2, 8);
        return `<div class="code-block">
            <div class="code-block-header">
                <span>${escapeHtml(label)}</span>
                <button class="code-block-copy" data-copy-target="${id}" onclick="copyCodeBlock(this, '${id}')">Copiar</button>
            </div>
            <code class="code-block-code" id="${id}">${code}</code>
        </div>`;
    });

    // Inline code: `...`
    html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');

    // Bold: **...**
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Italic: *...*
    html = html.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');

    // Headers: ### ... (only at start of line)
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

    // Unordered lists: - ...
    html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>');
    // Collapse consecutive <ul> tags
    html = html.replace(/<\/ul>\s*<ul>/g, '');

    // Ordered lists: 1. ...
    html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

    // Paragraphs: double newlines
    html = html.replace(/\n\n/g, '</p><p>');

    // Single newlines → <br> (but not inside code blocks)
    html = html.replace(/\n/g, '<br>');

    // Wrap in paragraph if not already
    if (!html.startsWith('<')) {
        html = '<p>' + html + '</p>';
    }

    return html;
}

/* ── Copy code block helper ────────────────────────────────────────── */
window.copyCodeBlock = function (btn, id) {
    const codeEl = document.getElementById(id);
    if (!codeEl) return;

    navigator.clipboard.writeText(codeEl.textContent).then(() => {
        btn.textContent = '✓ Copiado';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = 'Copiar';
            btn.classList.remove('copied');
        }, 2000);
    });
};

/* ── Auto-resize textarea ──────────────────────────────────────────── */
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 160) + 'px';
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  API LAYER                                                      ║
   ╚══════════════════════════════════════════════════════════════════╝ */

async function api(url, options = {}) {
    const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options,
    });

    let data;
    try {
        data = await response.json();
    } catch {
        throw new Error(`Error del servidor (${response.status}). Verifica que MAMP esté ejecutándose.`);
    }

    if (!response.ok || data.ok === false) {
        throw new Error(data.error || `Error ${response.status}`);
    }

    return data;
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  THEME                                                          ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function getStoredTheme() {
    return localStorage.getItem('nousgpt_theme') || 'light';
}

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('nousgpt_theme', theme);
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    setTheme(current === 'light' ? 'dark' : 'light');
}

// Apply stored theme immediately
setTheme(getStoredTheme());

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  RENDERING                                                      ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function switchAuthTab(mode) {
    const login = mode === 'login';
    el.loginForm.classList.toggle('hidden', !login);
    el.registerForm.classList.toggle('hidden', login);
    el.showLogin.classList.toggle('active', login);
    el.showRegister.classList.toggle('active', !login);
}

function renderAuth() {
    const logged = Boolean(state.user);
    el.authView.classList.toggle('hidden', logged);
    el.chatView.classList.toggle('hidden', !logged);

    if (logged) {
        el.userBadge.textContent = state.user.username;
        el.userEmail.textContent = state.user.email;
        el.userAvatar.textContent = (state.user.username || 'U').charAt(0).toUpperCase();
    }
}

function renderModelPicker() {
    const hasModels = state.models.length > 0;
    const options = hasModels ? state.models : ['fallback-local'];
    const selected = state.selectedModel && options.includes(state.selectedModel) ? state.selectedModel : options[0];

    state.selectedModel = selected;
    el.modelSelect.innerHTML = options
        .map((m) => `<option value="${escapeHtml(m)}"${m === selected ? ' selected' : ''}>${escapeHtml(m)}</option>`)
        .join('');
}

function setSendingState(sending) {
    state.sending = sending;
    el.sendBtn.disabled = sending;
    el.sendBtn.classList.toggle('sending', sending);
    el.messageInput.disabled = sending;
    el.typingIndicator.classList.toggle('hidden', !sending);
}

function renderConversations() {
    const filtered = state.searchQuery
        ? state.conversations.filter((c) => c.titulo.toLowerCase().includes(state.searchQuery.toLowerCase()))
        : state.conversations;

    el.statsLabel.textContent = `${state.conversations.length} conversación${state.conversations.length !== 1 ? 'es' : ''}`;

    if (!filtered.length) {
        const msg = state.searchQuery ? 'No se encontraron conversaciones.' : 'No hay conversaciones todavía.';
        el.conversationList.innerHTML = `<p class="empty">${msg}</p>`;
        return;
    }

    el.conversationList.innerHTML = filtered
        .map((conv) => {
            const active = Number(conv.id) === Number(state.activeConversationId) ? 'active' : '';
            const lastMsg = conv.ultimo_mensaje ? conv.ultimo_mensaje.slice(0, 50) : 'Sin mensajes';
            const msgs = conv.total_mensajes || 0;
            const updated = conv.updated_at ? timeAgo(conv.updated_at) : '';

            return `
            <article class="conversation-item ${active}" data-id="${conv.id}">
                <h4>${escapeHtml(conv.titulo)}</h4>
                <p>${escapeHtml(lastMsg)}</p>
                <div class="conv-meta">
                    <small>${msgs} msg${msgs !== 1 ? 's' : ''}</small>
                    ${updated ? `<small>· ${updated}</small>` : ''}
                </div>
            </article>`;
        })
        .join('');
}

function renderMessages(messages = []) {
    if (!messages.length) {
        el.messages.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">💬</div>
                <h3>Conversación vacía</h3>
                <p>Envía un mensaje para comenzar</p>
            </div>`;
        return;
    }

    el.messages.innerHTML = messages
        .map((msg) => {
            const isAssistant = msg.role === 'assistant';
            const roleLabel = isAssistant ? 'ShenronIA' : 'Tú';
            const roleIcon = isAssistant
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 0 1 0 4h-.09c-.658.003-1.25.396-1.51 1z"/></svg>'
                : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

            const bodyContent = isAssistant ? renderMarkdown(msg.contenido) : `<span style="white-space:pre-wrap">${escapeHtml(msg.contenido)}</span>`;
            const timeStr = msg.created_at ? timeAgo(msg.created_at) : '';

            return `
            <article class="msg ${msg.role}">
                <div class="msg-header">${roleIcon} ${roleLabel}</div>
                <div class="msg-body">${bodyContent}</div>
                <div class="msg-footer">
                    <span>${timeStr}</span>
                    <div class="msg-actions">
                        <button class="msg-action-btn" title="Copiar" onclick="navigator.clipboard.writeText(${JSON.stringify(msg.contenido).replace(/'/g, '&#39;')}).then(()=>showToast('Copiado'))">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </div>
                </div>
            </article>`;
        })
        .join('');

    requestAnimationFrame(() => {
        el.messages.scrollTop = el.messages.scrollHeight;
    });
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  DATA LOADING                                                   ║
   ╚══════════════════════════════════════════════════════════════════╝ */

async function loadSession() {
    try {
        const data = await api('api/auth.php?action=me');
        if (data.authenticated) {
            state.user = data.user;
            renderAuth();
            await Promise.all([loadModels(), loadConversations()]);
        }
    } catch (err) {
        // Not logged in – show auth view
    }
}

async function loadModels() {
    try {
        const data = await api('api/models.php');
        state.models = Array.isArray(data.models) ? data.models : [];
        state.selectedModel = data.selected_model || data.default_model || state.models[0] || 'fallback-local';
    } catch {
        state.models = [];
        state.selectedModel = 'fallback-local';
    }
    renderModelPicker();
}

async function loadConversations() {
    try {
        const data = await api('api/conversations.php');
        state.conversations = data.conversations || [];
    } catch {
        state.conversations = [];
    }

    if (state.conversations.length && !state.activeConversationId) {
        state.activeConversationId = Number(state.conversations[0].id);
    }

    if (state.activeConversationId) {
        const exists = state.conversations.some((c) => Number(c.id) === Number(state.activeConversationId));
        if (!exists) {
            state.activeConversationId = state.conversations.length ? Number(state.conversations[0].id) : null;
        }
    }

    renderConversations();

    if (state.activeConversationId) {
        await loadMessages(state.activeConversationId);
    } else {
        renderMessages([]);
        el.conversationTitle.textContent = 'Selecciona una conversación';
        el.conversationHint.textContent = 'Crea una conversación para empezar.';
    }
}

async function loadMessages(conversationId) {
    try {
        const data = await api(`api/messages.php?conversation_id=${conversationId}`);
        state.activeConversationId = Number(conversationId);
        el.conversationTitle.textContent = data.conversation.titulo;
        el.conversationHint.textContent = 'Experto en Dragon Ball Z activo - Pregunta lo que quieras sobre el universo DB.';
        renderConversations();
        renderMessages(data.messages || []);
    } catch (err) {
        showToast(err.message);
    }
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  EXPORT                                                         ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function exportConversation() {
    if (!state.activeConversationId) {
        showToast('Selecciona una conversación primero');
        return;
    }

    const msgs = el.messages.querySelectorAll('.msg');
    if (!msgs.length) {
        showToast('No hay mensajes que exportar');
        return;
    }

    const title = el.conversationTitle.textContent || 'conversacion';
    let text = `# ${title}\n# Exportado desde Oráculo Namek\n# Fecha: ${new Date().toLocaleString('es-ES')}\n\n`;

    msgs.forEach((msg) => {
        const role = msg.classList.contains('user') ? 'Tú' : 'ShenronIA';
        const body = msg.querySelector('.msg-body')?.textContent?.trim() || '';
        text += `[${role}]\n${body}\n\n---\n\n`;
    });

    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${title.replace(/[^a-zA-Z0-9áéíóúñ ]/gi, '_')}_${Date.now()}.txt`;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Conversación exportada');
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  EVENT LISTENERS                                                ║
   ╚══════════════════════════════════════════════════════════════════╝ */

// Auth tabs
el.showLogin.addEventListener('click', () => switchAuthTab('login'));
el.showRegister.addEventListener('click', () => switchAuthTab('register'));

// Theme
el.themeToggle.addEventListener('click', toggleTheme);

// Conversation search
el.searchConversations.addEventListener('input', (e) => {
    state.searchQuery = e.target.value.trim();
    renderConversations();
});

// Model change
el.modelSelect.addEventListener('change', async () => {
    const prev = state.selectedModel;
    state.selectedModel = el.modelSelect.value;
    try {
        const data = await api('api/models.php', {
            method: 'POST',
            body: JSON.stringify({ model: state.selectedModel }),
        });
        state.selectedModel = data.selected_model || state.selectedModel;
        renderModelPicker();
        showToast(`Modelo: ${state.selectedModel}`);
    } catch (err) {
        state.selectedModel = prev;
        renderModelPicker();
        showToast(err.message);
    }
});

// Textarea auto-resize + char counter
el.messageInput.addEventListener('input', () => {
    autoResizeTextarea(el.messageInput);
    const len = el.messageInput.value.length;
    el.charCount.textContent = `${len} / ${el.messageInput.maxLength}`;
});

// Enter to send
el.messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        el.messageForm.requestSubmit();
    }
});

// Login
el.loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const emailInput = document.getElementById('loginEmail');
    const passInput  = document.getElementById('loginPassword');
    if (!emailInput.value.trim() || !passInput.value) {
        showToast('Introduce email y contraseña', 4000);
        return;
    }
    try {
        const data = await api('api/auth.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'login',
                email: emailInput.value,
                password: passInput.value,
            }),
        });
        state.user = data.user;
        renderAuth();
        await Promise.all([loadModels(), loadConversations()]);
        showToast('Sesión iniciada');
    } catch (err) {
        showToast('⚠️ ' + (err.message || 'Email o contraseña incorrectos'), 5000);
    }
});

// Register
el.registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('registerUsername').value.trim();
    const email    = document.getElementById('registerEmail').value.trim();
    const password = document.getElementById('registerPassword').value;
    if (!username || !email || !password) {
        showToast('Todos los campos son obligatorios', 4000);
        return;
    }
    if (password.length < 6) {
        showToast('La contraseña debe tener al menos 6 caracteres', 4000);
        return;
    }
    try {
        const data = await api('api/auth.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'register', username, email, password }),
        });
        state.user = data.user;
        renderAuth();
        await Promise.all([loadModels(), loadConversations()]);
        showToast('Cuenta creada correctamente');
    } catch (err) {
        showToast('⚠️ ' + (err.message || 'No se pudo crear la cuenta'), 5000);
    }
});

// Logout
el.logoutBtn.addEventListener('click', async () => {
    try {
        await api('api/auth.php', { method: 'POST', body: JSON.stringify({ action: 'logout' }) });
        Object.assign(state, { user: null, conversations: [], activeConversationId: null, models: [], selectedModel: '', searchQuery: '' });
        renderModelPicker();
        renderAuth();
        renderConversations();
        renderMessages([]);
        el.searchConversations.value = '';
        showToast('Sesión cerrada');
    } catch (err) {
        showToast(err.message);
    }
});

// New conversation
el.newConversationBtn.addEventListener('click', async () => {
    const title = prompt('Título de la conversación:', 'Dragon Ball - Consulta sobre personajes o sagas');
    if (title === null) return;

    try {
        const data = await api('api/conversations.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'create', title }),
        });
        state.activeConversationId = Number(data.conversation.id);
        await loadConversations();
        showToast('Conversación creada');
    } catch (err) {
        showToast(err.message);
    }
});

// Click conversation
el.conversationList.addEventListener('click', async (e) => {
    const card = e.target.closest('.conversation-item');
    if (!card) return;
    const id = Number(card.dataset.id);
    if (!id) return;

    try {
        await loadMessages(id);
    } catch (err) {
        showToast(err.message);
    }
});

// Rename
el.renameConversationBtn.addEventListener('click', async () => {
    if (!state.activeConversationId) { showToast('Selecciona una conversación'); return; }

    const current = state.conversations.find((c) => Number(c.id) === Number(state.activeConversationId));
    const title = prompt('Nuevo título:', current?.titulo || '');
    if (title === null) return;

    try {
        await api('api/conversations.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'rename', conversation_id: state.activeConversationId, title }),
        });
        await loadConversations();
        showToast('Renombrada');
    } catch (err) {
        showToast(err.message);
    }
});

// Delete
el.deleteConversationBtn.addEventListener('click', async () => {
    if (!state.activeConversationId) { showToast('Selecciona una conversación'); return; }
    if (!confirm('¿Eliminar esta conversación con todos sus mensajes?')) return;

    try {
        await api('api/conversations.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete', conversation_id: state.activeConversationId }),
        });
        state.activeConversationId = null;
        await loadConversations();
        showToast('Eliminada');
    } catch (err) {
        showToast(err.message);
    }
});

// Export
el.exportConversationBtn.addEventListener('click', exportConversation);

// Send message
el.messageForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (state.sending) return;
    if (!state.activeConversationId) { showToast('Crea o selecciona una conversación'); return; }

    const message = el.messageInput.value.trim();
    if (!message) { showToast('Escribe un mensaje'); return; }

    try {
        setSendingState(true);
        const data = await api('api/messages.php', {
            method: 'POST',
            body: JSON.stringify({
                conversation_id: state.activeConversationId,
                message,
                model: state.selectedModel,
            }),
        });

        if (data.model) {
            state.selectedModel = data.model;
            renderModelPicker();
        }

        el.messageInput.value = '';
        el.messageInput.style.height = 'auto';
        el.charCount.textContent = `0 / ${el.messageInput.maxLength}`;

        await loadMessages(state.activeConversationId);
        await loadConversations();
    } catch (err) {
        showToast(err.message);
    } finally {
        setSendingState(false);
        el.messageInput.focus();
    }
});

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  INIT                                                           ║
   ╚══════════════════════════════════════════════════════════════════╝ */
renderAuth();
switchAuthTab('login');
setSendingState(false);
loadSession().catch(() => {});
