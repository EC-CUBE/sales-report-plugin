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

namespace Plugin\SalesReport44\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Util\CsvFormulaGuard;

/**
 * Class SalesReportService.
 */
class SalesReportService
{
    /**
     * @var string
     */
    private $reportType;

    /**
     * @var string
     */
    private $termStart;

    /**
     * @var string
     */
    private $termEnd;

    /**
     * @var string
     */
    private $unit;

    /**
     * @var array<int, string>
     */
    private $productCsvHeader = [
        'sales_report.admin.productCsvHeader.001',
        'sales_report.admin.productCsvHeader.002',
        'sales_report.admin.productCsvHeader.003',
        'sales_report.admin.productCsvHeader.004',
        'sales_report.admin.productCsvHeader.005',
    ];

    /**
     * @var array<int, string>
     */
    private $termCsvHeader = [
        'sales_report.admin.termCsvHeader.001',
        'sales_report.admin.termCsvHeader.002',
        'sales_report.admin.termCsvHeader.003',
        'sales_report.admin.termCsvHeader.004',
        'sales_report.admin.termCsvHeader.005',
        'sales_report.admin.termCsvHeader.006',
        'sales_report.admin.termCsvHeader.007',
        'sales_report.admin.termCsvHeader.008',
        'sales_report.admin.termCsvHeader.009',
        'sales_report.admin.termCsvHeader.010',
        'sales_report.admin.termCsvHeader.011',
    ];

    /**
     * @var array<int, string>
     */
    private $ageCsvHeader = [
        'sales_report.admin.ageCsvHeader.001',
        'sales_report.admin.ageCsvHeader.002',
        'sales_report.admin.ageCsvHeader.003',
        'sales_report.admin.ageCsvHeader.004',
    ];

    public const MALE = 1;

    public const FEMALE = 2;

    /**
     * SalesReportService constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        protected EntityManagerInterface $entityManager,
        private EccubeConfig $eccubeConfig,
        private readonly BaseInfoRepository $baseInfoRepository,
    ) {
    }

    /**
     * CSV の 1 行を数式インジェクション対策で無害化する.
     *
     * 本体の CsvExportService と同じく Eccube\Util\CsvFormulaGuard を使い、
     * 店舗設定（BaseInfo::isOptionSanitizeCsvFormulas()）の ON/OFF を尊重する。
     *
     * @param array<int, mixed> $row
     *
     * @return array<int, mixed>
     */
    private function sanitizeCsvRow(array $row): array
    {
        if (!$this->baseInfoRepository->get()->isOptionSanitizeCsvFormulas()) {
            return $row;
        }

        // CsvFormulaGuard::escape() は int|string|null を受けるため、
        // round() が返す float 等を渡さないよう文字列だけを対象にする。
        // （非文字列は escape() でもそのまま返される）
        return array_map(
            static fn ($value) => is_string($value) ? CsvFormulaGuard::escape($value) : $value,
            $row
        );
    }

    /**
     * setReportType.
     *
     * @param string $reportType
     *
     * @return SalesReportService
     */
    public function setReportType(string $reportType): self
    {
        $this->reportType = $reportType;

        return $this;
    }

    /**
     * set term from , to.
     *
     * @param string|null $termType 未指定 (null) の場合は期間集計として扱う
     * @param array<string, mixed> $request
     *
     * @return SalesReportService
     */
    public function setTerm(?string $termType, array $request): self
    {
        if ($termType === 'monthly') {
            // 月度集計
            $year = $request['monthly_year'];
            $month = $request['monthly_month'];

            $date = new \DateTime();
            $date->setDate($year, $month, 1)->setTime(0, 0, 0);

            $start = $date->format('Y-m-d G:i:s');
            $end = $date->modify('+ 1 month')->format('Y-m-d G:i:s');

            $this
                ->setTermStart($start)
                ->setTermEnd($end);
        } else {
            // 期間集計
            $start = $request['term_start']
                ->format('Y-m-d 00:00:00');
            $end = $request['term_end']
                ->modify('+ 1 day')
                ->format('Y-m-d 00:00:00');

            $this->setTermStart($start);
            $this->setTermEnd($end);
        }

        // 集計単位を設定。
        // 商品別/年代別の画面は集計単位のフォーム項目を持たないため、3 画面で共通の
        // セッションキーには unit を含まない検索条件が保存されることがある。
        // 未指定のまま期間別の集計に進むと集計単位が決まらないため既定値を入れる。
        $this->unit = $request['unit'] ?? 'byDay';

        return $this;
    }

