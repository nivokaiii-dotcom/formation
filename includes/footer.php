<footer class="mt-auto py-4" style="background: var(--bs-card-bg); border-top: 1px solid var(--bs-border-color); transition: background 0.3s ease;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <span class="small" style="color: var(--bs-body-color); opacity: 0.8;">
                        &copy; <?= date('Y') ?> <strong style="color: var(--bs-primary);">Formation Panel</strong>. Tous droits réservés.
                    </span>
                </div>

                <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                    <span class="badge border me-2" style="background: var(--bs-tertiary-bg); color: var(--bs-body-color); font-weight: 500; border-color: var(--bs-border-color) !important;">
                        <i class="bi bi-clock me-1 text-primary"></i> 
                        <span id="live-clock"><?= date('H:i') ?></span>
                    </span>
                    <span class="badge border" style="background: var(--bs-tertiary-bg); color: var(--bs-body-color); opacity: 0.8; font-weight: 500; border-color: var(--bs-border-color) !important;">
                        v3.2.0-Flash
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Initialisation des tooltips Bootstrap (Syntaxe moderne)
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            if (tooltipTriggerList.length > 0) {
                [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
            }

            // 2. Horloge interactive
            const clockElement = document.getElementById('live-clock');
            
            const updateClock = () => {
                if (!clockElement) return;
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                clockElement.textContent = `${hours}:${minutes}`;
            };

            // Mise à jour immédiate et répétition toutes les 10 secondes
            updateClock();
            setInterval(updateClock, 10000);
        });
    </script>
</body>
</html>