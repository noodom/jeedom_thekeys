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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'thekeys')) {
 echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (thekeys)', __FILE__);
 die();
}

$id = init('id');
$eqLogic = thekeys::byId($id);
if (!is_object($eqLogic)) {
	echo 'Id inconnu ' . init('id');
	die();
}

$sensor = init('sensor');
$cmd = thekeysCmd::byEqLogicIdAndLogicalId($id,$sensor);
if (!is_object($cmd)) {
	echo 'Commande inconnue : ' . init('sensor');
	die();
}

log::add('thekeys', 'debug', 'Event : ' . $sensor . ' sur ' . init('id'));

$value = 1;
if ($sensor == 'dooropen') {
	$value = 0;
}

$eqLogic->checkAndUpdateCmd(init('sensor'), $value);

?>