    /**
     * query and get order data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $excludes = [
            OrderStatus::CANCEL,
            OrderStatus::PENDING,
            OrderStatus::PROCESSING,
            OrderStatus::RETURNED,
        ];

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('o')
            ->from(Order::class, 'o')
            ->andWhere('o.order_date >= :start')
            ->andWhere('o.order_date < :end')
            ->andWhere('o.OrderStatus NOT IN (:excludes)')
            ->setParameter(':excludes', $excludes)
            ->setParameter(':start', new \DateTime($this->termStart))
            ->setParameter(':end', new \DateTime($this->termEnd));
        if ($this->reportType === 'product') {
            $qb->addSelect('oi')->innerJoin('o.OrderItems', 'oi', 'WITH', 'oi.OrderItemType = 1');
        }

        log_info('SalesReport Plugin : search parameters ', ['From' => $this->termStart, 'To' => $this->termEnd]);
        $result = [];
        try {
            $result = $qb->getQuery()->getResult();
        } catch (NoResultException $e) {
            log_info('SalesReport Plugin : Exception '.$e->getMessage());
        }

        return $this->convert($result);
    }

    /**
     * get product report csv.
     *
     * @param array<int|string, mixed> $rows
     * @param string $separator
     * @param string $encoding
     */
    public function exportProductCsv(array $rows, string $separator, string $encoding): void
    {
        try {
            $handle = fopen('php://output', 'w+');
            $headers = $this->productCsvHeader;
            $headerRow = [];
            // convert header to encoding
            foreach ($headers as $header) {
                $headerRow[] = mb_convert_encoding(trans($header), $encoding, 'UTF-8');
            }
            fputcsv($handle, $headerRow, $separator);
            // convert data to encoding
            foreach ($rows as $id => $row) {
                $code = mb_convert_encoding($row['OrderDetail']->getProductCode(), $encoding, 'UTF-8');
                $name = $row['OrderDetail']->getProductName().' '.$row['OrderDetail']->getClassCategoryName1().' '.$row['OrderDetail']->getClassCategoryName2();
                $name = mb_convert_encoding($name, $encoding, 'UTF-8');
                fputcsv($handle, $this->sanitizeCsvRow([$code, $name, $row['time'], $row['quantity'], $row['total']]), $separator);
            }
            fclose($handle);
        } catch (\Exception $e) {
            log_info('CSV product export exception', [$e->getMessage()]);
        }
    }

    /**
     * get term report csv.
     *
     * @param array<int|string, mixed> $rows
     * @param string $separator
     * @param string $encoding
     */
    public function exportTermCsv(array $rows, string $separator, string $encoding): void
    {
        try {
            $handle = fopen('php://output', 'w+');
            $headers = $this->termCsvHeader;
            $headerRow = [];
            // convert header to encoding
            foreach ($headers as $header) {
                $headerRow[] = mb_convert_encoding(trans($header), $encoding, 'UTF-8');
            }
            fputcsv($handle, $headerRow, $separator);
            foreach ($rows as $date => $row) {
                if ($row['time'] > 0) {
                    $money = round($row['price'] / $row['time']);
                } else {
                    $money = 0;
                }
                fputcsv($handle, $this->sanitizeCsvRow([$date, $row['time'], $row['male'], $row['female'], $row['other'], $row['member_male'], $row['nonmember_male'], $row['member_female'], $row['nonmember_female'], $row['price'], $money]), $separator);
            }
            fclose($handle);
        } catch (\Exception $e) {
            log_info('CSV term export exception', [$e->getMessage()]);
        }
    }

