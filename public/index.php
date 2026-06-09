<?php
$staticUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$staticMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (is_string($staticUri) && in_array($staticMethod, ['GET', 'HEAD'], true)) {
    $staticPath = realpath(__DIR__ . '/' . ltrim(rawurldecode($staticUri), '/'));
    $publicRoot = realpath(__DIR__);
    $staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'mp4', 'webm', 'otf', 'ttf', 'woff', 'woff2'];
    $extension = strtolower(pathinfo($staticPath ?: '', PATHINFO_EXTENSION));
    if ($publicRoot !== false && $staticPath !== false && str_starts_with($staticPath, $publicRoot . DIRECTORY_SEPARATOR) && is_file($staticPath) && in_array($extension, $staticExtensions, true)) {
        $mimeMap = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'otf' => 'font/otf',
            'ttf' => 'font/ttf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        $fileSize = (int)filesize($staticPath);
        $isVideo = in_array($extension, ['mp4', 'webm'], true);
        header('Content-Type: ' . ($mimeMap[$extension] ?? 'application/octet-stream'));
        if ($isVideo) {
            header('Accept-Ranges: bytes');
        }

        $rangeHeader = $isVideo ? (string)($_SERVER['HTTP_RANGE'] ?? '') : '';
        if ($rangeHeader !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $rangeMatch)) {
            $start = $rangeMatch[1] !== '' ? (int)$rangeMatch[1] : null;
            $end = $rangeMatch[2] !== '' ? (int)$rangeMatch[2] : null;

            if ($start === null && $end !== null) {
                $start = max(0, $fileSize - $end);
                $end = $fileSize - 1;
            } else {
                $start = $start ?? 0;
                $end = $end ?? ($fileSize - 1);
            }

            if ($start >= 0 && $end >= $start && $start < $fileSize) {
                $end = min($end, $fileSize - 1);
                $length = $end - $start + 1;
                http_response_code(206);
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
                header('Content-Length: ' . (string)$length);
                if ($staticMethod !== 'HEAD') {
                    $handle = fopen($staticPath, 'rb');
                    if ($handle !== false) {
                        fseek($handle, $start);
                        $remaining = $length;
                        while ($remaining > 0 && !feof($handle)) {
                            $chunk = fread($handle, min(8192, $remaining));
                            if ($chunk === false || $chunk === '') {
                                break;
                            }
                            $remaining -= strlen($chunk);
                            echo $chunk;
                        }
                        fclose($handle);
                    }
                }
                exit;
            }

            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }

        header('Content-Length: ' . (string)$fileSize);
        if ($staticMethod !== 'HEAD') {
            readfile($staticPath);
        }
        exit;
    }
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\FrontendController;
use App\Controllers\MonitoringController;

$admin = new AdminController($db, $view, $auth, $request, $uploadManager, $pluginManager);
$frontend = new FrontendController($db, $view, $pluginManager);
$monitoring = new MonitoringController($db, $displayStatusService);

$uri = $request->uri();
$method = $request->method();

if ($method === 'POST' && !preg_match('#^/display/[a-zA-Z0-9\-_]+/heartbeat$#', $uri)) {
    require_csrf();
}

if ($uri === '/' && $method === 'GET') {
    redirect($auth->check() ? '/admin' : '/admin/login');
}

if ($uri === '/admin/login' && $method === 'GET') { $admin->loginForm(); exit; }
if ($uri === '/admin/login' && $method === 'POST') { $admin->login(); exit; }
if ($uri === '/admin/logout' && $method === 'POST') { $admin->logout(); exit; }

if ($uri === '/admin' && $method === 'GET') { $admin->dashboard(); exit; }
if ($uri === '/admin/about' && $method === 'GET') { $admin->about(); exit; }
if ($uri === '/admin/accessibility' && $method === 'GET') { $admin->accessibility(); exit; }
if ($uri === '/admin/plugins' && $method === 'GET') { $admin->plugins(); exit; }
if ($uri === '/admin/settings' && $method === 'GET') { $admin->settingsForm(); exit; }
if ($uri === '/admin/settings' && $method === 'POST') { $admin->saveSettings(); exit; }
if (preg_match('#^/admin/plugins/([a-zA-Z0-9\-_]+)/toggle$#', $uri, $m) && $method === 'POST') { $admin->togglePlugin($m[1]); exit; }
if (preg_match('#^/admin/plugins/([a-zA-Z0-9\-_]+)/actions/([a-zA-Z0-9\-_]+)$#', $uri, $m) && $method === 'POST') { $admin->pluginAction($m[1], $m[2]); exit; }
if (preg_match('#^/admin/plugins/([a-zA-Z0-9\-_]+)/settings$#', $uri, $m) && $method === 'GET') { $admin->pluginSettingsForm($m[1]); exit; }
if (preg_match('#^/admin/plugins/([a-zA-Z0-9\-_]+)/settings$#', $uri, $m) && $method === 'POST') { $admin->savePluginSettings($m[1]); exit; }

