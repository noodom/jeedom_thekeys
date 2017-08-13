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
        $url = 'utilisateur/get/' . urlencode(config::byKey('username','thekeys'));
        $json = thekeys::callCloud($url);
        foreach ($json['data']['clefs'] as $key) {
            $thekeys = self::byLogicalId($key['id'], 'thekeys');
            if (!is_object($thekeys)) {
                $thekeys = new thekeys();
                $thekeys->setEqType_name('thekeys');
                $thekeys->setLogicalId($key['id']);
                $thekeys->setIsEnable(1);
                $thekeys->setIsVisible(1);
                $thekeys->setName($key['nom'] . ' ' . $key['id']);
                $thekeys->setConfiguration('id', $key['id']);
                $thekeys->setConfiguration('id_serrure', $key['id_serrure']);
                $thekeys->setConfiguration('code', $key['code']);
                $thekeys->setConfiguration('code_serrure', $key['code_serrure']);
                $thekeys->setConfiguration('nom', $key['nom']);
                $thekeys->save();
            }
            $thekeys->loadCmdFromConf();
            $value = ($key['etat'] == 'open') ? 0:1;
            $thekeys->checkAndUpdateCmd('status',$value);
            $url = 'partage/all/clef/' . $key['id'];
            $json = thekeys::callCloud($url);
            log::add('thekeys', 'debug', 'Retour : ' . print_r($json, true));
        }

    }

    public function loadCmdFromConf($_update = false) {
        if (!is_file(dirname(__FILE__) . '/../config/devices/key.json')) {
            return;
        }
        $content = file_get_contents(dirname(__FILE__) . '/../config/devices/key.json');
        if (!is_json($content)) {
            return;
        }
        $device = json_decode($content, true);
        if (!is_array($device) || !isset($device['commands'])) {
            return true;
        }
        if (isset($device['name']) && !$_update) {
            $this->setName('[' . $this->getLogicalId() . ']' . $device['name']);
        }
        $this->import($device);
    }

    public function cronHourly() {
        //thekeys::authCloud();
        $url = 'utilisateur/get/' . urlencode(config::byKey('username','thekeys'));
        $json = thekeys::callCloud($url);
        /*foreach ($json['data']['clefs'] as $key) {
            # code...
        }*/
    }

    public function cron() {
        foreach (eqLogic::byType('thekeys', true) as $thekeys) {
            $url = 'clef/get/' . $thekeys->getLogicalId();
            $json = thekeys::callCloud($url);
            //$value = ($json['data']['etat'] == 'open') ? 0:1;
            //$thekeys->checkAndUpdateCmd('status',$value);
        }
    }

    public function callCloud($url) {
        $url = 'https://api.the-keys.fr/fr/api/v2/' . $url . '?_format=json';
        if (time() > config::byKey('timestamp','thekeys')) {
            thekeys::authCloud();
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        $headers = [
            'Authorization: Bearer ' . config::byKey('token','thekeys')
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER , 1);
        $json = json_decode(curl_exec($curl), true);
        curl_close ($curl);
        log::add('thekeys', 'debug', 'URL : ' . $url);
        log::add('thekeys', 'debug', 'Authorization: Bearer ' . config::byKey('token','thekeys'));
        log::add('thekeys', 'debug', 'Retour : ' . print_r($json, true));
        return $json;
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
        curl_setopt($curl,CURLOPT_RETURNTRANSFER , 1);
        $json = json_decode(curl_exec($curl), true);
        curl_close ($curl);
        $timestamp = time() + (2 * 60 * 60);
        config::save('token', $json['token'],  'thekeys');
        config::save('timestamp', $timestamp,  'thekeys');
        //log::add('thekeys', 'debug', 'Retour : ' . print_r($json, true));
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
            $eqLogic = $this->getEqLogic();
            $timestamp = time() + (2 * 60 * 60);
            $url = 'http://' . $eqLogic->getConfiguration('gateway') . '/' . $this->getEqLogic() . '?identifier=' . $eqLogic->getConfiguration('id_serrure') . '&ts=' . $timestamp;
            if ($eqLogic->getConfiguration('gateway') != '') {
                file_get_contents($url);
            }
            log::add('thekeys', 'debug', 'Call : ' . $url);
            return true;
        }
        return true;
    }
}

?>
