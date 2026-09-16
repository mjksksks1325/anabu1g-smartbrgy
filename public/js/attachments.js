function previewAttachment(input) {
    const file = input.files[0];

    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        toast('Ang file ay masyadong malaki. Max 5MB lang.', 'red');
        input.value = '';
        return;
    }

    const url = URL.createObjectURL(file);

    document.getElementById('att-preview-img').src = url;
    document.getElementById('att-preview-name').textContent =
        '✅ ' + file.name;

    document.getElementById('att-preview').style.display = 'block';
    document.getElementById('att-dropzone').style.display = 'none';
}

function clearAttachment() {
    document.getElementById('f-attachment').value = '';
    document.getElementById('att-preview').style.display = 'none';
    document.getElementById('att-dropzone').style.display = 'block';
}

function handleAttachmentDrop(e) {
    e.preventDefault();

    document.getElementById('att-dropzone').style.borderColor =
        'var(--border)';

    const file = e.dataTransfer.files[0];

    if (!file) return;

    const input = document.getElementById('f-attachment');

    const dt = new DataTransfer();
    dt.items.add(file);

    input.files = dt.files;

    previewAttachment(input);
}