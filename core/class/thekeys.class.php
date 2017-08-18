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

    public function updateUser() {
        thekeys::authCloud();
        $url = 'utilisateur/get/' . urlencode(config::byKey('username','thekeys'));
        $json = thekeys::callCloud($url);
        foreach ($json['data']['serrures'] as $key) {
            $thekeys = self::byLogicalId($key['id_serrure'], 'thekeys');
            if (!is_object($thekeys)) {
                $thekeys = new thekeys();
                $thekeys->setEqType_name('thekeys');
                $thekeys->setLogicalId($key['id_serrure']);
                $thekeys->setName($key['nom'] . ' ' . $key['id_serrure']);
                $thekeys->setIsEnable(1);
                $thekeys->setConfiguration('type', 'locker');
                $thekeys->setConfiguration('id', $key['id']);
                $thekeys->setConfiguration('id_serrure', $key['id_serrure']);
                $thekeys->setConfiguration('code', $key['code']);
                $thekeys->setConfiguration('code_serrure', $key['code_serrure']);
                $thekeys->setConfiguration('serrure_droite', $key['serrure_droite']);
                //$thekeys->setConfiguration('etat', $key['etat']);
                $thekeys->setConfiguration('couleur', $key['couleur']);
                $thekeys->setConfiguration('public_key', $key['public_key']);
                $thekeys->setConfiguration('nom', $key['nom']);
                //$thekeys->setConfiguration('battery', $key['battery']);
                $thekeys->save();
                event::add('thekeys::found', array(
                    'message' => __('Nouvelle serrure ' . $key['nom'], __FILE__),
                ));
            }
            $thekeys->loadCmdFromConf($thekeys->getConfiguration('type'));
            $value = ($key['etat'] == 'open') ? 0:1;
            $thekeys->checkAndUpdateCmd('status',$value);
            $thekeys->checkAndUpdateCmd('battery',$key['battery']/1000);
            $thekeys->batteryStatus($key['battery']/40);
        }
    }

    public function allowLockers() {
        thekeys::checkShare();
        $this->scanLockers();
        thekeys::authCloud();
        $idgateway = $this->getConfiguration('idfield');
        $nbgateway = 0;
        foreach (eqLogic::byType('thekeys', true) as $location) {
            if ($location->getConfiguration('type') == 'locker' && $location->getConfiguration('share' . $idgateway, '0') != '1' && $location->getConfiguration('visible' . $idgateway, '0') == '1') {
                $url = 'partage/create/' . $location->getConfiguration('id') . '/accessoire/' . $idgateway;
                $data = array('partage_accessoire[description]' => '', 'partage_accessoire[nom]' => $this->getName(), 'partage_accessoire[actif]' => 1);
                $json = thekeys::callCloud($url,$data);
                if (isset($json['data']['code'])) {
                  $location->setConfiguration('share' . $idgateway,'1');
                  $location->setConfiguration('code' . $idgateway,$json['data']['code']);
                  $location->save();
                }
            }
            if ($location->getConfiguration('type') == 'gateway') {
                $nbgateway ++;
            }
        }
        if ($nbgateway == 1) {
            foreach (eqLogic::byType('thekeys', true) as $location) {
                if ($location->getConfiguration('type') == 'locker') {
                    $location->setConfiguration('gateway',$idgateway);
                    $location->save();
                }
            }
        }
    }

    public function scanLockers() {
        $idgateway = $this->getConfiguration('idfield');
        $url = 'http://' . $this->getConfiguration('ipfield') . '/lockers';
        $request_http = new com_http($url);
        $output = $request_http->exec(30);
        log::add('thekeys', 'debug', 'Scan : ' . $output);
        $json = json_decode($output, true);
        log::add('thekeys', 'debug', 'Scan : ' . $url);
        foreach ($json['devices'] as $device) {
            $thekeys = self::byLogicalId($device['identifier'], 'thekeys');
            if (is_object($thekeys)) {
                $thekeys->setConfiguration('rssi',$device['rssi']);
                $thekeys->setConfiguration('visible' . $idgateway,'1');
                $thekeys->save();
                //$value = ($key['etat'] == 'open') ? 0:1;
                //$thekeys->checkAndUpdateCmd('status',$value);
                $thekeys->checkAndUpdateCmd('battery',$device['battery']/1000);
                $thekeys->batteryStatus($device['battery']/40);;
                log::add('thekeys', 'debug', 'Rafraichissement serrure : ' . $device['identifier'] . ' ' . $device['battery'] . ' ' . $device['rssi']);
            }
        }
    }

    public function checkShare() {
        thekeys::authCloud();
        foreach (eqLogic::byType('thekeys', true) as $location) {
            if ($location->getConfiguration('type') == 'locker') {
                $url = 'partage/all/serrure/' . $location->getConfiguration('id');
                $json = thekeys::callCloud($url);
                $find = array();
                foreach ($json['data']['partages_accessoire'] as $share) {
                    log::add('thekeys', 'debug', 'Partage serrure : ' . $share['accessoire']['id_accessoire'] . ' ' . $share['code']);
                    $find[$share['accessoire']['id_accessoire']] = $share['code'];
                }
                foreach (eqLogic::byType('thekeys', true) as $location2) {
                    if ($location->getConfiguration('type') != 'locker') {
                      if (isset($find[$location2->getConfiguration('idfield')])) {
                        $location->setConfiguration('share' . $location2->getConfiguration('idfield'),'1');
                        $location->setConfiguration('code' . $location2->getConfiguration('idfield'),$find[$location2->getConfiguration('idfield')]);
                      } else {
                        $location->setConfiguration('share' . $location2->getConfiguration('idfield'),'0');
                      }
                    }
                }
                $location->save();
            }
        }
    }

    public function postAjax() {
        if ($this->getConfiguration('typeSelect') != $this->getConfiguration('type')) {
            $this->setConfiguration('type',$this->getConfiguration('typeSelect'));
            $this->save();
        }
        $this->loadCmdFromConf($this->getConfiguration('type'));
        if ($this->getConfiguration('type') == 'gateway') {
            $this->setLogicalId($this->getConfiguration('idfield'));
            $this->save();
            $this->allowLockers();
            event::add('thekeys::found', array(
                'message' => __('Nouvelle gateway' , __FILE__),
            ));
        }
    }

    public function loadCmdFromConf($type) {
        if (!is_file(dirname(__FILE__) . '/../config/devices/' . $type . '.json')) {
            return;
        }
        $content = file_get_contents(dirname(__FILE__) . '/../config/devices/' . $type . '.json');
        if (!is_json($content)) {
            return;
        }
        $device = json_decode($content, true);
        if (!is_array($device) || !isset($device['commands'])) {
            return true;
        }
        if (isset($device['name'])) {
            $this->setName('[' . $this->getLogicalId() . ']' . $device['name']);
        }
        $this->import($device);
    }

    public function cron15() {
        foreach (eqLogic::byType('thekeys', true) as $location) {
            if ($location->getConfiguration('type') == 'gateway') {
                $location->scanLockers();
            }
        }
    }

    public function cronHourly() {
        thekeys::updateUser();
        thekeys::checkShare();
    }

    public function pageConf() {
        thekeys::updateUser();
        thekeys::checkShare();
    }

    public function callCloud($url,$data = array('format' => 'json')) {
        $url = 'https://api.the-keys.fr/fr/api/v2/' . $url;
        if (isset($data['format'])) {
            $url .= '?_format=' . $data['format'];
        }
        if (time() > config::byKey('timestamp','thekeys')) {
            thekeys::authCloud();
        }
        $request_http = new com_http($url);
        $request_http->setHeader(array('Authorization: Bearer ' . config::byKey('token','thekeys')));
        if (!isset($data['format'])) {
            $request_http->setPost($data);
        }
        $output = $request_http->exec(30);
        $json = json_decode($output, true);
        log::add('thekeys', 'debug', 'URL : ' . $url);
        //log::add('thekeys', 'debug', 'Authorization: Bearer ' . config::byKey('token','thekeys'));
        log::add('thekeys', 'debug', 'Retour : ' . $output);
        return $json;
    }

    public function callGateway($uri,$id = '', $code = '') {
        $url = 'http://' . $this->getConfiguration('ipfield') . '/' . $uri;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($uri != ' lockers') {
            ini_set('date.timezone', 'UTC');
            $ts = time();
            $key = hash_hmac('sha256',$ts,$code,true);
            $hash = base64_encode($key);
            $fields = array('hash' => $hash, 'identifier' => $id, 'ts' => $ts);
            $fields_string = '';
            foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
            rtrim($fields_string, '&');
            curl_setopt($curl,CURLOPT_POST, count($fields));
            curl_setopt($curl,CURLOPT_POSTFIELDS, $fields_string);
            log::add('thekeys', 'debug', 'Array : ' . print_r($fields, true));
        }
        curl_setopt($curl,CURLOPT_RETURNTRANSFER , 1);
        $json = json_decode(curl_exec($curl), true);
        curl_close ($curl);
        log::add('thekeys', 'debug', 'Retour : ' . print_r($json, true));
        return;
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
        if ($this->getType() == 'info') {
            return;
        }
        switch ($this->getConfiguration('type')) {
            case 'locker' :
            $eqLogic = $this->getEqLogic();
            $gatewayid = $eqLogic->getConfiguration('gateway');
            $gateway = thekeys::byLogicalId($gatewayid, 'thekeys');
            if (is_object($gateway)) {
              $gateway->callGateway($this->getConfiguration('value'),$eqLogic->getConfiguration('id_serrure'),$eqLogic->getConfiguration('code' .$gatewayid));
            } else {
              log::add('thekeys', 'debug', 'Gateway non existante : ' . $gatewayid);
            }
            log::add('thekeys', 'debug', 'Commande : ' . $this->getConfiguration('value') . ' ' . $eqLogic->getConfiguration('id_serrure') . ' ' . $eqLogic->getConfiguration('code' .$gatewayid));
            thekeys::updateUser();
            return true;
        }
        return true;
    }
}

?>
