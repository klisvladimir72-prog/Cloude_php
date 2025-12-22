<?php ob_start(); ?>
<div class="form-container">
    <h2>Регистрация</h2> <!-- Исправили заголовок -->
    <form id="register-form"> <!-- Изменили id формы -->
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="login">Логин:</label> <!-- Новое поле -->
        <input type="text" id="login" name="login" required minlength="3" maxlength="20"> <!-- Ограничения на длину, если нужно -->

        <label for="password">Пароль:</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Зарегистрироваться</button> <!-- Изменили текст кнопки -->
    </form>
    <p>Уже есть аккаунт? <a href="/login">Войдите</a></p> <!-- Изменили текст ссылки -->
    <div id="register-message"></div> <!-- Изменили id сообщения -->
</div>

<script>
document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const response = await fetch('/register', {
        method: 'POST',
        body: JSON.stringify(Object.fromEntries(formData)),
        headers: { 'Content-Type': 'application/json' }
    });
    const data = await response.json();
    if(data.success) {
        window.location.href = data.redirect;
    } else {
        document.getElementById('register-message').innerText = data.message;
    }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>