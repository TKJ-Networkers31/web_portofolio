<?php

declare(strict_types=1);

/**
 * public_html/admin/includes/admin-footer.php
 */

if (!defined('CMS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

$loggedIn = function_exists('isAdminLoggedIn') && isAdminLoggedIn();
?>
    </main>
<?php if ($loggedIn): ?>
  </div>
<?php endif; ?>
</body>
</html>
