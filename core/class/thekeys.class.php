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
        thekeys::authCloud(config::byKey('username','thekeys'),config::byKey('password','thekeys'));
    }

    public function callGateway($url,$user,$pass) {
        $curl = curl_init();
        if (time() > config::byKey('timestamp','thekeys')) {
            thekeys::authCloud($user,$pass);
        }
        log::add('thekeys', 'debug', 'Appel : ' . $url . ' avec ' . $user . ':' . $pass);

        $auth = base64_encode($user . ':' . $pass);
        $header = array("Authorization: Basic $auth");
        $opts = array( 'http' => array ('method'=>'GET',
        'header'=>$header));
        $ctx = stream_context_create($opts);
        $retour = file_get_contents($url,false,$ctx);

        //$temp = split("\r\n", $data[1]) ;

        //$result = unserialize( $temp[2] ) ;

        log::add('thekeys', 'debug', 'Retour : ' . $retour);
    }

    public function authCloud($user,$pass) {
        $url = 'https://api.the-keys.fr/api/login_check';
        log::add('thekeys', 'debug', 'Appel : ' . $url . ' avec ' . $user . ':' . $pass);
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
        $retour = json_decode(curl_exec($curl), true);
        curl_close ($curl);
        $timestamp = time() + (2 * 60 * 60);
        config::save('token', $retour['token'],  'thekeys');
        config::save('timestamp', $timestamp,  'thekeys');

        log::add('thekeys', 'debug', 'Retour : ' . $retour['token']);
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
