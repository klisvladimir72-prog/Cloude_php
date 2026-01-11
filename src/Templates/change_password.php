<?php ob_start(); ?><div class="container">
  <h1>Смена пароля</h1>
  <p>
    Вы вошли как: <strong><?php echo htmlspecialchars($login ?? 'Гость'); ?></strong>
  </p>

  <form id="change-password-form">
    <div>
      <label for="current_password">Текущий пароль:</label>
      <input type="password" id="current_password" name="current_password" required />
    </div>

    <div>
      <label for="new_password">Новый пароль:</label>
      <input type="password" id="new_password" name="new_password" required />
    </div>

    <div>
      <label for="confirm_new_password">Подтвердите новый пароль:</label>
      <input type="password" id="confirm_new_password" name="confirm_new_password" required />
    </div>

    <button type="submit">Сменить пароль</button>
  </form>

  <div id="message-container"></div>
  <a href="/">Вернуться на главную</a>
</div>

<script>
  document.getElementById('change-password-form').addEventListener('submit', async (e) => {
    e.preventDefault()
  
    const formData = new FormData(e.target)
    const data = Object.fromEntries(formData)
  
    // Проверка совпадения паролей на клиенте (дополнительно)
    if (data.new_password !== data.confirm_new_password) {
      document.getElementById('message-container').innerHTML = '<div class="message error">Новый пароль и подтверждение не совпадают.</div>'
      return
    }
  
    try {
      const response = await fetch('/change_password', {
        method: 'POST',
        body: JSON.stringify(data),
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'include'
      })
  
      const result = await response.json()
  
      if (result.success) {
        document.getElementById('message-container').innerHTML = '<div class="message success">Пароль успешно изменён!</div>'
        // Очищаем форму
        e.target.reset()
        // (Опционально) Перенаправить на главную или logout
        // setTimeout(() => window.location.href = '/', 2000); // Через 2 секунды
      } else {
        document.getElementById('message-container').innerHTML = `<div class="message error">${result.message}</div>`
      }
    } catch (error) {
      console.error('Error:', error)
      document.getElementById('message-container').innerHTML = '<div class="message error">Произошла ошибка при смене пароля.</div>'
    }
  })
</script>

<style>
  /* Стили для формы смены пароля */
  #change-password-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-width: 400px;
    margin: 2rem auto;
    padding: 2rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    background-color: #f9f9f9;
  }
  
  #change-password-form label {
    font-weight: bold;
  }
  
  #change-password-form input {
    padding: 0.75rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1rem;
  }
  
  #change-password-form button {
    padding: 0.75rem 1.5rem;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
  }
  
  #change-password-form button:hover {
    background-color: #218838;
  }
  
  .message {
    padding: 0.75rem;
    border-radius: 4px;
    margin-top: 1rem;
  }
  
  .message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }
  
  .message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
  }
</style><?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
