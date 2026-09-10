(() => {
  'use strict';
  const editor = document.querySelector('[data-photo-editor]');
  if (!editor) return;
  const form = editor.closest('form');
  const get = name => editor.querySelector(`[data-photo-${name}]`);
  const input = get('file'), canvas = get('canvas'), controls = get('controls');
  const zoom = get('zoom'), x = get('x'), y = get('y'), status = get('status');
  let photo = null, blob = null, generation = 0, loading = false;
  function rectangle() {
    const scale = Math.max(300 / photo.naturalWidth, 400 / photo.naturalHeight) * Number(zoom.value);
    const width = 300 / scale, height = 400 / scale;
    return [(photo.naturalWidth - width) * x.value / 100, (photo.naturalHeight - height) * y.value / 100, width, height];
  }
  function draw() {
    if (!photo) return;
    generation++; blob = null;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, 300, 400);
    ctx.drawImage(photo, ...rectangle(), 0, 0, 300, 400);
    status.textContent = '位置・拡大率を調整し、この範囲で確定してください。';
  }
  function reset() {
    generation++; photo = null; blob = null; loading = false;
    controls.hidden = true; input.value = ''; status.textContent = '';
    form.elements.photo_consent.checked = false;
  }
  input.addEventListener('change', async () => {
    const file = input.files[0];
    reset();
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      status.textContent = '10MB以下のJPEG・PNG・WebPを選択してください。'; return;
    }
    loading = true;
    const current = generation, address = URL.createObjectURL(file), image = new Image();
    try {
      image.src = address; await image.decode();
      if (current !== generation) return;
      if (image.naturalWidth * image.naturalHeight > 40000000 || image.naturalWidth < 3 || image.naturalHeight < 4) throw new Error();
      photo = image; zoom.value = '1'; x.value = y.value = '50'; controls.hidden = false; draw();
    } catch (_) { if (current === generation) status.textContent = '画像を読み込めません。4000万画素以下の別の画像を選択してください。'; }
    finally { URL.revokeObjectURL(address); if (photo === image || current === generation) loading = false; }
  });
  [zoom, x, y].forEach(control => control.addEventListener('input', draw));
  get('cancel').addEventListener('click', reset);
  get('confirm').addEventListener('click', () => {
    if (!photo) return;
    const current = generation, rect = rectangle();
    const units = Math.floor(Math.min(400, rect[2] / 3, rect[3] / 4));
    if (units < 1) { status.textContent = '拡大率を下げてください。'; return; }
    const output = document.createElement('canvas');
    output.width = units * 3; output.height = units * 4;
    const ctx = output.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, output.width, output.height);
    ctx.drawImage(photo, ...rect, 0, 0, output.width, output.height);
    output.toBlob(result => {
      if (current !== generation) return;
      blob = result;
      status.textContent = result ? `確定しました（横${output.width}×縦${output.height}）。学生情報を保存すると反映されます。` : 'トリミングに失敗しました。';
    }, 'image/jpeg', 0.9);
  });
  let submitting = false;
  form.addEventListener('submit', event => {
    if (submitting || loading || (photo && !blob) || (blob && !form.elements.photo_consent.checked)) {
      event.preventDefault();
      status.textContent = submitting ? '保存中です。' : 'トリミングの確定と掲載同意の確認を済ませてください。';
      return;
    }
    submitting = true;
    if (blob) status.textContent = '写真と学生情報を保存しています…';
  });
  form.addEventListener('formdata', event => { if (blob) event.formData.set('student_photo', blob, 'photo.jpg'); });
  window.addEventListener('pageshow', () => { submitting = false; });
})();
