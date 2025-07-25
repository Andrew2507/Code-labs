document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Файл не выбран';
            const label = this.nextElementSibling;
            if (label && label.classList.contains('custom-file-label')) {
                label.textContent = fileName;
            }
        });
    });

    if (document.querySelector('.list-group-item')) {
        setInterval(updateSubmissions, 5000);
    }
});

function updateSubmissions() {
    const taskId = new URLSearchParams(window.location.search).get('id');
    if (!taskId) return;

    fetch(`api.php?action=get_submissions&task_id=${taskId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.querySelector('.list-group');
                if (container) {
                    container.innerHTML = data.html;
                }
            }
        });
}