    /**
     * get age report csv.
     *
     * @param array<int|string, mixed> $rows
     * @param string $separator
     * @param string $encoding
     */
    public function exportAgeCsv(array $rows, string $separator, string $encoding): void
    {
        try {
            $handle = fopen('php://output', 'w+');
            $headers = $this->ageCsvHeader;
            $headerRow = [];
            // convert header to encoding
            foreach ($headers as $header) {
                $headerRow[] = mb_convert_encoding(trans($header), $encoding, 'UTF-8');
            }
            fputcsv($handle, $headerRow, $separator);
            foreach ($rows as $age => $row) {
                if ($row['time'] > 0) {
                    $money = round($row['total'] / $row['time']);
                } else {
                    $money = 0;
                }
                // convert from number to japanese.
                if ($age == 999) {
                    $age = trans('sales_report.admin.age.list.001');
                    $age = mb_convert_encoding($age, $encoding, 'UTF-8');
                } else {
                    $age = mb_convert_encoding($age.trans('sales_report.admin.age.list.002'), $encoding, 'UTF-8');
                }
                fputcsv($handle, $this->sanitizeCsvRow([$age, $row['time'], $row['total'], $money]), $separator);
            }
            fclose($handle);
        } catch (\Exception $e) {
            log_info('CSV age export exception', [$e->getMessage()]);
        }
    }

    /**
     * setTermStart.
     *
     * @param string $term
     *
     * @return SalesReportService
     */
    private function setTermStart(string $term): self
    {
        $this->termStart = $term;

        return $this;
    }

    /**
     * setTermEnd.
     *
     * @param string $term
     *
     * @return SalesReportService
     */
    private function setTermEnd(string $term): self
    {
        $this->termEnd = $term;

        return $this;
    }

    /**
     * convert to graph data by report type.
     *
     * @param array<int, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convert(array $data): array
    {
        $result = [];
        switch ($this->reportType) {
            case 'term':
                $result = $this->convertByTerm($data);

                if (!empty($result['raw'])) {
                    foreach ($result['raw'] as $date => $value) {
                        foreach (array_keys($value) as $key) {
                            $result['raw']['total'][$key] = array_sum(array_column($result['raw'], $key));
                        }
                        break;
                    }
                }
                break;
            case 'product':
                $result = $this->convertByProduct($data);
                break;
            case 'age':
                $result = $this->convertByAge($data);
                break;
        }

        return $result;
    }

    /**
     * format unit date time.
     */
    private function formatUnit(): string
    {
        $unit = [
            'byDay' => 'Y-m-d',
            'byMonth' => 'Y-m',
            'byWeekDay' => 'D',
            'byHour' => 'H',
        ];

        // setTerm() を経由しない場合や未知の集計単位が渡った場合も日付書式を決められるようにする
        return $unit[$this->unit] ?? $unit['byDay'];
    }

    /**
     * sort array by value.
     *
     * @param string $field
     * @param array<int|string, mixed> $array
     * @param string $direction
     *
     * @return array<int|string, mixed>
     */
    private function sortBy(string $field, array &$array, string $direction = 'desc'): array
    {
        usort($array, function ($a, $b) use ($field, $direction) {
            $a = $a[$field];
            $b = $b[$field];
            if ($a == $b) {
                return 0;
            }
            if ($direction === 'desc') {
                return ($a > $b) ? -1 : 1;
            }

            return ($a < $b) ? -1 : 1;
        });

        return $array;
    }

