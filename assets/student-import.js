(function () {
  var button = document.getElementById('studentImportButton');
  var file = document.getElementById('studentImportFile');
  var form = document.getElementById('studentImportForm');
  var loading = document.getElementById('studentImportLoading');
  var message = document.getElementById('studentImportLoadingMessage');
  var progress = document.getElementById('studentImportProgress');
  var circle = document.getElementById('studentImportProgressCircle');
  var percent = document.getElementById('studentImportProgressText');

  if (!button || !file || !form || !loading || !message || !progress || !circle || !percent) return;

  var busy = false;
  var circumference = 314.16;

  function setProgress(value) {
    value = Math.max(1, Math.min(100, Math.round(value)));
    circle.style.strokeDashoffset = String(circumference * (1 - value / 100));
    progress.setAttribute('aria-valuenow', String(value));
    percent.textContent = value + '%';
  }

  function closeLoading() {
    loading.classList.add('hidden');
    loading.classList.remove('flex');
    busy = false;
    button.disabled = false;
    file.value = '';
  }

  function showError(text) {
    closeLoading();
    window.alert(text);
  }

  function handleFileChange() {
    if (!file.files.length || busy) return;

    var selected = file.files[0];
    var extension = selected.name.split('.').pop().toLowerCase();
    if (extension !== 'csv' && extension !== 'sql') {
      showError('Upload a CSV file or a SQL dump containing student records.');
      return;
    }
    if (selected.size === 0) {
      showError('The selected file is empty.');
      return;
    }
    if (selected.size > 5 * 1024 * 1024) {
      showError('The file must be 5 MB or smaller.');
      return;
    }

    busy = true;
    button.disabled = true;
    loading.classList.remove('hidden');
    loading.classList.add('flex');
    message.textContent = 'Uploading your file...';
    setProgress(1);

    var request = new XMLHttpRequest();
    var processingTimer;
    request.open('POST', form.action || window.location.href);
    request.timeout = 300000;

    request.upload.addEventListener('progress', function (event) {
      if (event.lengthComputable) {
        setProgress(1 + (event.loaded / event.total) * 69);
      }
    });

    request.upload.addEventListener('load', function () {
      setProgress(Math.max(70, Number(progress.getAttribute('aria-valuenow'))));
      message.textContent = 'Checking student data and looking for duplicates...';
      processingTimer = window.setInterval(function () {
        var current = Number(progress.getAttribute('aria-valuenow'));
        if (current < 95) setProgress(current + 1);
      }, 350);
    });

    request.addEventListener('load', function () {
      window.clearInterval(processingTimer);
      var result;
      try {
        result = JSON.parse(request.responseText);
      } catch (error) {
        showError('The server returned an unreadable response. Check the PHP upload limit and try again.');
        return;
      }

      if (request.status < 200 || request.status >= 300 || !result.ok) {
        showError(result.error || 'Unable to check the uploaded file.');
        return;
      }
      if (!result.token) {
        showError('The server did not create an import preview. Please upload the file again.');
        return;
      }

      setProgress(100);
      message.textContent = 'Check complete. Opening the data preview...';
      window.setTimeout(function () {
        window.location.href = 'students.php?show_import=' + encodeURIComponent(result.token);
      }, 300);
    });

    request.addEventListener('error', function () {
      window.clearInterval(processingTimer);
      showError('Network error while uploading student data. Check your connection and try again.');
    });

    request.addEventListener('timeout', function () {
      window.clearInterval(processingTimer);
      showError('The server took too long to check this file. Try a smaller file or upload again.');
    });

    request.send(new FormData(form));
  }

  button.addEventListener('click', function () {
    file.click();
  });

  file.addEventListener('change', function (event) {
    event.stopImmediatePropagation();
    handleFileChange();
  }, true);
})();
