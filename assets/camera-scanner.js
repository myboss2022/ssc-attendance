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
    if (!modal || !video || !status || !hiddenInput || !cameraButton || !scanApp || !frame) return;

    var dragMoved = false;
    var dragStartX = 0;
    var dragStartY = 0;
    var footerStartLeft = 0;
    var footerStartTop = 0;
    var suppressNextClick = false;

    function restoreCameraPosition() {
        if (!cameraFooter) return;
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
        cameraFooter.addEventListener('pointerdown', function (event) {
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
        if (!modal.classList.contains('hidden') && window.matchMedia('(max-width: 767px), (max-height: 500px) and (pointer: coarse)').matches) {
            if (modal.parentNode !== document.body) document.body.appendChild(modal);
            modal.classList.add('camera-mobile-fullscreen');
            modal.style.removeProperty('top');
            modal.style.removeProperty('left');
            modal.style.removeProperty('width');
            modal.style.removeProperty('height');
            modal.style.removeProperty('min-height');
            return;
        }
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
    var openingCamera = false;
    var detectorFailures = 0;
    var lastDetectorRun = 0;

    var qrFormats = ['qr_code', 'micro_qr_code', 'code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'codabar', 'data_matrix', 'pdf417', 'aztec'];

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
        openingCamera = false;
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
        cameraButton.classList.add('camera-active');
        cameraButton.innerHTML = '<img src="logo/Camera.png" alt="" class="scanner-toggle-icon h-9 w-9 object-contain">';
        cameraButton.title = 'Return to scanner input';
        cameraButton.setAttribute('aria-label', 'Return to scanner input');
        if (scannerStatus) scannerStatus.textContent = 'Opening camera...';
    }

    function showScannerPanel() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        alignCameraModal();
        cameraButton.classList.remove('gap-2', 'px-3', 'py-1');
        cameraButton.classList.add('h-9', 'w-9', 'justify-center', 'text-slate-700');
        cameraButton.classList.remove('text-emerald-700');
        cameraButton.classList.remove('camera-active');
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
        if (performance.now() - lastDetectorRun < 120) {
            scheduleFrame(token);
            return;
        }
        lastDetectorRun = performance.now();
        try {
            var results = await decoder.detect(video);
            if (token !== generation || !isScanning) return;
            detectorFailures = 0;
            var result = results && results.length ? results[0] : null;
            var code = result ? validDecodedValue(result.rawValue) : '';
            if (code) handleDetected(code, token);
            else if (!codeVisible) setStatus('Camera ready. Point the camera at a QR code or barcode.');
        } catch (error) {
            if (token !== generation || !isScanning) return;
            detectorFailures += 1;
            if (detectorFailures >= 3) {
                decoder = null;
                setStatus('Switching to a compatible QR/barcode decoder...');
                startZXing(token);
                return;
            }
        }
        scheduleFrame(token);
    }

    function startZXing(token) {
        if (!window.ZXingBrowser || token !== generation || !cameraStream) {
            isScanning = false;
            setStatus('Camera opened, but the QR/barcode decoder did not load. Reload the page and try again.');
            return;
        }
        try {
            zxingReader = new ZXingBrowser.BrowserMultiFormatReader();
            zxingReader.timeBetweenDecodingAttempts = 120;
            isScanning = true;
            setStatus('Camera ready. Point the camera at a QR code or barcode.');
            var decoding = zxingReader.decodeFromVideoElement(video, function (result) {
                if (!isScanning || token !== generation || !result) return;
                handleDetected(result.text, token);
            });
            if (decoding && typeof decoding.catch === 'function') {
                decoding.catch(function (error) {
                    if (token !== generation || !isScanning) return;
                    isScanning = false;
                    setStatus('Could not start the QR/barcode decoder. Close the camera and try again.');
                    console.error('ZXing camera decoder failed:', error);
                });
            }
        } catch (error) {
            isScanning = false;
            setStatus('Could not start the QR/barcode decoder. Close the camera and try again.');
            console.error('ZXing camera decoder failed:', error);
        }
    }

    async function startDecoder(token) {
        if (token !== generation) return;
        if ('BarcodeDetector' in window) {
            try {
                var formats = qrFormats.slice();
                if (BarcodeDetector.getSupportedFormats) {
                    var supportedFormats = await BarcodeDetector.getSupportedFormats();
                    if (token !== generation) return;
                    formats = formats.filter(function (format) { return supportedFormats.indexOf(format) !== -1; });
                }
                if (!formats.length) throw new Error('No supported barcode formats.');
                decoder = new BarcodeDetector({ formats: formats });
                detectorFailures = 0;
                lastDetectorRun = 0;
                isScanning = true;
                setStatus('Camera ready. Point the camera at a QR code or barcode.');
                scheduleFrame(token);
                return;
            } catch (error) { decoder = null; }
        }
        if (token !== generation) return;
        startZXing(token);
    }

    async function openCamera() {
        if (openingCamera) return;
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
        openingCamera = true;
        try {
            var stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: usingFrontCamera ? 'user' : 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });
            } catch (preferredError) {
                if (!['OverconstrainedError', 'NotFoundError', 'ConstraintNotSatisfiedError'].includes(preferredError && preferredError.name)) {
                    throw preferredError;
                }
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            }
            if (token !== generation) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                return;
            }
            cameraStream = stream;
            try {
                var cameras = (await navigator.mediaDevices.enumerateDevices()).filter(function (device) {
                    return device.kind === 'videoinput' && device.deviceId;
                });
                var wantedCamera = cameras.find(function (device) {
                    return usingFrontCamera
                        ? /front|user|facetime/i.test(device.label)
                        : /back|rear|environment|world/i.test(device.label);
                });
                var activeTrack = cameraStream.getVideoTracks()[0];
                if (wantedCamera && activeTrack && wantedCamera.deviceId !== activeTrack.getSettings().deviceId) {
                    var initialStream = cameraStream;
                    cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: { deviceId: { exact: wantedCamera.deviceId } },
                        audio: false
                    });
                    initialStream.getTracks().forEach(function (track) { track.stop(); });
                }
            } catch (cameraSelectionError) {
                console.warn('Could not select the preferred camera; using the browser-selected camera.', cameraSelectionError);
            }
            if (token !== generation) {
                cameraStream.getTracks().forEach(function (track) { track.stop(); });
                cameraStream = null;
                return;
            }
            video.srcObject = cameraStream;
            video.muted = true;
            video.playsInline = true;
            await video.play();
            if (token !== generation) return;
            if (video.readyState < 2 || !video.videoWidth) {
                await new Promise(function (resolve, reject) {
                    var timeout = window.setTimeout(function () {
                        cleanup();
                        reject(new Error('Camera preview took too long to start.'));
                    }, 8000);
                    function cleanup() {
                        window.clearTimeout(timeout);
                        video.removeEventListener('loadeddata', onReady);
                        video.removeEventListener('error', onError);
                    }
                    function onReady() {
                        cleanup();
                        resolve();
                    }
                    function onError() {
                        cleanup();
                        reject(video.error || new Error('Camera preview could not start.'));
                    }
                    video.addEventListener('loadeddata', onReady, { once: true });
                    video.addEventListener('error', onError, { once: true });
                    if (video.readyState >= 2 && video.videoWidth) onReady();
                });
            }
            if (token !== generation) return;
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
            await startDecoder(token);
            openingCamera = false;
            var activeVideoTrack = cameraStream && cameraStream.getVideoTracks()[0];
            if (activeVideoTrack) {
                activeVideoTrack.addEventListener('ended', function () {
                    if (token !== generation) return;
                    stopCameraScanner('Camera connection ended. Tap the camera button to reconnect.');
                }, { once: true });
            }
        } catch (error) {
            openingCamera = false;
            var message = 'Camera access failed. Please allow camera permission.';
            if (error && error.name === 'NotAllowedError') message = 'Camera permission was blocked. Allow camera access in browser settings.';
            else if (error && error.name === 'NotFoundError') message = 'No camera was found on this device.';
            else if (error && error.name === 'NotReadableError') message = 'The camera is already being used by another app.';
            else if (error && error.name === 'SecurityError') message = 'Camera requires HTTPS on Android/iOS. Use a secure network address.';
            else if (error && error.name === 'NotSupportedError') message = 'This browser could not start video playback. Try another supported browser.';
            else if (error && error.message === 'Camera preview took too long to start.') message = error.message + ' Close other apps using the camera and try again.';
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
    showScannerPanel();
    if (new URLSearchParams(window.location.search).get('camera') === '1') {
        openCamera();
    }
})();