    /**
     * get background color.
     *
     * @param int $index
     */
    private function getColor(int $index): string
    {
        $map = [
            '#F2594B',
            '#D17A45',
            '#FFAB48',
            '#FFE7AD',
            '#FFD393',
            '#9C9B7A',
            '#A7C9AE',
            '#63A69F',
            '#3F5765',
            '#685C79',
        ];
        $colorIndex = $index % count($map);

        return $map[$colorIndex];
    }

    /**
     * period sale report.
     *
     * @param array<int, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convertByTerm(array $data): array
    {
        $start = new \DateTime($this->termStart);
        $end = new \DateTime($this->termEnd);
        $raw = [];
        /** @var array<string, mixed> $price */
        $price = [];
        $orderNumber = 0;
        $format = $this->formatUnit();

        // Sort date in week
        if ($this->unit == 'byWeekDay') {
            $raw = ['Sun' => '', 'Mon' => '', 'Tue' => '', 'Wed' => '', 'Thu' => '', 'Fri' => '', 'Sat' => ''];
            $price = $raw;
        }

        for ($term = $start; $term < $end; $term = $term->modify('+ 1 Hour')) {
            $date = $term->format($format);
            $raw[$date] = [
                'price' => 0,
                'time' => 0,
                'male' => 0,
                'female' => 0,
                'other' => 0,
                'member_male' => 0,
                'nonmember_male' => 0,
                'member_female' => 0,
                'nonmember_female' => 0,
            ];
            $price[$date] = 0;
        }

        foreach ($data as $Order) {
            /* @var $Order \Eccube\Entity\Order */
            $orderDate = $Order
                ->getOrderDate()
                ->format($format);
            $price[$orderDate] += $Order->getPaymentTotal();
            $raw[$orderDate]['price'] += $Order->getPaymentTotal();
            ++$raw[$orderDate]['time'];

            // Get sex
            $Sex = $Order->getSex();
            $sexId = 0;
            if ($Sex) {
                $sexId = $Sex->getId();
            } else {
                ++$raw[$orderDate]['other'];
            }
            $raw[$orderDate]['male'] += ($sexId == self::MALE);
            $raw[$orderDate]['female'] += ($sexId == self::FEMALE);

            if ($Order->getCustomer()) {
                $raw[$orderDate]['member_male'] += ($sexId == self::MALE);
                $raw[$orderDate]['member_female'] += ($sexId == self::FEMALE);
            } else {
                $raw[$orderDate]['nonmember_male'] += ($sexId == self::MALE);
                $raw[$orderDate]['nonmember_female'] += ($sexId == self::FEMALE);
            }

            ++$orderNumber;
        }

        log_info('SalesReport Plugin : term report ', ['result count' => $orderNumber]);
        // Return null and not display in screen
        if ($orderNumber == 0) {
            return [
                'raw' => null,
                'graph' => null,
            ];
        }

        $graph = [
            'labels' => array_keys($price),
            'datasets' => [
                'label' => trans('sales_report.admin.list.label.012'),
                'data' => array_values($price),
                'lineTension' => 0.1,
                'backgroundColor' => 'rgba(75,192,192,0.4)',
                'borderColor' => 'rgba(75,192,192,1)',
                'borderCapStyle' => 'butt',
                'borderDash' => [],
                'borderDashOffset' => 0.0,
                'borderJoinStyle' => 'miter',
                'pointBorderColor' => 'rgba(75,192,192,1)',
                'pointBackgroundColor' => '#fff',
                'pointBorderWidth' => 1,
                'pointHoverRadius' => 5,
                'pointHoverBackgroundColor' => 'rgba(75,192,192,1)',
                'pointHoverBorderColor' => 'rgba(220,220,220,1)',
                'pointHoverBorderWidth' => 2,
                'pointRadius' => 1,
                'pointHitRadius' => 10,
                'spanGaps' => false,
                'borderWidth' => 1,
            ],
        ];

