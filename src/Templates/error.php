<?php ob_start(); ?>

<div class="container">
    <h1>Ошибка</h1>
    <p><?php echo htmlspecialchars($message ?? 'Произошла неизвестная ошибка.'); ?></p>
    <a href="/">Вернуться на главную</a>
</div>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>