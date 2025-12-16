<?php ob_start(); ?><div class="container">
  <h1>Общие файлы и папки</h1>

  <!-- Общие файлы -->
  <div class="section">
    <h2>📄 Общие файлы</h2>
    <ul class="files-list">
      <?php if (empty($sharedFiles)): ?><li>Нет общих файлов</li><?php else: ?> <?php foreach ($sharedFiles as $sharedFile): ?><li class="file-item">
        <span class="file-name"><?= htmlspecialchars($sharedFile['original_name']) ?></span>
        <span class="file-size">(<?= $sharedFile['size'] ?> байт)</span>
        <button class="btn-view" onclick="viewFile('<?= $sharedFile['filename'] ?>')">Просмотр</button>
        <button class="btn-download" onclick="downloadFile('<?= $sharedFile['filename'] ?>', '<?= $sharedFile['original_name'] ?>')">Скачать</button>
      </li><?php endforeach; ?> <?php endif; ?>
    </ul>
  </div>

  <!-- Общие папки -->
  <div class="section">
    <h2>📁 Общие папки</h2>
    <ul class="folders-list">
      <?php if (empty($sharedFolders)): ?><li>Нет общих папок</li><?php else: ?> <?php foreach ($sharedFolders as $sharedFolder): ?><li class="folder-item">
        <a href="?folder=<?= $sharedFolder['folder_id'] ?>" class="folder-link">📁 <?= htmlspecialchars($sharedFolder['name']) ?></a>
        <button class="btn-delete" onclick="deleteFolder(<?= $sharedFolder['folder_id'] ?>)">Удалить</button>
      </li><?php endforeach; ?> <?php endif; ?>
    </ul>
  </div>

  <script>
    function viewFile(filename) {
      window.open(`/view/${filename}`, '_blank')
    }
    
    function downloadFile(filename, originalFilename) {
      const link = document.createElement('a')
      link.href = `/download/${filename}`
      link.download = originalFilename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    }
  </script><?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
</div>
