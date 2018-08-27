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

use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

/**
 * Class SaleReportCommon.
 */
class SaleReportCommon extends AbstractAdminWebTestCase
{
    /** @var CustomerRepository */
    protected $customerRepository;

    /** @var OrderStatusRepository */
    protected $orderStatusRepository;

    /**
     * Set up function.
     */
    public function setUp()
    {
        parent::setUp();
        $this->customerRepository = $this->container->get(CustomerRepository::class);
        $this->orderStatusRepository = $this->container->get(OrderStatusRepository::class);
    }

    /**
     * createCustomerByNumber.
     *
     * @param int $number
     *
     * @return array
     */
    public function createCustomerByNumber($number = 5)
    {
        $arrCustomer = [];
        $current = new \DateTime();
        for ($i = 0; $i < $number; ++$i) {
            $email = 'customer0'.$i.'@mail.com';
            $age = rand(10, 50);
            $age = $current->modify("-$age years");
            $Customer = $this->createCustomer($email);
            $arrCustomer[] = $Customer->getId();
            $Customer->setBirth($age);
            $this->entityManager->persist($Customer);
            $this->entityManager->flush($Customer);
        }

        return $arrCustomer;
    }

    /**
     * createOrderByCustomer.
     *
     * @param int $number
     *
     * @return array $arrOrder
     */
    public function createOrderByCustomer($number = 5)
    {
        $arrCustomer = $this->createCustomerByNumber($number);
        $current = new \DateTime();
        $arrOrder = [];
        for ($i = 0; $i < count($arrCustomer); ++$i) {
            $Customer = $this->customerRepository->find($arrCustomer[$i]);
            $Order = $this->createOrder($Customer);
            $Order->setOrderStatus($this->orderStatusRepository->find(OrderStatus::NEW));
            $Order->setOrderDate($current);
            $arrOrder[] = $Order;
            $this->entityManager->persist($Order);
            $this->entityManager->flush($Order);
        }

        return $arrOrder;
    }
}
