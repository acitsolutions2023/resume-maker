<?php $ads = config('AdSense'); if ($ads->client !== ''): ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= esc($ads->client, 'url') ?>" crossorigin="anonymous"></script>
<?php endif ?>
