/**
 * Universal Converter Client Application
 * Fully interactive, instant reactivity, modern responsive design.
 */
(() => {
  'use strict';

  // SVG Icons
  const ICONS = {
    layers: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>`,
    image: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>`,
    pdf: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>`,
    compress: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6m0 0v6m0-6L3 21"></path><path d="M20 10h-6m0 0V4m0 6l7-7"></path></svg>`,
    download: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>`,
    trash: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>`,
    play: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>`,
    arrowUp: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>`,
    arrowDown: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>`
  };

  // State
  let sessionId = null;
  let files = [];
  let currentTab = 'all'; // 'all', 'images', 'pdf', 'optimize'
  let globalFormat = 'webp'; // current selected global action
  let timerInterval = null;
  let secondsRemaining = 0;

  // Preset definitions
  const TAB_CONFIGS = {
    all: {
      icon: ICONS.layers,
      title: 'Все операции',
      desc: 'Универсальный режим: загрузите любые файлы — выберите единый формат или настройте каждый файл отдельно.',
      dropTitle: 'Перетащите сюда файлы (любая группа)',
      dropSub: 'PNG, JPG, WebP, PDF &bull; до 2 GB &bull; без сторонних сервисов',
      accept: 'image/png,image/jpeg,image/webp,application/pdf',
      presets: [
        { label: 'Все в WebP', format: 'webp' },
        { label: 'Все в JPG', format: 'jpg' },
        { label: 'Все в PNG', format: 'png' },
        { label: 'Сжать без потерь', format: 'opt' },
        { label: 'Фото в PDF', format: 'images_to_pdf' },
        { label: 'Склейка PDF', format: 'merge_pdf' }
      ]
    },
    images: {
      icon: ICONS.image,
      title: 'Конвертация картинок',
      desc: 'Быстрая конвертация между PNG, JPG, JPEG и легким WebP, либо сборка группы фото в один PDF документ.',
      dropTitle: 'Перетащите изображения (PNG, JPG, WebP)',
      dropSub: 'Поддерживается групповая конвертация и фото в единый PDF',
      accept: 'image/png,image/jpeg,image/webp',
      presets: [
        { label: 'В WebP (легкий)', format: 'webp' },
        { label: 'В JPG (фото)', format: 'jpg' },
        { label: 'В PNG (высокое)', format: 'png' },
        { label: 'Собрать в PDF', format: 'images_to_pdf' },
        { label: 'Сжатие фото', format: 'opt' }
      ]
    },
    pdf: {
      icon: ICONS.pdf,
      title: 'PDF инструменты',
      desc: 'Объединение нескольких PDF в один, постраничное разделение, экспорт PDF в картинки и сборка фото в PDF.',
      dropTitle: 'Перетащите PDF документы или изображения',
      dropSub: 'Склейка документов, разбивка по страницам, экспорт в PNG/JPG',
      accept: 'application/pdf,image/png,image/jpeg,image/webp',
      presets: [
        { label: 'Склеить PDF в один', format: 'merge_pdf' },
        { label: 'Разбить PDF на страницы', format: 'split_pdf' },
        { label: 'PDF в картинки PNG', format: 'pdf_to_images' },
        { label: 'Сжать размер PDF', format: 'optimize_pdf' },
        { label: 'Фото в PDF', format: 'images_to_pdf' }
      ]
    },
    optimize: {
      icon: ICONS.compress,
      title: 'Сжатие и оптимизация',
      desc: 'Lossless оптимизация фото без потери качества (удаление метаданных) и сжатие объема тяжелых PDF документов.',
      dropTitle: 'Перетащите фото или PDF для оптимизации',
      dropSub: 'Уменьшение размера файлов без потери четкости и читаемости',
      accept: 'image/png,image/jpeg,image/webp,application/pdf',
      presets: [
        { label: 'Сжать все фото (Lossless)', format: 'opt' },
        { label: 'Сжать PDF (eBook 150 DPI)', format: 'optimize_pdf' },
        { label: 'В суперлегкий WebP', format: 'webp' }
      ]
    }
  };

  // DOM Elements
  const navTabs = document.getElementById('navTabs');
  const modeTitle = document.getElementById('modeTitle');
  const modeDesc = document.getElementById('modeDesc');
  const modeChips = document.getElementById('modeChips');
  const fileInput = document.getElementById('fileInput');

  // Views
  const emptyStateView = document.getElementById('emptyStateView');
  const activeWorkspaceView = document.getElementById('activeWorkspaceView');

  // Dropzone
  const dropzone = document.getElementById('dropzone');
  const dropzoneTitle = document.getElementById('dropzoneTitle');
  const dropzoneSub = document.getElementById('dropzoneSub');

  // Active workspace elements
  const summaryCount = document.getElementById('summaryCount');
  const summarySize = document.getElementById('summarySize');
  const filterIndicator = document.getElementById('filterIndicator');
  const countdownDisplay = document.getElementById('countdownDisplay');
  const btnDeleteSession = document.getElementById('btnDeleteSession');

  // Interactive Control Center
  const controlCenterCard = document.getElementById('controlCenterCard');
  const globalFormatSelect = document.getElementById('globalFormatSelect');
  const btnMainAction = document.getElementById('btnMainAction');
  const btnMainActionText = document.getElementById('btnMainActionText');
  const controlChipsBar = document.getElementById('controlChipsBar');
  const tabNotice = document.getElementById('tabNotice');

  // File list & actions
  const fileList = document.getElementById('fileList');
  const addMoreStrip = document.getElementById('addMoreStrip');
  const progressBarContainer = document.getElementById('progressBarContainer');
  const progressBar = document.getElementById('progressBar');
  const resultsSection = document.getElementById('resultsSection');
  const resultsList = document.getElementById('resultsList');
  const btnDownloadAll = document.getElementById('btnDownloadAll');
  const btnResetFiles = document.getElementById('btnResetFiles');
  const statusToast = document.getElementById('statusToast');

  // Badges
  const tabBadgeAll = document.getElementById('tabBadgeAll');
  const tabBadgeImages = document.getElementById('tabBadgeImages');
  const tabBadgePdf = document.getElementById('tabBadgePdf');
  const tabBadgeOpt = document.getElementById('tabBadgeOpt');

  // Init
  initEvents();
  applyTab(currentTab);

  function initEvents() {
    // Tab switching
    navTabs.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-tab]');
      if (!btn) return;
      const tabKey = btn.dataset.tab;
      if (tabKey && TAB_CONFIGS[tabKey]) {
        applyTab(tabKey);
      }
    });

    // File input change
    fileInput.addEventListener('change', () => {
      if (fileInput.files && fileInput.files.length > 0) {
        handleFiles(fileInput.files);
        fileInput.value = '';
      }
    });

    // Drag & Drop
    setupDragDrop(dropzone);
    setupDragDrop(addMoreStrip);

    // Global dragover indicator on window
    ['dragenter', 'dragover'].forEach(name => {
      window.addEventListener(name, (e) => {
        e.preventDefault();
      }, false);
    });

    // Global Format Select Change Listener
    globalFormatSelect.addEventListener('change', () => {
      setGlobalFormat(globalFormatSelect.value, true);
    });

    // Global Action Button Click
    btnMainAction.addEventListener('click', () => {
      executeGlobalAction();
    });

    // Delete Session Button
    btnDeleteSession.addEventListener('click', deleteSessionNow);

    // Reset Files Button
    btnResetFiles.addEventListener('click', resetApplicationState);
  }

  function setupDragDrop(el) {
    if (!el) return;
    ['dragenter', 'dragover'].forEach(eventName => {
      el.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        el.classList.add('dragover');
      }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      el.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        el.classList.remove('dragover');
      }, false);
    });

    el.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length > 0) {
        handleFiles(dt.files);
      }
    });
  }

  /**
   * Apply Tab View & Filter
   */
  function applyTab(tabKey) {
    currentTab = tabKey;
    const config = TAB_CONFIGS[tabKey];

    // Update active tab buttons
    navTabs.querySelectorAll('.seg-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabKey);
    });

    // Update Header
    modeTitle.innerHTML = `${config.icon} <span>${config.title}</span>`;
    modeDesc.innerHTML = config.desc;

    // Render mode chips in header
    renderModeChips(config.presets);

    // Update Dropzone
    dropzoneTitle.textContent = config.dropTitle;
    dropzoneSub.innerHTML = config.dropSub;
    fileInput.accept = config.accept;

    // Set intelligent default format for this tab based on actual files
    const hasImg = files.some(f => f.category === 'image');
    const hasPdf = files.some(f => f.category === 'pdf');

    if (tabKey === 'images') {
      if (!['webp', 'jpg', 'png', 'opt', 'images_to_pdf'].includes(globalFormat)) {
        globalFormat = 'webp';
      }
    } else if (tabKey === 'pdf') {
      const pdfCount = files.filter(f => f.category === 'pdf').length;
      globalFormat = pdfCount >= 2 ? 'merge_pdf' : 'optimize_pdf';
    } else if (tabKey === 'optimize') {
      if (hasPdf && !hasImg) {
        globalFormat = 'optimize_pdf';
      } else {
        globalFormat = 'opt';
      }
    } else if (tabKey === 'all') {
      if (hasPdf && !hasImg) {
        globalFormat = 'optimize_pdf';
      } else if (hasImg && !hasPdf) {
        globalFormat = 'webp';
      }
    }

    // Refresh active workspace if files are present
    if (files.length > 0) {
      renderWorkspace();
    }
  }

  /**
   * Render Header Mode Chips (for fast picking)
   */
  function renderModeChips(presets) {
    modeChips.innerHTML = '';
    presets.forEach(p => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'mode-preset-chip';
      chip.textContent = p.label;
      chip.addEventListener('click', () => {
        setGlobalFormat(p.format, true);
        if (files.length === 0) {
          fileInput.click();
        } else {
          showToast(`Выбран формат: ${p.label}`, 'info');
        }
      });
      modeChips.appendChild(chip);
    });
  }

  /**
   * Handle Multi-file Upload
   */
  async function handleFiles(fileListObj) {
    const formData = new FormData();
    if (sessionId) {
      formData.append('session_id', sessionId);
    }

    let addedCount = 0;
    for (let i = 0; i < fileListObj.length; i++) {
      formData.append('files[]', fileListObj[i]);
      addedCount++;
    }

    if (addedCount === 0) return;

    showProgress(25);
    showToast(`Загрузка ${addedCount} файл(ов)...`, 'info');

    try {
      const res = await fetch('api.php?action=upload', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      if (!data.ok) {
        showToast(data.error || 'Ошибка загрузки файлов', 'error');
        hideProgress();
        return;
      }

      sessionId = data.session_id;

      const hasImg = (data.files || []).some(f => f.category === 'image');
      const hasPdf = (data.files || []).some(f => f.category === 'pdf');

      // Intelligently align globalFormat to actual uploaded file types
      if (hasPdf && !hasImg) {
        if (!['optimize_pdf', 'split_pdf', 'pdf_to_images', 'merge_pdf'].includes(globalFormat)) {
          globalFormat = (currentTab === 'optimize') ? 'optimize_pdf' : ((data.files || []).length >= 2 ? 'merge_pdf' : 'split_pdf');
        }
      } else if (hasImg && !hasPdf) {
        if (!['webp', 'jpg', 'png', 'opt', 'images_to_pdf'].includes(globalFormat)) {
          globalFormat = (currentTab === 'optimize') ? 'opt' : 'webp';
        }
      }

      // Preserve existing per-file targetFormat selections if already set
      const existingMap = {};
      files.forEach(f => {
        if (f.targetFormat) existingMap[f.id] = f.targetFormat;
      });

      files = (data.files || []).map(f => {
        return {
          ...f,
          targetFormat: existingMap[f.id] || getDefaultTargetForFile(f, globalFormat)
        };
      });

      startTimer(data.ttl_remaining || 300);
      updateBadges();
      renderWorkspace();
      hideProgress();
      showToast(`Добавлено файлов: ${addedCount}`, 'success');
    } catch (err) {
      hideProgress();
      showToast('Сетевая ошибка при загрузке файлов', 'error');
    }
  }

  function getDefaultTargetForFile(file, desiredGlobal) {
    if (file.category === 'image') {
      if (['webp', 'jpg', 'png', 'opt'].includes(desiredGlobal)) {
        if (desiredGlobal === file.ext) {
          return file.ext === 'png' ? 'webp' : 'png';
        }
        return desiredGlobal;
      }
      return 'webp';
    }
    if (file.category === 'pdf') {
      if (['optimize_pdf', 'split_pdf', 'pdf_to_images', 'pdf_png', 'pdf_jpg'].includes(desiredGlobal)) {
        return desiredGlobal === 'optimize_pdf' ? 'pdf_opt' : (desiredGlobal === 'pdf_to_images' ? 'pdf_png' : desiredGlobal);
      }
      return 'pdf_opt';
    }
    return 'webp';
  }

  /**
   * Update Tab Badges
   */
  function updateBadges() {
    const imgCount = files.filter(f => f.category === 'image').length;
    const pdfCount = files.filter(f => f.category === 'pdf').length;
    const totalCount = files.length;

    updateSingleBadge(tabBadgeAll, totalCount);
    updateSingleBadge(tabBadgeImages, imgCount);
    updateSingleBadge(tabBadgePdf, pdfCount);
    updateSingleBadge(tabBadgeOpt, totalCount);
  }

  function updateSingleBadge(badgeEl, count) {
    if (!badgeEl) return;
    if (count > 0) {
      badgeEl.style.display = 'inline-block';
      badgeEl.textContent = count;
    } else {
      badgeEl.style.display = 'none';
    }
  }

  /**
   * Render Active Workspace with Tab Filtering
   */
  function renderWorkspace() {
    if (files.length === 0) {
      emptyStateView.style.display = 'block';
      activeWorkspaceView.style.display = 'none';
      return;
    }

    emptyStateView.style.display = 'none';
    activeWorkspaceView.style.display = 'flex';

    // Summary calculation
    const totalBytes = files.reduce((sum, f) => sum + (f.size || 0), 0);
    summaryCount.textContent = `${files.length} ${pluralizeFiles(files.length)}`;
    summarySize.textContent = `• ${formatBytes(totalBytes)}`;

    // Tab-based file filtering
    let visibleFiles = files;
    let otherCount = 0;
    let otherLabel = '';

    if (currentTab === 'images') {
      visibleFiles = files.filter(f => f.category === 'image');
      otherCount = files.length - visibleFiles.length;
      otherLabel = 'PDF документ(ов)';
    } else if (currentTab === 'pdf') {
      visibleFiles = files.filter(f => f.category === 'pdf');
      otherCount = files.length - visibleFiles.length;
      otherLabel = 'картинок';
    }

    // Filter indicator in status bar
    if (currentTab !== 'all' && files.length !== visibleFiles.length) {
      filterIndicator.style.display = 'inline-block';
      filterIndicator.textContent = `(показано: ${visibleFiles.length})`;
    } else {
      filterIndicator.style.display = 'none';
    }

    // Notice when files are hidden by current tab
    if (otherCount > 0) {
      tabNotice.style.display = 'block';
      tabNotice.innerHTML = `
        <span>В этой вкладке показано <strong>${visibleFiles.length}</strong>. Также в очереди есть <strong>${otherCount} ${otherLabel}</strong>.</span>
        <button type="button" class="btn-text-action" onclick="document.querySelector('[data-tab=\\'all\\']').click()">Показать все файлы</button>
      `;
    } else {
      tabNotice.style.display = 'none';
    }

    // Render Control Center (Select + Chips + CTA)
    renderControlCenter(visibleFiles);

    // Render File Rows
    renderFileRows(visibleFiles);
  }

  /**
   * Render Control Center (Dropdown, Chips, Big Button)
   */
  function renderControlCenter(visibleFiles) {
    const hasImages = visibleFiles.some(f => f.category === 'image');
    const hasPdf = visibleFiles.some(f => f.category === 'pdf');
    const imgCount = visibleFiles.filter(f => f.category === 'image').length;
    const pdfCount = visibleFiles.filter(f => f.category === 'pdf').length;

    // Validate globalFormat against what is actually present in visibleFiles
    if (hasPdf && !hasImages) {
      if (!['optimize_pdf', 'split_pdf', 'pdf_to_images', 'merge_pdf'].includes(globalFormat)) {
        globalFormat = (currentTab === 'optimize') ? 'optimize_pdf' : (pdfCount >= 2 ? 'merge_pdf' : 'split_pdf');
      }
    } else if (hasImages && !hasPdf) {
      if (!['webp', 'jpg', 'png', 'opt', 'images_to_pdf'].includes(globalFormat)) {
        globalFormat = (currentTab === 'optimize') ? 'opt' : 'webp';
      }
    }

    // 1. Build options for #globalFormatSelect strictly based on actual file types
    const optionsHtml = [];

    // Case 1: Tab is 'optimize'
    if (currentTab === 'optimize') {
      if (hasImages && hasPdf) {
        optionsHtml.push(`
          <optgroup label="Оптимизация картинок (${imgCount})">
            <option value="opt" ${globalFormat === 'opt' ? 'selected' : ''}>Сжать картинки без потерь (Lossless)</option>
            <option value="webp" ${globalFormat === 'webp' ? 'selected' : ''}>Конвертировать фото в легкий WebP</option>
          </optgroup>
          <optgroup label="Оптимизация PDF (${pdfCount})">
            <option value="optimize_pdf" ${globalFormat === 'optimize_pdf' ? 'selected' : ''}>Сжать размер PDF (eBook 150 DPI)</option>
          </optgroup>
        `);
      } else if (hasPdf) {
        optionsHtml.push(`
          <option value="optimize_pdf" ${globalFormat === 'optimize_pdf' ? 'selected' : ''}>Оптимизировать / сжать размер PDF (eBook 150 DPI)</option>
        `);
      } else if (hasImages) {
        optionsHtml.push(`
          <option value="opt" ${globalFormat === 'opt' ? 'selected' : ''}>Сжать картинки без потерь (Lossless)</option>
          <option value="webp" ${globalFormat === 'webp' ? 'selected' : ''}>Конвертировать фото в легкий WebP</option>
        `);
      }
    }
    // Case 2: Only PDF files visible (or 'pdf' tab)
    else if (currentTab === 'pdf' || (hasPdf && !hasImages)) {
      if (hasPdf) {
        if (pdfCount >= 2) {
          optionsHtml.push(`<option value="merge_pdf" ${globalFormat === 'merge_pdf' ? 'selected' : ''}>Объединить все PDF в один документ</option>`);
        }
        optionsHtml.push(`
          <option value="optimize_pdf" ${globalFormat === 'optimize_pdf' ? 'selected' : ''}>Оптимизировать / сжать размер PDF (eBook)</option>
          <option value="split_pdf" ${globalFormat === 'split_pdf' ? 'selected' : ''}>Разбить PDF на отдельные страницы (ZIP)</option>
          <option value="pdf_to_images" ${globalFormat === 'pdf_to_images' ? 'selected' : ''}>Экспортировать страницы PDF в картинки PNG</option>
        `);
      }
    }
    // Case 3: Only image files visible (or 'images' tab)
    else if (currentTab === 'images' || (hasImages && !hasPdf)) {
      if (hasImages) {
        optionsHtml.push(`
          <option value="webp" ${globalFormat === 'webp' ? 'selected' : ''}>Конвертировать в WebP (легкий формат)</option>
          <option value="jpg" ${globalFormat === 'jpg' ? 'selected' : ''}>Конвертировать в JPG (стандартное фото)</option>
          <option value="png" ${globalFormat === 'png' ? 'selected' : ''}>Конвертировать в PNG (без сжатия)</option>
          <option value="opt" ${globalFormat === 'opt' ? 'selected' : ''}>Сжать без потерь (Lossless, снятие метаданных)</option>
          ${imgCount >= 1 ? `<option value="images_to_pdf" ${globalFormat === 'images_to_pdf' ? 'selected' : ''}>Собрать все фото в единый PDF документ</option>` : ''}
        `);
      }
    }
    // Case 4: Tab is 'all' and BOTH images and PDFs are present
    else if (hasImages && hasPdf) {
      optionsHtml.push(`
        <optgroup label="Действия с картинками (${imgCount})">
          <option value="webp" ${globalFormat === 'webp' ? 'selected' : ''}>Все картинки в WebP</option>
          <option value="jpg" ${globalFormat === 'jpg' ? 'selected' : ''}>Все картинки в JPG</option>
          <option value="png" ${globalFormat === 'png' ? 'selected' : ''}>Все картинки в PNG</option>
          <option value="opt" ${globalFormat === 'opt' ? 'selected' : ''}>Сжать картинки без потерь</option>
          <option value="images_to_pdf" ${globalFormat === 'images_to_pdf' ? 'selected' : ''}>Собрать фото в единый PDF</option>
        </optgroup>
        <optgroup label="Действия с PDF документами (${pdfCount})">
          ${pdfCount >= 2 ? `<option value="merge_pdf" ${globalFormat === 'merge_pdf' ? 'selected' : ''}>Объединить все PDF в один</option>` : ''}
          <option value="optimize_pdf" ${globalFormat === 'optimize_pdf' ? 'selected' : ''}>Сжать размер PDF (eBook)</option>
          <option value="split_pdf" ${globalFormat === 'split_pdf' ? 'selected' : ''}>Разбить PDF на страницы</option>
          <option value="pdf_to_images" ${globalFormat === 'pdf_to_images' ? 'selected' : ''}>Экспорт PDF в картинки</option>
        </optgroup>
      `);
    }

    // If options empty (e.g. no matching files)
    if (optionsHtml.length === 0) {
      optionsHtml.push(`<option value="">Нет файлов для этого действия</option>`);
    }

    globalFormatSelect.innerHTML = optionsHtml.join('');

    // Ensure selected option matches globalFormat
    if (globalFormatSelect.value && globalFormatSelect.value !== globalFormat) {
      globalFormat = globalFormatSelect.value;
    }

    // 2. Build Quick Chips strictly matching present file types
    const chips = [];
    if (hasImages && !hasPdf) {
      chips.push({ label: 'В WebP', val: 'webp' });
      chips.push({ label: 'В JPG', val: 'jpg' });
      chips.push({ label: 'В PNG', val: 'png' });
      chips.push({ label: 'Сжать фото', val: 'opt' });
      if (imgCount >= 1) chips.push({ label: 'Фото в PDF', val: 'images_to_pdf' });
    } else if (hasPdf && !hasImages) {
      if (pdfCount >= 2) chips.push({ label: 'Склеить PDF', val: 'merge_pdf' });
      chips.push({ label: 'Сжать PDF', val: 'optimize_pdf' });
      chips.push({ label: 'Разбить PDF', val: 'split_pdf' });
      chips.push({ label: 'PDF в PNG', val: 'pdf_to_images' });
    } else if (hasImages && hasPdf) {
      chips.push({ label: 'Все в WebP', val: 'webp' });
      chips.push({ label: 'Все в JPG', val: 'jpg' });
      chips.push({ label: 'Сжать фото', val: 'opt' });
      if (pdfCount >= 2) chips.push({ label: 'Склеить PDF', val: 'merge_pdf' });
      chips.push({ label: 'Сжать PDF', val: 'optimize_pdf' });
      chips.push({ label: 'Разбить PDF', val: 'split_pdf' });
    }

    controlChipsBar.innerHTML = chips.map(c => {
      const activeCls = c.val === globalFormat ? 'active' : '';
      return `<button type="button" class="quick-chip ${activeCls}" data-val="${c.val}">${c.label}</button>`;
    }).join('');

    // Quick chip click listeners
    controlChipsBar.querySelectorAll('.quick-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        setGlobalFormat(btn.dataset.val, true);
      });
    });

    // 3. Update CTA Button Text
    updateMainActionText(visibleFiles);
  }

  /**
   * Set Global Format and propagate to all eligible rows
   */
  function setGlobalFormat(formatVal, updateRows = true) {
    if (!formatVal) return;
    globalFormat = formatVal;

    // Sync select dropdown
    if (globalFormatSelect.value !== formatVal) {
      globalFormatSelect.value = formatVal;
    }

    // Update active chips highlight
    controlChipsBar.querySelectorAll('.quick-chip').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.val === formatVal);
    });

    // Update all files target format if requested
    if (updateRows) {
      files.forEach(f => {
        f.targetFormat = getDefaultTargetForFile(f, formatVal);
      });
      renderFileRows(getVisibleFiles());
    }

    updateMainActionText(getVisibleFiles());
  }

  function getVisibleFiles() {
    if (currentTab === 'images') return files.filter(f => f.category === 'image');
    if (currentTab === 'pdf') return files.filter(f => f.category === 'pdf');
    return files;
  }

  /**
   * Update Big Action Button Text
   */
  function updateMainActionText(visibleFiles) {
    const count = visibleFiles.length;
    if (count === 0) {
      btnMainActionText.textContent = 'Нет файлов для обработки';
      btnMainAction.disabled = true;
      return;
    }

    btnMainAction.disabled = false;

    if (globalFormat === 'images_to_pdf') {
      btnMainActionText.textContent = `Собрать все фото в один PDF (${count})`;
    } else if (globalFormat === 'merge_pdf') {
      btnMainActionText.textContent = `Объединить все PDF в один (${count})`;
    } else if (globalFormat === 'split_pdf') {
      btnMainActionText.textContent = `Разбить PDF на страницы (${count})`;
    } else if (globalFormat === 'pdf_to_images') {
      btnMainActionText.textContent = `Экспортировать PDF в картинки (${count})`;
    } else if (globalFormat === 'optimize_pdf') {
      btnMainActionText.textContent = `Оптимизировать размер PDF (${count})`;
    } else if (globalFormat === 'opt') {
      btnMainActionText.textContent = `Сжать все файлы без потерь (${count})`;
    } else {
      btnMainActionText.textContent = `Сконвертировать всё в ${globalFormat.toUpperCase()} (${count})`;
    }
  }

  /**
   * Render File Rows
   */
  function renderFileRows(visibleFiles) {
    fileList.innerHTML = '';

    if (visibleFiles.length === 0) {
      fileList.innerHTML = `
        <div class="empty-tab-hint">
          <p>В этой категории пока нет загруженных файлов.</p>
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('fileInput').click()">
            Загрузить ${currentTab === 'images' ? 'картинки' : (currentTab === 'pdf' ? 'PDF' : 'файлы')}
          </button>
        </div>
      `;
      return;
    }

    visibleFiles.forEach((f, index) => {
      const row = document.createElement('div');
      row.className = 'file-row';
      row.dataset.fileId = f.id;

      const targetText = formatTargetLabel(f.targetFormat || 'webp');

      // Reorder buttons for PDFs when merging is possible
      let reorderHtml = '';
      if (f.category === 'pdf' && visibleFiles.length > 1) {
        reorderHtml = `
          <div class="row-order-tools">
            <button type="button" class="btn-order-move" data-move-up="${f.id}" title="Переместить выше" ${index === 0 ? 'disabled' : ''}>
              ${ICONS.arrowUp}
            </button>
            <button type="button" class="btn-order-move" data-move-down="${f.id}" title="Переместить ниже" ${index === visibleFiles.length - 1 ? 'disabled' : ''}>
              ${ICONS.arrowDown}
            </button>
          </div>
        `;
      }

      row.innerHTML = `
        <div class="file-left">
          ${reorderHtml}
          <div class="file-badge ${f.category}">${f.ext.toUpperCase()}</div>
          <div class="file-details">
            <div class="file-title" title="${escapeHtml(f.original_name)}">${escapeHtml(f.original_name)}</div>
            <div class="file-meta">
              <span>${formatBytes(f.size)}</span>
              <span class="file-target-indicator" id="ind_${f.id}">➔ ${targetText}</span>
            </div>
          </div>
        </div>
        <div class="file-controls">
          <div class="select-wrapper">
            <select class="row-target-select" data-file-id="${f.id}">
              ${renderRowSelectOptions(f)}
            </select>
          </div>
          <button type="button" class="btn btn-secondary btn-sm btn-single-convert" data-file-id="${f.id}" title="Конвертировать только этот файл">
            ${ICONS.play}
            <span class="btn-text-sm">Старт</span>
          </button>
          <button type="button" class="btn-remove-row" data-remove-id="${f.id}" title="Убрать из очереди">
            ${ICONS.trash}
          </button>
        </div>
      `;

      fileList.appendChild(row);
    });

    // 1. Row Select Change Listeners (Instant reactivity!)
    fileList.querySelectorAll('.row-target-select').forEach(sel => {
      sel.addEventListener('change', (e) => {
        const fileId = sel.dataset.fileId;
        const newTarget = sel.value;
        const fileObj = files.find(f => f.id === fileId);
        if (fileObj) {
          fileObj.targetFormat = newTarget;
        }

        // Update target badge
        const ind = document.getElementById(`ind_${fileId}`);
        if (ind) {
          ind.textContent = `➔ ${formatTargetLabel(newTarget)}`;
        }

        showToast(`Для файла выбран формат: ${formatTargetLabel(newTarget)}`, 'info');
      });
    });

    // 2. Single item convert button listeners
    fileList.querySelectorAll('.btn-single-convert').forEach(btn => {
      btn.addEventListener('click', () => {
        const fileId = btn.dataset.fileId;
        const fileObj = files.find(f => f.id === fileId);
        const target = fileObj ? fileObj.targetFormat : 'webp';
        executeSingleConvert(fileId, target);
      });
    });

    // 3. Remove single file
    fileList.querySelectorAll('[data-remove-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        const fileId = btn.dataset.removeId;
        files = files.filter(f => f.id !== fileId);
        updateBadges();
        renderWorkspace();
      });
    });

    // 4. Reorder listeners
    fileList.querySelectorAll('[data-move-up]').forEach(btn => {
      btn.addEventListener('click', () => {
        const fileId = btn.dataset.moveUp;
        moveFile(fileId, -1);
      });
    });

    fileList.querySelectorAll('[data-move-down]').forEach(btn => {
      btn.addEventListener('click', () => {
        const fileId = btn.dataset.moveDown;
        moveFile(fileId, 1);
      });
    });
  }

  function moveFile(fileId, direction) {
    const idx = files.findIndex(f => f.id === fileId);
    if (idx === -1) return;
    const targetIdx = idx + direction;
    if (targetIdx < 0 || targetIdx >= files.length) return;

    const temp = files[idx];
    files[idx] = files[targetIdx];
    files[targetIdx] = temp;

    renderWorkspace();
  }

  function renderRowSelectOptions(f) {
    const current = f.targetFormat || 'webp';

    if (f.category === 'image') {
      const opts = [
        { val: 'webp', label: 'в WebP' },
        { val: 'jpg', label: 'в JPG' },
        { val: 'png', label: 'в PNG' },
        { val: 'opt', label: 'Сжать без потерь' }
      ];

      return opts.map(o => `
        <option value="${o.val}" ${o.val === current ? 'selected' : ''}>${o.label}</option>
      `).join('');
    }

    if (f.category === 'pdf') {
      const opts = [
        { val: 'pdf_opt', label: 'Сжать размер PDF' },
        { val: 'pdf_split', label: 'Разбить на страницы' },
        { val: 'pdf_png', label: 'В картинки PNG' },
        { val: 'pdf_jpg', label: 'В картинки JPG' }
      ];

      return opts.map(o => `
        <option value="${o.val}" ${o.val === current ? 'selected' : ''}>${o.label}</option>
      `).join('');
    }

    return `<option value="webp">WebP</option>`;
  }

  function formatTargetLabel(val) {
    const map = {
      'webp': 'WebP',
      'jpg': 'JPG',
      'png': 'PNG',
      'opt': 'Сжатие (Lossless)',
      'pdf_opt': 'Сжатый PDF',
      'pdf_split': 'Разбивка на стр.',
      'pdf_png': 'Картинки PNG',
      'pdf_jpg': 'Картинки JPG',
      'images_to_pdf': 'Единый PDF',
      'merge_pdf': 'Склейка PDF'
    };
    return map[val] || val.toUpperCase();
  }

  /**
   * Execute Global Action (Main CTA button)
   */
  async function executeGlobalAction() {
    const visibleFiles = getVisibleFiles();
    if (visibleFiles.length === 0) return;

    const fileIds = visibleFiles.map(f => f.id);

    // 1. Images to PDF
    if (globalFormat === 'images_to_pdf') {
      await postConvert({
        session_id: sessionId,
        action_type: 'images_to_pdf',
        file_ids: fileIds
      });
      return;
    }

    // 2. Merge PDFs
    if (globalFormat === 'merge_pdf') {
      const pdfIds = visibleFiles.filter(f => f.category === 'pdf').map(f => f.id);
      if (pdfIds.length < 2) {
        showToast('Для склейки требуется минимум 2 PDF документа', 'error');
        return;
      }
      await postConvert({
        session_id: sessionId,
        action_type: 'merge_pdf',
        file_ids: pdfIds
      });
      return;
    }

    // 3. Custom Batch using per-file target selections
    const formatPerFile = {};
    visibleFiles.forEach(f => {
      formatPerFile[f.id] = f.targetFormat || globalFormat;
    });

    await postConvert({
      session_id: sessionId,
      action_type: 'custom_batch',
      target_format: globalFormat,
      file_ids: fileIds,
      options: {
        format_per_file: formatPerFile,
        quality: 90
      }
    });
  }

  /**
   * Execute Single Convert
   */
  async function executeSingleConvert(fileId, targetOption) {
    const formatPerFile = { [fileId]: targetOption };

    await postConvert({
      session_id: sessionId,
      action_type: 'custom_batch',
      file_ids: [fileId],
      options: {
        format_per_file: formatPerFile,
        quality: 90
      }
    });
  }

  /**
   * Post Convert API
   */
  async function postConvert(payload) {
    showProgress(40);
    showToast('Выполняется обработка...', 'info');

    try {
      const res = await fetch('api.php?action=convert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (!data.ok) {
        showToast(data.error || 'Ошибка при обработке файлов', 'error');
        hideProgress();
        return;
      }

      showProgress(100);
      setTimeout(hideProgress, 350);

      renderResults(data.results, data.zip_all_url);
      showToast('Готово! Файлы готовы к скачиванию', 'success');
    } catch (err) {
      hideProgress();
      showToast('Ошибка при выполнении запроса', 'error');
    }
  }

  /**
   * Render Results
   */
  function renderResults(results, zipAllUrl) {
    if (!results || results.length === 0) return;

    resultsSection.style.display = 'flex';
    resultsList.innerHTML = '';

    results.forEach(item => {
      const row = document.createElement('div');
      row.className = 'result-row';

      let sizeSavedHtml = '';
      if (item.saved_pct && item.saved_pct > 0) {
        sizeSavedHtml = `<span class="saved-tag">-${item.saved_pct}%</span>`;
      }

      row.innerHTML = `
        <div style="min-width: 0; flex: 1;">
          <div class="result-name">${escapeHtml(item.name)}</div>
          <div class="result-meta">
            ${item.orig_size ? `<span>${formatBytes(item.orig_size)} &rarr;</span>` : ''}
            <strong>${formatBytes(item.size)}</strong>
            ${sizeSavedHtml}
          </div>
        </div>
        <div style="flex-shrink: 0;">
          <a href="${item.download_url}" class="btn btn-primary btn-sm" download>
            ${ICONS.download}
            <span>Скачать</span>
          </a>
        </div>
      `;

      resultsList.appendChild(row);
    });

    if (zipAllUrl && results.length > 1) {
      btnDownloadAll.style.display = 'inline-flex';
      btnDownloadAll.href = zipAllUrl;
    } else {
      btnDownloadAll.style.display = 'none';
    }

    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  /**
   * 5-minute Auto-purge Countdown Timer
   */
  function startTimer(seconds) {
    if (timerInterval) {
      clearInterval(timerInterval);
    }
    secondsRemaining = seconds;
    updateTimerDisplay();

    timerInterval = setInterval(() => {
      secondsRemaining--;
      if (secondsRemaining <= 0) {
        clearInterval(timerInterval);
        resetApplicationState('Время хранения файлов (5 минут) истекло. Файлы безвозвратно удалены.');
        return;
      }
      updateTimerDisplay();
    }, 1000);
  }

  function updateTimerDisplay() {
    const mins = Math.floor(secondsRemaining / 60);
    const secs = secondsRemaining % 60;
    countdownDisplay.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  }

  /**
   * Delete Session Immediately
   */
  async function deleteSessionNow() {
    if (!sessionId) {
      resetApplicationState();
      return;
    }

    if (!confirm('Удалить все загруженные файлы и результаты немедленно?')) {
      return;
    }

    try {
      await fetch('api.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId })
      });
    } catch (e) {
      // ignore
    }

    resetApplicationState('Все файлы сессии безвозвратно удалены.');
  }

  /**
   * Reset Application State
   */
  function resetApplicationState(toastMsg = null) {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
    sessionId = null;
    files = [];
    resultsSection.style.display = 'none';
    resultsList.innerHTML = '';
    fileInput.value = '';

    updateBadges();
    renderWorkspace();

    if (toastMsg) {
      showToast(toastMsg, 'info');
    }
  }

  /**
   * Progress Bar Helpers
   */
  function showProgress(pct) {
    progressBarContainer.style.display = 'block';
    progressBar.style.width = `${pct}%`;
  }

  function hideProgress() {
    progressBarContainer.style.display = 'none';
    progressBar.style.width = '0%';
  }

  /**
   * Toast Notifications
   */
  let toastTimer = null;
  function showToast(msg, type = 'info') {
    if (!statusToast) return;
    if (toastTimer) clearTimeout(toastTimer);

    statusToast.textContent = msg;
    statusToast.className = `status-toast ${type}`;
    statusToast.style.display = 'block';

    toastTimer = setTimeout(() => {
      statusToast.style.display = 'none';
    }, 3500);
  }

  /**
   * Helpers
   */
  function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
  }

  function pluralizeFiles(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;
    if (mod100 >= 11 && mod100 <= 19) return 'файлов';
    if (mod10 === 1) return 'файл';
    if (mod10 >= 2 && mod10 <= 4) return 'файла';
    return 'файлов';
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

})();
