let attachmentPreviewUrl = null;

function clearAttachment() {
    if (attachmentPreviewUrl) URL.revokeObjectURL(attachmentPreviewUrl);
    attachmentPreviewUrl = null;
    document.getElementById('f-attachment').value = '';
    document.getElementById('att-preview-img').removeAttribute('src');
    document.getElementById('att-preview-name').textContent = '';
    document.getElementById('att-preview').style.display = 'none';
    document.getElementById('att-dropzone').style.display = 'block';
}

function previewAttachment(input) {
    const file = input.files[0];
    if (!file) return;
    const supportedImageExtension = /\.(jpe?g|png|gif|bmp|webp|avif|heic|heif)$/i.test(file.name);
    const isSupportedImage = file.type
        ? file.type.startsWith('image/') && file.type !== 'image/svg+xml' && supportedImageExtension
        : supportedImageExtension;
    if (!isSupportedImage || file.size > 5 * 1024 * 1024) {
        clearAttachment();
        toast('Pumili ng image file na hindi lalampas sa 5 MB. Hindi puwede ang PDF at iba pang document file.', 'red');
        return;
    }
    if (attachmentPreviewUrl) URL.revokeObjectURL(attachmentPreviewUrl);
    attachmentPreviewUrl = URL.createObjectURL(file);
    document.getElementById('att-preview-img').src = attachmentPreviewUrl;
    document.getElementById('att-preview-name').textContent = file.name;
    document.getElementById('att-preview').style.display = 'block';
    document.getElementById('att-dropzone').style.display = 'none';
}

function handleAttachmentDrop(event) {
    event.preventDefault();
    document.getElementById('att-dropzone').style.borderColor = '';
    const input = document.getElementById('f-attachment');
    if (!event.dataTransfer.files.length) return;
    input.files = event.dataTransfer.files;
    previewAttachment(input);
}

window.addEventListener('pagehide', () => {
    if (attachmentPreviewUrl) URL.revokeObjectURL(attachmentPreviewUrl);
});
