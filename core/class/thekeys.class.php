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
    if (substr(config::byKey('username','thekeys'),0,1) != '+') {
      log::add('thekeys', 'error', 'Nom utilisateur mal formé, vous devez saisir +33...');
      return;
    }
    thekeys::authCloud();
    $url = 'utilisateur/get/' . urlencode(config::byKey('username','thekeys'));
    $json = thekeys::callCloud($url);
    foreach ($json['data']['serrures'] as $key) {
      $thekeys = thekeys::byLogicalId($key['id_serrure'], 'thekeys');
      if (!is_object($thekeys)) {
        $thekeys = new thekeys();
        $thekeys->setEqType_name('thekeys');
        $thekeys->setLogicalId($key['id_serrure']);
        $thekeys->setName('Serrure ' . $key['id_serrure']);
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
      $thekeysCmd = thekeysCmd::byEqLogicIdAndLogicalId($thekeys->getId(),'status');
      if (!is_object($thekeysCmd)) {
        $thekeys->loadCmdFromConf($thekeys->getConfiguration('type'));
      }
      $value = ($key['etat'] == 'open') ? 0:1;
      $thekeys->checkAndUpdateCmd('status',$value);
      $thekeys->checkAndUpdateCmd('battery',$key['battery']/1000);
      $thekeys->batteryStatus($key['battery']/40);
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
      $thekeys = thekeys::byLogicalId($device['identifier'], 'thekeys');
      if (is_object($thekeys)) {
        $thekeys->setConfiguration('rssi',$device['rssi']);
        $thekeys->save();
        //createCmds for this gateway
        $thekeys->checkCmdOk($idgateway, 'open', 'locker', 'Déverrouillage avec ' . $this->getName());
        $thekeys->checkCmdOk($idgateway, 'close', 'locker', 'Verrouillage avec ' . $this->getName());
        $thekeys->checkAndUpdateCmd('battery',$device['battery']/1000);
        $thekeys->batteryStatus($device['battery']/40);;
        log::add('thekeys', 'debug', 'Rafraichissement serrure : ' . $device['identifier'] . ' ' . $device['battery'] . ' ' . $device['rssi']);
      }
    }
    $url = 'http://' . $this->getConfiguration('ipfield') . '/synchronize';
    $request_http = new com_http($url);
    $output = $request_http->exec(30);
    log::add('thekeys', 'debug', 'Synchronise : ' . $url . ' ' . $output);
  }

  public function cmdsShare() {
    foreach (eqLogic::byType('thekeys', true) as $keyeq) {
      if ($keyeq->getConfiguration('type') == 'locker') {
        $this->checkCmdOk($keyeq->getLogicalId(), 'enable', $this->getConfiguration('type'), 'Activer partage avec ' . $keyeq->getName());
        $this->checkCmdOk($keyeq->getLogicalId(), 'unable', $this->getConfiguration('type'), 'Désactiver partage avec ' . $keyeq->getName());
        $this->checkCmdOk($keyeq->getLogicalId(), 'status', $this->getConfiguration('type'), 'Statut partage avec ' . $keyeq->getName());
      }
    }
  }

  public function checkShare() {
    if (substr(config::byKey('username','thekeys'),0,1) != '+') {
      return;
    }
    thekeys::authCloud();
    $accessoire = array();
    $phone = array();
    foreach (eqLogic::byType('thekeys', true) as $keyeq) {
      if ($keyeq->getConfiguration('type') == 'gateway') {
        $accessoire[$keyeq->getConfiguration('idfield')] = array();
      }
      if ($keyeq->getConfiguration('type') == 'phone') {
        $phone[$keyeq->getConfiguration('idfield')] = array();
      }
      if ($keyeq->getConfiguration('type') == 'button') {
        $accessoire[$keyeq->getConfiguration('idfield')] = array();
      }
    }
    log::add('thekeys', 'debug', 'Accessoire : ' . print_r($accessoire,true));
    foreach (eqLogic::byType('thekeys', true) as $keyeq) {
      if ($keyeq->getConfiguration('type') == 'locker') {
        $url = 'partage/all/serrure/' . $keyeq->getConfiguration('id');
        $json = thekeys::callCloud($url);
        foreach ($json['data']['partages_accessoire'] as $share) {
          log::add('thekeys', 'debug', 'Partage serrure : ' . $share['accessoire']['id_accessoire'] . ' ' . $share['code']);
          if (!(isset($share['date_debut']) || isset($share['date_fin']) || isset($share['heure_debut']) || isset($share['heure_fin']))) {
            //on vérifier que c'est un partage permanent, jeedom ne prend pas en compte les autres
            $accessoire[$share['accessoire']['id_accessoire']][$keyeq->getConfiguration('id')]['id'] = $share['id'];
            $accessoire[$share['accessoire']['id_accessoire']][$keyeq->getConfiguration('id')]['code'] = $share['code'];
            //on sauvegarde le statut si bouton/phone, si gateway on s'assure d'etre en actif
            $eqtest = thekeys::byLogicalId($share['accessoire']['id_accessoire'], 'thekeys');
            if (is_object($eqtest)) {
              if ($eqtest->getConfiguration('type') == 'gateway' && !$share['actif']) {
                $keyeq->editShare($share['id'], $share['accessoire']['id_accessoire']);
              }
              if ($eqtest->getConfiguration('type') == 'phone' || $eqtest->getConfiguration('type') == 'button') {
                $value = ($share['actif']) ? 1:0;
                $eqtest->checkAndUpdateCmd('status-'.$keyeq->getLogicalId(), $value);
              }
            }
          }
        }
        foreach ($accessoire as $id => $stuff) {
          //boucle pour vérifier si chaque gateway/bouton possède une entrée de partage avec l'équipement en cours, sinon on appelle le createShare et on ajoute le retour
          log::add('thekeys', 'debug', 'ID : ' . $id . ' ' . print_r($stuff,true));
          if (count($stuff) == 0) {
            log::add('thekeys', 'debug', 'Create Share : ' . $id . ' ' . print_r($stuff,true));
            $json = $keyeq->createShare($id);
            if (isset($json['data']['code'])) {
              $accessoire[$id]['id'] = $json['data']['id'];
              $accessoire[$id]['code'] = $json['data']['code'];
            }
          }
        }
        foreach ($json['data']['partages_utilisateur'] as $share) {
          log::add('thekeys', 'debug', 'Partage serrure : ' . $share['utilisateur']['username']);
          if (!(isset($share['date_debut']) || isset($share['date_fin']) || isset($share['heure_debut']) || isset($share['heure_fin']))) {
            //on vérifier que c'est un partage permanent, jeedom ne prend pas en compte les autres
            $phone[$share['utilisateur']['username']][$keyeq->getConfiguration('id')]['id'] = $share['id'];
            //$phone[$share['utilisateur']['username']][$keyeq->getConfiguration('id')]['code'] = $share['code'];
            $eqtest = thekeys::byLogicalId($share['utilisateur']['username'], 'thekeys');
            if (is_object($eqtest)) {
                $value = ($share['actif']) ? 1:0;
                $eqtest->checkAndUpdateCmd('status-'.$keyeq->getLogicalId(), $value);
                log::add('thekeys', 'debug', 'Partage serrure : ' . $share['utilisateur']['username']. 'status-'.$keyeq->getConfiguration('id') . ' ' . $value);
            }
          }
        }
        log::add('thekeys', 'debug', 'Phones trouvés : ' . print_r($phone,true));
        foreach ($phone as $id => $stuff) {
          //boucle pour vérifier si chaque gateway/bouton possède une entrée de partage avec l'équipement en cours, sinon on appelle le createShare et on ajoute le retour
          log::add('thekeys', 'debug', 'ID : ' . $id . ' ' . print_r($stuff,true));
          if (count($stuff) == 0) {
            log::add('thekeys', 'debug', 'Create Share : ' . $id . ' ' . print_r($stuff,true));
            $json = $keyeq->createShare($id,true);
            if (isset($json['data']['code'])) {
              $phone[$id]['id'] = $json['data']['id'];
              $phone[$id]['code'] = $json['data']['code'];
            }
          }
        }
      }
    }
    config::save('shares_accessoire', json_encode($accessoire),  'thekeys');
    config::save('shares_phone', json_encode($phone),  'thekeys');
  }

  public function createShare($_id, $_phone = false, $_digicode = '') {
    if (substr(config::byKey('username','thekeys'),0,1) != '+') {
      return;
    }
    thekeys::authCloud();
    if ($_phone) {
        $url = 'partage/create/' . $this->getConfiguration('id') . '/' . urlencode($_id);
        $data = array('partage[description]' => 'jeedom', 'partage[nom]' => 'jeedom' . str_replace('+','',$_id), 'partage[actif]' => 1);
    } else {
        $url = 'partage/create/' . $this->getConfiguration('id') . '/accessoire/' . $_id;
        $data = array('partage_accessoire[description]' => 'jeedom', 'partage_accessoire[nom]' => 'jeedom' . str_replace('+','',$_id), 'partage_accessoire[actif]' => 1);
        if ($_digicode != '') {
            $data['partage_accessoire[code]'] = $_digicode;
        }
    }
    $json = thekeys::callCloud($url,$data);
    return $json;
  }

  public function editShare($_id, $_eqId, $_actif = 'enable', $_phone = false, $_digicode = '') {
    if (substr(config::byKey('username','thekeys'),0,1) != '+') {
      return;
    }
    thekeys::authCloud();
    if ($_phone) {
        $url = 'partage/update/' . urlencode($_id);
        $data = array('partage[nom]' => 'jeedom' . str_replace('+','',$_id));
        if ($_actif == 'enable') {
            $data['partage[actif]'] = 1;
        }
    } else {
        $url = 'partage/accessoire/update/' . $_id;
        $data = array('partage_accessoire[nom]' => 'jeedom' . str_replace('+','',$_eqId));
        if ($_actif == 'enable') {
            $data['partage_accessoire[actif]'] = 1;
        }
    }
    if ($_digicode != '') {
        $data['partage_accessoire[code]'] = $_digicode;
    }
    log::add('thekeys', 'debug', 'ID : ' . $_id . ' ' . $_actif . ' ' . print_r($data,true));
    $json = thekeys::callCloud($url,$data);
    return $json;
  }

  public function postAjax() {
    if ($this->getConfiguration('type') != 'locker') {
      $this->setConfiguration('type',$this->getConfiguration('typeSelect'));
      $this->setLogicalId($this->getConfiguration('idfield'));
      $this->save();
    }
    if ($this->getConfiguration('type') == 'gateway') {
      $this->loadCmdFromConf($this->getConfiguration('type'));
      $this->save();
      $this->scanLockers();
      event::add('thekeys::found', array(
        'message' => __('Nouvelle gateway' , __FILE__),
      ));
    }
    if ($this->getConfiguration('type') == 'button' || $this->getConfiguration('type') == 'phone') {
      $this->cmdsShare();
    }
    self::updateUser();
    self::checkShare();
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
    $this->import($device);
  }

  public function checkCmdOk($_id, $_value, $_category, $_name) {
    $thekeysCmd = thekeysCmd::byEqLogicIdAndLogicalId($this->getId(),$_value . '-' . $_id);
    if (!is_object($thekeysCmd)) {
      log::add('thekeys', 'debug', 'Création de la commande ' . $_value . '-' . $_id);
      $thekeysCmd = new thekeysCmd();
      $thekeysCmd->setName(__($_name, __FILE__));
      $thekeysCmd->setEqLogic_id($this->getId());
      $thekeysCmd->setEqType('thekeys');
      $thekeysCmd->setLogicalId($_value . '-' . $_id);
      if ($_value == 'status') {
        $thekeysCmd->setType('info');
        $thekeysCmd->setSubType('binary');
        $thekeysCmd->setTemplate("mobile",'lock' );
        $thekeysCmd->setTemplate("dashboard",'lock' );
      } else {
        $thekeysCmd->setType('action');
        $thekeysCmd->setSubType('other');
        if ($_value == 'open' || $_value == 'enable') {
          $thekeysCmd->setDisplay("icon",'"<i class=\"fa fa-unlock\"><\/i>"' );
        } else {
          $thekeysCmd->setDisplay("icon",'"<i class=\"fa fa-lock\"><\/i>"' );
        }
      }
      $thekeysCmd->setConfiguration('value', $_value);
      $thekeysCmd->setConfiguration('id', $_id);
      $thekeysCmd->setConfiguration('category', $_category);
      if ($_category == 'locker') {
        $thekeysCmd->setConfiguration('gateway', $_id);
      }
      $thekeysCmd->save();
    }
  }

  public function cron15() {
    //scan des lockers par les gateways toutes les 15mn
    foreach (eqLogic::byType('thekeys', true) as $keyeq) {
      if ($keyeq->getConfiguration('type') == 'gateway') {
        $keyeq->scanLockers();
      }
    }
    //update des infos de l'API (lockers existants, batterie, status) + verification que les share sont existants
    thekeys::updateUser();
    thekeys::checkShare();
  }

  public function pageConf() {
    //sur sauvegarde page de conf update des infos de l'API (lockers existants, batterie, status) + verification que les share sont existants
    thekeys::updateUser();
    thekeys::checkShare();
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
    switch ($this->getConfiguration('category')) {
      case 'locker' :
      $eqLogic = $this->getEqLogic();
      $gatewayid = $this->getConfiguration('gateway');
      $gateway = thekeys::byLogicalId($gatewayid, 'thekeys');
      $key = config::byKey('shares_accessoire','thekeys');
      //log::add('thekeys', 'debug', 'Config : ' . print_r(config::byKey('shares_accessoire','thekeys'),true));
      $code = $key[$gatewayid][$eqLogic->getConfiguration('id')]['code'];
      if (is_object($gateway)) {
        $gateway->callGateway($this->getConfiguration('value'),$eqLogic->getConfiguration('id_serrure'),$code);
      } else {
        log::add('thekeys', 'debug', 'Gateway non existante : ' . $gatewayid);
      }
      log::add('thekeys', 'debug', 'Commande : ' . $this->getConfiguration('value') . ' ' . $eqLogic->getConfiguration('id_serrure') . ' ' . $code);
      thekeys::updateUser();
      break;
      case 'gateway' :
      $eqLogic = $this->getEqLogic();
      thekeys::updateUser();
      thekeys::checkShare();
      $eqLogic->scanLockers();
      break;
      default :
      $eqLogic = $this->getEqLogic();
      if ($this->getConfiguration('category') == 'phone') {
          $key = config::byKey('shares_phone','thekeys');
          $phone = true;
      } else {
          $key = config::byKey('shares_accessoire','thekeys');
          $phone = false;
      }
      $locker = thekeys::byLogicalId($this->getConfiguration('id'), 'thekeys');
      $id = $key[$eqLogic->getLogicalId()][$locker->getConfiguration('id')]['id'];
      log::add('thekeys', 'debug', 'Config : ' . $eqLogic->getLogicalId() . ' ' . $locker->getConfiguration('id') . ' ' . print_r(config::byKey('shares_accessoire','thekeys'),true));
      $locker->editShare($id, $eqLogic->getLogicalId(), $this->getConfiguration('value'), $phone);
      thekeys::updateUser();
      thekeys::checkShare();
      break;
    }
  }
}

?>
