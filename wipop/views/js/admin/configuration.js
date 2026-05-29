(function () {
  'use strict';

  function alertClass(status) {
    if (status === 'success') {
      return 'alert-success';
    }

    if (status === 'warning') {
      return 'alert-warning';
    }

    return 'alert-danger';
  }

  function showResult(result, status, message) {
    result.className = 'alert d-print-none mt-3 ' + alertClass(status);
    result.textContent = message;
  }

  function hideResult(result) {
    result.className = 'alert d-none mt-3';
    result.textContent = '';
  }

  function parseJsonResponse(response) {
    return response.json().catch(function () {
      return null;
    });
  }

  function sectionSnapshot(section) {
    var data = new URLSearchParams();
    var fields = section.querySelectorAll('input, select, textarea');

    for (var index = 0; index < fields.length; index += 1) {
      var field = fields[index];

      if (!field.name || field.disabled) {
        continue;
      }

      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
        continue;
      }

      if (field.tagName === 'SELECT' && field.multiple) {
        for (var optionIndex = 0; optionIndex < field.options.length; optionIndex += 1) {
          if (field.options[optionIndex].selected) {
            data.append(field.name, field.options[optionIndex].value);
          }
        }

        continue;
      }

      data.append(field.name, field.value);
    }

    return data.toString();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var verifyButton = document.getElementById('wipop-verify-credentials-button');
    var saveButtons = document.querySelectorAll('[data-wipop-save-button]');
    var result = document.getElementById('wipop-verify-credentials-result');
    var form = document.querySelector('[data-wipop-settings-form]');
    var sections = document.querySelectorAll('[data-wipop-section]');

    if (!verifyButton || saveButtons.length === 0 || !result || !form || sections.length === 0) {
      return;
    }

    var verifyUrl = verifyButton.dataset.verifyUrl || '';
    var csrfToken = verifyButton.dataset.csrfToken || '';

    if (!verifyUrl || !csrfToken) {
      return;
    }

    var canVerifyStoredSettings = !verifyButton.disabled;
    var defaultLabel = verifyButton.textContent.trim();
    var verifyingLabel = verifyButton.dataset.verifyingLabel || defaultLabel;
    var unsavedMessage = verifyButton.dataset.unsavedMessage || '';
    var errorMessage = verifyButton.dataset.errorMessage || 'Verification failed.';

    var initialSectionSnapshots = {};
    var sectionsByName = {};

    for (var sectionIndex = 0; sectionIndex < sections.length; sectionIndex += 1) {
      var section = sections[sectionIndex];
      var sectionName = section.dataset.wipopSection;

      sectionsByName[sectionName] = section;
      initialSectionSnapshots[sectionName] = sectionSnapshot(section);
    }

    function sectionHasChanges(sectionName) {
      var section = sectionsByName[sectionName];

      return section && sectionSnapshot(section) !== initialSectionSnapshots[sectionName];
    }

    function syncButtonState() {
      var configurationHasChanges = sectionHasChanges('configuration');

      verifyButton.disabled = !canVerifyStoredSettings || configurationHasChanges;
      verifyButton.title = configurationHasChanges ? unsavedMessage : '';

      for (var index = 0; index < saveButtons.length; index += 1) {
        saveButtons[index].disabled = !sectionHasChanges(saveButtons[index].dataset.wipopSaveButton);
      }

      if (configurationHasChanges) {
        hideResult(result);
      }
    }

    form.addEventListener('input', syncButtonState);
    form.addEventListener('change', syncButtonState);
    syncButtonState();

    verifyButton.addEventListener('click', function () {
      verifyButton.disabled = true;
      verifyButton.textContent = verifyingLabel;
      hideResult(result);

      var body = new URLSearchParams();
      body.append('_csrf_token', csrfToken);

      fetch(verifyUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
        },
        body: body.toString(),
      })
        .then(function (response) {
          return parseJsonResponse(response).then(function (data) {
            var status = data && data.status;

            if (!response.ok || status === 'error' || (status !== 'success' && status !== 'warning')) {
              throw new Error((data && data.message) || errorMessage);
            }

            return data;
          });
        })
        .then(function (data) {
          showResult(result, data.status || 'success', data.message || '');
        })
        .catch(function (error) {
          showResult(result, 'error', error.message || errorMessage);
        })
        .finally(function () {
          verifyButton.textContent = defaultLabel;
          syncButtonState();
        });
    });
  });
})();
