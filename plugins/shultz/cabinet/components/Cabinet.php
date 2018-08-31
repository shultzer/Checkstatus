<?php

namespace shultz\cabinet\Components;

use Cms\Classes\ComponentBase;

class Cabinet extends ComponentBase
{

    public $method;

    public function componentDetails () {
        return [
          'name'        => 'Проверка статуса',
          'description' => 'Show status of order by tel number',
        ];
    }

    public function defineProperties () {
        return [
          'apikey' => [
            'title'             => 'CRM API KEY',
            'description'       => 'Type your api key from crm',
            'default'           => 'fea2824d00834c7e82439872b3190cbf',
            'type'              => 'string',
            'validationMessage' => 'The api key is required',
            'required'          => 'required',
          ],
        ];
    }

    public function onRun () {
        $this->page [ 'method' ] = $_SERVER[ 'REQUEST_METHOD' ];
        $res                     = NULL;
        $this->page [ 'notfound' ]  = '';

        // определение мобильного устройства
        $mobile_agent_array     = [
          'ipad',
          'iphone',
          'android',
          'pocket',
          'palm',
          'windows ce',
          'windowsce',
          'cellphone',
          'opera mobi',
          'ipod',
          'small',
          'sharp',
          'sonyericsson',
          'symbian',
          'opera mini',
          'nokia',
          'htc_',
          'samsung',
          'motorola',
          'smartphone',
          'blackberry',
          'playstation portable',
          'tablet browser',
        ];
        $agent                  = strtolower($_SERVER[ 'HTTP_USER_AGENT' ]);
        $this->page[ 'mobile' ] = FALSE;

        foreach ( $mobile_agent_array as $value ) {
            if ( strpos($agent, $value) !== FALSE ) {
                $this->page[ 'mobile' ] = TRUE;
            }
        }

    }

    public function onCheckstatus () {
        /*
               * если пришел post запрос без номера телефона
               * */

        if ( $_SERVER[ 'REQUEST_METHOD' ] == 'POST' && empty($_POST[ 'tel' ]) ) {

            $this->page['notfound']=1;
        }
        /*
         *если пришел post запрос с номером телефона
         * */
        if ( $_SERVER[ 'REQUEST_METHOD' ] == 'POST' && !empty($_POST[ 'tel' ]) ) {

            $tel = strip_tags(trim(preg_replace('![^0-9]+!', '', $_POST[ 'tel' ])));
            if ( strlen($tel) == 11 ) {
                $clientphone = "&client_phones[]=$tel";

                /*
                 * получаем токен от crm и добавляем в url
                 * api_key=fea2824d00834c7e82439872b3190cbf
                 * */
                if ( $curl = curl_init() ) {
                    curl_setopt($curl, CURLOPT_URL, 'https://api.remonline.ru/token/new');
                    curl_setopt($curl, CURLOPT_REFERER, 'engine/');
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($curl, CURLOPT_POST, TRUE);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, "api_key={$this->property('apikey')}");
                    $out = curl_exec($curl);
                    curl_close($curl);
                    $out    = json_decode($out, TRUE);
                    $token  = $out[ 'token' ];
                    $fields = "token=" . $token;
                }

                $fields .= $clientphone;

                /*
                 * с токеном и телефоном в строке обращаемся по api в crm
                 * */
                if ( $ch = curl_init() ) {
                    curl_setopt($ch, CURLOPT_URL, 'https://api.remonline.ru/order/?' . $fields);
                    curl_setopt($ch, CURLOPT_REFERER, 'engine/');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    $res = curl_exec($ch);
                    curl_close($ch);
                    $res = json_decode($res, TRUE);

                    if ( $res[ 'count' ] == 0 ) {
                        $this->page['notfound']=1;
                    }

                    $orders_data = $res[ 'data' ];
                    //приводим timestamp из crm к совместимому формату с getdate убирая три последних знака
                    foreach ( $orders_data as &$item ) {
                        $arr                  = getdate(substr($item[ 'created_at' ], 0, -3));
                        $day                  = $arr[ 'mday' ] < 10 ? '0' . $arr[ 'mday' ] : $arr[ 'mday' ];
                        $month                = $arr[ 'mon' ] < 10 ? '0' . $arr[ 'mon' ] : $arr[ 'mon' ];
                        $year                 = $arr[ 'year' ];
                        $item[ 'created_at' ] = $day . '.' . $month . '.' . $year;
                        //
                        $item[ 'price' ] = number_format($item[ 'price' ], 0, ',', ' ');
                    }
                    unset($item);
                    $this->page[ 'data' ] = $orders_data;
                }
            }
            else {
                $this->page['notfound']=1;
            }
        }

    }

    public function onEmpty (  ) {
        $this->page[ 'data' ] = null;
    }

}
