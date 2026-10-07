<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Google AdSense settings. Set these in .env, e.g.:
 *
 *   adsense.client = ca-pub-1234567890123456
 *   adsense.slotHome = 1234567890
 *   adsense.slotBuilder = 1234567890
 *   adsense.slotRetrieve = 1234567890
 *
 * Ads are not rendered while `client` is empty. Leave Auto ads (anchor,
 * vignette, side rail) turned off in the AdSense dashboard so only these
 * fixed, in-page units are shown.
 */
class AdSense extends BaseConfig
{
    public string $client = '';

    public string $slotHome = '';

    public string $slotBuilder = '';

    public string $slotRetrieve = '';
}
