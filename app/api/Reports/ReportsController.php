<?php
namespace Api\Reports;

use Core\BaseController;
use Models\Reports;

class ReportsController extends BaseController
{
    public function sales()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use GET to generate reports.']);
        }
        try {
            $data = (new Reports())->sales(
                array_key_exists('period', $_GET) ? $_GET['period'] : 'daily',
                array_key_exists('date', $_GET) ? $_GET['date'] : date('Y-m-d')
            );
        } catch (\InvalidArgumentException $error) {
            http_response_code(400);
            $this->json(['status'=>'error','message'=>$error->getMessage()]);
            return;
        }
        $this->json(['status'=>'success','data'=>$data]);
    }
}
