<?php
$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$appName = htmlspecialchars($config['app_name'] ?? 'Конвертер файлов', ENT_QUOTES, 'UTF-8');
$appHost = htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Universal Converter', ENT_QUOTES, 'UTF-8');

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= $appName ?> &mdash; <?= $appHost ?></title>
  <meta name="description" content="Приватный автономный конвертер и оптимизатор изображений и PDF. Локальная обработка без внешних API, автоудаление через 5 минут.">
  <link rel="stylesheet" href="assets/style.css?v=9">
</head>
<body>
  <header>
    <div class="container header-content">
      <div class="logo-group">
        <div class="logo-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="16 3 21 3 21 8"></polyline>
            <line x1="4" y1="20" x2="21" y2="3"></line>
            <polyline points="21 16 21 21 16 21"></polyline>
            <line x1="15" y1="15" x2="21" y2="21"></line>
            <line x1="4" y1="4" x2="9" y2="9"></line>
          </svg>
        </div>
        <span class="site-title"><?= $appName ?></span>
      </div>
      <div class="privacy-tag">
        <span class="privacy-dot"></span>
        <span>100% Приватно &bull; TTL 5 мин</span>
      </div>
    </div>
  </header>

  <main>
    <div class="container">

      <!-- Single Unified Workspace Card -->
      <div class="workspace-card">

        <!-- Segmented Tab Navigation -->
        <nav class="segmented-control" id="navTabs" role="tablist">
          <button type="button" class="seg-btn active" data-tab="all">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
              <polyline points="2 17 12 22 22 17"></polyline>
              <polyline points="2 12 12 17 22 12"></polyline>
            </svg>
            <span>Все файлы</span>
            <span class="seg-badge" id="tabBadgeAll" style="display: none;">0</span>
          </button>
          <button type="button" class="seg-btn" data-tab="images">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
              <circle cx="8.5" cy="8.5" r="1.5"></circle>
              <polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <span>Картинки</span>
            <span class="seg-badge" id="tabBadgeImages" style="display: none;">0</span>
          </button>
          <button type="button" class="seg-btn" data-tab="pdf">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <span>PDF</span>
            <span class="seg-badge" id="tabBadgePdf" style="display: none;">0</span>
          </button>
          <button type="button" class="seg-btn" data-tab="optimize">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 14h6m0 0v6m0-6L3 21"></path>
              <path d="M20 10h-6m0 0V4m0 6l7-7"></path>
            </svg>
            <span>Сжатие</span>
            <span class="seg-badge" id="tabBadgeOpt" style="display: none;">0</span>
          </button>
        </nav>

        <!-- Mode Context Header -->
        <div class="mode-header" id="modeHeader">
          <div class="mode-title-row">
            <div class="mode-title" id="modeTitle">
              <!-- Dynamically populated with SVG icon & title -->
            </div>
            <div class="mode-chips" id="modeChips">
              <!-- Dynamically populated quick preset chips -->
            </div>
          </div>
          <div class="mode-desc" id="modeDesc">
            <!-- Dynamically populated description -->
          </div>
        </div>

        <!-- Hidden input for file picking -->
        <input type="file" id="fileInput" class="file-input-hidden" multiple accept="image/png,image/jpeg,image/webp,application/pdf" style="display: none !important;">

        <!-- 1. EMPTY STATE VIEW (Shown when 0 files uploaded) -->
        <div id="emptyStateView">
          <div id="dropzone" class="dropzone" onclick="document.getElementById('fileInput').click()">
            <div class="dropzone-icon-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
            </div>
            <div id="dropzoneTitle" class="dropzone-title">Перетащите сюда файлы (можно сразу группой)</div>
            <div id="dropzoneSub" class="dropzone-sub">Поддерживаются PNG, JPG, WebP, PDF &bull; до 2 GB &bull; без ограничений</div>
            <button type="button" class="btn btn-secondary btn-sm dropzone-btn" onclick="document.getElementById('fileInput').click()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14"></path>
              </svg>
              Выбрать файлы с устройства
            </button>
          </div>
        </div>

        <!-- 2. ACTIVE WORKSPACE VIEW (Shown when files are uploaded) -->
        <div id="activeWorkspaceView" class="active-workspace" style="display: none;">

          <!-- Session Status & Expiry Bar -->
          <div class="session-status-bar">
            <div class="session-summary">
              <span class="summary-count" id="summaryCount">1 файл</span>
              <span class="summary-size" id="summarySize">&bull; 1.3 MB</span>
              <span class="filter-indicator" id="filterIndicator" style="display: none;"></span>
            </div>
            <div class="session-right">
              <div class="timer-chip" title="Файлы будут безвозвратно удалены по истечении таймера">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Удаление через:</span>
                <span class="timer-val" id="countdownDisplay">05:00</span>
              </div>
              <button type="button" id="btnDeleteSession" class="btn-clear-session" title="Удалить все файлы немедленно">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span>Очистить</span>
              </button>
            </div>
          </div>

          <!-- Main Interactive Control Center (Global Format Selector + Big Action Button) -->
          <div class="control-center-card" id="controlCenterCard">
            <div class="control-center-top">
              <div class="select-field-group">
                <label for="globalFormatSelect" class="control-label">Действие для всех файлов:</label>
                <div class="custom-select-box">
                  <select id="globalFormatSelect" class="main-select">
                    <!-- Populated dynamically based on active tab & files -->
                  </select>
                </div>
              </div>
              <button type="button" id="btnMainAction" class="btn btn-primary btn-main-action">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                <span id="btnMainActionText">Сконвертировать всё в WebP</span>
              </button>
            </div>

            <!-- Quick Chips Selection -->
            <div class="control-chips-bar" id="controlChipsBar">
              <!-- Dynamically populated quick preset chips -->
            </div>
          </div>

          <!-- Notice when tab filters out some files -->
          <div id="tabNotice" class="tab-notice" style="display: none;"></div>

          <!-- Files Table / List -->
          <div class="files-table" id="fileList"></div>

          <!-- Compact Add More Files Strip -->
          <div class="add-more-strip" id="addMoreStrip" onclick="document.getElementById('fileInput').click()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5v14M5 12h14"></path>
            </svg>
            <span>Добавить еще файлы в эту сессию (или перетащите их сюда)</span>
          </div>

          <!-- Progress Bar -->
          <div id="progressBarContainer" class="progress-bar-container">
            <div id="progressBar" class="progress-bar"></div>
          </div>

          <!-- Results Section (Inside Card) -->
          <div id="resultsSection" class="results-box" style="display: none;">
            <div class="results-header">
              <div class="results-heading">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>Готово к скачиванию</span>
              </div>
              <a id="btnDownloadAll" href="#" class="btn btn-primary btn-sm" style="display: none;" download>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Скачать всё архивом (.ZIP)</span>
              </a>
            </div>
            <div class="results-list" id="resultsList"></div>
            <div class="results-footer">
              <button type="button" class="btn btn-secondary btn-sm" id="btnResetFiles">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                  <path d="M21 3v5h-5"></path>
                  <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                  <path d="M3 21v-5h5"></path>
                </svg>
                <span>Конвертировать другие файлы</span>
              </button>
            </div>
          </div>

        </div>

      </div>

      <!-- Security / Privacy Footnote & Author Attribution -->
      <footer class="footer-note">
        <p>🔒 Полная конфиденциальность: файлы обрабатываются изолированно и не передаются третьим сервисам.<br>Все данные автоматически и безвозвратно удаляются через 5 минут.</p>
        <div class="footer-meta">
          <span>Автор: <a href="https://github.com/Anton-Barinov" target="_blank" rel="noopener noreferrer">Anton Barinov</a></span>
          <span class="meta-sep">&bull;</span>
          <a href="https://github.com/Anton-Barinov/pdf-png-jpg-webp-converter" target="_blank" rel="noopener noreferrer">Исходный код на GitHub</a>
        </div>
      </footer>

    </div>
  </main>

  <!-- Notification Toast -->
  <div id="statusToast" class="status-toast"></div>

  <script src="assets/app.js?v=9"></script>
</body>
</html>
