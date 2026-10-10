(function () {
  var originalOpenCamera = window.openCamera;
  var originalSubmitCameraCode = window.submitCameraCode;
  var lastCameraCode = '';
  var lastCameraScanAt = 0;
  var pendingCameraCode = '';
  var pendingCameraCount = 0;
  var pendingCameraAt = 0;
  var ignoreUntil = 0;

  if (typeof originalOpenCamera !== 'function' || typeof originalSubmitCameraCode !== 'function') return;

  window.openCamera = function () {
    ignoreUntil = Date.now() + 650;
    return originalOpenCamera.apply(this, arguments);
  };

  window.submitCameraCode = function (code) {
    code = String(code || '').trim();
    var now = Date.now();
    if (!code || now < ignoreUntil || (code === lastCameraCode && now - lastCameraScanAt < 5000)) return;
    if (code !== pendingCameraCode || now - pendingCameraAt > 1800) {
      pendingCameraCode = code;
      pendingCameraCount = 1;
      pendingCameraAt = now;
      return;
    }
    pendingCameraCount += 1;
    pendingCameraAt = now;
    if (pendingCameraCount < 2) return;
    pendingCameraCode = '';
    pendingCameraCount = 0;
    lastCameraCode = code;
    lastCameraScanAt = now;
    return originalSubmitCameraCode.call(this, code);
  };
}());
