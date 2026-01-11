<?php ob_start(); ?><div class="container">
  <h1>Панель администратора: Управление пользователями</h1>
  <p>
    Добро пожаловать, <strong><?php echo htmlspecialchars($login ?? 'Admin'); ?></strong>!
  </p>
  <a href="/">Вернуться на главную</a>

  <div class="section">
    <h2>Список пользователей</h2>
    <table class="users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Email</th>
          <th>Логин</th>
          <th>Дата регистрации</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?><tr data-user-id="<?php echo $user['id']; ?>">
          <td><?php echo htmlspecialchars($user['id']); ?></td>
          <td>
            <span class="user-field" data-field="email"><?php echo htmlspecialchars($user['email']); ?></span>
            <input type="text" class="user-input" data-field="email" value="<?php echo htmlspecialchars($user['email']); ?>" style="display: none;" />
          </td>
          <td>
            <span class="user-field" data-field="login"><?php echo htmlspecialchars($user['login']); ?></span>
            <input type="text" class="user-input" data-field="login" value="<?php echo htmlspecialchars($user['login']); ?>" style="display: none;" />
          </td>
          <td><?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?></td>
          <td>
            <button class="btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">Редактировать</button>
            <button class="btn-reset-password" onclick="openResetPasswordModal(<?php echo $user['id']; ?>)">Сбросить пароль</button>
            <button class="btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">Удалить</button>
            <button class="btn-save" onclick="saveUser(<?php echo $user['id']; ?>)" style="display: none;">Сохранить</button>
            <button class="btn-cancel" onclick="cancelEdit(<?php echo $user['id']; ?>)" style="display: none;">Отмена</button>
          </td>
        </tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Модальное окно для сброса пароля -->
<div id="reset-password-modal" class="modal" style="display: none;">
  <div class="modal-content">
    <span class="close" onclick="closeResetPasswordModal()">&times;</span>
    <h3>Сбросить пароль</h3>
    <p>Введите новый пароль для пользователя. Оставьте пустым для сброса на стандартный.</p>
    <input type="password" id="new-password-input" placeholder="Новый пароль (оставьте пустым для стандартного)" />
    <br /><br />
    <button class="btn-save" onclick="confirmResetPassword()">Сбросить</button>
    <button class="btn-cancel" onclick="closeResetPasswordModal()">Отмена</button>
  </div>
</div>

