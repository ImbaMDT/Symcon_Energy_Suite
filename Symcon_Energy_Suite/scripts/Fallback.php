<?php
/*
    Fallback / Kommunikationsüberwachung
    - Prüft KNX und MQTT/Loxone
    - Erkennt ausbleibende Aktualisierung einer Referenzvariable
    - Schreibt Status in HTMLBox
    Programmierer: Mike Dorr
    Projekt: HVG241 Meisterprüfung
*/

define('IS_ACTIVE', 102);

// ---------------- IDs definieren ----------------
$knxIoID          = 39901; // KNX I/O
$mqttSocketID     = 27773; // MQTT Socket / Server
$htmlBoxID        = 53762; // String-Variable mit ~HTMLBox
$loxoneheartbeatID   = 33860; // Referenzvariable zur Überwachung des Datenflusses

// ---------------- Schwellenwert ----------------
// 600 Sekunden = 10 Minuten
$inaktivSchwelle = 600;

// ---------------- Vorprüfungen ----------------
if (!IPS_InstanceExists($knxIoID)) {
    echo "KNX-Instanz nicht gefunden\n";
    return;
}
if (!IPS_InstanceExists($mqttSocketID)) {
    echo "MQTT-Instanz nicht gefunden\n";
    return;
}
if (!IPS_VariableExists($htmlBoxID)) {
    echo "HTMLBox-Variable nicht gefunden\n";
    return;
}
if (!IPS_VariableExists($loxoneheartbeatID)) {
    echo "Heartbeat-Variable nicht gefunden\n";
    return;
}

// ---------------- Statusdaten ----------------
$jetzt            = time();
$letzteAenderung  = IPS_GetVariable($loxoneheartbeatID)['VariableChanged'];
$diffSekunden     = $jetzt - $letzteAenderung;

$statusKNX        = IPS_GetInstance($knxIoID)['InstanceStatus'];
$statusMQTT       = IPS_GetInstance($mqttSocketID)['InstanceStatus'];

$knxAktiv         = ($statusKNX == IS_ACTIVE);
$mqttAktiv        = ($statusMQTT == IS_ACTIVE);
$datenVeraltet    = ($diffSekunden > $inaktivSchwelle);

$diffText = floor($diffSekunden / 60) . ' min ' . ($diffSekunden % 60) . ' s';

// ---------------- HTML-Ausgabe ----------------
$meldung  = '<div style="font-family:Segoe UI, sans-serif; padding:10px;">';
$meldung .= '<h2 style="margin:0 0 10px 0;">Kommunikationsstatus</h2>';
$meldung .= '<table style="width:100%; border-collapse:collapse;">';

if ($knxAktiv) {
    $meldung .= '<tr><td>KNX</td><td style="color:green;"><b>Verbunden</b></td></tr>';
} else {
    $meldung .= '<tr><td>KNX</td><td style="color:red;"><b>Keine Verbindung</b></td></tr>';
}

if ($mqttAktiv) {
    $meldung .= '<tr><td>MQTT / Loxone</td><td style="color:green;"><b>Verbunden</b></td></tr>';
} else {
    $meldung .= '<tr><td>MQTT / Loxone</td><td style="color:red;"><b>Nicht aktiv</b></td></tr>';
}

$meldung .= '<tr><td>Letzte Datenänderung</td><td><b>' . date("d.m.Y H:i:s", $letzteAenderung) . '</b></td></tr>';
$meldung .= '<tr><td>Inaktiv seit</td><td><b>' . $diffText . '</b></td></tr>';

if ($knxAktiv && $mqttAktiv && $datenVeraltet) {
    $meldung .= '<tr><td>Bewertung</td><td style="color:orange;"><b>Warnung: Datenfluss unterbrochen</b></td></tr>';
} elseif (!$knxAktiv || !$mqttAktiv) {
    $meldung .= '<tr><td>Bewertung</td><td style="color:red;"><b>Störung in der Kommunikation</b></td></tr>';
} else {
    $meldung .= '<tr><td>Bewertung</td><td style="color:green;"><b>System arbeitet normal</b></td></tr>';
}

$meldung .= '</table>';
$meldung .= '<div style="margin-top:8px; font-size:11px; color:gray;">Stand: ' . date("d.m.Y H:i:s") . '</div>';
$meldung .= '</div>';

// ---------------- Ausgabe schreiben ----------------
SetValueString($htmlBoxID, $meldung);

// ---------------- Log ----------------
echo "Fallback-Prüfung abgeschlossen um " . date("H:i:s") . "\n";
?>