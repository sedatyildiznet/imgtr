<?php
/** Sample configuration only. Copy to includes/config.php on the server and fill in secrets. */
define('IMGTR_INSTALLED', true);
define('SITE_URL', 'https://imgtr.xyz');
define('DB_HOST', 'localhost');
define('DB_NAME', 'YOUR_DATABASE');
define('DB_USER', 'YOUR_DB_USERNAME');
define('DB_PASS', 'YOUR_DB_PASSWORD');
define('MAX_FILE_SIZE', 10485760);
define('MAX_FILES', 10);
define('MAX_IMAGE_PIXELS', 40000000);
define('NOSTR_CHUNK_SIZE', 28000);
define('RELAY_TARGET', 8);
define('RELAY_MINIMUM', 2);
define('LOG_DIR', __DIR__ . '/../logs');
define('SESSION_NAME', 'imgtr_session');
