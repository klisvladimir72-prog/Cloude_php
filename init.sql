-- Инициализация базы данных для Cloud Storage

-- Удаление существующей базы данных (если требуется)
DROP DATABASE IF EXISTS cloud_storage;

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS cloud_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cloud_storage;

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role TINYINT DEFAULT 0, -- 0 - обычный, 1 - админ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица папок
CREATE TABLE IF NOT EXISTS folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INT NULL, -- NULL для корневой папки
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
);

-- Таблица файлов
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL, -- имя файла на диске
    size BIGINT NOT NULL, -- размер в байтах
    mime_type VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    folder_id INT NULL, -- может быть NULL, если файл в корне
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
);

-- Таблица общих файлов (шаринг по email)
CREATE TABLE IF NOT EXISTS shared_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    shared_by INT NOT NULL,
    shared_with_email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица общих папок (шаринг по email)
CREATE TABLE IF NOT EXISTS shared_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_id INT NOT NULL,
    shared_by INT NOT NULL,
    shared_with_email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE
);


-- Таблица групп пользователей
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица связи пользователей и групп
CREATE TABLE IF NOT EXISTS user_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    UNIQUE KEY unique_user_group (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE
);

-- Таблица для хранения прав доступа к файлам/папкам для групп
CREATE TABLE IF NOT EXISTS shared_resources_by_group (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('file', 'folder') NOT NULL,
    resource_id INT NOT NULL,
    group_id INT NOT NULL,
    permissions ENUM('read', 'write', 'full') DEFAULT 'read',
    shared_by_user_id INT, -- ID пользователя, который поделился
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    INDEX idx_resource_group (resource_type, resource_id, group_id),
    INDEX idx_shared_by (shared_by_user_id)
);

-- Таблица для токенов 
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE, -- Токен должен быть уникальным
    expires_at DATETIME NOT NULL, -- Срок действия токена
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- Если пользователь удален, удаляются и его токены
    INDEX idx_token (token), -- Индекс для быстрого поиска по токену
    INDEX idx_expires (expires_at) -- Индекс для очистки просроченных токенов
);

-- --- ИНДЕКСЫ ДЛЯ ОПТИМИЗАЦИИ ЗАПРОСОВ ---
CREATE INDEX idx_files_user_folder ON files(user_id, folder_id);
CREATE INDEX idx_folders_user_parent ON folders(user_id, parent_id);
CREATE INDEX idx_shared_files_email ON shared_files(shared_with_email);
CREATE INDEX idx_shared_folders_email ON shared_folders(shared_with_email);

-- --- ВСТАВКА ПРИМЕРНЫХ ДАННЫХ ---

-- Добавляем администратора
-- Пароль: 123
INSERT INTO users (login, email, password_hash, role) VALUES
('admin', 'admin@example.com', '$2y$10$2EdF.riWuxMfsTGJLs63jOeLN3rCKWm0y1vpQWNTtSJSuLwbcBnXe', 1); 

-- Добавляем тестового пользователя
-- Пароль: 123
INSERT INTO users (login, email, password_hash, role) VALUES
('testuser', 'test@mail.ru', '$2y$10$2EdF.riWuxMfsTGJLs63jOeLN3rCKWm0y1vpQWNTtSJSuLwbcBnXe', 0); 

-- Добавляем тестовую группу
INSERT INTO user_groups (name) VALUES ('Developers');

-- Добавляем пользователя testuser в группу Developers
INSERT INTO user_group_members (user_id, group_id) VALUES (2, 1);

