<?php

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../vendor/autoload.php';

use Src\Core\App;
use Src\Core\Request;
use Src\Core\Response;
use Src\Core\Router;
use Src\Repositories\UserRepository;
use Src\Repositories\FileRepository;
use Src\Repositories\FolderRepository;
use Src\Services\AuthService;
use Src\Services\FileService;
use Src\Services\FolderService;

// --- Регистрируем существующие и новые сервисы в DI-контейнере ---
App::bind('user_repository', fn() => new UserRepository());
App::bind('file_repository', fn() => new FileRepository());
App::bind('folder_repository', fn() => new FolderRepository());
App::bind('auth_service', fn() => new AuthService());
App::bind('file_service', fn() => new FileService());
App::bind('folder_service', fn() => new FolderService());
App::bind('shared_file_repository', fn() => new \Src\Repositories\SharedFileRepository());
App::bind('shared_folder_repository', fn() => new \Src\Repositories\SharedFolderRepository());

// --- Новые сервисы и репозитории для групп и шаринга по группам ---
App::bind('user_group_repository', fn() => new \Src\Repositories\UserGroupRepository());
App::bind('user_group_member_repository', fn() => new \Src\Repositories\UserGroupMemberRepository());
App::bind('shared_resource_by_group_repository', fn() => new \Src\Repositories\SharedResourceByGroupRepository());
App::bind('group_service', fn() => new \Src\Services\GroupService());
App::bind('share_by_group_service', fn() => new \Src\Services\ShareByGroupService());
// ---

$request = new Request();
$router = new Router();

// --- Существующие маршруты ---
$router->add('GET', '', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->index($request, $response);
});

$router->add('GET', 'files', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->index($request, $response);
});

// Загрузка
$router->add('POST', 'upload', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->upload($request, $response);
});

// Удаление файла
$router->add('DELETE', 'delete-file', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->delete($request, $response);
});

// Удаление папки
$router->add('DELETE', 'delete-folder', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FolderController();
    $controller->delete($request, $response);
});

// Создание папки
$router->add('POST', 'create-folder', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FolderController();
    $controller->create($request, $response);
});

// Авторизация
$router->add('GET', 'login', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->loginForm($request, $response);
});

$router->add('GET', 'register', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->registerForm($request, $response);
});

$router->add('POST', 'login', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->login($request, $response);
});

$router->add('POST', 'register', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->register($request, $response);
});

$router->add('GET', 'logout', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->logout($request, $response);
});

$router->add('GET', 'download', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->download($request, $response);
    // Важно: метод download сам вызывает exit() после отправки файла
});

$router->add('POST', 'share-file', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->shareFile($request, $response);
});

$router->add('POST', 'share-folder', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->shareFolder($request, $response);
});

$router->add('GET', 'shared', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->shared($request, $response);
});

$router->add('GET', 'get-users', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->getUsers($request, $response);
});

$router->add('GET', 'get-shared-users/file/{fileId}', function (Request $request, Response $response) {
    $matches = $request->getMatches();
    $fileId = $matches['fileId'] ?? null;

    if (!$fileId) {
        http_response_code(400);
        $response->setData(['error' => 'ID файла не указан']);
        $response->sendJson();
        return;
    }

    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        $response->setData(['error' => 'Требуется аутентификация']);
        $response->sendJson();
        return;
    }

    $sharedFileRepo = App::getService('shared_file_repository');
    $sharedFiles = $sharedFileRepo->findBy('shared_files', ['file_id' => $fileId]);

    $userIds = [];
    foreach ($sharedFiles as $sharedFile) {
        $userRepo = App::getService('user_repository');
        $user = $userRepo->findByEmail($sharedFile['shared_with_email']);
        if ($user) {
            $userIds[] = $user['id'];
        }
    }

    $response->setData(['user_ids' => $userIds]);
    $response->sendJson();
});

$router->add('GET', 'get-shared-users/folder/{folderId}', function (Request $request, Response $response) {
    $matches = $request->getMatches();
    $folderId = $matches['folderId'] ?? null;

    if (!$folderId) {
        http_response_code(400);
        $response->setData(['error' => 'ID папки не указан']);
        $response->sendJson();
        return;
    }

    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        $response->setData(['error' => 'Требуется аутентификация']);
        $response->sendJson();
        return;
    }

    $sharedFolderRepo = App::getService('shared_folder_repository');
    $sharedFolders = $sharedFolderRepo->findBy('shared_folders', ['folder_id' => $folderId]);

    $userIds = [];
    foreach ($sharedFolders as $sharedFolder) {
        $userRepo = App::getService('user_repository');
        $user = $userRepo->findByEmail($sharedFolder['shared_with_email']);
        if ($user) {
            $userIds[] = $user['id'];
        }
    }

    $response->setData(['user_ids' => $userIds]);
    $response->sendJson();
});
// ---

// --- Новые маршруты для управления группами и шарингом по группам ---
// Маршрут для отображения админ-панели теперь в ShareController
$router->add('GET', 'admin/groups', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->showAdminPanel($request, $response);
});

// Маршруты для CRUD операций с группами (эти вызовы останутся такими же, как в предыдущем примере)
// Но теперь они будут вызывать методы в GroupController.php
// Нам нужно создать этот файл или реализовать логику в ShareController.php
// Для простоты, давай создадим GroupController.php и перенесем туда логику

// Маршруты для CRUD групп (предполагаем, что GroupController существует)
$router->add('POST', 'create-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->createGroup($request, $response);
});

$router->add('POST', 'update-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->updateGroup($request, $response);
});

$router->add('POST', 'delete-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->deleteGroup($request, $response);
});

$router->add('POST', 'add-user-to-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->addUserToGroup($request, $response);
});

$router->add('POST', 'remove-user-from-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->removeUserFromGroup($request, $response);
});

$router->add('POST', 'share-resource-by-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareByGroupController();
    $controller->shareResource($request, $response);
});
// ---

// Обработка запроса
$route = $request->getRoute();
$method = $request->getMethod();

// Проверяем, является ли запрос статическим файлом (например, css, js, img)
// Если маршрут не начинается с известных префиксов ваших API, и файл существует, отдаем его
$publicPath = __DIR__ . '/../public/' . $route; // Предполагаем, что статика в /public/
if (
    $method === 'GET' && !in_array($route, ['', 'files', 'upload', 'delete-file', 'delete-folder', 'create-folder', 'login', 'register', 'logout', 'download', 'view', 'share-file']) // список ваших API маршрутов
    && file_exists($publicPath) && is_file($publicPath)
) {
    // Отдача статических файлов
    $mimeType = mime_content_type($publicPath);
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($publicPath));
    readfile($publicPath);
    exit();
}

// Если не статический файл, обрабатываем через Router
$response = $router->processRequest($request);

// Ответ по умолчанию, если маршрут не найден и не был отправлен заголовок
if (http_response_code() === 404) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Маршрут не найден']);
    exit();
}