if ($uri === '/admin/schedules' && $method === 'GET') { $admin->schedules(); exit; }
if ($uri === '/admin/schedules/create' && $method === 'GET') { $admin->scheduleForm(); exit; }
if ($uri === '/admin/schedules/create' && $method === 'POST') { $admin->saveSchedule(); exit; }
if (preg_match('#^/admin/schedules/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->scheduleForm((int)$m[1]); exit; }
if (preg_match('#^/admin/schedules/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveSchedule((int)$m[1]); exit; }
if (preg_match('#^/admin/schedules/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteSchedule((int)$m[1]); exit; }

if ($uri === '/admin/displays' && $method === 'GET') { $admin->displays(); exit; }
if ($uri === '/admin/displays/create' && $method === 'GET') { $admin->displayForm(); exit; }
if ($uri === '/admin/displays/create' && $method === 'POST') { $admin->saveDisplay(); exit; }
if (preg_match('#^/admin/displays/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->displayForm((int)$m[1]); exit; }
if (preg_match('#^/admin/displays/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveDisplay((int)$m[1]); exit; }
if (preg_match('#^/admin/displays/(\d+)/reload$#', $uri, $m) && $method === 'POST') { $admin->reloadDisplay((int)$m[1]); exit; }
if (preg_match('#^/admin/displays/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteDisplay((int)$m[1]); exit; }

if ($uri === '/admin/locations' && $method === 'GET') { $admin->locations(); exit; }
if ($uri === '/admin/locations/create' && $method === 'POST') { $admin->saveLocation(); exit; }
if (preg_match('#^/admin/locations/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->locationForm((int)$m[1]); exit; }
if (preg_match('#^/admin/locations/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveLocation((int)$m[1]); exit; }
if (preg_match('#^/admin/locations/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteLocation((int)$m[1]); exit; }
if ($uri === '/admin/display-groups/create' && $method === 'POST') { $admin->saveDisplayGroup(); exit; }
if ($uri === '/admin/display-groups/bulk' && $method === 'POST') { $admin->moveDisplaysToGroup(); exit; }
if (preg_match('#^/admin/display-groups/(\d+)$#', $uri, $m) && $method === 'GET') { $admin->displayGroup((int)$m[1]); exit; }
if (preg_match('#^/admin/display-groups/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveDisplayGroup((int)$m[1]); exit; }
if (preg_match('#^/admin/display-groups/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteDisplayGroup((int)$m[1]); exit; }
if (preg_match('#^/admin/display-groups/(\d+)/layout$#', $uri, $m) && $method === 'POST') { $admin->saveDisplayGroupLayout((int)$m[1]); exit; }

// Legacy channel routes redirect to playlists for compatibility.
if ($uri === '/admin/channels' && $method === 'GET') { $admin->playlists(); exit; }
if ($uri === '/admin/channels/create' && $method === 'GET') { $admin->playlistForm(); exit; }
if ($uri === '/admin/channels/create' && $method === 'POST') { $admin->savePlaylist(); exit; }
if (preg_match('#^/admin/channels/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->playlistForm((int)$m[1]); exit; }
if (preg_match('#^/admin/channels/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->savePlaylist((int)$m[1]); exit; }
if (preg_match('#^/admin/channels/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deletePlaylist((int)$m[1]); exit; }
if ($uri === '/admin/sort/channels' && $method === 'POST') { $admin->sortPlaylists(); exit; }
if (preg_match('#^/admin/channels/(\d+)/slides/add$#', $uri, $m) && $method === 'POST') { $admin->addSlidesToPlaylist((int)$m[1]); exit; }
if (preg_match('#^/admin/channels/(\d+)/slides/(\d+)/remove$#', $uri, $m) && $method === 'POST') { $admin->removeSlideFromPlaylist((int)$m[2], (int)$m[1]); exit; }

if ($uri === '/admin/playlists' && $method === 'GET') { $admin->playlists(); exit; }
if ($uri === '/admin/playlists/create' && $method === 'GET') { $admin->playlistForm(); exit; }
if ($uri === '/admin/playlists/create' && $method === 'POST') { $admin->savePlaylist(); exit; }
if (preg_match('#^/admin/playlists/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->playlistForm((int)$m[1]); exit; }
if (preg_match('#^/admin/playlists/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->savePlaylist((int)$m[1]); exit; }
if (preg_match('#^/admin/playlists/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deletePlaylist((int)$m[1]); exit; }
if ($uri === '/admin/sort/playlists' && $method === 'POST') { $admin->sortPlaylists(); exit; }

if ($uri === '/admin/slide-templates' && $method === 'GET') { $admin->slideTemplates(); exit; }
if ($uri === '/admin/slide-templates/create' && $method === 'GET') { $admin->slideTemplateForm(); exit; }
if ($uri === '/admin/slide-templates/create' && $method === 'POST') { $admin->saveSlideTemplate(); exit; }
if (preg_match('#^/admin/slide-templates/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->slideTemplateForm((int)$m[1]); exit; }
if (preg_match('#^/admin/slide-templates/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveSlideTemplate((int)$m[1]); exit; }
if (preg_match('#^/admin/slide-templates/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteSlideTemplate((int)$m[1]); exit; }

if ($uri === '/admin/slides' && $method === 'GET') { $admin->slides(); exit; }
if ($uri === '/admin/slides/create' && $method === 'GET') { $admin->slideForm(); exit; }
if ($uri === '/admin/slides/create' && $method === 'POST') { $admin->saveSlide(); exit; }
if (preg_match('#^/admin/slides/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->slideForm((int)$m[1]); exit; }
if (preg_match('#^/admin/slides/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveSlide((int)$m[1]); exit; }
if (preg_match('#^/admin/slides/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteSlide((int)$m[1]); exit; }
if (preg_match('#^/admin/playlists/(\d+)/slides/add$#', $uri, $m) && $method === 'POST') { $admin->addSlidesToPlaylist((int)$m[1]); exit; }
if (preg_match('#^/admin/playlists/(\d+)/slides/(\d+)/remove$#', $uri, $m) && $method === 'POST') { $admin->removeSlideFromPlaylist((int)$m[2], (int)$m[1]); exit; }
if ($uri === '/admin/sort/slides' && $method === 'POST') { $admin->sortSlides(); exit; }

if ($uri === '/admin/media' && $method === 'GET') { $admin->media(); exit; }
if ($uri === '/admin/media/upload' && $method === 'POST') { $admin->uploadMedia(); exit; }
if (preg_match('#^/admin/media/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteMedia((int)$m[1]); exit; }

if ($uri === '/admin/users' && $method === 'GET') { $admin->users(); exit; }
if ($uri === '/admin/users/create' && $method === 'GET') { $admin->userForm(); exit; }
if ($uri === '/admin/users/create' && $method === 'POST') { $admin->saveUser(); exit; }
if (preg_match('#^/admin/users/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->userForm((int)$m[1]); exit; }
if (preg_match('#^/admin/users/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveUser((int)$m[1]); exit; }
if (preg_match('#^/admin/users/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteUser((int)$m[1]); exit; }

if ($uri === '/api/monitoring/health' && $method === 'GET') { $monitoring->health(); exit; }
if ($uri === '/api/monitoring/summary' && $method === 'GET') { $monitoring->summary(); exit; }
if ($uri === '/api/monitoring/displays' && $method === 'GET') { $monitoring->displays(); exit; }
if (preg_match('#^/api/monitoring/displays/([a-zA-Z0-9\-_]+)$#', $uri, $m) && $method === 'GET') { $monitoring->display($m[1]); exit; }

if (preg_match('#^/plugin-assets/([a-zA-Z0-9\-_]+)/(.+)$#', $uri, $m) && $method === 'GET') { $pluginManager->serveAsset($m[1], $m[2]); exit; }
if (preg_match('#^/preview-slide/(\d+)$#', $uri, $m) && $method === 'GET') { $frontend->previewSlide((int)$m[1]); exit; }
if (preg_match('#^/preview-slide/(\d+)/heartbeat$#', $uri, $m) && $method === 'POST') { $frontend->previewHeartbeat((int)$m[1]); exit; }
if (preg_match('#^/preview-slide/(\d+)/state$#', $uri, $m) && $method === 'GET') { $frontend->previewState((int)$m[1]); exit; }
if (preg_match('#^/display/([a-zA-Z0-9\-_]+)/offline-manifest$#', $uri, $m) && $method === 'GET') { $frontend->offlineManifest($m[1]); exit; }
if (preg_match('#^/display/([a-zA-Z0-9\-_]+)$#', $uri, $m) && $method === 'GET') { $frontend->display($m[1]); exit; }
if (preg_match('#^/display/([a-zA-Z0-9\-_]+)/heartbeat$#', $uri, $m) && $method === 'POST') { $frontend->heartbeat($m[1]); exit; }
if (preg_match('#^/display/([a-zA-Z0-9\-_]+)/state$#', $uri, $m) && $method === 'GET') { $frontend->state($m[1]); exit; }

http_response_code(404);
echo __('errors.page_not_found');
