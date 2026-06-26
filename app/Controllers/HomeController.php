<?php

namespace App\Controllers;

use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect('dashboard');
        }

        return $this->redirect('login');
    }
}
