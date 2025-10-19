            </div>
        </div>
    </div>
</div>

<!-- Mobile Content -->
<div class="d-md-none content-area">
    <?php displayAlert(); ?>
</div>

<!-- Mobile Bottom Navigation -->
<div class="bottom-nav d-md-none">
    <div class="nav-items">
        <a href="index.php" class="nav-item <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">
            <i class="bi bi-house-door-fill"></i>
            <span>หน้าหลัก</span>
        </a>
        <a href="qrcode.php" class="nav-item <?php echo ($currentPage == 'qrcode') ? 'active' : ''; ?>">
            <i class="bi bi-qr-code"></i>
            <span>QR Code</span>
        </a>
        <a href="history.php" class="nav-item <?php echo ($currentPage == 'history') ? 'active' : ''; ?>">
            <i class="bi bi-clock-history"></i>
            <span>ประวัติ</span>
        </a>
        <a href="vehicle.php" class="nav-item <?php echo ($currentPage == 'vehicle') ? 'active' : ''; ?>">
            <i class="bi bi-car-front-fill"></i>
            <span>ยานพาหนะ</span>
        </a>
        <a href="profile.php" class="nav-item <?php echo ($currentPage == 'profile') ? 'active' : ''; ?>">
            <i class="bi bi-person-circle"></i>
            <span>โปรไฟล์</span>
        </a>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Prevent zoom on mobile
document.addEventListener('touchstart', function(event) {
    if (event.touches.length > 1) {
        event.preventDefault();
    }
}, { passive: false });

// Auto hide alerts
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

</body>
</html>
