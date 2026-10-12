(function () {
    var modalNodes = document.querySelectorAll('#cameraModal');
    var videoNodes = document.querySelectorAll('#cameraVideo');
    var modal = modalNodes[modalNodes.length - 1];
    var video = videoNodes[videoNodes.length - 1];
    var status = document.getElementById('status');
    var scannerStatus = document.getElementById('status');
    var closeButton = document.getElementById('closeCamera');
    var cancelNodes = document.querySelectorAll('#cameraCancel');
    var switchNodes = document.querySelectorAll('#switchCamera');
    var cancelButton = cancelNodes[cancelNodes.length - 1];
    var switchButton = switchNodes[switchNodes.length - 1];
    var cameraButton = document.getElementById('cameraBtn');
    var cameraFooter = document.getElementById('cameraFooter');
    var scanApp = document.getElementById('scanApp');
    var frame = document.getElementById('frame');
    var hiddenInput = document.getElementById('qrHidden');
    if (!modal || !video || !status || !hiddenInput || !cameraButton || !cameraFooter || !scanApp || !frame) return;

    function isMobileScannerLayout() {
        return window.matchMedia('(max-width: 767px), (max-height: 500px) and (pointer: coarse)').matches;
    }

    var dragMoved = false;
    var dragStartX = 0;
    var dragStartY = 0;
    var footerStartLeft = 0;
    var footerStartTop = 0;
    var suppressNextClick = false;

    function restoreCameraPosition() {
        if (!cameraFooter) return;
        if (isMobileScannerLayout()) {
            cameraFooter.style.removeProperty('left');
            cameraFooter.style.removeProperty('top');
            cameraFooter.style.removeProperty('right');
            cameraFooter.style.removeProperty('bottom');
            return;
        }
        try {
            var saved = JSON.parse(localStorage.getItem('sscCameraButtonPosition') || 'null');
            if (!saved) return;
            cameraFooter.style.left = Math.max(0, Math.min(window.innerWidth - cameraFooter.offsetWidth, saved.left)) + 'px';
            cameraFooter.style.top = Math.max(0, Math.min(window.innerHeight - cameraFooter.offsetHeight, saved.top)) + 'px';
            cameraFooter.style.right = 'auto';
            cameraFooter.style.bottom = 'auto';
        } catch (error) {}
    }

    if (cameraFooter) {
        restoreCameraPosition();
        window.addEventListener('resize', restoreCameraPosition);
        cameraFooter.addEventListener('pointerdown', function (event) {
            if (isMobileScannerLayout()) return;
            if (event.button !== undefined && event.button !== 0) return;
            var rect = cameraFooter.getBoundingClientRect();
            dragMoved = false;
            dragStartX = event.clientX;
            dragStartY = event.clientY;
            footerStartLeft = rect.left;
            footerStartTop = rect.top;
            cameraFooter.setPointerCapture(event.pointerId);
        });
        cameraFooter.addEventListener('pointermove', function (event) {
            if (!cameraFooter.hasPointerCapture(event.pointerId)) return;
            var deltaX = event.clientX - dragStartX;
            var deltaY = event.clientY - dragStartY;
            if (Math.abs(deltaX) > 4 || Math.abs(deltaY) > 4) {
                dragMoved = true;
                cameraFooter.classList.add('is-dragging');
                cameraFooter.style.left = Math.max(0, Math.min(window.innerWidth - cameraFooter.offsetWidth, footerStartLeft + deltaX)) + 'px';
                cameraFooter.style.top = Math.max(0, Math.min(window.innerHeight - cameraFooter.offsetHeight, footerStartTop + deltaY)) + 'px';
                cameraFooter.style.right = 'auto';
                cameraFooter.style.bottom = 'auto';
            }
        });
        cameraFooter.addEventListener('pointerup', function (event) {
            if (cameraFooter.hasPointerCapture(event.pointerId)) cameraFooter.releasePointerCapture(event.pointerId);
            cameraFooter.classList.remove('is-dragging');
            if (dragMoved) {
                suppressNextClick = true;
                localStorage.setItem('sscCameraButtonPosition', JSON.stringify({left: cameraFooter.offsetLeft, top: cameraFooter.offsetTop}));
            } else {
                suppressNextClick = true;
                if (modal.classList.contains('hidden')) openCamera();
                else closeCamera();
            }
        });
        cameraFooter.addEventListener('click', function (event) {
            if (!suppressNextClick) return;
            suppressNextClick = false;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    }

    var cameraPanel = modal.firstElementChild;
    scanApp.appendChild(modal);
    modal.classList.remove('fixed', 'inset-0', 'p-4');
    modal.classList.add('absolute', 'z-20', 'rounded-3xl', 'bg-slate-50');
    function alignCameraModal() {
        if (modal.parentNode !== scanApp) scanApp.appendChild(modal);
        modal.classList.remove('camera-mobile-fullscreen');
        var appRect = scanApp.getBoundingClientRect();
        var frameRect = frame.getBoundingClientRect();
        modal.style.top = (frameRect.top - appRect.top) + 'px';
        modal.style.left = (frameRect.left - appRect.left) + 'px';
        modal.style.width = frameRect.width + 'px';
        modal.style.setProperty('height', frameRect.height + 'px', 'important');
        modal.style.setProperty('min-height', frameRect.height + 'px', 'important');
    }
    alignCameraModal();
    window.addEventListener('resize', alignCameraModal);
    if (window.ResizeObserver) {
        var cameraResizeObserver = new ResizeObserver(alignCameraModal);
        cameraResizeObserver.observe(frame);
    }
    document.addEventListener('ssc:scan-zoom-changed', function () {
        requestAnimationFrame(alignCameraModal);
    });
    if (cameraPanel) {
        cameraPanel.classList.remove('max-w-lg', 'rounded-3xl', 'shadow-2xl');
        cameraPanel.classList.add('h-full', 'max-w-none', 'rounded-3xl');
    }

    var cameraStream = null;
    var animationFrameId = null;
    var decoder = null;
    var zxingReader = null;
    var isScanning = false;
    var scanLocked = false;
    var usingFrontCamera = false;
    var lastScannedCode = null;
    var lastScanTime = 0;
    var codeVisible = false;
    var generation = 0;
    var cooldownMs = 1800;
    var absenceTimer = null;

    function setStatus(message) {
        status.textContent = message;
        if (scannerStatus && scannerStatus !== status) scannerStatus.textContent = message;
    }

    function stopDecoder() {
        isScanning = false;
        if (animationFrameId !== null) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        if (decoder && typeof decoder.reset === 'function') {
            try { decoder.reset(); } catch (error) {}
        }
        if (zxingReader && typeof zxingReader.reset === 'function') {
            try { zxingReader.reset(); } catch (error) {}
        }
        if (absenceTimer !== null) {
            clearTimeout(absenceTimer);
            absenceTimer = null;
        }
        decoder = null;
        zxingReader = null;
    }

    function armCodeAbsenceTimer() {
        if (absenceTimer !== null) clearTimeout(absenceTimer);
        absenceTimer = setTimeout(function () {
            absenceTimer = null;
            scanLocked = false;
            codeVisible = false;
            if (isScanning) setStatus('Camera ready. Point the camera at a QR code or barcode.');
        }, 1200);
    }

    function stopCameraScanner(message) {
        generation += 1;
        stopDecoder();
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
            cameraStream = null;
        }
        video.pause();
        video.srcObject = null;
        scanLocked = false;
        codeVisible = false;
        if (message) setStatus(message);
    }

    function showCameraPanel() {
        alignCameraModal();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        cameraButton.classList.remove('gap-2', 'px-3', 'py-1');
        cameraButton.classList.add('h-9', 'w-9', 'justify-center', 'text-slate-700');
        cameraButton.classList.remove('text-emerald-700');
        cameraButton.innerHTML = '<img src="logo/Camera.png" alt="" class="scanner-toggle-icon h-9 w-9 object-contain">';
        cameraButton.title = 'Return to scanner input';
        cameraButton.setAttribute('aria-label', 'Return to scanner input');
        if (scannerStatus) scannerStatus.textContent = 'Opening camera...';
    }

    function showScannerPanel() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        cameraButton.classList.remove('gap-2', 'px-3', 'py-1');
        cameraButton.classList.add('h-9', 'w-9', 'justify-center', 'text-slate-700');
        cameraButton.classList.remove('text-emerald-700');
        cameraButton.innerHTML = '<img src="logo/Camera.png" alt="" class="scanner-toggle-icon h-9 w-9 object-contain">';
        cameraButton.title = 'Open camera scanner';
        cameraButton.setAttribute('aria-label', 'Open camera scanner');
        if (scannerStatus) scannerStatus.textContent = 'Place Student ID, QR Code, or Barcode in front of the Scanner';
    }

    function validDecodedValue(value) {
        var code = typeof value === 'string' ? value.trim() : '';
        if (!code || code.length > 255 || /[\u0000-\u001f\u007f]/.test(code)) return '';
        return code;
    }

    function codeIsDuplicate(code) {
        var now = Date.now();
        if (code === lastScannedCode && now - lastScanTime < cooldownMs) return true;
        lastScannedCode = code;
        lastScanTime = now;
        return false;
    }

    function submitCode(code) {
        code = validDecodedValue(code);
        if (!code || scanLocked || codeIsDuplicate(code)) return false;
        scanLocked = true;
        codeVisible = true;
        setStatus('QR/Barcode detected. Processing...');
        window.SSC_SCAN_CONFIG.scanType = 'camera';
        hiddenInput.value = code;
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }

    function handleDetected(code, token) {
        if (token !== generation || !isScanning) return;
        if (validDecodedValue(code)) {
            codeVisible = true;
            armCodeAbsenceTimer();
        }
        submitCode(code);
    }

    function scheduleFrame(token) {
        if (!isScanning || token !== generation || animationFrameId !== null) return;
        animationFrameId = requestAnimationFrame(function () {
            animationFrameId = null;
            scanFrame(token);
        });
    }

    async function scanFrame(token) {
        if (!isScanning || token !== generation || !decoder || video.readyState < 2) return;
        try {
            var results = await decoder.detect(video);
            if (token !== generation || !isScanning) return;
            var result = results && results.length ? results[0] : null;
            var code = result ? validDecodedValue(result.rawValue) : '';
            if (code) handleDetected(code, token);
            else if (!codeVisible) setStatus('Camera ready. Point the camera at a QR code or barcode.');
        } catch (error) {}
        scheduleFrame(token);
    }

    function startZXing(token) {
        if (!window.ZXingBrowser || token !== generation || !cameraStream) {
            setStatus('Camera ready. Point the camera at a QR code or barcode.');
            return;
        }
        zxingReader = new ZXingBrowser.BrowserMultiFormatReader();
        zxingReader.timeBetweenDecodingAttempts = 80;
        isScanning = true;
        setStatus('Camera ready. Point the camera at a QR code or barcode.');
        zxingReader.decodeFromVideoElement(video, function (result) {
            if (!isScanning || token !== generation || !result) return;
            handleDetected(result.text, token);
        });
    }

    function startDecoder(token) {
        if ('BarcodeDetector' in window) {
            try {
                decoder = new BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf'] });
                isScanning = true;
                setStatus('Camera ready. Point the camera at a QR code or barcode.');
                scheduleFrame(token);
                return;
            } catch (error) { decoder = null; }
        }
        startZXing(token);
    }

    async function openCamera() {
        stopCameraScanner();
        generation += 1;
        var token = generation;
        scanLocked = false;
        codeVisible = false;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        showCameraPanel();
        setStatus('Opening camera...');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('Camera unavailable. Open this page using HTTPS and a supported browser.');
            return;
        }
        if (!window.isSecureContext) {
            setStatus('Camera requires HTTPS on Android/iOS. Use a secure network address.');
            return;
        }
        try {
            var stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: usingFrontCamera ? 'user' : 'environment' }
                },
                audio: false
            });
            if (token !== generation) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                return;
            }
            cameraStream = stream;
            video.srcObject = cameraStream;
            video.play().catch(function () {});
            var videoTrack = cameraStream.getVideoTracks()[0];
            if (videoTrack && videoTrack.applyConstraints) {
                var qualityConstraints = {
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    frameRate: { ideal: 24, max: 30 }
                };
                var capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
                if (capabilities.focusMode && capabilities.focusMode.indexOf('continuous') !== -1) {
                    qualityConstraints.advanced = [{ focusMode: 'continuous' }];
                }
                videoTrack.applyConstraints(qualityConstraints).catch(function () {});
            }
            startDecoder(token);
        } catch (error) {
            var message = 'Camera access failed. Please allow camera permission.';
            if (error && error.name === 'NotAllowedError') message = 'Camera permission was blocked. Allow camera access in browser settings.';
            else if (error && error.name === 'NotFoundError') message = 'No camera was found on this device.';
            else if (error && error.name === 'NotReadableError') message = 'The camera is already being used by another app.';
            else if (error && error.name === 'SecurityError') message = 'Camera requires HTTPS on Android/iOS. Use a secure network address.';
            stopCameraScanner(message);
        }
    }

    function closeCamera() {
        stopCameraScanner('Camera stopped.');
        showScannerPanel();
        hiddenInput.focus();
    }

    function capture(handler) {
        return function (event) {
            event.stopImmediatePropagation();
            handler(event);
        };
    }

    if (closeButton) closeButton.addEventListener('click', capture(closeCamera), true);
    cancelButton.addEventListener('click', capture(closeCamera), true);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeCamera();
    });
    switchButton.addEventListener('click', capture(function () {
        usingFrontCamera = !usingFrontCamera;
        openCamera();
    }), true);

    window.addEventListener('beforeunload', function () { stopCameraScanner(); });
    window.addEventListener('ssc:camera-scan-complete', closeCamera);
    window.SSC_CAMERA_SCANNER = { open: openCamera, close: closeCamera, stop: stopCameraScanner };
    cameraButton.addEventListener('click', capture(function () {
        if (modal.classList.contains('hidden')) openCamera();
        else closeCamera();
    }), true);
    cameraButton.addEventListener('click', function () {
        var wasHidden = modal.classList.contains('hidden');
        window.setTimeout(function () {
            if (wasHidden && modal.classList.contains('hidden')) openCamera();
        }, 0);
    });
    showScannerPanel();
    if (new URLSearchParams(window.location.search).get('camera') === '1') {
        window.addEventListener('load', openCamera, { once: true });
    }
})();
