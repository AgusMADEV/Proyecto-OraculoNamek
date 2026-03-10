<?php

declare(strict_types=1);

/* ───────────────────────────── Base de datos ───────────────────────────── */
const DB_HOST   = 'localhost';
const DB_PORT   = '3306';
const DB_SOCKET = '/ruta/al/mysql.sock';  // Ajusta según tu servidor (XAMPP, MAMP, etc.)
const DB_NAME   = 'nombre_base_datos';
const DB_USER   = 'usuario_db';
const DB_PASS   = 'contraseña_db';

/* ───────────────────────────── Aplicación ──────────────────────────────── */
const APP_NAME              = 'Nombre de tu Aplicación';
const APP_VERSION           = '1.0.0';
const ASSISTANT_NAME        = 'Nombre del Asistente';
const DEFAULT_SYSTEM_PROMPT = 'Tu prompt del sistema personalizado aquí. Define el comportamiento y personalidad del asistente.';

/* ───────────────────────────── Ollama (IA local) ──────────────────────── */
const OLLAMA_ENABLED         = true;
const OLLAMA_BASE_URL        = 'http://127.0.0.1:11434';
const OLLAMA_MODEL           = 'nombre-modelo:tag';  // Ej: llama2, mistral, qwen2.5, etc.
const OLLAMA_TIMEOUT_SECONDS = 90;

/* ───────────────────────────── OpenAI (fallback) ──────────────────────── */
const OPENAI_MODEL          = 'gpt-4o-mini';  // O 'gpt-3.5-turbo', 'gpt-4', etc.
const OPENAI_TIMEOUT        = 30;

/* ───────────────────────────── Rate limiting ──────────────────────────── */
const RATE_LIMIT_MESSAGES_PER_MINUTE = 12;
const RATE_LIMIT_WINDOW_SECONDS      = 60;

/* ───────────────────────────── Límites ─────────────────────────────────── */
const MAX_MESSAGE_LENGTH     = 5000;
const MAX_HISTORY_MESSAGES   = 30;
const MAX_CONVERSATION_TITLE = 120;
