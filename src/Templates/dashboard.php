<?php ob_start(); ?>
<div class="container">
    <h1>Мои файлы и папки</h1>
    <!-- Отображение логина авторизованного пользователя -->
    <p>Вы вошли как: <strong><?php echo htmlspecialchars($login ?? 'Гость'); ?></strong></p>

    <!-- Хлебные крошки -->
    <div class="breadcrumbs">
        <a href="/">🏠</a>
        <?php foreach ($breadcrumbs ?? [] as $crumb): ?>
            <span> &gt; </span>
            <a href="?folder=<?= $crumb['id'] ?>"><?= htmlspecialchars($crumb['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Текущая папка -->
    <?php if (isset($currentFolder) && $currentFolder): ?>
        <h2>
            <?php if ($isCurrentFolderShared ?? false): ?>
                📁 <?= htmlspecialchars($currentFolder['name']) ?> <span class="lock-icon">🔒</span> (Общая)
            <?php else: ?>
                📁 <?= htmlspecialchars($currentFolder['name']) ?>
            <?php endif; ?>
        </h2>
    <?php else: ?>
        <h2>🏠 Корневая папка</h2>
    <?php endif; ?>

    <!-- Форма создания папки и загрузки файла -->
    <?php if (!($isCurrentFolderShared ?? false)): ?>
    <div class="form-section">
        <h3>Создать папку</h3>
        <form id="create-folder-form">
            <input type="text" name="name" placeholder="Название папки" required>
            <input type="hidden" name="parent_id" value="<?= $currentFolder ? $currentFolder['id'] : 'null' ?>">
            <button type="submit">Создать</button>
        </form>
        <div id="folder-message" class="message"></div>
    </div>

    <div class="form-section">
        <h3>Загрузить файл</h3>
        <form id="upload-form" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <input type="hidden" name="folder_id" value="<?= $currentFolder ? $currentFolder['id'] : '' ?>">
            <button type="submit">Загрузить</button>
        </form>
        <div id="upload-message" class="message"></div>
    </div>
    <?php else: ?>
        <p class="info-message">Вы просматриваете расшаренную папку. Создание и загрузка файлов недоступны.</p>
    <?php endif; ?>

    <!-- Таблица файлов и папок -->
    <table class="files-table">
        <thead>
            <tr>
                <th>Имя</th>
                <th>Дата изменения</th>
                <th>Размер</th>
                <th>Владелец</th>
                <th>Доступ</th> <!-- Новый столбец -->
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <!-- Папки -->
            <?php if (empty($folders)): ?>
                <tr><td colspan="6">Нет папок</td></tr>
            <?php else: ?>
                <?php foreach ($folders as $folder): ?>
                    <tr class="folder-row">
                        <td>
                            <span class="file-icon">📁</span>
                            <?php if ($folder['is_shared'] ?? false): ?>
                                <!-- Общая папка -->
                                <span class="shared-item">
                                    <?php if ($folder['is_shared_by_group'] ?? false): ?>
                                        <span class="shared-via-group">[Группа: <?= htmlspecialchars($folder['group_name'] ?? 'N/A') ?>]</span>
                                    <?php else: ?>
                                        <span class="shared-via-user">[Пользователь]</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($folder['name'] ?? 'Без имени') ?>
                                </span>
                                <span class="lock-icon">🔒</span>
                                <a href="?folder=<?= $folder['id'] ?>" class="folder-link shared-link">Открыть</a>
                            <?php else: ?>
                                <!-- Своя папка -->
                                <a href="?folder=<?= $folder['id'] ?>" class="folder-link"><?= htmlspecialchars($folder['name'] ?? 'Без имени') ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($folder['created_at'] ?? '') ?></td>
                        <td>-</td>
                        <td><?= htmlspecialchars($folder['owner_email'] ?? '-') ?></td>
                        <td>
                            <?php if ($folder['is_shared'] ?? false): ?>
                                <?php if ($folder['is_shared_by_group'] ?? false): ?>
                                    <span class="shared-label">🔒 Общая (группа)</span>
                                    <span class="permissions-info">Права: <?= htmlspecialchars($folder['permissions'] ?? 'read') ?></span>
                                <?php else: ?>
                                    <span class="shared-label">🔒 Общая (пользователь)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="own-label">🏠 Своя</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($folder['is_shared'] ?? false): ?>
                                <!-- Общая папка -->
                                <span class="shared-label">🔒 Общая</span>
                            <?php else: ?>
                                <!-- Своя папка -->
                                <button class="btn-share-folder" onclick="shareFolder(<?= $folder['id'] ?>)">Поделиться</button>
                                <button class="btn-delete" onclick="deleteFolder(<?= $folder['id'] ?>)">Удалить</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Файлы -->
            <?php if (empty($files)): ?>
                <tr><td colspan="6">Нет файлов</td></tr>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <tr class="file-row">
                        <td>
                            <?php
                            $ext = strtolower(pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION));
                            $icon = match ($ext) {
                                'pdf' => '📄',
                                'txt' => '📝',
                                'jpg', 'jpeg', 'png', 'gif' => '🖼️',
                                'doc', 'docx' => '📝',
                                'xls', 'xlsx' => '📊',
                                'zip', 'rar' => '📦',
                                default => '📁',
                            };
                            ?>
                            <span class="file-icon"><?= $icon ?></span>
                            <?php if ($file['is_shared'] ?? false): ?>
                                <!-- Общий файл -->
                                <span class="shared-item">
                                    <?php if ($file['is_shared_by_group'] ?? false): ?>
                                        <span class="shared-via-group">[Группа: <?= htmlspecialchars($file['group_name'] ?? 'N/A') ?>]</span>
                                    <?php else: ?>
                                        <span class="shared-via-user">[Пользователь]</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($file['original_name'] ?? 'Без имени') ?>
                                </span>
                                <span class="lock-icon">🔒</span>
                            <?php else: ?>
                                <!-- Свой файл -->
                                <span class="file-name"><?= htmlspecialchars($file['original_name'] ?? 'Без имени') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($file['created_at'] ?? '') ?></td>
                        <td><?= $file['size'] ?? 0 ?> байт</td>
                        <td><?= htmlspecialchars($file['owner_email'] ?? '-') ?></td>
                        <td>
                            <?php if ($file['is_shared'] ?? false): ?>
                                <?php if ($file['is_shared_by_group'] ?? false): ?>
                                    <span class="shared-label">🔒 Общий (группа)</span>
                                    <span class="permissions-info">Права: <?= htmlspecialchars($file['permissions'] ?? 'read') ?></span>
                                <?php else: ?>
                                    <span class="shared-label">🔒 Общий (пользователь)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="own-label">🏠 Свой</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($file['is_shared'] ?? false): ?>
                                <!-- Общий файл -->
                                <button class="btn-download" onclick="downloadFile('<?= $file['filename'] ?? '' ?>', '<?= $file['original_name'] ?? '' ?>')">Скачать</button>
                                <span class="shared-label">🔒 Общий</span>
                            <?php else: ?>
                                <!-- Свой файл -->
                                <button class="btn-download" onclick="downloadFile('<?= $file['filename'] ?? '' ?>', '<?= $file['original_name'] ?? '' ?>')">Скачать</button>
                                <button class="btn-share" onclick="shareFile(<?= $file['id'] ?>)">Поделиться</button>
                                <button class="btn-delete" onclick="deleteFile(<?= $file['id'] ?>)">Удалить</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<script>
    // Форма создания папки
    const createFolderForm = document.getElementById('create-folder-form');
    if (createFolderForm) {
        createFolderForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            const parentId = formData.get('parent_id');
            const data = {
                name: formData.get('name'),
                parent_id: parentId === 'null' ? null : parseInt(parentId)
            };

            const response = await fetch('/create-folder', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
            const dataResponse = await response.json();
            document.getElementById('folder-message').innerText = dataResponse.message || '';
            if(dataResponse.success) location.reload();
        });
    }

    // Форма загрузки файла
    const uploadForm = document.getElementById('upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('/upload', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            document.getElementById('upload-message').innerText = data.message || '';
            if(data.success) location.reload();
        });
    }

    // Функции для работы с файлами и папками
    async function deleteFile(fileId) {
        if (!confirm("Удалить файл?")) return;
        const response = await fetch('/delete-file', {
            method: 'DELETE',
            body: JSON.stringify({ file_id: fileId }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        alert(data.message);
        location.reload();
    }

    async function deleteFolder(folderId) {
        if (!confirm("Удалить папку и всё её содержимое?")) return;
        const response = await fetch('/delete-folder', {
            method: 'DELETE',
            body: JSON.stringify({ folder_id: folderId }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        alert(data.message);
        location.reload();
    }

    // Функции шаринга 
    async function shareFile(fileId) {
        // Получаем как пользователей, так и группы
        const [users, groups] = await Promise.all([fetchUsers(), fetchGroups()]);
        if (!users || !groups) return; // Если не удалось получить один из списков

        const selection = await showUserSelectionModal(users, groups);
        if (!selection || (selection.users.length === 0 && selection.groups.length === 0)) return;

        // Отправляем на ОДИН маршрут, передавая и пользователей, и группы
        const response = await fetch('/share-file', {
            method: 'POST',
            body: JSON.stringify({ file_id: fileId, user_ids: selection.users, group_ids: selection.groups }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        alert(data.message);
    }

    async function shareFolder(folderId) {
        const [users, groups] = await Promise.all([fetchUsers(), fetchGroups()]);
        if (!users || !groups) return;

        const selection = await showUserSelectionModal(users, groups);
        if (!selection || (selection.users.length === 0 && selection.groups.length === 0)) return;

        const response = await fetch('/share-folder', {
            method: 'POST',
            body: JSON.stringify({ folder_id: folderId, user_ids: selection.users, group_ids: selection.groups }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        alert(data.message);
    }

    // Получение пользователей
    async function fetchUsers() {
        const response = await fetch('/get-users');
        const data = await response.json();

        if (!data || !Array.isArray(data.users)) {
            console.error('Ошибка при получении списка пользователей:', data);
            alert('Не удалось получить список пользователей. Попробуйте снова позже.');
            return null;
        }

        return data.users;
    }

    //Функция получения групп
    async function fetchGroups() {
        const response = await fetch('/get-groups'); // Используем новый маршрут
        const data = await response.json();

        if (!data || !Array.isArray(data.groups)) {
            console.error('Ошибка при получении списка групп:', data);
            alert('Не удалось получить список групп. Попробуйте снова позже.');
            return null;
        }

        return data.groups;
    }

    async function fetchSharedUsersForFile(fileId) {
        try {
            const response = await fetch(`/get-shared-users/file/${fileId}`);
            const data = await response.json();
            // Убедитесь, что возвращается массив
            return Array.isArray(data.user_ids) ? data.user_ids : [];
        } catch (error) {
            console.error('Ошибка при получении общих пользователей для файла:', error);
            return [];
        }
    }

    async function fetchSharedUsersForFolder(folderId) {
        try {
            const response = await fetch(`/get-shared-users/folder/${folderId}`);
            const data = await response.json();
            // Убедитесь, что возвращается массив
            return Array.isArray(data.user_ids) ? data.user_ids : [];
        } catch (error) {
            console.error('Ошибка при получении общих пользователей для папки:', error);
            return [];
        }
    }

    //Функция showUserSelectionModal для отображения пользователей и групп
    async function showUserSelectionModal(users, groups) {
        return new Promise((resolve, reject) => {
            // Добавляем проверку
            if (!Array.isArray(users)) {
                console.error('Переданы неверные данные в showUserSelectionModal:', users);
                alert('Произошла ошибка при загрузке списка пользователей.');
                resolve({ users: [], groups: [] });
                return;
            }
            if (!Array.isArray(groups)) {
                console.error('Переданы неверные данные групп в showUserSelectionModal:', groups);
                alert('Произошла ошибка при загрузке списка групп.');
                resolve({ users: [], groups: [] });
                return;
            }

            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;

            const content = document.createElement('div');
            content.style.cssText = `
                background: white;
                padding: 2rem;
                border-radius: 8px;
                width: 600px; /* Увеличим ширину */
                max-height: 80vh;
                overflow-y: auto;
            `;

            // --- Генерация HTML для пользователей ---
            const userListHtml = users.map(user => `
                <div style="margin-bottom: 0.5rem;">
                    <label>
                        <input type="checkbox" name="user" value="${user.id}">
                        ${user.email} (${user.login})
                    </label>
                </div>
            `).join('');

            // --- Генерация HTML для групп ---
            const groupListHtml = groups.map(group => `
                <div style="margin-bottom: 0.5rem;">
                    <label>
                        <input type="checkbox" name="group" value="${group.id}">
                        ${group.name}
                    </label>
                </div>
            `).join('');

            content.innerHTML = `
                <h3>Выберите пользователей и/или группы</h3>
                <div>
                    <h4>Пользователи</h4>
                    <div style="margin-bottom: 1rem;">
                        <label>
                            <input type="checkbox" id="select-all-users"> Выбрать всех пользователей
                        </label>
                    </div>
                    <div id="user-list" style="margin-bottom: 1rem; max-height: 200px; overflow-y: auto;">
                        ${userListHtml}
                    </div>
                </div>
                <div>
                    <h4>Группы</h4>
                    <div style="margin-bottom: 1rem;">
                        <label>
                            <input type="checkbox" id="select-all-groups"> Выбрать все группы
                        </label>
                    </div>
                    <div id="group-list" style="margin-bottom: 1rem; max-height: 200px; overflow-y: auto;">
                        ${groupListHtml}
                    </div>
                </div>
                <button id="add-btn" style="padding: 0.5rem 1rem; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Поделиться</button>
                <button id="cancel-btn" style="margin-left: 1rem; padding: 0.5rem 1rem; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Отмена</button>
            `;

            modal.appendChild(content);
            document.body.appendChild(modal);

            const selectAllUsersCheckbox = document.getElementById('select-all-users');
            const selectAllGroupsCheckbox = document.getElementById('select-all-groups');
            const userCheckboxes = document.querySelectorAll('input[name="user"]');
            const groupCheckboxes = document.querySelectorAll('input[name="group"]');
            const addBtn = document.getElementById('add-btn');
            const cancelBtn = document.getElementById('cancel-btn');

            selectAllUsersCheckbox.addEventListener('change', (e) => {
                userCheckboxes.forEach(checkbox => checkbox.checked = e.target.checked);
            });

            selectAllGroupsCheckbox.addEventListener('change', (e) => {
                groupCheckboxes.forEach(checkbox => checkbox.checked = e.target.checked);
            });

            addBtn.addEventListener('click', () => {
                const selectedUsers = Array.from(userCheckboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => parseInt(checkbox.value));

                const selectedGroups = Array.from(groupCheckboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => parseInt(checkbox.value));

                document.body.removeChild(modal);
                resolve({ users: selectedUsers, groups: selectedGroups });
            });

            cancelBtn.addEventListener('click', () => {
                document.body.removeChild(modal);
                resolve({ users: [], groups: [] });
            });
        });
    }
    // УДАЛЯЕМ ФУНКЦИЮ viewFile
    // function viewFile(filename) {
    //     window.open(`/view/${filename}`, '_blank');
    // }

    function downloadFile(filename, originalFilename) {
        try {
            const encodedFilename = encodeURIComponent(filename);
            const downloadUrl = `/download?file=${encodedFilename}`;
            window.open(downloadUrl, '_blank');
        } catch (error) {
            console.error('Ошибка при скачивании файла:', error);
            alert('Произошла ошибка при скачивании файла. Попробуйте снова.');
        }
    }
</script>
<style>
/* Дополнительные стили для лучшей визуализации различий */
.shared-item {
    font-weight: normal;
    color: #555;
}

.shared-via-group {
    font-size: 0.8em;
    color: #007bff; /* Цвет для выделения, что доступ через группу */
    background-color: #e7f3ff;
    padding: 2px 4px;
    border-radius: 3px;
    margin-right: 5px;
}

.shared-via-user {
    font-size: 0.8em;
    color: #6c757d; /* Цвет для выделения, что доступ через пользователя */
    background-color: #e9ecef;
    padding: 2px 4px;
    border-radius: 3px;
    margin-right: 5px;
}

.shared-link {
    margin-left: 5px;
    font-size: 0.9em;
    text-decoration: underline;
    color: #007bff;
}

.shared-label {
    font-size: 0.85em;
    color: #6c757d;
}

.permissions-info {
    display: block;
    font-size: 0.75em;
    color: #28a745;
    font-style: italic;
}

.own-label {
    font-size: 0.85em;
    color: #28a745;
    font-weight: bold;
}

.lock-icon {
    margin-left: 3px;
    color: #ff6b6b;
}

.info-message {
    font-style: italic;
    color: #6c757d;
    margin-top: 10px;
}

/* Стили для нового столбца "Доступ" */
th:nth-child(5), td:nth-child(5) {
    text-align: center;
    white-space: nowrap;
}
</style>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>