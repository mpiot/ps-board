<?php

/*
 * Copyright 2019 Mathieu Piot.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace App\Controller;

use App\Services\PrestashopApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HomepageController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index(PrestashopApi $prestashopApi)
    {
        $nbUsers = $prestashopApi->getNumberOfLoginUsers();
        $nbNewUsers = $prestashopApi->getNumberOfNewUsers();
        $nbOrders = $prestashopApi->getNumberOfOrders();
        $nbOrdersPreviousWeek = $prestashopApi->getNumberOfOrders(true);
        $lastOrders = $prestashopApi->getLastOrders();
        $ca = $prestashopApi->getCA();

        return $this->render('homepage/index.html.twig', [
            'nbUsers' => $nbUsers,
            'nbNewUsers' => $nbNewUsers,
            'nbOrders' => $nbOrders,
            'nbOrdersPreviousWeek' => $nbOrdersPreviousWeek,
            'lastOrders' => $lastOrders,
            'ca' => $ca,
        ]);
    }

    /**
     * @Route("/stats/user", name="stats_user")
     */
    public function userStats(PrestashopApi $prestashopApi)
    {
        return new JsonResponse([
            'connected_users' => $prestashopApi->getNumberOfLoginUsers(),
            'new_users' => $prestashopApi->getNumberOfNewUsers(),
        ]);
    }

    /**
     * @Route("/stats/ca", name="stats_ca")
     */
    public function caStats(PrestashopApi $prestashopApi)
    {
        return new JsonResponse($prestashopApi->getCA());
    }

    /**
     * @Route("/stats/orders", name="stats_order")
     */
    public function orderStats(PrestashopApi $prestashopApi)
    {
        return $this->render('homepage/_number_orders.html.twig', [
            'nbOrders' => $prestashopApi->getNumberOfOrders(),
            'nbOrdersPreviousWeek' => $prestashopApi->getNumberOfOrders(true),
        ]);
    }

    /**
     * @Route("/stats/orders-list", name="stats_order_list")
     */
    public function orderList(PrestashopApi $prestashopApi)
    {
        return $this->render('homepage/_orders_list.html.twig', [
            'orders' => $prestashopApi->getLastOrders(),
        ]);
    }
}