        return [
            'raw' => $raw,
            'graph' => $graph,
        ];
    }

    /**
     * product sale report.
     *
     * @param array<int, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convertByProduct(array $data): array
    {
        $label = [];
        $graphData = [];
        $backgroundColor = [];
        $products = [];

        foreach ($data as $Order) {
            /* @var $Order \Eccube\Entity\Order */
            foreach ($Order['OrderItems'] as $OrderDetail) {
                // Get product class id
                $productClass = $OrderDetail->getProductClass();
                if ($productClass) {
                    $productClassId = $productClass->getId();
                    if (!array_key_exists($productClassId, $products)) {
                        $products[$productClassId] = [
                            'OrderDetail' => $OrderDetail,
                            'total' => 0,
                            'quantity' => 0,
                            'price' => 0,
                            'time' => 0,
                        ];
                    }
                    $products[$productClassId]['quantity'] += $OrderDetail->getQuantity();
                    $products[$productClassId]['total'] += $OrderDetail->getTotalPrice();
                    ++$products[$productClassId]['time'];
                }
            }
        }
        // sort by total money
        $count = 0;
        $maxDisplayCount = $this->eccubeConfig['sales_report_product_maximum_display'];
        $products = $this->sortBy('total', $products);
        log_info('SalesReport Plugin : product report ', ['result count' => count($products)]);
        foreach ($products as $key => $product) {
            $backgroundColor[$count] = $this->getColor($count);

            $label[$count] = $product['OrderDetail']->getProductName().' ';
            $label[$count] .= $product['OrderDetail']->getClassCategoryName1().' ';
            $label[$count] .= $product['OrderDetail']->getClassCategoryName2();
            $graphData[$count] = $product['total'];
            ++$count;

            if ($maxDisplayCount <= $count) {
                break;
            }
        }

        $result = [
            'labels' => $label,
            'datasets' => [
                'data' => $graphData,
                'backgroundColor' => $backgroundColor,
                'borderWidth' => 0,
            ],
        ];

        // return null and not display in screen
        if ($count == 0) {
            return [
                'raw' => null,
                'graph' => null,
            ];
        }

        return [
            'raw' => $products,
            'graph' => $result,
        ];
    }

    /**
     * Age sale report.
     *
     * @param array<int, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convertByAge(array $data): array
    {
        $raw = [];
        $result = [];
        $tmp = [];
        $backgroundColor = [];
        $orderNumber = 0;
        foreach ($data as $Order) {
            $age = 999;
            /* @var $Order \Eccube\Entity\Order */
            $birth = $Order->getBirth();
            $orderDate = $Order->getOrderDate();
            if ($birth) {
                $orderDate = $orderDate ?: new \DateTime();
                $age = (floor($birth->diff($orderDate)->y / 10) * 10);
            }
            if (!isset($result[$age])) {
                $result[$age] = 0;
                $raw[$age] = [
                    'total' => 0,
                    'time' => 0,
                ];
            }
            $result[$age] += $Order->getPaymentTotal();
            $raw[$age]['total'] += $Order->getPaymentTotal();
            ++$raw[$age]['time'];
            $backgroundColor[$orderNumber] = $this->getColor($orderNumber);
            ++$orderNumber;
        }
        // Sort by age ASC.
        ksort($result);
        ksort($raw);
        foreach ($result as $key => $value) {
            if ($key == 999) {
                $key = trans('sales_report.admin.age.list.001');
                $tmp[$key] = $value;
            } else {
                $tmp[$key.trans('sales_report.admin.generation')] = $value;
            }
        }
        log_info('SalesReport Plugin : age report ', ['result count' => count($raw)]);
        // Return null and not display in screen
        if (count($raw) == 0) {
            return [
                'raw' => null,
                'graph' => null,
            ];
        }

        $graph = [
            'labels' => array_keys($tmp),
            'datasets' => [
                'label' => trans('sales_report.admin.list.label.012'),
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'borderWidth' => 0,
                'data' => array_values($tmp),
            ],
        ];

        return [
            'raw' => $raw,
            'graph' => $graph,
        ];
    }
}
