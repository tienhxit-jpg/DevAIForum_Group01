<?php

require_once __DIR__ . '/../Core/BaseController.php';

class HomeController extends BaseController
{
    public function index()
    {
        $this->view('client/home/index', [
            'title' => 'Trang chủ'
        ]);
    }

    public function about()
    {
        echo "Day la trang gioi thieu DevAI Hub!";
    }
}