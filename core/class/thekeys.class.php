<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class thekeys extends eqLogic {

    public function pageConf() {
        thekeys::authCloud();
        $url = 'https://api.the-keys.fr/fr/api/v1/welcome';
        //$url = 'https://api.the-keys.fr/fr/api/v1/get/' . urlencode(config::byKey('username','thekeys'));
        thekeys::callCloud($url);
    }

    public function callCloud($url) {
        if (time() > config::byKey('timestamp','thekeys')) {
            thekeys::authCloud($user,$pass);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        $headers = [
            'Authorization: Bearer ' . config::byKey('token','thekeys')
        ];
        log::add('thekeys', 'debug', 'Headers : ' . $headers[0]);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $retour = curl_exec($curl);
        curl_close ($curl);
        $json = json_decode($retour, true);

        log::add('thekeys', 'debug', 'Retour : ' . $retour);
    }

    public function authCloud() {
        $url = 'https://api.the-keys.fr/api/login_check';
        $user = config::byKey('username','thekeys');
        $pass = config::byKey('password','thekeys');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_POST, 1);
        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $fields = array(
        	'_username' => urlencode($user),
        	'_password' => urlencode($pass),
        );
        $fields_string = '';
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');
        curl_setopt($curl,CURLOPT_POST, count($fields));
        curl_setopt($curl,CURLOPT_POSTFIELDS, $fields_string);
        $retour = curl_exec($curl);
        $json = json_decode($retour, true);
        curl_close ($curl);
        $timestamp = time() + (2 * 60 * 60);
        config::save('token', $json['token'],  'thekeys');
        config::save('timestamp', $timestamp,  'thekeys');
        log::add('thekeys', 'debug', 'Retour : ' . $retour);
        return;
    }

}

class thekeysCmd extends cmd {
    public function execute($_options = null) {
        switch ($this->getType()) {
            case 'info' :
            return $this->getConfiguration('value');
            break;
            case 'action' :
            $request = $this->getConfiguration('request');
            switch ($this->getSubType()) {
                case 'slider':
                $request = str_replace('#slider#', $value, $request);
                break;
                case 'color':
                $request = str_replace('#color#', $_options['color'], $request);
                break;
                case 'message':
                if ($_options != null)  {
                    $replace = array('#title#', '#message#');
                    $replaceBy = array($_options['title'], $_options['message']);
                    if ( $_options['title'] == '') {
                        throw new Exception(__('Le sujet ne peuvent être vide', __FILE__));
                    }
                    $request = str_replace($replace, $replaceBy, $request);

                }
                else
                $request = 1;
                break;
                default : $request == null ?  1 : $request;
            }

            return true;
        }
        return true;
    }
}

?>
