</main>
<footer class="footer">
    <div class="container">&copy; <?= date('Y') ?> <?= h(APP_NAME) ?>. Open Monday to Saturday, <?= h(date('g:i A', strtotime(CLINIC_OPEN))) ?> - <?= h(date('g:i A', strtotime(CLINIC_CLOSE))) ?> &middot; <a href="<?= h(url('privacy.php')) ?>">Privacy Policy</a></div>
</footer>
</div>
<script src="<?= h(url('assets/js/app.js')) ?>"></script>
<?php foreach (($pageScripts ?? []) as $__s): ?>
<script src="<?= h(url('assets/js/' . $__s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
