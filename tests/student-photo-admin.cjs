const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const handlers = {};
const element = name => ({ value: '1', files: [], hidden: false, textContent: '', addEventListener(type, fn) { handlers[name + ':' + type] = fn; } });
const elements = Object.fromEntries(['file','canvas','controls','zoom','x','y','status','cancel','confirm'].map(n => [n, element(n)]));
const context = { fillRect() {}, drawImage() {} };
elements.canvas.getContext = () => context;
const form = element('form'); form.elements = { photo_consent: { checked: false } };
const editor = { closest: () => form, querySelector: query => elements[query.match(/data-photo-(.*)\]/)[1]] };
let dimensions = [2400, 3200], output;
const sandbox = {
  document: { querySelector: () => editor, createElement: () => (output = { getContext: () => context, toBlob(fn) { fn({ jpeg: true }); } }) },
  URL: { createObjectURL: () => 'blob:test', revokeObjectURL() {} },
  Image: class { constructor() { [this.naturalWidth, this.naturalHeight] = dimensions; } async decode() {} },
  window: { addEventListener() {} },
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../public/assets/student-photo-admin.js'), 'utf8'), sandbox);
(async () => {
  for (const size of [[2400,3200], [600,800], [1920,1080], [333,777]]) {
    dimensions = size;
    elements.file.files = [{ type: 'image/jpeg', size: 1000 }];
    await handlers['file:change']();
    let blocked = false;
    handlers['form:submit']({ preventDefault() { blocked = true; } });
    assert.ok(blocked, 'Unconfirmed crop blocked');
    handlers['confirm:click']();
    assert.equal(output.width * 4, output.height * 3);
    assert.ok(output.width <= 1200 && output.height <= 1600);
    assert.ok(output.width <= size[0] && output.height <= size[1], 'No upscaling');
    const sent = [];
    handlers['form:formdata']({ formData: { set(...args) { sent.push(args); } } });
    assert.equal(sent[0][0], 'student_photo');
    handlers['zoom:input']();
    const invalidated = [];
    handlers['form:formdata']({ formData: { set(...args) { invalidated.push(args); } } });
    assert.equal(invalidated.length, 0, 'Adjusting invalidates confirmed image');
    handlers['cancel:click']();
    assert.equal(elements.controls.hidden, true);
  }
  console.log('Crop dimensions, confirmation, FormData and cancellation checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
