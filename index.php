<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
?>
<!doctype html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(APP_NAME) ?> — Chat especializado en Dragon Ball con IA, tu experto en el universo de Goku.">
    <title><?= htmlspecialchars(APP_NAME) ?> · Experto Dragon Ball con IA</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐉</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
</head>

<body>
    <main class="app-shell">
        <!-- ═══════════════ AUTH VIEW ═══════════════ -->
        <section id="authView" class="panel auth-panel">
            <header class="auth-hero">
                <div class="auth-logo">🐉</div>
                <h1><?= htmlspecialchars(APP_NAME) ?></h1>
                <p class="auth-subtitle">Tu experto en Dragon Ball Z con Inteligencia Artificial</p>
                <div class="auth-badges">
                    <span class="badge">🥋 DBZ</span>
                    <span class="badge">⚡ DBS</span>
                    <span class="badge">🌟 DBGT</span>
                    <span class="badge">🔮 DB</span>
                </div>
            </header>

            <div class="auth-tabs">
                <button id="showLogin" class="tab-btn active" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Iniciar sesión
                </button>
                <button id="showRegister" class="tab-btn" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="8.5" cy="7" r="4" />
                        <line x1="20" y1="8" x2="20" y2="14" />
                        <line x1="23" y1="11" x2="17" y2="11" />
                    </svg>
                    Crear cuenta
                </button>
            </div>

            <form id="loginForm" class="auth-form">
                <label>
                    <span class="label-text">Email</span>
                    <input type="email" id="loginEmail" required placeholder="saiyajin@dragonball.com" autocomplete="email">
                </label>
                <label>
                    <span class="label-text">Contraseña</span>
                    <input type="password" id="loginPassword" required minlength="6" placeholder="••••••" autocomplete="current-password">
                </label>
                <button class="btn-primary btn-lg" type="submit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Entrar
                </button>
            </form>

            <form id="registerForm" class="auth-form hidden">
                <label>
                    <span class="label-text">Nombre de usuario</span>
                    <input type="text" id="registerUsername" required maxlength="50" placeholder="goku_fan" autocomplete="username">
                </label>
                <label>
                    <span class="label-text">Email</span>
                    <input type="email" id="registerEmail" required placeholder="fan@dragonball.com" autocomplete="email">
                </label>
                <label>
                    <span class="label-text">Contraseña</span>
                    <input type="password" id="registerPassword" required minlength="6" placeholder="••••••" autocomplete="new-password">
                </label>
                <button class="btn-primary btn-lg" type="submit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="8.5" cy="7" r="4" />
                        <line x1="20" y1="8" x2="20" y2="14" />
                        <line x1="23" y1="11" x2="17" y2="11" />
                    </svg>
                    Crear cuenta
                </button>
            </form>

            <footer class="auth-footer">
                <small><?= htmlspecialchars(APP_NAME) ?> v<?= APP_VERSION ?> · Experto en Dragon Ball</small>
            </footer>
        </section>

        <!-- ═══════════════ CHAT VIEW ═══════════════ -->
        <section id="chatView" class="chat-layout hidden">
            <div class="chat-layout-inner">
                <section class="chat-main panel">
                <header class="chat-header">
                    <div class="chat-header-info">
                        <h2 id="conversationTitle">Selecciona una conversación</h2>
                        <p id="conversationHint">Crea una conversación para empezar a preguntar sobre Dragon Ball.</p>
                    </div>
                    <div class="chat-actions">
                        <label class="model-picker" for="modelSelect">
                            <span class="model-label">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3" />
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                                </svg>
                                Modelo
                            </span>
                            <select id="modelSelect"></select>
                        </label>
                        <button id="exportConversationBtn" class="btn-icon" type="button" title="Exportar conversación" aria-label="Exportar conversación">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="7 10 12 15 17 10" />
                                <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                        </button>
                        <button id="renameConversationBtn" class="btn-icon" type="button" title="Renombrar" aria-label="Renombrar conversación">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                        </button>
                        <button id="deleteConversationBtn" class="btn-icon btn-icon-danger" type="button" title="Eliminar" aria-label="Eliminar conversación">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                            </svg>
                        </button>
                    </div>
                </header>

                <div id="messages" class="messages">
                    <div class="empty-state">
                        <div class="empty-state-icon">💬</div>
                        <h3>Bienvenido a <?= htmlspecialchars(APP_NAME) ?></h3>
                        <p>Selecciona o crea una conversación para comenzar</p>
                    </div>
                </div>

                <div id="typingIndicator" class="typing-indicator hidden">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <span><?= htmlspecialchars(ASSISTANT_NAME) ?> está pensando…</span>
                </div>

                <form id="messageForm" class="message-form">
                    <div class="message-input-wrapper">
                        <textarea id="messageInput" rows="1" maxlength="<?= MAX_MESSAGE_LENGTH ?>" placeholder="Escribe tu consulta…" aria-label="Mensaje"></textarea>
                        <div class="input-hints">
                            <small><kbd>Enter</kbd> enviar · <kbd>Shift+Enter</kbd> nueva línea</small>
                            <small id="charCount">0 / <?= MAX_MESSAGE_LENGTH ?></small>
                        </div>
                    </div>
                    <button id="sendBtn" class="btn-send" type="submit" aria-label="Enviar mensaje">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13" />
                            <polygon points="22 2 15 22 11 13 2 9 22 2" />
                        </svg>
                    </button>
                </form>
            </section>

            <aside class="sidebar panel">
                <div class="sidebar-title">
                    <h2><?= htmlspecialchars(APP_NAME) ?></h2>
                </div>
                
                <button id="newConversationBtn" class="btn-primary btn-new-conv" type="button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Nueva conversación
                </button>

                <div id="conversationList" class="conversation-list"></div>
                
                <div class="sidebar-header">
                    <div class="user-info">
                        <div class="user-avatar" id="userAvatar">U</div>
                        <div>
                            <strong id="userBadge">Usuario</strong>
                            <small id="userEmail">email@dominio.com</small>
                        </div>
                    </div>
                    <div class="sidebar-actions">
                        <button id="themeToggle" class="btn-icon" type="button" title="Cambiar tema" aria-label="Cambiar tema">
                            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5" />
                                <line x1="12" y1="1" x2="12" y2="3" />
                                <line x1="12" y1="21" x2="12" y2="23" />
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                <line x1="1" y1="12" x2="3" y2="12" />
                                <line x1="21" y1="12" x2="23" y2="12" />
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                            </svg>
                            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                            </svg>
                        </button>
                        <button id="logoutBtn" class="btn-icon btn-icon-danger" type="button" title="Cerrar sesión" aria-label="Cerrar sesión">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                <polyline points="16 17 21 12 16 7" />
                                <line x1="21" y1="12" x2="9" y2="12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="sidebar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" id="searchConversations" placeholder="Buscar conversaciones…" autocomplete="off">
                </div>

                <div id="conversationList" class="conversation-list"></div>

                <div class="sidebar-footer">
                    <small class="stats-label" id="statsLabel">0 conversaciones</small>
                </div>
            </aside>
            </div>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>

    <script src="assets/app.js"></script>
</body>

</html>