<?php
/**
 * One in-page ad unit with reserved space (no layout jump) and a clear label.
 * @var string $slot  Property name on Config\AdSense, e.g. 'slotHome'
 */
$ads = config('AdSense'); $slotId = $ads->{$slot} ?? '';
if ($ads->client === '' || $slotId === '') return;
?>
<aside class="ad-slot" aria-label="Advertisement">
 <span class="ad-label">Advertisement</span>
 <ins class="adsbygoogle" style="display:block" data-ad-client="<?= esc($ads->client, 'attr') ?>" data-ad-slot="<?= esc($slotId, 'attr') ?>" data-ad-format="horizontal" data-full-width-responsive="true"></ins>
 <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
</aside>
