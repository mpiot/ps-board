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

namespace App\Services;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PrestashopApi
{
    private const TOKEN = 'IF7ENX7FA4CMHXBA1SSV3EDHMUFH8K55';
    private const API_URL = 'http://prestashop.quentinbesnard.fr/api/';

    private $httpClient;
    /** @var HttpClient $client */
    private $client;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getNumberOfLoginUsers()
    {
        $response = $this->getClient()->request('GET', self::API_URL.'customers?display=[id]&filter[optin]=[1]&output_format=JSON');

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $content = json_decode($response->getContent());

        if (empty($content)) {
            return 0;
        }

        return \count($content->customers);
    }

    public function getNumberOfNewUsers()
    {
        $dates = $this->getThisWeekStartAndEnd();
        $response = $this->getClient()->request('GET', self::API_URL.'customers?date=1&display=[id]&filter[date_add]=['.$dates['start'].','.$dates['end'].']&output_format=JSON');

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $content = json_decode($response->getContent());

        if (empty($content)) {
            return 0;
        }

        return \count($content->customers);
    }

    public function getNumberOfOrders(bool $previousWeek = false)
    {
        if ($previousWeek) {
            $dates = $this->getPreviousWeekStartAndEnd();
        } else {
            $dates = $this->getThisWeekStartAndEnd();
        }

        $response = $this->getClient()->request('GET', self::API_URL.'orders?date=1&display=[id,date_add]&sort=[date_add_DESC]&filter[date_add]=['.$dates['start'].','.$dates['end'].']&output_format=JSON');

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $content = json_decode($response->getContent());

        if (empty($content)) {
            return 0;
        }

        return \count($content->orders);
    }

    public function getLastOrders()
    {
        $dates = $this->getThisWeekStartAndEnd();
        $response = $this->getClient()->request('GET', self::API_URL.'orders?date=1&display=[id,id_customer,total_paid,date_add,current_state]&filter[current_state]=[1|2|9|10|11|12|13|14]&filter[date_add]=['.$dates['start'].','.$dates['end'].']&limit=0,5&sort=[date_add_DESC]&output_format=JSON');

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $content = json_decode($response->getContent());

        foreach ($content->orders as &$order) {
            $order->customer = $this->getCustomer($order->id_customer);
            $order->status = $this->getOrderStatus($order->current_state);
            $order->date_add = new \DateTime($order->date_add);
        }

        return $content->orders;
    }

    public function getCA()
    {
        $dates = $this->getThisYearStartAndEnd();
        $response = $this->getClient()->request('GET', self::API_URL.'orders?date=1&display=[total_paid,date_add]&filter[current_state]=[1|2|9|10|11|12|13|14]&filter[date_add]=['.$dates['start'].','.$dates['end'].']&sort=[date_add_ASC]&output_format=JSON');

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $content = json_decode($response->getContent());

        $ca = [
            'week' => 0.0,
            'month' => 0.0,
            'year' => 0.0,
        ];

        $weekDates = $this->getThisWeekStartAndEnd(false);
        $monthDates = $this->getThisMonthStartAndEnd(false);

        foreach ($content->orders as $value) {
            $date = new \DateTime($value->date_add);

            $ca['year'] += $value->total_paid;

            if ($date >= $monthDates['start'] && $date <= $monthDates['end']) {
                $ca['month'] += $value->total_paid;
            } else {
                continue;
            }

            if ($date >= $weekDates['start'] && $date <= $weekDates['end']) {
                $ca['week'] += $value->total_paid;
            }
        }

        return $ca;
    }

    private function getCustomer(int $id)
    {
        $response = $this->getClient()->request('GET', self::API_URL.'customers/'.$id.'?output_format=JSON');

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        return json_decode($response->getContent())->customer;
    }

    private function getOrderStatus(int $id)
    {
        $response = $this->getClient()->request('GET', self::API_URL.'order_states/'.$id.'?output_format=JSON');

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        return json_decode($response->getContent())->order_state;
    }

    private function getThisWeekStartAndEnd($formated = true)
    {
        $start = (new \DateTime('monday this week'))->setTime(0, 0, 0, 0);
        $end = (new \DateTime('sunday this week'))->setTime(23, 59, 59, 999999);

        if ($formated) {
            $start = $start->format('Y-m-d');
            $end = $end->format('Y-m-d');
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function getPreviousWeekStartAndEnd($formated = true)
    {
        $start = (new \DateTime('monday last week'))->setTime(0, 0, 0, 0);
        $end = (new \DateTime('sunday last week'))->setTime(23, 59, 59, 999999);

        if ($formated) {
            $start = $start->format('Y-m-d');
            $end = $end->format('Y-m-d');
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function getThisMonthStartAndEnd($formated = true)
    {
        $start = (new \DateTime('first day of this month'))->setTime(0, 0, 0, 0);
        $end = (new \DateTime('last day of this month'))->setTime(23, 59, 59, 999999);

        if ($formated) {
            $start = $start->format('Y-m-d');
            $end = $end->format('Y-m-d');
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function getThisYearStartAndEnd($formated = true)
    {
        $start = (new \DateTime('first day of january this year'))->setTime(0, 0, 0, 0);
        $end = (new \DateTime('last day of december this year'))->setTime(23, 59, 59, 999999);

        if ($formated) {
            $start = $start->format('Y-m-d');
            $end = $end->format('Y-m-d');
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function getClient(): HttpClientInterface
    {
        if (null === $this->client) {
            $this->client = HttpClient::create([
                'auth_basic' => [self::TOKEN],
            ]);
        }

        return $this->client;
    }
}
