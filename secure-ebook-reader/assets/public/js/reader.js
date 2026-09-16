/**
 * Contrôleur Frontend du Lecteur PDF Sécurisé — Secure Ebook Reader
 * Intégration PDF.js Engine, Dual-Layer Watermark, Range Streaming & Anti-Piratage
 */

(function () {
    'use strict';

    const config = window.SECURE_EBOOK_CONFIG;
    if (!config) {
        console.error('Configuration Secure Ebook Reader manquante.');
        return;
    }

    // Définition du Worker PDF.js
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = config.workerSrc;
    }

    // État applicatif
    const state = {
        pdfDoc: null,
        currentPage: config.initialPage || 1,
        totalPages: 0,
        pageRendering: false,
        pageNumPending: null,
        scale: 1.0,
        zoomMode: 'page-width', // 'page-width', 'page-fit', 'custom'
        token: null,
        isSidebarOpen: false,
        lastSavedPage: 0,
        heartbeatTimer: null,
        saveTimer: null,
        touchStartX: 0,
        touchEndX: 0,
    };

    // Éléments DOM
    const elements = {
        canvas: document.getElementById('pdf-canvas'),
        pageWrapper: document.getElementById('page-wrapper'),
        viewerContainer: document.getElementById('viewer-container'),
        watermarkOverlay: document.getElementById('watermark-overlay'),
        loader: document.getElementById('reader-loader'),
        loaderText: document.getElementById('loader-text'),
        pageInput: document.getElementById('page-input'),
        totalPagesCount: document.getElementById('total-pages-count'),
        btnPrev: document.getElementById('btn-prev-page'),
        btnNext: document.getElementById('btn-next-page'),
        btnZoomIn: document.getElementById('btn-zoom-in'),
        btnZoomOut: document.getElementById('btn-zoom-out'),
        btnZoomFitWidth: document.getElementById('btn-zoom-fit-width'),
        zoomLevel: document.getElementById('zoom-level'),
        btnToggleSidebar: document.getElementById('btn-toggle-sidebar'),
        sidebar: document.getElementById('reader-sidebar'),
        btnCloseSidebar: document.getElementById('btn-close-sidebar'),
        thumbnailsContainer: document.getElementById('thumbnails-container'),
        btnFullscreen: document.getElementById('btn-fullscreen'),
        progressIndicator: document.getElementById('progress-indicator'),
        toast: document.getElementById('reader-toast'),
    };

    /**
     * Affiche un toast discret
     */
    function showToast(message, duration = 3000) {
        if (!elements.toast) return;
        elements.toast.textContent = message;
        elements.toast.classList.add('is-visible');
        setTimeout(() => {
            elements.toast.classList.remove('is-visible');
        }, duration);
    }

    /**
     * Initialisation du lecteur
     */
    async function init() {
        bindAntiPiracyEvents();
        bindUIEvents();

        try {
            // 1. Obtenir un jeton éphémère auprès de l'API
            elements.loaderText.textContent = config.strings.loading;
            const tokenResponse = await fetch(`${config.restUrl}read/${config.ebookId}/token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                }
            });

            const tokenData = await tokenResponse.json();
            if (!tokenResponse.ok || !tokenData.token) {
                throw new Error(tokenData.message || config.strings.accessRevoked);
            }

            state.token = tokenData.token;

            // 2. Lancer le Heartbeat pour rafraîchissement transparent du jeton
            startHeartbeat();

            // 3. Charger le document PDF via PDF.js et Range Requests
            const streamUrl = `${config.restUrl}read/${config.ebookId}?token=${encodeURIComponent(state.token)}`;
            
            const loadingTask = pdfjsLib.getDocument({
                url: streamUrl,
                withCredentials: true,
                httpHeaders: {
                    'X-WP-Nonce': config.nonce
                },
                rangeChunkSize: 65536, // Chunks de 64 Ko pour streaming progressif instantané
            });

            state.pdfDoc = await loadingTask.promise;
            state.totalPages = state.pdfDoc.numPages;
            elements.totalPagesCount.textContent = state.totalPages;
            elements.pageInput.max = state.totalPages;

            // Masquer le loader
            elements.loader.style.display = 'none';

            // Rendu de la première page ou reprise mémorisée
            if (state.currentPage > state.totalPages) {
                state.currentPage = 1;
            }

            renderPage(state.currentPage);
            updateProgressBar();
            scheduleProgressSave();

        } catch (error) {
            elements.loaderText.innerHTML = `<span style="color: #ef4444;">⚠️ ${error.message}</span>`;
            console.error('Erreur chargement Secure Ebook:', error);
        }
    }

    /**
     * Rendu d'une page PDF
     */
    async function renderPage(num) {
        if (!state.pdfDoc) return;
        state.pageRendering = true;

        try {
            const page = await state.pdfDoc.getPage(num);
            const ctx = elements.canvas.getContext('2d');

            // Calcul de l'échelle d'affichage
            let viewport = page.getViewport({ scale: 1.0 });

            if (state.zoomMode === 'page-width') {
                const containerWidth = elements.viewerContainer.clientWidth - 80;
                state.scale = containerWidth / viewport.width;
            } else if (state.zoomMode === 'page-fit') {
                const containerHeight = elements.viewerContainer.clientHeight - 80;
                state.scale = containerHeight / viewport.height;
            }

            // Limiter l'échelle entre 50% et 300%
            state.scale = Math.min(Math.max(state.scale, 0.5), 3.0);
            elements.zoomLevel.textContent = `${Math.round(state.scale * 100)}%`;

            viewport = page.getViewport({ scale: state.scale });

            // Support haute densité (Retina/High-DPI)
            const pixelRatio = window.devicePixelRatio || 1;
            elements.canvas.width = Math.floor(viewport.width * pixelRatio);
            elements.canvas.height = Math.floor(viewport.height * pixelRatio);
            elements.canvas.style.width = `${Math.floor(viewport.width)}px`;
            elements.canvas.style.height = `${Math.floor(viewport.height)}px`;

            elements.pageWrapper.style.width = `${Math.floor(viewport.width)}px`;
            elements.pageWrapper.style.height = `${Math.floor(viewport.height)}px`;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport,
                transform: [pixelRatio, 0, 0, pixelRatio, 0, 0]
            };

            await page.render(renderContext).promise;

            // Incrustation directe du filigrane sur le canvas (Anti-Inspecteur)
            if (config.watermark.enabled && config.watermark.canvas) {
                renderCanvasWatermark(ctx, viewport, pixelRatio);
            }

            // Génération de l'overlay de filigrane DOM
            if (config.watermark.enabled) {
                renderDOMWatermark();
            }

            state.pageRendering = false;

            if (state.pageNumPending !== null) {
                const nextNum = state.pageNumPending;
                state.pageNumPending = null;
                renderPage(nextNum);
            }

            // Mettre à jour les champs de contrôle
            elements.pageInput.value = num;
            updateProgressBar();

            // Mettre en surbrillance la vignette active
            updateActiveThumbnail(num);

        } catch (err) {
            state.pageRendering = false;
            console.error('Erreur rendu page:', err);
        }
    }

    /**
     * Dessine le filigrane indélébile directement dans les pixels du canvas
     */
    function renderCanvasWatermark(ctx, viewport, pixelRatio) {
        ctx.save();
        ctx.scale(pixelRatio, pixelRatio);
        ctx.globalAlpha = config.watermark.opacity;
        ctx.fillStyle = config.watermark.color;
        ctx.font = `bold ${config.watermark.size}px monospace`;

        const text = config.watermark.text;
        const textMetrics = ctx.measureText(text);
        const textWidth = textMetrics.width;

        const stepX = textWidth + 120;
        const stepY = 180;

        ctx.rotate(-28 * Math.PI / 180);

        for (let x = -viewport.width; x < viewport.width * 2; x += stepX) {
            for (let y = -viewport.height; y < viewport.height * 2; y += stepY) {
                ctx.fillText(text, x, y);
            }
        }

        ctx.restore();
    }

    /**
     * Génère l'overlay de filigrane CSS répété
     */
    function renderDOMWatermark() {
        if (!elements.watermarkOverlay) return;
        elements.watermarkOverlay.innerHTML = '';

        const repetitions = 8;
        for (let i = 0; i < repetitions; i++) {
            const token = document.createElement('div');
            token.className = 'watermark-token-item';
            token.textContent = config.watermark.text;
            token.style.opacity = config.watermark.opacity;
            token.style.color = config.watermark.color;
            token.style.fontSize = `${config.watermark.size}px`;
            elements.watermarkOverlay.appendChild(token);
        }
    }

    /**
     * File d'attente de rendu
     */
    function queueRenderPage(num) {
        if (state.pageRendering) {
            state.pageNumPending = num;
        } else {
            renderPage(num);
        }
    }

    function goToPrevPage() {
        if (state.currentPage <= 1) return;
        state.currentPage--;
        queueRenderPage(state.currentPage);
        scheduleProgressSave();
    }

    function goToNextPage() {
        if (state.currentPage >= state.totalPages) return;
        state.currentPage++;
        queueRenderPage(state.currentPage);
        scheduleProgressSave();
    }

    /**
     * Met à jour la barre de progression inférieure
     */
    function updateProgressBar() {
        if (!state.totalPages || !elements.progressIndicator) return;
        const pct = Math.min(100, Math.round((state.currentPage / state.totalPages) * 100));
        elements.progressIndicator.style.width = `${pct}%`;
    }

    /**
     * Enregistre la progression de lecture avec limitation de débit (Debounce)
     */
    function scheduleProgressSave() {
        clearTimeout(state.saveTimer);
        state.saveTimer = setTimeout(async () => {
            if (state.currentPage === state.lastSavedPage) return;
            try {
                await fetch(`${config.restUrl}progress/${config.ebookId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({
                        page: state.currentPage,
                        total_pages: state.totalPages
                    })
                });
                state.lastSavedPage = state.currentPage;
            } catch (e) {
                // Silencieux pour ne pas interrompre la lecture
            }
        }, (config.saveInterval || 15) * 1000);
    }

    /**
     * Heartbeat périodique pour renouveler le token et vérifier la validité continue des droits
     */
    function startHeartbeat() {
        clearInterval(state.heartbeatTimer);
        state.heartbeatTimer = setInterval(async () => {
            if (!state.token) return;
            try {
                const response = await fetch(`${config.restUrl}heartbeat/${config.ebookId}?token=${encodeURIComponent(state.token)}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    }
                });
                const data = await response.json();

                // Si les droits d'accès ont été clôturés (remboursement en cours de session)
                if (data.revoked) {
                    clearInterval(state.heartbeatTimer);
                    lockViewerDueToRevocation();
                }
            } catch (e) {
                // Erreur réseau temporaire
            }
        }, 5 * 60 * 1000); // Toutes les 5 minutes
    }

    /**
     * Verrouille l'interface en cas de révocation immédiate des droits
     */
    function lockViewerDueToRevocation() {
        document.body.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 100vh; background: #0b0f19; color: #f8fafc; text-align: center; font-family: sans-serif;">
                <div style="max-width: 450px; padding: 30px; background: #1e293b; border-radius: 8px; border: 1px solid #ef4444;">
                    <h2 style="color: #ef4444;">Accès Révoqué</h2>
                    <p>${config.strings.accessRevoked}</p>
                    <a href="/" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px;">Retour au site</a>
                </div>
            </div>
        `;
    }

    /**
     * Génère les vignettes de pages de manière paresseuse (Lazy)
     */
    async function generateThumbnails() {
        if (!elements.thumbnailsContainer || elements.thumbnailsContainer.children.length > 0) return;

        for (let i = 1; i <= state.totalPages; i++) {
            const thumbItem = document.createElement('div');
            thumbItem.className = `thumb-item ${i === state.currentPage ? 'active' : ''}`;
            thumbItem.dataset.page = i;

            const canvas = document.createElement('canvas');
            canvas.width = 100;
            canvas.height = 140;

            const label = document.createElement('div');
            label.className = 'thumb-label';
            label.textContent = `${config.strings.page} ${i}`;

            thumbItem.appendChild(canvas);
            thumbItem.appendChild(label);

            thumbItem.addEventListener('click', () => {
                state.currentPage = i;
                queueRenderPage(i);
                scheduleProgressSave();
            });

            elements.thumbnailsContainer.appendChild(thumbItem);

            // Rendu de la vignette
            state.pdfDoc.getPage(i).then(page => {
                const viewport = page.getViewport({ scale: 0.15 });
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport: viewport
                });
            });
        }
    }

    function updateActiveThumbnail(page) {
        if (!elements.thumbnailsContainer) return;
        const thumbs = elements.thumbnailsContainer.querySelectorAll('.thumb-item');
        thumbs.forEach(t => {
            if (parseInt(t.dataset.page, 10) === page) {
                t.classList.add('active');
                t.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                t.classList.remove('active');
            }
        });
    }

    /**
     * Écouteurs d'événements de l'interface
     */
    function bindUIEvents() {
        elements.btnPrev.addEventListener('click', goToPrevPage);
        elements.btnNext.addEventListener('click', goToNextPage);

        elements.pageInput.addEventListener('change', (e) => {
            let val = parseInt(e.target.value, 10);
            if (isNaN(val) || val < 1) val = 1;
            if (val > state.totalPages) val = state.totalPages;
            state.currentPage = val;
            queueRenderPage(val);
            scheduleProgressSave();
        });

        // Zoom
        elements.btnZoomIn.addEventListener('click', () => {
            state.zoomMode = 'custom';
            state.scale = Math.min(3.0, state.scale + 0.2);
            queueRenderPage(state.currentPage);
        });

        elements.btnZoomOut.addEventListener('click', () => {
            state.zoomMode = 'custom';
            state.scale = Math.max(0.5, state.scale - 0.2);
            queueRenderPage(state.currentPage);
        });

        elements.btnZoomFitWidth.addEventListener('click', () => {
            state.zoomMode = 'page-width';
            queueRenderPage(state.currentPage);
        });

        // Thèmes
        document.querySelectorAll('.btn-theme').forEach(btn => {
            btn.addEventListener('click', () => {
                const theme = btn.dataset.theme;
                document.body.className = `secure-reader-body theme-${theme}`;
                localStorage.setItem('ser_reader_theme', theme);
            });
        });

        const savedTheme = localStorage.getItem('ser_reader_theme');
        if (savedTheme) {
            document.body.className = `secure-reader-body theme-${savedTheme}`;
        }

        // Plein écran
        elements.btnFullscreen.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        });

        // Sidebar
        elements.btnToggleSidebar.addEventListener('click', () => {
            state.isSidebarOpen = !state.isSidebarOpen;
            elements.sidebar.classList.toggle('is-open', state.isSidebarOpen);
            if (state.isSidebarOpen) {
                generateThumbnails();
            }
        });

        elements.btnCloseSidebar.addEventListener('click', () => {
            state.isSidebarOpen = false;
            elements.sidebar.classList.remove('is-open');
        });

        // Redimensionnement automatique
        window.addEventListener('resize', () => {
            if (state.zoomMode === 'page-width' || state.zoomMode === 'page-fit') {
                queueRenderPage(state.currentPage);
            }
        });

        // Gestes tactiles mobiles (Swipe)
        elements.viewerContainer.addEventListener('touchstart', (e) => {
            state.touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        elements.viewerContainer.addEventListener('touchend', (e) => {
            state.touchEndX = e.changedTouches[0].screenX;
            handleSwipeGesture();
        }, { passive: true });
    }

    function handleSwipeGesture() {
        const diff = state.touchStartX - state.touchEndX;
        if (Math.abs(diff) > 60) {
            if (diff > 0) {
                goToNextPage(); // Balayage vers la gauche -> page suivante
            } else {
                goToPrevPage(); // Balayage vers la droite -> page précédente
            }
        }
    }

    /**
     * Verrouillage anti-piratage et anti-fuite
     */
    function bindAntiPiracyEvents() {
        // Blocage clic droit
        window.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            showToast(config.strings.protectedNotice);
            return false;
        });

        // Blocage des raccourcis de copie et d'impression
        window.addEventListener('keydown', (e) => {
            const isCtrlOrCmd = e.ctrlKey || e.metaKey;

            // Flèches clavier pour navigation
            if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                goToPrevPage();
                return;
            }
            if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
                goToNextPage();
                return;
            }

            // Ctrl+P / Cmd+P (Print)
            if (isCtrlOrCmd && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                showToast(config.strings.protectedNotice);
                return false;
            }

            // Ctrl+S / Cmd+S (Save)
            if (isCtrlOrCmd && (e.key === 's' || e.key === 'S')) {
                e.preventDefault();
                showToast(config.strings.protectedNotice);
                return false;
            }

            // F12 / Inspecteur
            if (e.key === 'F12' || (isCtrlOrCmd && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c'))) {
                e.preventDefault();
                return false;
            }

            // Ctrl+U (Source)
            if (isCtrlOrCmd && (e.key === 'u' || e.key === 'U')) {
                e.preventDefault();
                return false;
            }
        });
    }

    // Démarrage
    document.addEventListener('DOMContentLoaded', init);
})();
