<?php

namespace Core;

class BaseController
{
    public function view($view, $data = [], $layout = 'default')
    {
        $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            exit('View not found.');
        }

        foreach ($data as $key => $value) {
            $$key = $value;
        }

        if ($layout === 'none') {
            require $viewFile;
            return;
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = dirname(__DIR__) . '/views/Layout/' . $layout . '.php';

        if (!file_exists($layoutFile)) {
            exit('Layout not found.');
        }

        require $layoutFile;
    }

    public function json($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function redirect($url): void
    {
        header('Location: ' . $url);
        exit;
    }
}