<script>
  // --- Переменные для модального окна ---
  let currentUserId = null
  
  function showEditButtons(userId) {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`)
    row.querySelectorAll('.btn-edit, .btn-reset-password, .btn-delete').forEach((btn) => (btn.style.display = 'none'))
    row.querySelectorAll('.btn-save, .btn-cancel').forEach((btn) => (btn.style.display = 'inline-block'))
    row.querySelectorAll('.user-field').forEach((span) => (span.style.display = 'none'))
    row.querySelectorAll('.user-input').forEach((input) => (input.style.display = 'inline-block'))
  }
  
  function hideEditButtons(userId) {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`)
    row.querySelectorAll('.btn-edit, .btn-reset-password, .btn-delete').forEach((btn) => (btn.style.display = 'inline-block'))
    row.querySelectorAll('.btn-save, .btn-cancel').forEach((btn) => (btn.style.display = 'none'))
    row.querySelectorAll('.user-field').forEach((span) => (span.style.display = 'inline'))
    row.querySelectorAll('.user-input').forEach((input) => (input.style.display = 'none'))
    // Восстановим исходные значения
    row.querySelectorAll('.user-input').forEach((input) => {
      const field = input.getAttribute('data-field')
      const originalValue = row.querySelector(`.user-field[data-field="${field}"]`).textContent
      input.value = originalValue
    })
  }
  
  // --- Функции для модального окна ---
  function openResetPasswordModal(userId) {
    currentUserId = userId
    document.getElementById('new-password-input').value = '' // Очищаем поле при открытии
    document.getElementById('reset-password-modal').style.display = 'block'
  }
  
  function closeResetPasswordModal() {
    currentUserId = null
    document.getElementById('reset-password-modal').style.display = 'none'
  }
  
  async function confirmResetPassword() {
    if (!currentUserId) {
      alert('Произошла ошибка: пользователь не выбран.')
      return
    }
  
    const newPassword = document.getElementById('new-password-input').value.trim()
  
    if (!confirm('Вы уверены, что хотите сбросить пароль этого пользователя?')) {
      return
    }
  
    try {
      const response = await fetch('/reset_password', {
        method: 'POST',
        body: JSON.stringify({ user_id: currentUserId, new_password: newPassword }),
        headers: { 'Content-Type': 'application/json' }
      })
      const data = await response.json()
  
      if (data.success) {
        alert('Пароль сброшен.')
        closeResetPasswordModal()
      } else {
        alert('Ошибка при сбросе пароля: ' + (data.message || 'Неизвестная ошибка'))
      }
    } catch (error) {
      console.error('Error:', error)
      alert('Произошла ошибка при сбросе пароля.')
    }
  }
  
  // --- Функции для кнопок ---
  function editUser(userId) {
    showEditButtons(userId)
  }
  
  function cancelEdit(userId) {
    hideEditButtons(userId)
  }
  
  async function saveUser(userId) {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`)
    const emailInput = row.querySelector('input[data-field="email"]')
    const loginInput = row.querySelector('input[data-field="login"]')
  
    const newEmail = emailInput.value.trim()
    const newLogin = loginInput.value.trim()
  
    // Валидация (простая)
    if (!newEmail || !newLogin) {
      alert('Email и Логин обязательны.')
      return
    }
  
    try {
      const response = await fetch('/update-user-field', {
        method: 'POST',
        body: JSON.stringify({ user_id: userId, field: 'email', value: newEmail }),
        headers: { 'Content-Type': 'application/json' }
      })
      const data = await response.json()
  
      if (data.success) {
        // Если email обновлен успешно, обновим и логин
        const response2 = await fetch('/update-user-field', {
          method: 'POST',
          body: JSON.stringify({ user_id: userId, field: 'login', value: newLogin }),
          headers: { 'Content-Type': 'application/json' }
        })
        const data2 = await response2.json()
  
        if (data2.success) {
          // Обновляем отображаемые значения
          row.querySelector('.user-field[data-field="email"]').textContent = newEmail
          row.querySelector('.user-field[data-field="login"]').textContent = newLogin
          alert('Пользователь обновлён.')
          hideEditButtons(userId)
        } else {
          alert('Ошибка при обновлении логина: ' + (data2.message || 'Неизвестная ошибка'))
        }
      } else {
        alert('Ошибка при обновлении email: ' + (data.message || 'Неизвестная ошибка'))
      }
    } catch (error) {
      console.error('Error:', error)
      alert('Произошла ошибка при сохранении.')
    }
  }
  
  async function deleteUser(userId) {
    if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) {
      return
    }
  
    try {
      const response = await fetch('/delete-user', {
        method: 'DELETE',
        body: JSON.stringify({ user_id: userId }),
        headers: { 'Content-Type': 'application/json' }
      })
      const data = await response.json()
  
      if (data.success) {
        alert('Пользователь удалён.')
        // Удаляем строку из таблицы
        document.querySelector(`tr[data-user-id="${userId}"]`).remove()
      } else {
        alert('Ошибка при удалении: ' + (data.message || 'Неизвестная ошибка'))
      }
    } catch (error) {
      console.error('Error:', error)
      alert('Произошла ошибка при удалении.')
    }
  }
  
  // Закрытие модального окна при клике вне его содержимого
  window.onclick = function (event) {
    const modal = document.getElementById('reset-password-modal')
    if (event.target === modal) {
      closeResetPasswordModal()
    }
  }
</script>

<style>
  /* Стили для админки пользователей */
  .users-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
  }
  
  .users-table th,
  .users-table td {
    border: 1px solid #ddd;
    padding: 0.75rem;
    text-align: left;
  }
  
  .users-table th {
    background-color: #f2f2f2;
  }
  
  .btn-edit,
  .btn-reset-password,
  .btn-delete,
  .btn-save,
  .btn-cancel {
    margin-right: 0.5rem;
    padding: 0.4rem 0.8rem;
    font-size: 0.9rem;
    border-radius: 4px;
    cursor: pointer;
  }
  
  .btn-edit {
    background-color: #007bff;
    color: white;
  }
  .btn-reset-password {
    background-color: #ffc107;
    color: black;
  }
  .btn-delete {
    background-color: #dc3545;
    color: white;
  }
  .btn-save {
    background-color: #28a745;
    color: white;
  }
  .btn-cancel {
    background-color: #6c757d;
    color: white;
  }
  
  .user-input {
    width: 150px;
    padding: 0.3rem;
    font-size: 0.9rem;
  }
  
  /* Стили для модального окна */
  .modal {
    display: block;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgb(0, 0, 0);
    background-color: rgba(0, 0, 0, 0.4);
  }
  
  .modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    /padding: 20px;
    border: 1px solid #888;
    width: 400px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  }
  
  .close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
  }
  
  .close:hover,
  .close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
  }
</style><?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
