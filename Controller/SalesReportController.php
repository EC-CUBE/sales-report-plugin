<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport44\Controller;

use Eccube\Controller\AbstractController;
use Plugin\SalesReport44\Form\Type\SalesReportType;
use Plugin\SalesReport44\Service\SalesReportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class SalesReportController.
 */
class SalesReportController extends AbstractController
{
    /**
     * SalesReportController constructor.
     *
     * @param SalesReportService $salesReportService
     */
    public function __construct(
        protected SalesReportService $salesReportService,
    ) {
    }

    /**
     * 期間別集計.
     *
     * @param Request $request
     */
    #[Route(path: '%eccube_admin_route%/plugin/sales_report/term', name: 'sales_report_admin_term')]
    public function term(Request $request): Response
    {
        return $this->response($request, 'term');
    }

    /**
     * 商品別集計.
     *
     * @param Request $request
     */
    #[Route(path: '%eccube_admin_route%/plugin/sales_report/product', name: 'sales_report_admin_product')]
    public function product(Request $request): Response
    {
        return $this->response($request, 'product');
    }

    /**
     * 年代別集計.
     *
     * @param Request $request
     */
    #[Route(path: '%eccube_admin_route%/plugin/sales_report/age', name: 'sales_report_admin_age')]
    public function age(Request $request): Response
    {
        return $this->response($request, 'age');
    }

    /**
     * 商品CSVの出力.
     *
     * @param Request $request
     * @param string $type
     */
    #[Route(path: '%eccube_admin_route%/plugin/sales_report/export/{type}', name: 'sales_report_admin_export', methods: ['POST'], requirements: ['type' => 'term|product|age'])]
    public function export(Request $request, string $type): StreamedResponse
    {
        set_time_limit(0);
        $response = new StreamedResponse();
        $session = $request->getSession();
        if ($session->has('eccube.admin.sales_report.export')) {
            $searchData = $session->get('eccube.admin.sales_report.export');
        } else {
            $searchData = [];
        }

        $data = [
            'graph' => null,
            'raw' => null,
        ];

        // Query data from database
        if ($searchData) {
            if ($searchData['term_end']) {
                $searchData['term_end'] = $searchData['term_end']->modify('- 1 day');
            }
            $data = $this->salesReportService
                ->setReportType($type)
                ->setTerm($searchData['term_type'], $searchData)
                ->getData();
        }

        $response->setCallback(function () use ($data, $type) {
            $exportSeparator = $this->eccubeConfig['eccube_csv_export_separator'];
            $exportEncoding = $this->eccubeConfig['eccube_csv_export_encoding'];
            // Export data by type
            // 集計結果が 0 件のとき $data['raw'] は null になる。export メソッドは
            // array 型を要求するため、空配列を渡してヘッダ行のみの CSV を出力する。
            match ($type) {
                'term' => $this->salesReportService->exportTermCsv($data['raw'] ?? [], $exportSeparator, $exportEncoding),
                'product' => $this->salesReportService->exportProductCsv($data['raw'] ?? [], $exportSeparator, $exportEncoding),
                'age' => $this->salesReportService->exportAgeCsv($data['raw'] ?? [], $exportSeparator, $exportEncoding),
                default => $this->salesReportService->exportTermCsv($data['raw'] ?? [], $exportSeparator, $exportEncoding),
            };
        });

        // Set filename by type
        $now = new \DateTime();
        $filename = match ($type) {
            'term' => 'salesreport_term_'.$now->format('YmdHis').'.csv',
            'product' => 'salesreport_product_'.$now->format('YmdHis').'.csv',
            'age' => 'salesreport_age_'.$now->format('YmdHis').'.csv',
            default => 'salesreport_term_'.$now->format('YmdHis').'.csv',
        };

        $response->headers->set('Content-Type', 'application/octet-stream;');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

        log_info('売上集計CSV出力ファイル名', [$filename]);

        return $response;
    }

    /**
     * direct by report type(default term).
     *
     * @param Request $request
     * @param string|null $reportType
     */
    private function response(Request $request, ?string $reportType = null): Response
    {
        $builder = $this->formFactory
            ->createBuilder(SalesReportType::class);
        if (!is_null($reportType) && $reportType !== 'term') {
            $builder->remove('unit');
        }
        /* @var $form \Symfony\Component\Form\Form */
        $form = $builder->getForm();
        $form->handleRequest($request);

        $data = [
            'graph' => null,
            'raw' => null,
        ];

        $options = [];

        if (!is_null($reportType) && $form->isSubmitted() && $form->isValid()) {
            $session = $request->getSession();
            $searchData = $form->getData();
            $searchData['term_type'] = $form->get('term_type')->getData();
            $session->set('eccube.admin.sales_report.export', $searchData);
            $termType = $form->get('term_type')->getData();

            $data = $this->salesReportService
                ->setReportType($reportType)
                ->setTerm($termType, $searchData)
                ->getData();
            $options = $this->getRenderOptions($reportType, $searchData);
        }

        $template = is_null($reportType) ? 'term' : $reportType;
        log_info('SalesReport Plugin : render ', ['template' => $template]);

        return $this->render(
            '@SalesReport44/admin/'.$template.'.twig',
            [
                'form' => $form->createView(),
                'graphData' => json_encode($data['graph']),
                'rawData' => $data['raw'],
                'type' => $reportType,
                'options' => $options,
            ]
        );
    }

    /**
     * get option params for render.
     *
     * @param string $termType
     * @param array<string, mixed> $searchData
     *
     * @return array<string, mixed> options
     */
    private function getRenderOptions(string $termType, array $searchData): array
    {
        $options = [];

        switch ($termType) {
            case 'term':
                // 期間の集計単位
                if (isset($searchData['unit'])) {
                    $options['unit'] = $searchData['unit'];
                }
                break;
            default:
                // no option
                break;
        }

        return $options;
    }
}
