<!-- File: templates/admin_groups.php -->
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <title>Управление группами - Админ</title>
    <style>
      body {
        font-family: Arial, sans-serif;
      }
      .container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
      }
      .group,
      .user {
        border: 1px solid #ccc;
        margin: 10px 0;
        padding: 10px;
      }
      .form-group {
        margin: 10px 0;
      }
      .form-group label {
        display: inline-block;
        width: 150px;
      }
      .form-group input,
      .form-group select,
      .form-group button {
        margin-left: 10px;
      }
      .message {
        padding: 10px;
        margin: 10px 0;
      }
      .success {
        background-color: #d4edda;
        color: #155724;
      }
      .error {
        background-color: #f8d7da;
        color: #721c24;
      }
    </style>
  </head>
  <body>
    <div class="container">
      <h1>Панель администратора: Управление группами</h1>
      <p>
        Добро пожаловать, <strong><?php echo htmlspecialchars($_SESSION['login'] ?? 'Admin'); ?></strong>!
      </p>
      <a href="/">Вернуться на главную</a>

      <?php if (isset($message) && $message): ?><div class="message success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?> <?php if (isset($error) && $error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

      <h2>Создать новую группу</h2>
      <form id="create-group-form" method="post" action="/create-group">
        <div class="form-group">
          <label for="group_name">Имя группы:</label>
          <input type="text" id="group_name" name="name" required />
          <button type="submit">Создать</button>
        </div>
      </form>

      <h2>Существующие группы</h2>
      <?php if (!empty($groups)): ?> <?php foreach ($groups as $group): ?><div class="group" data-group-id="<?php echo $group['id']; ?>">
        <h3>
          <?php echo htmlspecialchars($group['name']); ?>
          <button class="edit-group-btn" data-id="<?php echo $group['id']; ?>">Редактировать</button>
          <button class="delete-group-btn" data-id="<?php echo $group['id']; ?>">Удалить</button>
        </h3>

        <h4>Пользователи в группе:</h4>
        <ul class="group-users">
          <?php if (!empty($usersInGroups[$group['id']])): ?> <?php foreach ($usersInGroups[$group['id']] as $user): ?><li>
            <?php echo htmlspecialchars($user['login'] ?? $user['email']); ?>
            <button class="remove-user-btn" data-user-id="<?php echo $user['id']; ?>" data-group-id="<?php echo $group['id']; ?>">Удалить из группы</button>
          </li><?php endforeach; ?> <?php else: ?><li>Нет пользователей в этой группе.</li><?php endif; ?>
        </ul>

        <h4>Добавить пользователя в группу:</h4>
        <form class="add-user-form" method="post" action="/add-user-to-group">
          <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>" />
          <select name="user_id" required>
            <option value="">Выберите пользователя</option><?php foreach ($allUsers as $user): ?> <?php if (!in_array($user['id'], array_column($usersInGroups[$group['id']] ?? [], 'id'))): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['login'] ?? $user['email']); ?></option><?php endif; ?> <?php endforeach; ?>
          </select>
          <button type="submit">Добавить в группу</button>
        </form>
      </div><?php endforeach; ?> <?php else: ?><p>Группы не найдены.</p><?php endif; ?>
    </div>

    <script>
      // Простой пример обработки форм с подтверждением
      document.querySelectorAll('.delete-group-btn').forEach((button) => {
        button.addEventListener('click', function () {
          const groupId = this.getAttribute('data-id')
          if (confirm('Вы уверены, что хотите удалить эту группу?')) {
            // Отправляем POST-запрос на /delete-group
            const form = document.createElement('form')
            form.method = 'POST'
            form.action = '/delete-group'
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = 'id'
            input.value = groupId
            form.appendChild(input)
            document.body.appendChild(form)
            form.submit()
          }
        })
      })
      
      document.querySelectorAll('.remove-user-btn').forEach((button) => {
        button.addEventListener('click', function () {
          const userId = this.getAttribute('data-user-id')
          const groupId = this.getAttribute('data-group-id')
          if (confirm('Вы уверены, что хотите удалить пользователя из группы?')) {
            const form = document.createElement('form')
            form.method = 'POST'
            form.action = '/remove-user-from-group'
            const inputUser = document.createElement('input')
            const inputGroup = document.createElement('input')
            inputUser.type = 'hidden'
            inputUser.name = 'user_id'
            inputUser.value = userId
            inputGroup.type = 'hidden'
            inputGroup.name = 'group_id'
            inputGroup.value = groupId
            form.appendChild(inputUser)
            form.appendChild(inputGroup)
            document.body.appendChild(form)
            form.submit()
          }
        })
      })
      
      // Обработка формы редактирования (пример)
      document.querySelectorAll('.edit-group-btn').forEach((button) => {
        button.addEventListener('click', function () {
          const groupId = this.getAttribute('data-id')
          const newGroupName = prompt('Введите новое имя группы:')
          if (newGroupName && newGroupName.trim() !== '') {
            const form = document.createElement('form')
            form.method = 'POST'
            form.action = '/update-group'
            const inputId = document.createElement('input')
            const inputName = document.createElement('input')
            inputId.type = 'hidden'
            inputId.name = 'id'
            inputId.value = groupId
            inputName.type = 'hidden'
            inputName.name = 'new_name'
            inputName.value = newGroupName
            form.appendChild(inputId)
            form.appendChild(inputName)
            document.body.appendChild(form)
            form.submit()
          }
        })
      })
    </script>
  </body>
</html>
