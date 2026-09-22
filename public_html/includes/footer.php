<?php
/**
 * includes/footer.php
 *
 * Menutup <main>, menampilkan footer, memuat JavaScript, dan menutup dokumen.
 * Pasangan dari includes/header.php.
 */

if (!defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
?>
  </main>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-inner">
        <p class="footer-name">Mohamad Lingga Syahputra</p>

        <nav class="footer-nav" aria-label="Ecosystem footer">
          <ul role="list">
            <li><a href="./" aria-current="true">Portfolio</a></li>
            <!-- DUMMY: nanti menjadi subdomain business.mohamadlingga.my.id -->
            <li><a href="#" data-dummy aria-disabled="true">Business</a></li>
            <!-- DUMMY: nanti menjadi subdomain lab.mohamadlingga.my.id -->
            <li><a href="#" data-dummy aria-disabled="true">Lab</a></li>
          </ul>
        </nav>
      </div>

      <p class="footer-copy meta">&copy; <?= date('Y') ?> Mohamad Lingga Syahputra. All rights reserved.</p>
    </div>
  </footer>

  <script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
  <script src="<?= e(asset('assets/js/navigation.js')) ?>" defer></script>
  <script src="<?= e(asset('assets/js/reveal.js')) ?>" defer></script>
</body>
</html>
