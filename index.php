<?php
/**
 * index.php - the home page (WF 01).
 *
 * Public, no sign-in needed. It carries the catalog search that reads the
 * inventory table, so the page is a real use case rather than a static card
 * (the assignment note on the home page). The hero, the media-type tiles, the
 * Get started list, and the events panel are reproduced from WF 01.
 */
declare(strict_types=1);

require_once __DIR__ . '/security/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/DB/members.php';
require_once __DIR__ . '/DB/inventory.php';

boot_session();

// The catalogue search. A GET, so the term survives a reload and can be
// linked. Only public descriptive fields are read: no member data is ever
// reachable from here (BR 15, FR 34).
$query = get_str('q');
$results = [];
$searched = $query !== '';
if ($searched) {
    $results = inventory_search(['search' => $query, 'type' => 'all', 'status' => 'all']);
}

// Live counts per media type, derived from the seeded table so the tiles show
// real numbers rather than the wireframe's sample totals.
$typeCounts = [];
foreach (media_types() as $key => $label) {
    $typeCounts[$key] = 0;
}
foreach (inventory_all() as $item) {
    if (!empty($item['deleted'])) {
        continue;
    }
    if (isset($typeCounts[$item['media_type']])) {
        $typeCounts[$item['media_type']]++;
    }
}

$page_title = 'Home';
$active_nav = '';
$layout = 'public';
require __DIR__ . '/includes/header.php';
?>

<section class="hero" aria-labelledby="hero-title">
  <h1 id="hero-title">Everything the library has, in one place</h1>
  <p>Search books, film, music, periodicals, and audiobooks.</p>
  <form class="hero-search" action="/index.php" method="get" role="search" id="catalog">
    <label class="sr-only" for="hero-q">Title, author, subject, or ISBN</label>
    <input type="search" id="hero-q" name="q" value="<?= e($query) ?>"
           placeholder="Title, author, subject, or ISBN">
    <button class="btn btn-primary btn-lg" type="submit">Search</button>
  </form>
</section>

<?php if ($searched): ?>
  <section class="detail-section" aria-labelledby="results-title">
    <h2 class="section-title" id="results-title">
      <?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for &ldquo;<?= e($query) ?>&rdquo;
    </h2>
    <?php if ($results === []): ?>
      <div class="empty-state">
        <h2>No items match that search</h2>
        <p>Try a shorter word, or search by author, subject, or ISBN.</p>
        <a class="btn btn-secondary" href="/index.php">Clear the search</a>
      </div>
    <?php else: ?>
      <ul class="result-list">
        <?php foreach ($results as $item): ?>
          <?php $counts = inventory_counts($item); ?>
          <li class="result-card">
            <img class="result-cover" src="/images/placeholder-cover.svg" alt="" width="60" height="86">
            <div class="result-body">
              <p class="result-title"><?= e($item['title']) ?></p>
              <p class="result-meta">
                <?= e($item['creator'] !== '' ? $item['creator'] . ' · ' : '') ?><?= e(media_type_label($item['media_type'])) ?><?php
                  if (($item['publish_date'] ?? '') !== '') {
                      echo ', ' . e(substr((string) $item['publish_date'], 0, 4));
                  } elseif (($item['year'] ?? '') !== '') {
                      echo ', ' . e((string) $item['year']);
                  }
                ?>
              </p>
              <p class="result-avail<?= $counts['available'] > 0 ? ' is-avail' : ' is-none' ?>">
                <?= (int) $counts['available'] ?> of <?= (int) $counts['total'] ?> available
              </p>
            </div>
            <?php if ($counts['available'] > 0): ?>
              <a class="btn btn-primary btn-sm" href="/login.php">Add to cart</a>
            <?php else: ?>
              <span class="btn btn-secondary btn-sm is-disabled" aria-disabled="true">Unavailable</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="hint">Add to cart appears only for available copies on an active account (FR 04). Signing in and checking out arrive in Phase 2.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="detail-section" aria-labelledby="browse-title">
  <h2 class="section-title" id="browse-title">Browse by media type</h2>
  <div class="tile-row">
    <?php foreach (media_types() as $key => $label): ?>
      <a class="tile" href="/index.php?q=<?= e(rawurlencode($label)) ?>">
        <span class="tile-name"><?= e($label === 'Book' ? 'Books' : ($label === 'Film' ? 'Film' : ($label === 'Music' ? 'Music' : ($label === 'Periodical' ? 'Periodicals' : 'Audiobooks')))) ?></span>
        <span class="tile-count"><?= (int) $typeCounts[$key] ?> item<?= $typeCounts[$key] === 1 ? '' : 's' ?></span>
        <span class="tile-link">Browse</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<div class="two-col">
  <section aria-labelledby="start-title">
    <h2 class="section-title" id="start-title">Get started</h2>
    <ul class="link-list">
      <li><a href="/login.php"><span>Get a library card</span><span class="chev" aria-hidden="true">&rsaquo;</span></a></li>
      <li><a href="/index.php#catalog"><span>Search the catalog</span><span class="chev" aria-hidden="true">&rsaquo;</span></a></li>
      <li><a href="/login.php"><span>My account</span><span class="chev" aria-hidden="true">&rsaquo;</span></a></li>
      <li><a href="/login.php"><span>Log in</span><span class="chev" aria-hidden="true">&rsaquo;</span></a></li>
    </ul>
  </section>

  <section aria-labelledby="events-title" id="events">
    <h2 class="section-title" id="events-title">Events this week</h2>
    <div class="panel">
      <ul class="event-list">
        <li class="event-row"><span class="event-day">Mon</span><span class="event-what">Story time, 10:00 am</span><span class="event-who">Kids</span></li>
        <li class="event-row"><span class="event-day">Wed</span><span class="event-what">Research help desk, 2:00 pm</span><span class="event-who">Adults</span></li>
        <li class="event-row"><span class="event-day">Fri</span><span class="event-what">Film night, 6:30 pm</span><span class="event-who">All ages</span></li>
        <li class="event-row"><span class="event-day">Sat</span><span class="event-what">Book sale, 9:00 am</span><span class="event-who">All ages</span></li>
      </ul>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
