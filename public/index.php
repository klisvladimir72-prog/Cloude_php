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
use Src\Middleware\AuthMiddleware;
use Src\Repositories\PasswordResetRepository;

// --- Регистрируем существующие и новые сервисы в DI-контейнере ---
App::bind('user_repository', fn() => new UserRepository());
App::bind('file_repository', fn() => new FileRepository());
App::bind('folder_repository', fn() => new FolderRepository());
App::bind('password_reset_repository', fn() => new PasswordResetRepository());
App::bind('auth_service', fn() => new AuthService());
App::bind('file_service', fn() => new FileService());
App::bind('folder_service', fn() => new FolderService());
App::bind('shared_file_repository', fn() => new \Src\Repositories\SharedFileRepository());
App::bind('shared_folder_repository', fn() => new \Src\Repositories\SharedFolderRepository());
App::bind('user_group_repository', fn() => new \Src\Repositories\UserGroupRepository());
App::bind('user_group_member_repository', fn() => new \Src\Repositories\UserGroupMemberRepository());
App::bind('shared_resource_by_group_repository', fn() => new \Src\Repositories\SharedResourceByGroupRepository());
App::bind('group_service', fn() => new \Src\Services\GroupService());
App::bind('share_by_group_service', fn() => new \Src\Services\ShareByGroupService());
App::bind('user_token_repository', fn() => new \Src\Repositories\UserTokenRepository());
App::bind('user_groups', fn() => new \Src\Repositories\UserGroupRepository());
App::bind('shared_resources_by_group', fn() => new \Src\Repositories\SharedResourceByGroupRepository());

$request = new Request();
$router = new Router();

// --- Существующие маршруты ---
$router->add('GET', '', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->index($request, $response);
});


// Авторизация
$router->add('GET', 'login', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->loginForm($request, $response);
});

$router->add('POST', 'login', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->login($request, $response);
});

$router->add('GET', 'register', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->registerForm($request, $response);
});

$router->add('POST', 'register', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->register($request, $response);
});

$router->addSecureByAuth('GET', 'logout', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->logout($request, $response);
});

$router->add('GET', 'reset_password', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\PasswordResetController();
    return $controller->requestResetPassword($request, $response);
});

// ______Авторизация 



// Маршрут для отображения формы смены пароля  (доступен любому аутентифицированному пользователю)
$router->addSecureByAuth('GET', 'change_password', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->showChangePasswordForm($request, $response);
});

// Маршрут для смены пароля (доступен любому аутентифицированному пользователю)
$router->addSecureByAuth('POST', 'change_password', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AuthController();
    $controller->changePassword($request, $response);
});


// USERS 
$router->addSecureByAuth('GET', 'get-users', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->getUsers($request, $response);
});

$router->addSecureByAuth('GET', 'users/list', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\UserController();
    $controller->getUsersList($request, $response);
});

$router->addSecureByAuth('GET', 'users/search/{email}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\UserController();
    $controller->getUserByEmail($request, $response);
});

$router->addSecureByAuth('GET', 'users/get/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\UserController();
    $controller->getUserById($request, $response);
});

$router->addSecureByAuth('PUT', 'users/update', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\UserController();
    $controller->updateUserFieldByUser($request, $response);
});


// ____________USERS

// ADMIN
$router->addSecureByAuth('GET', 'admin/users/list', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AdminController();
    $controller->getUsersList($request, $response);
});

$router->addSecureByAuth('GET', 'admin/users/get/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AdminController();
    $controller->getUserById($request, $response);
});

$router->addSecureByAuth('DELETE', 'admin/users/delete/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AdminController();
    $controller->deleteUser($request, $response);
});

$router->addSecureByAuth('PUT', 'admin/users/update/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AdminController();
    $controller->updateUserField($request, $response);
});

// Сброс пороля (user123) или задать новый пароль
$router->addSecureByAuth('POST', 'admin/users/reset_password/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\AdminController();
    $controller->resetUserPassword($request, $response);
});

// ___________ADMIN


// FILES 
// Получение списка всех файлов
$router->addSecureByAuth('GET', 'files/list', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->getFilesList($request, $response);
});

// Получение списка всех файлов пользователя
$router->addSecureByAuth('GET', 'files/list/get/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->getFilesListById($request, $response);
});

// Получение данных о файле по id файла 
$router->addSecureByAuth('GET', 'files/get/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->getFileByFileId($request, $response);
});

// Изменение имени файла
$router->addSecureByAuth('PUT', 'files/rename', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->renameFile($request, $response);
});

