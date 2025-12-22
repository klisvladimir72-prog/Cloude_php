-- Создание базы данных (если ещё не создана)
-- SOURCE G:/OSPanel/home/projectPHP/final/init.sql;
CREATE DATABASE IF NOT EXISTS cloud_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cloud_storage;

-- Таблица пользователей
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role TINYINT DEFAULT 0, -- 0 - обычный, 1 - админ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица папок
CREATE TABLE folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INT NULL, -- NULL для корневой папки
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
);

-- Таблица файлов
CREATE TABLE files (
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

-- Таблица общих файлов
CREATE TABLE shared_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    shared_by INT NOT NULL,
    shared_with_email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица общих папок
CREATE TABLE shared_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_id INT NOT NULL,
    shared_by INT NOT NULL,
    shared_with_email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Индексы для ускорения запросов
CREATE INDEX idx_files_user_folder ON files(user_id, folder_id);
CREATE INDEX idx_folders_user_parent ON folders(user_id, parent_id);
CREATE INDEX idx_shared_files_email ON shared_files(shared_with_email);
CREATE INDEX idx_shared_folders_email ON shared_folders(shared_with_email);