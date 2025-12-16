<?php ob_start(); ?>
<div class="form-container">
    <h2>Регистрация</h2>
    <form id="register-form">
        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Пароль:</label>
        <input type="password" name="password" required>

        <button type="submit">Зарегистрироваться</button>
    </form>
    <p>Уже есть аккаунт? <a href="/login">Войти</a></p>
    <div id="register-message"></div>
</div>

<script>
    document.getElementById('register-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const response = await fetch('/register', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(formData)), // <-- JSON-тело
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const data = await response.json();
        document.getElementById('register-message').innerText = data.message;

        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        }
    });
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php'; ?>