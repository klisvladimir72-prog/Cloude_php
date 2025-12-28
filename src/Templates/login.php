<?php ob_start(); ?><div class="form-container">
  <h2>Вход в аккаунт</h2>
  <form id="login-form">
    <label for="email_or_login">Email или Логин:</label> <!-- Изменили текст -->
    <input type="text" id="email_or_login" name="email_or_login" required /> <!-- Изменили name -->

    <label for="password">Пароль:</label>
    <input type="password" id="password" name="password" required />

    <button type="submit">Войти</button>
  </form>
  <div class="message-container"></div>
  <p>
    Нет аккаунта? <a href="/register">Зарегистрируйтесь</a>
  </p>
  <div id="login-message"></div>
</div>

<script>
  document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault()
  
    // Данные из формы
    const formData = new FormData(e.target)
    const formDataObj = Object.fromEntries(formData)
  
    // Подготовка данных для отправки
    let requestData = {
      email_or_login: formDataObj.email_or_login,
      password: formDataObj.password
    }
  
    try {
      const response = await fetch('/login', {
        method: 'POST',
        body: JSON.stringify(requestData),
        headers: { 'Content-Type': 'application/json' }
      })
  
      const data = await response.json()
  
      if (data.success && data.redirect) {
        window.location.href = data.redirect
      } else {
        document.getElementById('login-message').innerText = data.message || 'Произошла ошибка.'
      }
    } catch (error) {
      console.error('Ошибка при отправке запроса: ', error)
      document.getElementById('login-message').innerText = 'Ошибка соединения с сервером.'
    }
  })
</script><?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
