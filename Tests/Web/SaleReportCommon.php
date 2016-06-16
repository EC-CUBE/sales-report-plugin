<?php
/**
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Tests\Web;


use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

class SaleReportCommon extends AbstractAdminWebTestCase
{
    public function createCustomerByNumber($number = 5)
    {
        $arrCustomer = array();
        $current = new \DateTime();
        for ($i = 0; $i < $number; $i++) {

            $email = 'customer0' . $i . '@mail.com';
            $age = ($i < 7) ? $i * 10 + 19 : $i * 10 - 19;
            $age = $current->modify("-$age years");
            $Customer = $this->createCustomer($email);
            $arrCustomer[] = $Customer->getId();
            $Customer->setBirth($age);
            $this->app['orm.em']->persist($Customer);
            $this->app['orm.em']->flush();
        }
        return $arrCustomer;
    }

    public function createOrderByCustomer($number = 5)
    {
        $arrCustomer = $this->createCustomerByNumber($number);
        $arrOrder = array();
        for ($i = 0; $i < count($arrCustomer); $i++) {
            $Customer = $this->app['eccube.repository.customer']->find($arrCustomer[$i]);
            $arrOrder[] = $this->createOrder($Customer)->getId();
        }

        return $arrOrder;
    }
}
