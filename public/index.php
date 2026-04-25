<?php
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
if ($uri === '/admin/plugins' && $method === 'GET') { $admin->plugins(); exit; }
if (preg_match('#^/admin/plugins/([a-zA-Z0-9\-_]+)/toggle$#', $uri, $m) && $method === 'POST') { $admin->togglePlugin($m[1]); exit; }

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
if (preg_match('#^/admin/displays/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteDisplay((int)$m[1]); exit; }
if ($uri === '/admin/sort/displays' && $method === 'POST') { $admin->sortDisplays(); exit; }

if ($uri === '/admin/channels' && $method === 'GET') { $admin->channels(); exit; }
if ($uri === '/admin/channels/create' && $method === 'GET') { $admin->channelForm(); exit; }
if ($uri === '/admin/channels/create' && $method === 'POST') { $admin->saveChannel(); exit; }
if (preg_match('#^/admin/channels/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->channelForm((int)$m[1]); exit; }
if (preg_match('#^/admin/channels/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveChannel((int)$m[1]); exit; }
if (preg_match('#^/admin/channels/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteChannel((int)$m[1]); exit; }
if ($uri === '/admin/sort/channels' && $method === 'POST') { $admin->sortChannels(); exit; }

if ($uri === '/admin/slides' && $method === 'GET') { $admin->slides(); exit; }
if ($uri === '/admin/slides/create' && $method === 'GET') { $admin->slideForm(); exit; }
if ($uri === '/admin/slides/create' && $method === 'POST') { $admin->saveSlide(); exit; }
if (preg_match('#^/admin/slides/(\d+)/edit$#', $uri, $m) && $method === 'GET') { $admin->slideForm((int)$m[1]); exit; }
if (preg_match('#^/admin/slides/(\d+)/edit$#', $uri, $m) && $method === 'POST') { $admin->saveSlide((int)$m[1]); exit; }
if (preg_match('#^/admin/slides/(\d+)/delete$#', $uri, $m) && $method === 'POST') { $admin->deleteSlide((int)$m[1]); exit; }
if (preg_match('#^/admin/channels/(\d+)/slides/(\d+)/remove$#', $uri, $m) && $method === 'POST') { $admin->removeSlideFromChannel((int)$m[2], (int)$m[1]); exit; }
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
if (preg_match('#^/display/([a-zA-Z0-9\-_]+)$#', $uri, $m) && $method === 'GET') { $frontend->display($m[1]); exit; }
if (preg_match('#^/display/([a-zA-Z0-9\-_]+)/heartbeat$#', $uri, $m) && $method === 'POST') { $frontend->heartbeat($m[1]); exit; }
if (preg_match('#^/display/([a-zA-Z0-9\-_]+)/state$#', $uri, $m) && $method === 'GET') { $frontend->state($m[1]); exit; }

http_response_code(404);
echo __('errors.page_not_found');
