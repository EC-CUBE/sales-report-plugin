<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\SalesReport\Controller;

use Eccube\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController
{
    public function index(Application $app, Request $request)
    {
        return $this->response($app, $request);
    }

    public function term(Application $app, Request $request)
    {
        return $this->response($app, $request, 'term');
    }

    public function product(Application $app, Request $request)
    {
        return $this->response($app, $request, 'product');
    }

    public function age(Application $app, Request $request)
    {
        return $this->response($app, $request, 'age');
    }

    public function member(Application $app, Request $request)
    {
        return $this->response($app, $request, 'member');
    }

    private function response(Application $app, Request $request, $reportType = null) {
        /* @var $form \Symfony\Component\Form\Form */
        $builder = $app['form.factory'] ->createBuilder('sales_report');
        if (!is_null($reportType) && $reportType !== 'term') {
            $builder->remove('unit');
        }
        $form = $builder->getForm();
        $form->handleRequest($request);

        // データを構築
        $data = array( 'graph' => null, 'raw' => null);

        if (!is_null($reportType) && $form->isValid()) {
            $data = $app['eccube.plugin.service.sales_report']
                ->setReportType($reportType)
                ->setTerm($form->get('term_type')->getData(), $form->getData())
                ->getData();
        }

        $template = is_null($reportType) ? 'term' : $reportType;

        //csvがonの場合にCSVを出力
        if($request->get('csv')){
          $this->exportShipping($app, $request, $data['raw']);
          return "";
        }

        return $app->render(
          'SalesReport/Resource/template/' . $template . '.twig',
          array(
            'form' => $form->createView(),
            'graphData' => json_encode($data['graph']),
            'rawData' => $data['raw'],
            'type' => $reportType,
          )
        );
    }

    /**
     * CSVの出力.
     *
     * @param Application $app
     * @param Request $request
     * @return StreamedResponse
     */
    public function exportShipping(Application $app, Request $request, $data)
    {
      // タイムアウトを無効にする.
      set_time_limit(0);
      $em = $app['orm.em'];
      $em->getConfiguration()->setSQLLogger(null);

      #:TODO csvEsportServiceを使って書き直すべき？
      #$csvExportService = $app['eccube.service.csv.export'];
      #$csvExportService->fopen();
      $response = new StreamedResponse();
      $response->setCallback(function () use ($app, $request, $data){
        /* ヘッダを記載 */
        $content = "";
        $head = [];
        $head[]="商品ID";
        $head[]="商品コード";
        $head[]="商品名";
        $head[]="購入件数";
        $head[]="数量";
        $head[]="単価";
        $head[]="金額";
        $head[]="\n";
        $content .= join(',', $head);
        /* データを記載 */
        foreach($data as $d){
          $n=[];
          //$n[] = $d['price'];
          $n[] = $d['ProductClass']['Product']['id'];
          $n[] = $d['ProductClass']['code'];
          $n[] = $d['ProductClass']['Product']['name'];
          $n[] = $d['time'];
          $n[] = $d['quantity'];
          $n[] = $d['price'];
          $n[] = $d['total'];
          $n[] = "\n";
          $content .= join(',', $n);
        }

        /* ShiftJISで出力する*/
        echo mb_convert_encoding($content,"SJIS", "UTF-8");
      });
      #$csvExportService->fputcsv($content);
      #$csvExportService->fclose();

      $now = new \DateTime();
      $filename = 'shipping_' . $now->format('YmdHis') . '.csv';
      $response->setCharset('UTF-8');
      $response->headers->set('Content-Type', 'application/octet-stream');
      $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);
      $response->send();

      return $response;
    }

}
