<footer class="mt-auto py-4" style="background: var(--card-bg); border-top: 1px solid var(--border-color); transition: background 0.3s ease;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <span class="small" style="color: var(--text-main); opacity: 0.6;">
                        &copy; <?= date('Y') ?> <strong style="color: var(--primary-accent);">Staff Panel</strong>. Tous droits réservés.
                    </span>
                </div>
                <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                    <span class="badge border me-2" style="background: var(--table-header); color: var(--text-main); font-weight: 500; border-color: var(--border-color) !important;">
                        <i class="bi bi-clock me-1 text-primary"></i> <?= date('H:i') ?>
                    </span>
                    <span class="badge border" style="background: var(--table-header); color: var(--text-main); opacity: 0.8; font-weight: 500; border-color: var(--border-color) !important;">
                        v3.2.0-Flash
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialisation des tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Petite astuce : Si tu veux que l'heure s'actualise sans rafraîchir la page
        /*
        setInterval(() => {
            const now = new Date();
            const timeString = now.getHours().toString().padStart(2, '0') + ":" + 
                               now.getMinutes().toString().padStart(2, '0');
            document.querySelector('.bi-clock').nextSibling.textContent = " " + timeString;
        }, 10000);
        */
    </script>
</body>
</html>