// Удаление файла по id 
$router->addSecureByAuth('DELETE', 'files/remove/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->delete($request, $response);
});

// Загрузка на сервер
$router->addSecureByAuth('POST', 'files/add', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->upload($request, $response);
});

// Скачать файл по id 
$router->addSecureByAuth('GET', 'files/download/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->download($request, $response);
});

// Логика для шаблона отображения файлов в приложении
$router->addSecureByAuth('GET', 'files', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->index($request, $response);
});
// ___________FILES


// FOLDERS 
// Создание папки
$router->addSecureByAuth('POST', 'directories/add', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FolderController();
    $controller->createFolder($request, $response);
});

// Переименование папки
$router->addSecureByAuth('PUT', 'directories/rename', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FolderController();
    $controller->renameFolder($request, $response);
});

// Удаление папки по id
$router->addSecureByAuth('DELETE', 'directories/delete/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FolderController();
    $controller->deleteFolder($request, $response);
});

// Получение содержимого папки по id
$router->addSecureByAuth('GET', 'directories/get/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FolderController();
    $controller->getContentFolder($request, $response);
});
// __________FOLDERS


// FILES/SHARE
// Получение пользователей для расшаренного файла по id 
$router->addSecureByAuth('GET', 'files/share/{id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->getUsersBySharedFile($request, $response);
});

// Шаринг по id файла пользователям(группам) по их id
$router->addSecureByAuth('PUT', 'files/share/{id}/{user_id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->shareFile($request, $response);
});

// Удалить шаринг по id файла пользователям(группам) по их id[] 
$router->addSecureByAuth('DELETE', 'files/share/{id}/{user_id}', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->removeShareFile($request, $response);
});

$router->addSecureByAuth('GET', 'shared', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\FileController();
    $controller->shared($request, $response);
});

// _________FILES/SHARE






$router->addSecureByAuth('POST', 'share-folder', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->shareFolder($request, $response);
});


$router->addSecureByAuth('GET', 'get-shared-users/folder/{folderId}', function (Request $request, Response $response) {
    $matches = $request->getMatches();
    $folderId = $matches['folderId'] ?? null;

    if (!$folderId) {
        http_response_code(400);
        $response->setData(['error' => 'ID папки не указан']);
        $response->sendJson();
        return;
    }

    $authResult = AuthMiddleware::handle($request, $response);
    if (!$authResult) {
        http_response_code(401);
        $response->sendHtml('login.php');
        return;
    }

    $sharedFolderRepo = App::getService('shared_folder_repository');
    $sharedFolders = $sharedFolderRepo->findBy('shared_folders', ['folder_id' => $folderId]);

    $userIds = [];
    foreach ($sharedFolders as $sharedFolder) {
        $userRepo = App::getService('user_repository');
        $user = $userRepo->findBy($userRepo->getTable(), ['email' => $sharedFolder['shared_with_email']]);
        if ($user) {
            $userIds[] = $user['id'];
        }
    }

    $response->setData(['user_ids' => $userIds]);
    $response->sendJson();
});
// ---

// Маршрут для отображения админ-панели 
$router->addSecureByAuth('GET', 'admin/groups', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->showAdminPanel($request, $response);
});

// Маршруты для CRUD групп 
$router->addSecureByAuth('POST', 'create-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->createGroup($request, $response);
});

$router->addSecureByAuth('POST', 'update-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->updateGroup($request, $response);
});

$router->addSecureByAuth('POST', 'delete-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->deleteGroup($request, $response);
});

$router->addSecureByAuth('POST', 'add-user-to-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->addUserToGroup($request, $response);
});

$router->addSecureByAuth('POST', 'remove-user-from-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\GroupController();
    $controller->removeUserFromGroup($request, $response);
});

$router->addSecureByAuth('POST', 'share-resource-by-group', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareByGroupController();
    $controller->shareResource($request, $response);
});

$router->addSecureByAuth('GET', 'get-groups', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\ShareController();
    $controller->getGroups($request, $response);
});

// Маршрут для отображения админ-панели пользователей
$router->addSecureByAuth('GET', 'admin/users', function (Request $request, Response $response) {
    $controller = new \Src\Controllers\UserController();
    $controller->showAdminUserPanel($request, $response);
});





// Обработка запроса
$route = $request->getRoute();
$method = $request->getMethod();

// Проверяем, является ли запрос статическим файлом (например, css, js, img)
// Если маршрут не начинается с известных префиксов ваших API, и файл существует, отдаем его
$publicPath = __DIR__ . '/../public/' . $route;
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
$router->processRequest($request);
