<?php
/**
 * includes/footer.php
 *
 * Closes the page and carries the Reset demo data action, so a marker can put
 * the session tables back to the seed at any time. The reset is a POST with a
 * CSRF token; it never happens on a plain link. This is the one form on the
 * public pages that lives outside the main content, which is why it is a
 * separate small form rather than part of a page.
 */
declare(strict_types=1);
?>
</main>

<footer class="site-footer">
  <div class="wrap footer-inner">
    <div class="footer-brand">
      <p class="footer-title">Pioneer Public Library System</p>
      <p class="footer-note">IS 6465 Web Based Applications · Phase 1 · No database: every record lives in this browser session.</p>
    </div>
    <form class="footer-reset" action="/reset.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/index.php') ?>">
      <button class="btn btn-secondary btn-sm" type="submit">Reset demo data</button>
      <span class="footer-note">Restores the seeded members and inventory.</span>
    </form>
    <div class="footer-links">
      <a href="/index.php">Home</a>
      <a href="/login.php">Staff sign in</a>
      <a href="/README.md">README</a>
    </div>
  </div>
</footer>
<script src="/js/site.js" defer></script>
</body>
</html>
