<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Tests\Web;

use Eccube\Repository\TaxRuleRepository;

/**
 * Class SaleReportControllerTest.
 */
class SaleReportControllerTest extends SaleReportCommon
{
    /** @var TaxRuleRepository */
    protected $taxRuleRepository;

    public function setUp()
    {
        parent::setUp();
        $this->taxRuleRepository = $this->container->get(TaxRuleRepository::class);
    }

    /**
     * test routing admin sale report.
     *
     * @param string $type
     * @param string $expected
     * @dataProvider dataRoutingProvider
     */
    public function testRouting($type, $expected)
    {
        $crawler = $this->client->request('GET', $this->generateUrl('sales_report_admin'.$type));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertContains($expected, $crawler->html());
    }

    /**
     * test display today as default.
     *
     * @param string $type
     * @dataProvider dataRoutingProvider
     */
    public function testDisplayTodayAsDefault($type)
    {
        $current = new \DateTime();
        $crawler = $this->client->request('GET', $this->generateUrl('sales_report_admin'.$type));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertContains($current->format('Y-m-d'), $crawler->html());
    }

    /**
     * data routing provider.
     *
     * @return array
     */
    public function dataRoutingProvider()
    {
        return [
            ['_term', '期間別集計'],
            ['_product', '商品別集計'],
            ['_age', '年代別集計'],
        ];
    }

    /**
     * test sale report all type.
     *
     * @param string $type
     * @param string $termType
     * @param array  $unit
     * @param string $expected
     * @dataProvider dataReportProvider
     */
    public function testSaleReportAll($type, $termType, $unit, $expected)
    {
        $current = new \DateTime();
        $arrSearch = [
            'term_type' => $termType,
            '_token' => 'dummy',
        ];
        if ($type == '' || $type == '_term') {
            $arrSearch['unit'] = $unit;
        }

        if ($termType == 'monthly') {
            $arrSearch['monthly_year'] = $current->format('Y');
            $arrSearch['monthly_month'] = $current->format('n');
        } else {
            $arrSearch['term_start'] = $current->modify('-15 days')->format('Y-m-d');
            $arrSearch['term_end'] = $current->modify('+15 days')->format('Y-m-d');
        }
        $crawler = $this->client->request('POST', $this->generateUrl('sales_report_admin'.$type), ['sales_report' => $arrSearch]);
        $this->assertContains($expected, $crawler->html());

        // Test display csv download button
        $this->assertContains('CSVダウンロード', $crawler->html());
    }

    /**
     * test product report sort by order money.
     *
     * @param string $type
     * @param string $termType
     * @dataProvider dataProductReportProvider
     */
    public function testProductReportSortByOrderMoney($type, $termType)
    {
        $i = 0;
        $j = 0;
        $orderMoney = [];
        $flag = false;
        $this->createOrderByCustomer(5);
        $current = new \DateTime();
        $arrSearch = [
            'term_type' => $termType,
            '_token' => 'dummy',
        ];

        if ($termType == 'monthly') {
            $arrSearch['monthly_year'] = $current->format('Y');
            $arrSearch['monthly_month'] = $current->format('n');
        } else {
            $arrSearch['term_start'] = $current->modify('-15 days')->format('Y-m-d');
            $arrSearch['term_end'] = $current->modify('+15 days')->format('Y-m-d');
        }
        $crawler = $this->client->request('POST', $this->generateUrl('sales_report_admin'.$type), ['sales_report' => $arrSearch]);
        $moneyElement = $crawler->filter('tr .d-none');
        //get only total money. don't get product price
        foreach ($moneyElement as $domElement) {
            if ($i % 2 != 0) {
                $orderMoney[$j] = $domElement->nodeValue;
                ++$j;
            }
            ++$i;
        }
        //check array is order by desc or not
        for ($i = 0; $i < (sizeof($orderMoney) - 1); ++$i) {
            if ($orderMoney[$i] >= $orderMoney[$i + 1]) {
                $flag = true;
            } else {
                $flag = false;
            }
        }
        $this->assertTrue($flag);
    }

    /**
     * test product delete for all pattern.
     *
     * @param string $type
     * @param string $termType
     * @param array  $unit
     * @param string $expected
     * @dataProvider dataReportProvider
     */
    public function testProductDelete($type, $termType, $unit, $expected)
    {
        $this->createOrderByCustomer(5);

        $current = new \DateTime();
        $arrSearch = [
            'term_type' => $termType,
            '_token' => 'dummy',
        ];

        if ($type == '' || $type == '_term') {
            $arrSearch['unit'] = $unit;
        }

        if ($termType == 'monthly') {
            $arrSearch['monthly_year'] = $current->format('Y');
            $arrSearch['monthly_month'] = $current->format('n');
        } else {
            $arrSearch['term_start'] = $current->modify('-15 days')->format('Y-m-d');
            $arrSearch['term_end'] = $current->modify('+15 days')->format('Y-m-d');
        }
        $crawler = $this->client->request('POST', $this->generateUrl('sales_report_admin'.$type), ['sales_report' => $arrSearch]);
        $this->assertContains($expected, $crawler->html());
    }

    /**
     * test change order detail.
     *
     * @param string $type
     * @param string $termType
     * @dataProvider dataProductReportProvider
     */
    public function testChangeOrderDetail($type, $termType)
    {
        $i = 0;
        $orderMoney = 0;
        $current = new \DateTime();
        $arrOrder = $this->createOrderByCustomer(5);
        $TaxRule = $this->taxRuleRepository->getByRule();
        $this->changeOrderDetail($arrOrder);
        $arrSearch = [
            'term_type' => $termType,
            '_token' => 'dummy',
        ];

        if ($termType == 'monthly') {
            $arrSearch['monthly_year'] = $current->format('Y');
            $arrSearch['monthly_month'] = $current->format('n');
        } else {
            $arrSearch['term_start'] = $current->modify('-15 days')->format('Y-m-d');
            $arrSearch['term_end'] = $current->modify('+15 days')->format('Y-m-d');
        }
        $crawler = $this->client->request('POST', $this->generateUrl('sales_report_admin'.$type), ['sales_report' => $arrSearch]);
        $moneyElement = $crawler->filter('tr .d-none');
        //get only total money. don't get product price
        foreach ($moneyElement as $domElement) {
            $orderMoney += $domElement->nodeValue;
            ++$i;
        }

        $tax = $TaxRule->getTaxRate() / 100;
        $this->expected = 500 * 5 * (1 + $tax);
        $this->actual = $orderMoney;
        $this->verify();
    }

    /**
     * data report provider.
     *
     * @return array
     */
    public function dataReportProvider()
    {
        return [
            ['_term', 'monthly', 'byDay', '購入平均'],
            ['_term', 'monthly', 'byMonth', '購入平均'],
            ['_term', 'monthly', 'byWeekDay', '購入平均'],
            ['_term', 'monthly', 'byHour', '購入平均'],
            ['_term', 'term', 'byDay', '購入平均'],
            ['_term', 'term', 'byMonth', '購入平均'],
            ['_term', 'term', 'byWeekDay', '購入平均'],
            ['_term', 'term', 'byHour', '購入平均'],
//            ['_product', 'monthly', null, '商品名'],
//            ['_product', 'term', null, '商品名'],
            ['_age', 'monthly', null, '購入平均'],
            ['_age', 'term', null, '購入平均'],
        ];
    }

    /**
     * product report data provider.
     *
     * @return array
     */
    public function dataProductReportProvider()
    {
        return [
            ['_product', 'monthly'],
            ['_product', 'term'],
        ];
    }
}
