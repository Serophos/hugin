<?php
$title = __('display.vnc_title', ['display' => $display['name']]);
$breadcrumbs = [
    ['label' => __('display.plural'), 'url' => '/admin/displays'],
    ['label' => $display['name'], 'url' => '/admin/displays/' . $display['id'] . '/edit'],
    ['label' => __('display.vnc')],
];
$vncConfig = [
    'url' => $vncUrl,
    'credentials' => $vncCredentials,
    'labels' => [
        'connecting' => __('display.vnc_connecting'),
        'connected' => __('display.vnc_connected'),
        'disconnected' => __('display.vnc_disconnected'),
        'missing_endpoint' => __('display.vnc_missing_endpoint'),
        'missing_credentials' => __('display.vnc_missing_credentials'),
        'security_failure' => __('display.vnc_security_failure'),
        'connect_failed' => __('display.vnc_connect_failed'),
    ],
];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="<?= e(url('/admin/displays/' . $display['id'] . '/edit')) ?>"><?= admin_icon('back') ?><span><?= e(__('common.previous')) ?></span></a>
    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="<?= e(url('/display/' . $display['slug'] . '?preview=1')) ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('preview') ?><span><?= e(__('common.preview')) ?></span></a>
</div>

<section class="card vnc-viewer-shell" data-vnc-viewer data-vnc-config="<?= e(json_encode($vncConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
    <div class="card-header vnc-viewer-toolbar">
        <div class="vnc-viewer-toolbar__identity">
            <strong><?= e($display['name']) ?></strong>
            <span><?= e($vncUrl !== '' ? $vncUrl : __('display.vnc_no_endpoint')) ?></span>
        </div>
        <div class="vnc-viewer-toolbar__actions">
            <span class="badge text-bg-secondary status-chip" data-vnc-status><?= e($vncUrl !== '' ? __('display.vnc_connecting') : __('display.vnc_missing_endpoint')) ?></span>
            <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" data-vnc-reconnect><?= admin_icon('reload') ?><span><?= e(__('display.vnc_reconnect')) ?></span></button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" data-vnc-disconnect><?= admin_icon('cancel') ?><span><?= e(__('display.vnc_disconnect')) ?></span></button>
        </div>
    </div>
    <div class="vnc-viewer-screen bg-dark" data-vnc-screen>
        <p class="alert alert-secondary mb-0 vnc-viewer-message" data-vnc-message><?= e($vncUrl !== '' ? __('display.vnc_connecting') : __('display.vnc_missing_endpoint')) ?></p>
    </div>
</section>

<script type="module">
import RFB from '<?= e(asset_url('/assets/vendor/novnc/core/rfb.js')) ?>';

const root = document.querySelector('[data-vnc-viewer]');
const screen = root?.querySelector('[data-vnc-screen]');
const status = root?.querySelector('[data-vnc-status]');
const message = root?.querySelector('[data-vnc-message]');
const reconnectButton = root?.querySelector('[data-vnc-reconnect]');
const disconnectButton = root?.querySelector('[data-vnc-disconnect]');
const config = root ? JSON.parse(root.dataset.vncConfig || '{}') : {};
const labels = config.labels || {};
let rfb = null;

function setStatus(text, persistentMessage = false) {
    if (status) status.textContent = text || '';
    if (message) {
        message.textContent = text || '';
        message.hidden = !persistentMessage;
    }
}

function disconnect() {
    if (rfb) {
        rfb.disconnect();
        rfb = null;
    }
}

function connect() {
    disconnect();
    if (!screen || !config.url) {
        setStatus(labels.missing_endpoint || 'No VNC endpoint available.', true);
        return;
    }

    setStatus(labels.connecting || 'Connecting...', false);
    try {
        rfb = new RFB(screen, config.url, {
            credentials: config.credentials || {},
            shared: true,
        });
        rfb.viewOnly = false;
        rfb.scaleViewport = true;
        rfb.resizeSession = false;
        rfb.focusOnClick = true;

        rfb.addEventListener('connect', () => {
            setStatus(labels.connected || 'Connected.', false);
            rfb?.focus?.({ preventScroll: true });
        });
        rfb.addEventListener('disconnect', (event) => {
            rfb = null;
            setStatus(event.detail?.clean ? (labels.disconnected || 'Disconnected.') : (labels.connect_failed || 'Connection failed.'), true);
        });
        rfb.addEventListener('credentialsrequired', () => {
            setStatus(labels.missing_credentials || 'VNC credentials are missing.', true);
        });
        rfb.addEventListener('securityfailure', () => {
            setStatus(labels.security_failure || 'VNC authentication failed.', true);
        });
    } catch (error) {
        setStatus(labels.connect_failed || 'Connection failed.', true);
    }
}

reconnectButton?.addEventListener('click', connect);
disconnectButton?.addEventListener('click', () => {
    disconnect();
    setStatus(labels.disconnected || 'Disconnected.', true);
});

connect();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
