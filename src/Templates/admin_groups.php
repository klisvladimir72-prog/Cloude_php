<!-- File: templates/admin_groups.php -->

<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <title>Управление группами - Админ</title>
    <!-- Подключаем общий CSS -->
    <link rel="stylesheet" href="/assets/style.css" />

    <!-- Дополнительные стили для админки -->
    <style>
      /* Современные стили для админки */
      .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e9ecef;
      }
      
      .admin-header h1 {
        font-size: 1.8rem;
        color: #333;
        margin: 0;
      }
      
      .admin-header p {
        font-size: 1rem;
        color: #6c757d;
        margin: 0;
      }
      
      .admin-actions {
        display: flex;
        gap: 0.5rem;
      }
      
      .group-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      
      .group-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
      }
      
      .group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #eee;
      }
      
      .group-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #333;
        margin: 0;
      }
      
      .group-actions {
        display: flex;
        gap: 0.5rem;
      }
      
      .group-actions button {
        padding: 0.4rem 0.8rem;
        font-size: 0.9rem;
        border-radius: 6px;
        transition: all 0.2s ease;
      }
      
      .group-actions button:hover {
        opacity: 0.9;
      }
      
      .group-users {
        list-style: none;
        padding: 0;
        margin: 0;
      }
      
      .user-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        transition: background-color 0.2s ease;
      }
      
      .user-item:hover {
        background-color: #e9ecef;
      }
      
      .user-name {
        font-weight: 500;
        color: #333;
      }
      
      .user-actions button {
        padding: 0.3rem 0.6rem;
        font-size: 0.8rem;
        border-radius: 4px;
      }
      
      .add-user-form {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        align-items: center;
      }
      
      .add-user-form select {
        flex-grow: 1;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 0.9rem;
      }
      
      .add-user-form button {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
        border-radius: 6px;
      }
      
      /* Стили для редактирования группы */
      .editing-group .group-title {
        display: none;
      }
      
      .editing-group .group-input {
        display: inline-block;
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 0.9rem;
        margin-right: 0.5rem;
      }
      
      .editing-group .save-btn,
      .editing-group .cancel-btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.9rem;
        border-radius: 6px;
      }
      
      /* Анимация для сообщений */
      .message {
        animation: fadeIn 0.3s ease-out;
      }
      
      @keyframes fadeIn {
        from {
          opacity: 0;
        }
        to {
          opacity: 1;
        }
      }
      
      /* Адаптивность */
      @media (max-width: 768px) {
        .admin-header {
          flex-direction: column;
          align-items: stretch;
          text-align: center;
        }
      
        .admin-actions {
          margin-top: 1rem;
          justify-content: center;
        }
      
        .group-actions {
          flex-wrap: wrap;
        }
      
        .add-user-form {
          flex-direction: column;
          gap: 0.5rem;
        }
      
        .add-user-form select {
          width: 100%;
        }
      }
    </style>
  </head>
  <body>
    <!-- Навигация -->
    <div class="navbar">
      <a href="/" class="logo">Cloud Storage</a>
      <div class="nav-links">
        <a href="/manage-groups">Группы</a>
        <a href="/logout" class="btn-logout">Выйти</a>
      </div>
    </div>

    <!-- Основной контейнер -->
    <div class="container">
      <!-- Заголовок -->
      <div class="admin-header">
        <div>
          <h1>Панель администратора: Управление группами</h1>
          <p>
            Добро пожаловать, <strong><?php echo htmlspecialchars($login ?? 'Admin1'); ?></strong>!
          </p>
        </div>
        <div class="admin-actions">
          <a href="/" class="btn-login">Вернуться на главную</a>
        </div>
      </div>

      <!-- Сообщения -->
      <?php if (isset($message) && $message): ?><div class="message success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?> <?php if (isset($error) && $error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

      <!-- Секция: Создание новой группы -->
      <div class="form-section">
        <h3>Создать новую группу</h3>
        <form id="create-group-form" method="post" action="/create-group">
          <div style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" id="group_name" name="name" required placeholder="Введите имя группы" style="flex-grow: 1; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;" />
            <button type="submit" class="btn-share-folder" style="padding: 0.75rem 1.25rem; font-size: 1rem;">Создать</button>
          </div>
        </form>
      </div>

      <!-- Секция: Существующие группы -->
      <div class="section">
        <h2>Существующие группы</h2>

        <?php if (!empty($groups)): ?> <?php foreach ($groups as $group): ?><div class="group-card" data-group-id="<?php echo $group['id']; ?>">
          <div class="group-header">
            <h3 class="group-title"><?php echo htmlspecialchars($group['name']); ?></h3>
            <div class="group-actions">
              <button class="btn-share-folder edit-group-btn" data-id="<?php echo $group['id']; ?>">Редактировать</button>
              <button class="btn-delete delete-group-btn" data-id="<?php echo $group['id']; ?>">Удалить</button>
            </div>
          </div>

          <h4>Пользователи в группе:</h4>
          <ul class="group-users">
            <?php if (!empty($usersInGroups[$group['id']])): ?> <?php foreach ($usersInGroups[$group['id']] as $user): ?><li class="user-item">
              <span class="user-name"><?php echo htmlspecialchars($user['login'] ?? $user['email']); ?></span>
              <div class="user-actions">
                <button class="btn-delete remove-user-btn" data-user-id="<?php echo $user['id']; ?>" data-group-id="<?php echo $group['id']; ?>">Удалить из группы</button>
              </div>
            </li><?php endforeach; ?> <?php else: ?><li class="user-item" style="background: #f8f9fa; text-align: center; color: #6c757d;">Нет пользователей в этой группе.</li><?php endif; ?>
          </ul>

          <h4>Добавить пользователя в группу:</h4>
          <form class="add-user-form" method="post" action="/add-user-to-group">
            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>" />
            <select name="user_id" required style="flex-grow: 1;">
              <option value="">Выберите пользователя</option><?php foreach ($allUsers as $user): ?> <?php // Проверяем, не состоит ли пользователь уже в группе $isInGroup = false; if (!empty($usersInGroups[$group['id']])) { foreach ($usersInGroups[$group['id']] as $existingUser) { if ($existingUser['id'] == $user['id']) { $isInGroup = true; break; } } } ?> <?php if (!$isInGroup): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['login'] ?? $user['email']); ?></option><?php endif; ?> <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-share-folder">Добавить в группу</button>
          </form>
        </div><?php endforeach; ?> <?php else: ?><div class="group-card" style="text-align: center; padding: 2rem; background: #f8f9fa;">
          <p style="color: #6c757d; font-size: 1.1rem;">Группы не найдены.</p>
        </div><?php endif; ?>
      </div>
    </div>

    <!-- Встроенный JavaScript -->
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        // --- Вспомогательные функции для управления DOM ---
        // Функция для скрытия элемента
        function hideElement(element) {
          if (element) element.style.display = 'none'
        }
      
        // Функция для показа элемента
        function showElement(element) {
          if (element) element.style.display = 'inline-block' // или 'block', в зависимости от стилей
        }
      
        // Функция для поиска элементов в карточке группы
        function getGroupElements(groupCard) {
          return {
            title: groupCard.querySelector('.group-title'),
            input: groupCard.querySelector('.group-input'),
            editBtn: groupCard.querySelector('.edit-group-btn'),
            deleteBtn: groupCard.querySelector('.delete-group-btn'),
            saveBtn: groupCard.querySelector('.save-btn'),
            cancelBtn: groupCard.querySelector('.cancel-btn'),
            actionsContainer: groupCard.querySelector('.group-actions') // Контейнер для кнопок
          }
        }
      
        // --- Обработчики событий ---
      
        // Обработка формы создания группы
        const createGroupForm = document.getElementById('create-group-form')
        if (createGroupForm) {
          createGroupForm.addEventListener('submit', function (e) {
            e.preventDefault()
            const formData = new FormData(this)
            fetch('/create-group', {
              method: 'POST',
              body: JSON.stringify(Object.fromEntries(formData)),
              headers: { 'Content-Type': 'application/json' }
            })
              .then((response) => response.json())
              .then((data) => {
                if (data.success) {
                  alert(data.message || 'Группа создана.')
                  location.reload() // Перезагрузка страницы для обновления списка
                } else {
                  alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'))
                }
              })
              .catch((error) => {
                console.error('Error:', error)
                alert('Произошла ошибка при создании группы.')
              })
          })
        }
      
        // Обработка кнопки "Редактировать" (делегирование)
        document.addEventListener('click', function (e) {
          if (e.target.classList.contains('edit-group-btn')) {
            const button = e.target
            const groupId = button.getAttribute('data-id')
            const groupCard = button.closest('.group-card')
      
            const elements = getGroupElements(groupCard)
            const { title, editBtn, deleteBtn, actionsContainer } = elements
      
            // Проверяем, не находится ли уже группа в режиме редактирования
            if (groupCard.classList.contains('editing-group')) {
              // Если находится, сначала отменяем предыдущее редактирование
              const currentInput = elements.input
              const currentSaveBtn = elements.saveBtn
              const currentCancelBtn = elements.cancelBtn
              if (currentInput) currentInput.remove()
              if (currentSaveBtn) currentSaveBtn.remove()
              if (currentCancelBtn) currentCancelBtn.remove()
              showElement(title) // Показываем заголовок
              showElement(editBtn) // Показываем кнопки
              showElement(deleteBtn)
              groupCard.classList.remove('editing-group') // Убираем класс
            }
      
            // Скрываем оригинальные кнопки "Редактировать" и "Удалить"
            hideElement(editBtn)
            hideElement(deleteBtn)
      
            // Создаем инпут для редактирования
            const input = document.createElement('input')
            input.type = 'text'
            input.className = 'group-input'
            input.value = title.textContent.trim()
            input.placeholder = 'Введите новое имя группы'
      
            // Заменяем заголовок на инпут
            hideElement(title)
            title.parentNode.insertBefore(input, title)
      
            // Создаем кнопки "Сохранить" и "Отмена" и добавляем их в контейнер
            const saveBtn = document.createElement('button')
            saveBtn.type = 'button' // Убедимся, что это не submit
            saveBtn.className = 'btn-delete save-btn'
            saveBtn.setAttribute('data-id', groupId)
            saveBtn.textContent = 'Сохранить'
      
            const cancelBtn = document.createElement('button')
            cancelBtn.type = 'button' // Убедимся, что это не submit
            cancelBtn.className = 'btn-delete cancel-btn'
            cancelBtn.setAttribute('data-id', groupId)
            cancelBtn.textContent = 'Отмена'
      
            // Вставляем кнопки внутрь контейнера кнопок
            actionsContainer.appendChild(saveBtn)
            actionsContainer.appendChild(cancelBtn)
      
            groupCard.classList.add('editing-group') // Добавляем класс для стилей
          }
        })
      
        // Обработка кнопки "Сохранить" (делегирование)
        document.addEventListener('click', function (e) {
          if (e.target.classList.contains('save-btn')) {
            const button = e.target
            const groupId = button.getAttribute('data-id')
            const groupCard = button.closest('.group-card')
      
            // Проверяем, что карточка все еще в режиме редактирования
            if (!groupCard.classList.contains('editing-group')) {
              console.warn('Карточка группы не в режиме редактирования.')
              return // Прерываем выполнение
            }
      
            const elements = getGroupElements(groupCard)
            const { title, input, editBtn, deleteBtn } = elements
      
            const newName = input ? input.value.trim() : ''
      
            if (!newName) {
              alert('Имя группы не может быть пустым.')
              return
            }
      
            // Блокируем кнопку на время запроса
            button.disabled = true
            button.textContent = 'Сохранение...'
      
            fetch('/update-group', {
              method: 'POST',
              body: JSON.stringify({
                id: groupId,
                new_name: newName
              }),
              headers: { 'Content-Type': 'application/json' }
            })
              .then((response) => response.json())
              .then((data) => {
                // Разблокируем кнопку
                button.disabled = false
                button.textContent = 'Сохранить'
      
                if (data.success) {
                  // После успешного обновления, обновляем UI
                  if (title) {
                    title.textContent = newName
                    showElement(title)
                  } else {
                    console.error('Элемент .group-title не найден в карточке при сохранении.')
                  }
      
                  if (input) input.remove()
                  else console.warn('Элемент .group-input не найден при сохранении.')
                  button.remove() // Удаляем saveBtn
                  const cancelBtn = groupCard.querySelector('.cancel-btn') // Нужно найти cancelBtn заново
                  if (cancelBtn) cancelBtn.remove()
                  else console.warn('Кнопка .cancel-btn не найдена при сохранении.')
      
                  // Показываем оригинальные кнопки "Редактировать" и "Удалить" обратно
                  showElement(editBtn)
                  showElement(deleteBtn)
      
                  groupCard.classList.remove('editing-group') // Убираем класс
                } else {
                  alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'))
                }
              })
              .catch((error) => {
                // Разблокируем кнопку в случае ошибки
                button.disabled = false
                button.textContent = 'Сохранить'
                console.error('Error:', error)
                alert('Произошла ошибка при обновлении группы.')
              })
          }
        })
      
        // Обработка кнопки "Отмена" (делегирование)
        document.addEventListener('click', function (e) {
          if (e.target.classList.contains('cancel-btn')) {
            const button = e.target
            const groupCard = button.closest('.group-card')
      
            // Проверяем, что карточка в режиме редактирования
            if (!groupCard.classList.contains('editing-group')) {
              console.warn('Карточка группы не в режиме редактирования.')
              return // Прерываем выполнение
            }
      
            const elements = getGroupElements(groupCard)
            const { title, input, editBtn, deleteBtn } = elements
      
            if (title) showElement(title)
            else console.error('Элемент .group-title не найден при отмене.')
            if (input) input.remove()
            else console.warn('Элемент .group-input не найден при отмене.')
      
            button.remove() // Удаляем cancelBtn
            const saveBtn = groupCard.querySelector('.save-btn') // Нужно найти saveBtn заново
            if (saveBtn) saveBtn.remove()
            else console.warn('Кнопка .save-btn не найдена при отмене.')
      
            // Показываем оригинальные кнопки "Редактировать" и "Удалить" обратно
            showElement(editBtn)
            showElement(deleteBtn)
      
            groupCard.classList.remove('editing-group') // Убираем класс
          }
        })
      
        // Обработка кнопки "Удалить"
        document.addEventListener('click', function (e) {
          if (e.target.classList.contains('delete-group-btn')) {
            const button = e.target
            const groupId = button.getAttribute('data-id')
            if (confirm('Вы уверены, что хотите удалить эту группу?')) {
              fetch('/delete-group', {
                method: 'POST',
                body: JSON.stringify({ id: groupId }),
                headers: { 'Content-Type': 'application/json' }
              })
                .then((response) => response.json())
                .then((data) => {
                  if (data.success) {
                    alert(data.message || 'Группа удалена.')
                    location.reload() // Перезагрузка страницы
                  } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'))
                  }
                })
                .catch((error) => {
                  console.error('Error:', error)
                  alert('Произошла ошибка при удалении группы.')
                })
            }
          }
        })
      
        // Обработка формы добавления пользователя
        document.addEventListener('submit', function (e) {
          if (e.target.classList.contains('add-user-form')) {
            e.preventDefault()
            const formData = new FormData(e.target)
            fetch('/add-user-to-group', {
              method: 'POST',
              body: JSON.stringify(Object.fromEntries(formData)),
              headers: { 'Content-Type': 'application/json' }
            })
              .then((response) => response.json())
              .then((data) => {
                if (data.success) {
                  alert(data.message || 'Пользователь добавлен в группу.')
                  location.reload() // Перезагрузка страницы
                } else {
                  alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'))
                }
              })
              .catch((error) => {
                console.error('Error:', error)
                alert('Произошла ошибка при добавлении пользователя в группу.')
              })
          }
        })
      
        // Обработка кнопки "Удалить из группы"
        document.addEventListener('click', function (e) {
          if (e.target.classList.contains('remove-user-btn')) {
            const button = e.target
            const userId = button.getAttribute('data-user-id')
            const groupId = button.getAttribute('data-group-id')
            if (confirm('Вы уверены, что хотите удалить пользователя из группы?')) {
              fetch('/remove-user-from-group', {
                method: 'POST',
                body: JSON.stringify({
                  user_id: userId,
                  group_id: groupId
                }),
                headers: { 'Content-Type': 'application/json' }
              })
                .then((response) => response.json())
                .then((data) => {
                  if (data.success) {
                    alert(data.message || 'Пользователь удален из группы.')
                    location.reload() // Перезагрузка страницы
                  } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'))
                  }
                })
                .catch((error) => {
                  console.error('Error:', error)
                  alert('Произошла ошибка при удалении пользователя из группы.')
                })
            }
          }
        })
      })
    </script>
  </body>
</html>
