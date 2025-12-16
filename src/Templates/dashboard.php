<?php ob_start(); ?>
<div class="container">
    <h1>Мои файлы и папки</h1>

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
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <!-- Папки -->
            <?php if (empty($folders)): ?>
                <tr><td colspan="5">Нет папок</td></tr>
            <?php else: ?>
                <?php foreach ($folders as $folder): ?>
                    <tr class="folder-row">
                        <td>
                            <span class="file-icon">📁</span>
                            <?php if ($folder['is_shared'] ?? false): ?>
                                <!-- Общая папка -->
                                <span class="shared-item"><?= htmlspecialchars($folder['name'] ?? 'Без имени') ?></span>
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
                <tr><td colspan="5">Нет файлов</td></tr>
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
                                'zip', 'rar' => '📦',
                                default => '📁',
                            };
                            ?>
                            <span class="file-icon"><?= $icon ?></span>
                            <?php if ($file['is_shared'] ?? false): ?>
                                <!-- Общий файл -->
                                <span class="shared-item"><?= htmlspecialchars($file['original_name'] ?? 'Без имени') ?></span>
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
                                <!-- Общий файл -->
                                <!-- Убираем кнопку "Просмотр" -->
                                <button class="btn-download" onclick="downloadFile('<?= $file['filename'] ?? '' ?>', '<?= $file['original_name'] ?? '' ?>')">Скачать</button>
                                <span class="shared-label">🔒 Общий</span>
                            <?php else: ?>
                                <!-- Свой файл -->
                                <!-- Убираем кнопку "Просмотр" -->
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
    // --- Код JavaScript остается без изменений ---
    // (Вставьте сюда весь ваш текущий код из блока <script> в dashboard.php)
    // Формы, удаление, шаринг, модальное окно и т.д.
    // Важно: не изменяйте логику, которая вызывает shareFile/shareFolder,
    // так как она работает с ID оригинальных файлов/папок, что корректно.

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

    // Функции шаринга (предполагается, что они реализованы)
    async function shareFile(fileId) {
        const users = await fetchUsers();
        if (!users) return;

        const sharedUsers = await fetchSharedUsersForFile(fileId);
        const selectedUsers = await showUserSelectionModal(users, sharedUsers);
        if (!selectedUsers || selectedUsers.length === 0) return;

        const response = await fetch('/share-file', {
            method: 'POST',
            body: JSON.stringify({ file_id: fileId, user_ids: selectedUsers }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        alert(data.message);
    }

    async function shareFolder(folderId) {
        const users = await fetchUsers();
        if (!users) return;

        const sharedUsers = await fetchSharedUsersForFolder(folderId);
        const selectedUsers = await showUserSelectionModal(users, sharedUsers);
        if (!selectedUsers || selectedUsers.length === 0) return;

        const response = await fetch('/share-folder', {
            method: 'POST',
            body: JSON.stringify({ folder_id: folderId, user_ids: selectedUsers }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        alert(data.message);
    }

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

    async function showUserSelectionModal(users, sharedUsers) {
        return new Promise((resolve, reject) => {
            // Добавляем проверку
            if (!Array.isArray(users)) {
                console.error('Переданы неверные данные в showUserSelectionModal:', users);
                alert('Произошла ошибка при загрузке списка пользователей.');
                resolve([]);
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
                width: 500px;
                max-height: 80vh;
                overflow-y: auto;
            `;

            // Генерируем список пользователей, используя map только если users - массив
            const userListHtml = users.map(user => `
                <div style="margin-bottom: 0.5rem;">
                    <label>
                        <input type="checkbox" name="user" value="${user.id}" ${sharedUsers.includes(user.id) ? 'checked' : ''}>
                        ${user.email}
                    </label>
                </div>
            `).join('');

            content.innerHTML = `
                <h3>Выберите пользователей</h3>
                <div style="margin-bottom: 1rem;">
                    <label>
                        <input type="checkbox" id="select-all"> Выбрать всех
                    </label>
                </div>
                <div id="user-list" style="margin-bottom: 1rem; max-height: 300px; overflow-y: auto;">
                    ${userListHtml}
                </div>
                <button id="add-btn" style="padding: 0.5rem 1rem; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Добавить</button>
                <button id="cancel-btn" style="margin-left: 1rem; padding: 0.5rem 1rem; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Отмена</button>
            `;

            modal.appendChild(content);
            document.body.appendChild(modal);

            const selectAllCheckbox = document.getElementById('select-all');
            const userCheckboxes = document.querySelectorAll('input[name="user"]');
            const addBtn = document.getElementById('add-btn');
            const cancelBtn = document.getElementById('cancel-btn');

            selectAllCheckbox.addEventListener('change', (e) => {
                userCheckboxes.forEach(checkbox => checkbox.checked = e.target.checked);
            });

            addBtn.addEventListener('click', () => {
                const selected = Array.from(userCheckboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => parseInt(checkbox.value));

                document.body.removeChild(modal);
                resolve(selected);
            });

            cancelBtn.addEventListener('click', () => {
                document.body.removeChild(modal);
                resolve([]);
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

.lock-icon {
    margin-left: 3px;
    color: #ff6b6b;
}

.info-message {
    font-style: italic;
    color: #6c757d;
    margin-top: 10px;
}
</style>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>