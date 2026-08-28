<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

class AccountController
{
    public function __construct(private Auth $auth, private View $view, private Request $request)
    {
    }

    public function show(): void
    {
        $this->auth->requireLogin();
        $this->view->render('admin/account', ['user' => $this->auth->user()]);
    }

    public function setLocale(): void
    {
        $this->auth->requireLogin();
        $locale = trim((string)$this->request->input('locale'));
        if (array_key_exists($locale, app_available_locales())) {
            $_SESSION['_locale'] = $locale;
        }
        $returnTo = parse_url((string)($_SERVER['HTTP_REFERER'] ?? '/admin'), PHP_URL_PATH);
        redirect(is_string($returnTo) && str_starts_with($returnTo, '/admin') ? $returnTo : '/admin');
    }
}
