<!-- File: templates/admin_groups.php -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление группами - Админ</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .group, .user { border: 1px solid #ccc; margin: 10px 0; padding: 10px; }
        .form-group { margin: 10px 0; }
        .form-group label { display: inline-block; width: 150px; }
        .form-group input, .form-group select, .form-group button { margin-left: 10px; }
        .message { padding: 10px; margin: 10px 0; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        /* Стиль для группы, которая редактируется */
        .group.editing { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Панель администратора: Управление группами</h1>
        <p>Добро пожаловать, <strong><?php echo htmlspecialchars($_SESSION['login'] ?? 'Admin'); ?></strong>!</p>
        <a href="/">Вернуться на главную</a>

        <?php if (isset($message) && $message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (isset($error) && $error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h2>Создать новую группу</h2>
        <form id="create-group-form" method="post" action="/create-group">
            <div class="form-group">
                <label for="group_name">Имя группы:</label>
                <input type="text" id="group_name" name="name" required>
                <button type="submit">Создать</button>
            </div>
        </form>

        <h2>Существующие группы</h2>
        <div id="groups-list"> <!-- Оборачиваем список групп в контейнер для обновления -->
            <?php if (!empty($groups)): ?>
                <?php foreach ($groups as $group): ?>
                    <div class="group" data-group-id="<?php echo $group['id']; ?>">
                        <h3>
                            <span class="group-name-span"><?php echo htmlspecialchars($group['name']); ?></span>
                            <input type="text" class="group-name-input" value="<?php echo htmlspecialchars($group['name']); ?>" style="display: none;" required>
                            <button class="edit-group-btn" data-id="<?php echo $group['id']; ?>">Редактировать</button>
                            <button class="save-group-btn" data-id="<?php echo $group['id']; ?>" style="display: none;">Сохранить</button>
                            <button class="cancel-edit-btn" data-id="<?php echo $group['id']; ?>" style="display: none;">Отмена</button>
                            <button class="delete-group-btn" data-id="<?php echo $group['id']; ?>">Удалить</button>
                        </h3>

                        <h4>Пользователи в группе:</h4>
                        <ul class="group-users">
                            <?php if (!empty($usersInGroups[$group['id']])): ?>
                                <?php foreach ($usersInGroups[$group['id']] as $user): ?>
                                    <li>
                                        <?php echo htmlspecialchars($user['login'] ?? $user['email']); ?>
                                        <button class="remove-user-btn" data-user-id="<?php echo $user['id']; ?>" data-group-id="<?php echo $group['id']; ?>">Удалить из группы</button>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>Нет пользователей в этой группе.</li>
                            <?php endif; ?>
                        </ul>

                        <h4>Добавить пользователя в группу:</h4>
                        <form class="add-user-form" method="post" action="/add-user-to-group">
                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                            <select name="user_id" required>
                                <option value="">Выберите пользователя</option>
                                <?php foreach ($allUsers as $user): ?>
                                    <?php if (!in_array($user['id'], array_column($usersInGroups[$group['id']] ?? [], 'id'))): ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['login'] ?? $user['email']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Добавить в группу</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Группы не найдены.</p>
            <?php endif; ?>
        </div> <!-- Конец контейнера #groups-list -->

    </div>

    <script>
        // --- Функции для обновления UI ---

        // Функция для обновления списка групп (например, после создания/удаления)
        function updateGroupsList() {
            // Для простоты перезагрузим страницу, если список групп изменился критически
            // Или сделаем AJAX-запрос для получения обновленного списка и вставим его в #groups-list
            // Пока реализуем через перезагрузку
            location.reload();
        }

        // --- Обработчики событий ---

        document.addEventListener('DOMContentLoaded', function() {
            // Обработка формы создания группы
            document.getElementById('create-group-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('/create-group', {
                    method: 'POST',
                    body: JSON.stringify(Object.fromEntries(formData)),
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // После успешного создания, обновляем список
                        updateGroupsList();
                    } else {
                        alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Произошла ошибка при создании группы.');
                });
            });

            // Обработка кнопки "Редактировать"
            document.querySelectorAll('.edit-group-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const groupId = this.getAttribute('data-id');
                    const groupDiv = this.closest('.group');
                    const nameSpan = groupDiv.querySelector('.group-name-span');
                    const nameInput = groupDiv.querySelector('.group-name-input');
                    const editBtn = this;
                    const saveBtn = groupDiv.querySelector('.save-group-btn');
                    const cancelBtn = groupDiv.querySelector('.cancel-edit-btn');

                    // Показываем инпут, скрываем спан и кнопки
                    nameSpan.style.display = 'none';
                    nameInput.style.display = 'inline-block';
                    editBtn.style.display = 'none';
                    saveBtn.style.display = 'inline-block';
                    cancelBtn.style.display = 'inline-block';
                    groupDiv.classList.add('editing'); // Добавляем класс для визуального выделения
                });
            });

            // Обработка кнопки "Сохранить"
            document.querySelectorAll('.save-group-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const groupId = this.getAttribute('data-id');
                    const groupDiv = this.closest('.group');
                    const nameInput = groupDiv.querySelector('.group-name-input');
                    const newName = nameInput.value.trim();

                    if (!newName) {
                        alert('Имя группы не может быть пустым.');
                        return;
                    }

                    fetch('/update-group', {
                        method: 'POST',
                        body: JSON.stringify({
                            id: groupId,
                            new_name: newName
                        }),
                        headers: { 'Content-Type': 'application/json' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // После успешного обновления, обновляем UI
                            const nameSpan = groupDiv.querySelector('.group-name-span');
                            nameSpan.textContent = newName;
                            nameSpan.style.display = 'inline';
                            nameInput.style.display = 'none';
                            groupDiv.querySelector('.edit-group-btn').style.display = 'inline-block';
                            this.style.display = 'none';
                            groupDiv.querySelector('.cancel-edit-btn').style.display = 'none';
                            groupDiv.classList.remove('editing');
                        } else {
                            alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Произошла ошибка при обновлении группы.');
                    });
                });
            });

            // Обработка кнопки "Отмена"
            document.querySelectorAll('.cancel-edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const groupDiv = this.closest('.group');
                    const nameSpan = groupDiv.querySelector('.group-name-span');
                    const nameInput = groupDiv.querySelector('.group-name-input');
                    const editBtn = groupDiv.querySelector('.edit-group-btn');
                    const saveBtn = groupDiv.querySelector('.save-group-btn');

                    // Возвращаем к исходному состоянию
                    nameSpan.style.display = 'inline';
                    nameInput.style.display = 'none';
                    editBtn.style.display = 'inline-block';
                    saveBtn.style.display = 'none';
                    this.style.display = 'none';
                    groupDiv.classList.remove('editing');
                    // Восстанавливаем исходное значение инпута
                    nameInput.value = nameSpan.textContent;
                });
            });

            // Обработка кнопки "Удалить"
            document.querySelectorAll('.delete-group-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const groupId = this.getAttribute('data-id');
                    if (confirm('Вы уверены, что хотите удалить эту группу?')) {
                        fetch('/delete-group', {
                            method: 'POST',
                            body: JSON.stringify({ id: groupId }),
                            headers: { 'Content-Type': 'application/json' }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // После успешного удаления, обновляем список
                                updateGroupsList();
                            } else {
                                alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Произошла ошибка при удалении группы.');
                        });
                    }
                });
            });

            // Обработка формы добавления пользователя
            document.querySelectorAll('.add-user-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch('/add-user-to-group', {
                        method: 'POST',
                        body: JSON.stringify(Object.fromEntries(formData)),
                        headers: { 'Content-Type': 'application/json' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // После успешного добавления, обновляем список
                            updateGroupsList();
                        } else {
                            alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Произошла ошибка при добавлении пользователя в группу.');
                    });
                });
            });

            // Обработка кнопки "Удалить из группы"
            document.querySelectorAll('.remove-user-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const groupId = this.getAttribute('data-group-id');
                    if (confirm('Вы уверены, что хотите удалить пользователя из группы?')) {
                        fetch('/remove-user-from-group', {
                            method: 'POST',
                            body: JSON.stringify({
                                user_id: userId,
                                group_id: groupId
                            }),
                            headers: { 'Content-Type': 'application/json' }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // После успешного удаления, обновляем список
                                updateGroupsList();
                            } else {
                                alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Произошла ошибка при удалении пользователя из группы.');
                        });
                    }
                });
            });

        });

    </script>
</body>
</html>