window.unlockTermsIfAtBottom = function () {
    const box = document.getElementById('tnc-scroll');
    const checkbox = document.getElementById('tnc-agree');
    const notice = document.getElementById('tnc-notice');

    if (!box || !checkbox) return;

    if (box.scrollTop + box.clientHeight >= box.scrollHeight - 50) {
        checkbox.disabled = false;

        if (notice) {
            notice.textContent = '✅ Nabasa mo na ang lahat ng tuntunin — maaari nang magpatuloy';
            notice.style.background = '#d1fae5';
            notice.style.borderColor = '#6ee7b7';
            notice.style.color = '#065f46';
        }
    }
}

window.onTncCheck = function () {
    const checkbox = document.getElementById('tnc-agree');
    const button = document.getElementById('btn-proceed-terms');

    if (!checkbox || !button) return;

    button.disabled = !checkbox.checked;
}

window.proceedFromTerms = function () {
    const checkbox = document.getElementById('tnc-agree');

    if (!checkbox || !checkbox.checked) return;

    showScreen('screen-doctype');
}