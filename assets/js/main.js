/* =====================================================================
   OMNIA EDGE STRATEGIC SERVICES — STOREFRONT JS
   Vanilla JS only. No frameworks, no build step.
   ===================================================================== */

document.addEventListener('DOMContentLoaded', function () {

  /* Keep legacy root-relative links and form actions inside the project
     when running from an XAMPP subfolder such as /omnia-edge. */
  var basePath = document.documentElement.getAttribute('data-base-path') || '';
  if (basePath) {
    document.querySelectorAll('a[href^="/"], form[action^="/"]').forEach(function (element) {
      var attribute = element.tagName === 'FORM' ? 'action' : 'href';
      var value = element.getAttribute(attribute);
      if (value && value.indexOf('//') !== 0 && value.indexOf(basePath + '/') !== 0) {
        element.setAttribute(attribute, basePath + value);
      }
    });
  }

  /* ---------------- Mobile menu toggle ---------------- */
  var menuToggle = document.getElementById('menuToggle');
  var mainNav = document.getElementById('mainNav');
  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('open');
      menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  /* ---------------- Product gallery thumbnails ---------------- */
  var galleryMain = document.getElementById('galleryMain');
  var thumbButtons = document.querySelectorAll('[data-gallery-thumb]');
  if (galleryMain && thumbButtons.length) {
    thumbButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var fullUrl = btn.getAttribute('data-full');
        galleryMain.src = fullUrl;
        thumbButtons.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
      });
    });
  }

  /* ---------------- Product detail quantity stepper ---------------- */
  document.querySelectorAll('[data-qty-stepper]').forEach(function (wrap) {
    var input = wrap.querySelector('input[type="number"]');
    var min = parseInt(input.getAttribute('min') || '1', 10);
    var max = parseInt(input.getAttribute('max') || '9999', 10);
    wrap.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var val = parseInt(input.value || '1', 10);
        if (btn.dataset.step === 'up' && val < max) val++;
        if (btn.dataset.step === 'down' && val > min) val--;
        input.value = val;
      });
    });
  });

  /* ---------------- Cart page: quantity forms auto-submit on change ---------------- */
  document.querySelectorAll('.cart-qty-form input[type="number"]').forEach(function (input) {
    input.addEventListener('change', function () {
      var min = parseInt(input.getAttribute('min') || '1', 10);
      var max = parseInt(input.getAttribute('max') || '9999', 10);
      var val = parseInt(input.value || '1', 10);
      if (val < min) val = min;
      if (val > max) val = max;
      input.value = val;
      input.form.submit();
    });
  });

  /* ---------------- Image preview for admin upload forms ---------------- */
  document.querySelectorAll('[data-image-input]').forEach(function (input) {
    input.addEventListener('change', function () {
      var previewId = input.getAttribute('data-image-input');
      var preview = document.getElementById(previewId);
      if (!preview) return;
      preview.innerHTML = '';
      Array.prototype.forEach.call(input.files, function (file) {
        if (!file.type.match('image.*')) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          var img = document.createElement('img');
          img.src = e.target.result;
          img.className = 'preview-thumb';
          preview.appendChild(img);
        };
        reader.readAsDataURL(file);
      });
    });
  });

  /* ---------------- Generic confirm-before-delete ---------------- */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) {
      var msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  /* ---------------- Auto-dismiss flash alerts ---------------- */
  document.querySelectorAll('.alert[data-autohide]').forEach(function (alertEl) {
    setTimeout(function () {
      alertEl.style.transition = 'opacity 0.4s ease';
      alertEl.style.opacity = '0';
      setTimeout(function () { alertEl.remove(); }, 400);
    }, 4000);
  });

});
