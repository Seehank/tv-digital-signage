document.addEventListener('DOMContentLoaded', function () {
  var designWidth = 1920;
  var designHeight = 1080;
  var layers = document.getElementById('merk-signage-layers');
  var addButton = document.getElementById('merk-signage-add-layer');
  var canvas = document.getElementById('merk-signage-canvas');
  var backgroundInput = document.getElementById('merk-signage-background-url');

  if (!layers || !addButton || !canvas || !window.MerkSignageStudio || !window.MerkSignageStudio.rowTemplate) {
    return;
  }

  var nextIndex = Number(layers.dataset.nextIndex || '0');
  var scale = 1;
  var dragging = null;

  function rows() {
    return Array.prototype.slice.call(layers.querySelectorAll('.merk-signage-layer-row'));
  }

  function field(row, name) {
    return row.querySelector('[name*="[' + name + ']"]');
  }

  function numberField(row, name, fallback) {
    var input = field(row, name);
    var value = input ? Number(input.value) : fallback;
    return Number.isFinite(value) ? value : fallback;
  }

  function updateScale() {
    var viewport = canvas.parentElement;
    scale = Math.min(viewport.clientWidth / designWidth, viewport.clientHeight / designHeight);
    scale = scale > 0 ? scale : 1;
    canvas.style.transform = 'scale(' + scale + ')';
  }

  function refreshPreview() {
    canvas.innerHTML = '';
    canvas.style.backgroundImage = backgroundInput && backgroundInput.value ? 'url("' + backgroundInput.value.replace(/"/g, '\\"') + '")' : 'none';

    rows().forEach(function (row, index) {
      var element = document.createElement('div');
      var text = field(row, 'text');
      var color = field(row, 'color');
      var weight = field(row, 'font_weight');
      var align = field(row, 'align');
      var width = numberField(row, 'width', 300);

      element.className = 'merk-signage-preview-layer';
      element.dataset.layerKey = row.dataset.layerKey || String(index);
      element.style.left = numberField(row, 'x', 0) + 'px';
      element.style.top = numberField(row, 'y', 0) + 'px';
      element.style.width = width + 'px';
      element.style.fontSize = numberField(row, 'font_size', 32) + 'px';
      element.style.color = color ? color.value : '#ffffff';
      element.style.fontWeight = weight ? weight.value : '400';
      element.style.textAlign = align ? align.value : 'left';
      element.textContent = text ? text.value : '';
      canvas.appendChild(element);
    });

    updateScale();
  }

  addButton.addEventListener('click', function (event) {
    event.preventDefault();
    var wrapper = document.createElement('div');
    wrapper.innerHTML = window.MerkSignageStudio.rowTemplate.replace(/__INDEX__/g, String(nextIndex));

    if (wrapper.firstElementChild) {
      layers.appendChild(wrapper.firstElementChild);
      nextIndex += 1;
      refreshPreview();
    }
  });

  layers.addEventListener('click', function (event) {
    var removeButton = event.target.closest('.merk-signage-remove');
    if (!removeButton) {
      return;
    }

    event.preventDefault();
    var row = removeButton.closest('.merk-signage-layer-row');
    if (row) {
      row.remove();
      refreshPreview();
    }
  });

  document.addEventListener('input', function (event) {
    if ((backgroundInput && event.target === backgroundInput) || event.target.closest('.merk-signage-layer-row')) {
      refreshPreview();
    }
  });

  canvas.addEventListener('pointerdown', function (event) {
    var element = event.target.closest('.merk-signage-preview-layer');
    if (!element) {
      return;
    }

    event.preventDefault();
    dragging = {
      element: element,
      startX: event.clientX,
      startY: event.clientY,
      originX: Number.parseFloat(element.style.left) || 0,
      originY: Number.parseFloat(element.style.top) || 0,
      width: Number.parseFloat(element.style.width) || 0,
      height: element.offsetHeight || 0,
      pointerId: event.pointerId
    };
    element.setPointerCapture(event.pointerId);
  });

  document.addEventListener('pointermove', function (event) {
    if (!dragging) {
      return;
    }

    var x = Math.round(dragging.originX + (event.clientX - dragging.startX) / scale);
    var y = Math.round(dragging.originY + (event.clientY - dragging.startY) / scale);
    x = Math.max(0, Math.min(designWidth - dragging.width, x));
    y = Math.max(0, Math.min(designHeight - dragging.height, y));
    dragging.element.style.left = x + 'px';
    dragging.element.style.top = y + 'px';
  });

  document.addEventListener('pointerup', function () {
    if (!dragging) {
      return;
    }

    var row = layers.querySelector('[data-layer-key="' + dragging.element.dataset.layerKey + '"]');
    if (row) {
      field(row, 'x').value = Math.round(Number.parseFloat(dragging.element.style.left) || 0);
      field(row, 'y').value = Math.round(Number.parseFloat(dragging.element.style.top) || 0);
    }

    if (dragging.element.hasPointerCapture(dragging.pointerId)) {
      dragging.element.releasePointerCapture(dragging.pointerId);
    }
    dragging = null;
  });

  window.addEventListener('resize', updateScale);
  refreshPreview();
});
