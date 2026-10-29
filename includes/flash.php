<?php
/**
 * includes/flash.php
 *
 * One-shot flash messages. A handler sets a message and redirects (303); the
 * page it lands on renders the message and the message is gone. This is what
 * keeps a POST from ever re-rendering its own result.
 */
declare(strict_types=1);

/** Queue a message for the next rendered page. */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Take the queued message, if any, so it renders exactly once. */
function flash_take(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

/**
 * Render the queued message. $type is 'success', 'error', or 'notice'.
 * role="status" for good news, role="alert" for a refusal so a screen
 * reader announces it.
 */
function flash_render(): void
{
    $flash = flash_take();
    if ($flash === null || empty($flash['message'])) {
        return;
    }
    $type = (string) ($flash['type'] ?? 'notice');
    $role = $type === 'error' ? 'alert' : 'status';
    $class = match ($type) {
        'success' => 'flash flash-success',
        'error'   => 'flash flash-error',
        default   => 'flash flash-notice',
    };
    ?>
    <div class="<?= e($class) ?>" role="<?= e($role) ?>">
      <p class="flash-text"><?= e((string) $flash['message']) ?></p>
    </div>
    <?php
}
