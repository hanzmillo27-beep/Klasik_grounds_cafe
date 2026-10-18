<?php
namespace Controllers;

use Core\Auth;
use Core\BaseController;

class ReportsController extends BaseController
{
    public function index()
    {
        Auth::requireLogin();
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila')))->format('Y-m-d');
        $week = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila')))->format('o-\WW');
        $this->view('Reports/index', ['title'=>'Reports', 'today'=>$today, 'week'=>$week]);
    }
}
