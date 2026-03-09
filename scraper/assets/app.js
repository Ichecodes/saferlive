(function () {
    var form = document.getElementById('run-form');
    var button = document.getElementById('run-btn');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', function () {
        button.disabled = true;
        button.textContent = 'Running...';
    });
})();

