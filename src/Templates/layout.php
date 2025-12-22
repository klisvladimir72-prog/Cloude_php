<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cloud Storage</title>
    <link rel="stylesheet" href="/assets/style.css" />
  </head>
  <body>
    <nav class="navbar">
      <a href="/" class="logo">Cloud Storage</a>
      <div class="nav-links">
        <?php if (isset($_SESSION['user_id'])): ?> <?php if (isset($_SESSION['login']) && $_SESSION['login'] === 'admin'): ?>
        <a href="/admin/groups">Группы</a>
        <?php endif; ?>
        <a href="/logout" class="btn-logout">Выйти</a>
        <?php else: ?>
        <a href="/login" class="btn-login">Войти</a>
        <a href="/register" class="btn-register">Регистрация</a>
        <?php endif; ?>
      </div>
    </nav>

    <main><?php echo $content ?? ''; ?></main>
  </body>
</html>
