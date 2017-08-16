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
                $thekeys->setIsEnable(1);
                $thekeys->setIsVisible(1);
                $thekeys->setName($key['nom'] . ' ' . $key['id_serrure']);
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
        foreach (eqLogic::byType('thekeys', true) as $location) {
            if ($location->getConfiguration('type') == 'locker' && $location->getConfiguration('share' . $idgateway, 0) != 1 && $location->getConfiguration('accessible' . $idgateway, 0) == 1) {
                $url = 'partage/create/' . $location->getConfiguration('id') . '/accessoire/' . $idgateway;
                $data = array('partage_accessoire[description]' => '', 'partage_accessoire[nom]' => $this->getName());
                $json = thekeys::callCloud($url,$data);
            }
        }
    }

    public function scanLockers() {
        $idgateway = $this->getConfiguration('idfield');
        $json = $this->callGateway('lockers');
        if (!is_array($json) || $json['status'] != 'ok') {
            log::add('thekeys', 'error', 'Passerelle injoignable');
        }
        foreach ($json['devices'] as $device) {
            $thekeys = self::byLogicalId($device['identifier'], 'thekeys');
            if (is_object($thekeys)) {
                $thekeys->setConfiguration('rssi',$device['rssi']);
                $thekeys->setConfiguration('visible' . $this->getConfiguration('id'),1);
                $thekeys->save();
                //$value = ($key['etat'] == 'open') ? 0:1;
                //$thekeys->checkAndUpdateCmd('status',$value);
                $thekeys->checkAndUpdateCmd('battery',$key['battery']/1000);
                $thekeys->batteryStatus($key['battery']/40);;
            }
        }
    }

    public function checkShare() {
        thekeys::authCloud();
        foreach (eqLogic::byType('thekeys', true) as $location) {
            if ($location->getConfiguration('type') == 'locker') {
                $url = 'partage/all/serrure/' . $location->getConfiguration('id');
                $json = thekeys::callCloud($url);
                /*if (!is_array($json) || $json['status'] != '200') {
                    log::add('thekeys', 'error', 'Erreur dans la vérification des partages');
                }*/
                //update 'share' . $idtrouve + infos sur la plage horaire
                foreach ($json['data']['partages_accessoire'] as $share) {
                    $location->setConfiguration('share' . $share['accessoire']['id_accessoire'],1);
                    $location->setConfiguration('code' . $share['accessoire']['id_accessoire'],$share['accessoire']['code']);
                }
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
            $this->setLogicalId($this->getConfiguration('id'));
            $this->save();
            $this->allowLockers();
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
        log::add('thekeys', 'debug', 'Retour : ' . print_r($json, true));
        return $json;
    }

    public function callGateway($uri,$id = '', $code = '') {
        $url = 'http://' . $this->getConfiguration('ipfield') . '/' . $uri;
        $request_http = new com_http($url);
        if ($uri != ' lockers') {
            $ts = time();
            $key = hash_hmac('sha512',$ts,$code);
            $hash = base64_encode($key);
            $data = array('hash' => $hash, 'identifier' => $id, 'ts' => $ts);
            $request_http->setPost($data);
        }
        $output = $request_http->exec(30);
        $json = json_decode($output, true);
        log::add('thekeys', 'debug', 'URL : ' . $url);
        //log::add('thekeys', 'debug', 'Authorization: Bearer ' . config::byKey('token','thekeys'));
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
            case 'action' :
            $eqLogic = $this->getEqLogic();
            $url = 'http://' . $eqLogic->getConfiguration('gateway') . '/' . $this->getEqLogic() . '?identifier=' . $eqLogic->getConfiguration('id_serrure') . '&ts=' . $timestamp;
            $gateway = self::byLogicalId($eqLogic->getConfiguration('gateway'), 'thekeys');
            $gateway->callGateway($this->getConfiguration('value'),$eqLogic->getConfiguration('id_serrure'),$eqLogic->getConfiguration('code' . $gateway->getConfiguration('id')));
            return true;
        }
        return true;
    }
}

?>
