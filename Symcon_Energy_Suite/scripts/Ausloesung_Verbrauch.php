<?php

/*
    Trigger für Verbrauch
    - Erstellt Auslöser
    Programmierer: Mike Dorr
    Projekt: HVG241 Meisterprüfung
*/


// ------------------ Konfiguration ------------------

$categoryID = 32805;  
$verbrauchScriptID = 14766;  
$energiemanagementScriptID = 38851;  

// ------------------ Alte Events löschen ------------------

foreach ([$verbrauchScriptID, $energiemanagementScriptID] as $scriptID) {

    foreach (IPS_GetChildrenIDs($scriptID) as $eid) {

        if (IPS_GetObject($eid)['ObjectType'] == 4) {
            IPS_DeleteEvent($eid);
        }

    }
}

// ------------------ Neue Events erzeugen ------------------

$anzahl = 0;

foreach (IPS_GetChildrenIDs($categoryID) as $vid) {

    if (!IPS_VariableExists($vid)) {
        continue;
    }

    $name = IPS_GetName($vid);

    // Verbrauch Script
    $eid = IPS_CreateEvent(0);
    IPS_SetName($eid, "Trigger_Verbrauch_" . $name);
    IPS_SetEventTrigger($eid, 0, $vid); // 0 = OnChange
    IPS_SetParent($eid, $verbrauchScriptID);
    IPS_SetEventActive($eid, true);

    // Energiemanagement Script
    $eid = IPS_CreateEvent(0);
    IPS_SetName($eid, "Trigger_EM_" . $name);
    IPS_SetEventTrigger($eid, 0, $vid);
    IPS_SetParent($eid, $energiemanagementScriptID);
    IPS_SetEventActive($eid, true);

    $anzahl++;
}

echo "Events für $anzahl Variablen erstellt.\n";