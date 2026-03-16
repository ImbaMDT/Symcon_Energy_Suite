<?php
/*
    Fehlerverlauf
    - Registriert Alarmzustände
    - Erstellt automatisch HTMLBox und speichert chronologische Alarmmeldungen
    - Loggt nur Zustandsänderungen
    Programmierer: Mike Dorr
    Projekt: HVG241 Meisterprüfung
*/

// ---------------- Kategorie prüfen/erstellen ----------------
$parentName = "Sicherheit";
$parentID = @IPS_GetObjectIDByName($parentName, 0);
if ($parentID === false) {
    $parentID = IPS_CreateCategory();
    IPS_SetName($parentID, $parentName);
    IPS_SetParent($parentID, 0);
}

// ---------------- Hilfsfunktion ----------------
function ensureVariable(string $name, int $type, int $parentID, string $profile = ''): int
{
    $vid = @IPS_GetVariableIDByName($name, $parentID);
    if ($vid === false) {
        $vid = IPS_CreateVariable($type);
        IPS_SetName($vid, $name);
        IPS_SetParent($vid, $parentID);
        if ($profile !== '') {
            IPS_SetVariableCustomProfile($vid, $profile);
        }
        IPS_LogMessage('Sicherheit', "Variable '$name' wurde automatisch erstellt (ID: $vid)");
    }
    return $vid;
}

// ---------------- Anzeige- und Datenspeicher-Variablen ----------------
$VID_LOG_HTML = ensureVariable('Fehlerverlauf_HTML', 3, $parentID, '~HTMLBox');
$VID_LOG_DATA = ensureVariable('Fehlerverlauf_Daten', 3, $parentID);

// ---------------- Alarm-Quellen ----------------
$alarmSources = [
    'Einbruch' => 38398,
    'Wasser'   => 58335,
    'Brand'    => 36472
];

// ---------------- Vorprüfung ----------------
foreach ($alarmSources as $name => $vid) {
    if (!IPS_VariableExists($vid)) {
        IPS_LogMessage('Sicherheit', "Alarmquelle '$name' mit ID $vid existiert nicht.");
        unset($alarmSources[$name]);
    }
}

// ---------------- Bisherige Logdaten laden ----------------
$logEntries = [];
$rawData = GetValueString($VID_LOG_DATA);

if ($rawData !== '') {
    $decoded = json_decode($rawData, true);
    if (is_array($decoded)) {
        $logEntries = $decoded;
    }
}

// ---------------- Zeitstempel ----------------
$now = date('d.m.Y H:i:s');

// ---------------- Zustandswechsel prüfen ----------------
foreach ($alarmSources as $type => $sourceVID) {
    $currentState = GetValueBoolean($sourceVID);

    $lastStateVID = ensureVariable($type . '_LastState', 0, $parentID);
    $lastState = GetValueBoolean($lastStateVID);

    if ($currentState !== $lastState) {
        if ($currentState) {
            $text = "<li>$now – <b style='color:red;'>$type-Alarm erkannt</b></li>";
        } else {
            $text = "<li>$now – <b style='color:green;'>$type-Alarm beendet</b></li>";
        }

        array_unshift($logEntries, $text);
        SetValueBoolean($lastStateVID, $currentState);
    }
}

// ---------------- Maximal 10 Einträge ----------------
$logEntries = array_slice($logEntries, 0, 10);

// ---------------- Logdaten speichern ----------------
SetValueString($VID_LOG_DATA, json_encode($logEntries));

// ---------------- HTML erzeugen ----------------
$html  = '<div style="font-family:Segoe UI, sans-serif; padding:10px;">';
$html .= '<h3 style="margin:0 0 10px 0;">Fehlerverlauf</h3>';

if (count($logEntries) > 0) {
    $html .= '<ul style="padding-left:20px; margin-top:10px;">' . implode("\n", $logEntries) . '</ul>';
} else {
    $html .= '<div style="color:gray;">Keine Ereignisse vorhanden.</div>';
}

$html .= '<div style="margin-top:8px; font-size:11px; color:gray;">Stand: ' . date('d.m.Y H:i:s') . '</div>';
$html .= '</div>';

SetValueString($VID_LOG_HTML, $html);
?>