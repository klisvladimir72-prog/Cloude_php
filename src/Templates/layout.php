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
        <?php if (isset($id)): ?>
        <a href="/change_password">Сменить пароль</a>
        <?php if (isset($login) && $login === 'admin'): ?>
        <a href="/admin/users">Пользователи</a>
        <a href="/admin/groups">Группы</a>
        <?php endif; ?>
        <a href="" class="btn-logout">Выйти</a>
        <?php else: ?>
        <a href="/login" class="btn-login">Войти</a>
        <a href="/register" class="btn-register">Регистрация</a>
        <?php endif; ?>
      </div>
    </nav>

    <main><?php echo $content ?? ''; ?></main>
  </body>

  <script>
    const logoutLink = document.querySelector('.btn-logout')
    
    if (logoutLink) {
      logoutLink.addEventListener('click', async (e) => {
        e.preventDefault()
    
        try {
          const response = await fetch('/logout')
    
          if (response.ok) {
            window.location.href = '/'
          } else {
            console.error('Ошибка при выходе: ', response.status)
            alert('Ошибка при выходе из системы.')
          }
        } catch (error) {
          console.error('Ошибка сети: ', error)
          alert('Произошла ошибка сети.')
        }
      })
    }
  </script>
</html>
