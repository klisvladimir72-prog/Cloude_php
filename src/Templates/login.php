<?php ob_start(); ?>
<div class="form-container">
    <h2>Вход в аккаунт</h2>
    <form id="login-form">
        <label for="email_or_login">Email или Логин:</label> <!-- Изменили текст -->
        <input type="text" id="email_or_login" name="email_or_login" required> <!-- Изменили name -->

        <label for="password">Пароль:</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Войти</button>
    </form>
    <p>Нет аккаунта? <a href="/register">Зарегистрируйтесь</a></p>
    <div id="login-message"></div>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const response = await fetch('/login', {
        method: 'POST',
        body: JSON.stringify(Object.fromEntries(formData)),
        headers: { 'Content-Type': 'application/json' }
    });
    const data = await response.json();
    if(data.success) {
        window.location.href = data.redirect;
    } else {
        document.getElementById('login-message').innerText = data.message;
    }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>