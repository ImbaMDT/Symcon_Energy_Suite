<?php
/*
    Archivierer
    - Archiviert automatisch alle Großverbraucher
    Programmierer: Mike Dorr
    Projekt: HVG241 Meisterprüfung
*/

// ---------------- Konfiguration ----------------

$logVars = [
    17849, // Wallbox1_Ladeleistung
    12904, // Wallbox2_Ladeleistung
    54164, // Wärmepumpe
    42994, // Heizstab
    37381, // PV-Leistung
    37163, // Hausverbrauch
    53609, // Batterie_Ladeleistung
    56622  // Verbrauch Gesamt
];

// ---------------- Archivinstanz suchen ----------------

$archives = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}");

if (count($archives) == 0) {
    echo "Archiv-Instanz nicht gefunden!\n";
    return;
}

$archiveID = $archives[0];

// ---------------- Logging aktivieren ----------------

foreach ($logVars as $vid) {

    if (!IPS_VariableExists($vid)) {
        echo "Variable $vid existiert nicht\n";
        continue;
    }

    if (!AC_GetLoggingStatus($archiveID, $vid)) {
        AC_SetLoggingStatus($archiveID, $vid, true);
        echo "Logging aktiviert für $vid\n";
    }

    if (AC_GetAggregationType($archiveID, $vid) != 1) {
        AC_SetAggregationType($archiveID, $vid, 1);
    }
}

// ---------------- Änderungen anwenden ----------------

IPS_ApplyChanges($archiveID);