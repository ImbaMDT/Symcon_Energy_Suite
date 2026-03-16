<?php
/*
    Energiesystem
    - Energiemanagement
    - Verbrauchsmonitoring
    - Plausibilitätsprüfung
    - Verlauf / Statuswechsel
    Programmierer: Mike Dorr
    Projekt: HVG241 Meisterprüfung
*/

// ---------------- Änderungen aus WebFront ----------------
if ($_IPS['SENDER'] == 'WebFront') {
    SetValue($_IPS['VARIABLE'], $_IPS['VALUE']);
    IPS_RunScript($_IPS['SELF']);
    return;
}

// ---------------- Konfiguration ----------------
$VID_PV            = 37381;
$VID_HOUSE         = 37163;
$VID_BATT_SOC      = 33860;
$VID_BATT_POWER    = 53609;
$VID_WB1_RD        = 17849;
$VID_WB2_RD        = 12904;
$VID_WB1_CONNECTED = 11167;
$VID_WB2_CONNECTED = 31450;
$VID_WB1_WR        = 17849;   // später besser eigene Sollwert-ID
$VID_WB2_WR        = 12904;   // später besser eigene Sollwert-ID
$VID_HEATPUMP      = 42994;
$VID_GRID          = 55216;
$VID_RESULT        = 56622;

$MAX_HAUS_KW       = 60.0;
$WB_MAX            = ['wb1' => 11.0, 'wb2' => 11.0];
$PRIO_START        = 8;
$PRIO_END          = 18;

$PV_MIN_UEBERSCHUSS = 1.0;
$BATT_FULL          = 95;
$HEATPUMP_POWER     = 3.0;

$TOLERANZ           = 0.01;
$MAX_LOG_ENTRIES    = 50;

// ---------------- Hilfsfunktionen ----------------
function ensureCategory(string $name, int $parentID): int
{
    $id = @IPS_GetObjectIDByName($name, $parentID);
    if ($id === false) {
        $id = IPS_CreateCategory();
        IPS_SetName($id, $name);
        IPS_SetParent($id, $parentID);
    }
    return $id;
}

function ensureVariable(string $name, int $type, int $parentID, string $profile = '', ?int $actionScript = null): int
{
    $id = @IPS_GetVariableIDByName($name, $parentID);
    if ($id === false) {
        $id = IPS_CreateVariable($type);
        IPS_SetName($id, $name);
        IPS_SetParent($id, $parentID);

        if ($profile !== '') {
            IPS_SetVariableCustomProfile($id, $profile);
        }

        if ($actionScript !== null) {
            IPS_SetVariableCustomAction($id, $actionScript);
        }

        IPS_LogMessage('Energiesystem', "Variable '$name' erstellt (ID: $id)");
    }
    return $id;
}

function htmlEscape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function readFloat(int $id): float
{
    if (!IPS_VariableExists($id)) {
        throw new Exception('Variable fehlt: ' . $id);
    }
    return (float) GetValue($id);
}

function readBool(int $id): bool
{
    if (!IPS_VariableExists($id)) {
        throw new Exception('Variable fehlt: ' . $id);
    }
    return (bool) GetValue($id);
}

// ---------------- Kategorien / Variablen ----------------
$selfID   = $_IPS['SELF'];
$rootID   = @IPS_GetObjectIDByName('Lademanagement', 0);
if ($rootID === false) {
    $rootID = IPS_CreateCategory();
    IPS_SetName($rootID, 'Lademanagement');
    IPS_SetParent($rootID, 0);
}

$monitorCatID = ensureCategory('Energie-Monitoring', $rootID);

$varMode        = ensureVariable('Lademodus', 1, $rootID, '~Switch', $selfID);
$varZeit        = ensureVariable('Zeitfenster aktiv', 0, $rootID, '~Switch', $selfID);
$varDashboard   = ensureVariable('Dashboard_HTML', 3, $rootID, '~HTMLBox');
$varHistoryHTML = ensureVariable('Energie-Verlauf', 3, $monitorCatID, '~HTMLBox');
$varHistoryData = ensureVariable('Energie_Verlauf_Daten', 3, $monitorCatID);
$varState       = ensureVariable('Energie_StatusIntern', 1, $monitorCatID);

// ---------------- Werte einlesen ----------------
$pv             = readFloat($VID_PV);
$house          = readFloat($VID_HOUSE);
$battSOC        = (int) GetValue($VID_BATT_SOC);
$battPower      = readFloat($VID_BATT_POWER);
$wb1Actual      = readFloat($VID_WB1_RD);
$wb2Actual      = readFloat($VID_WB2_RD);
$grid           = readFloat($VID_GRID);
$wb1Connected   = readBool($VID_WB1_CONNECTED);
$wb2Connected   = readBool($VID_WB2_CONNECTED);

$hour           = (int) date('G');
$mode           = (int) GetValue($varMode);
$zeitAktiv      = (bool) GetValue($varZeit);

// ---------------- Energiemanagement: verfügbare Leistung ----------------
$available_kW = 0.0;

switch ($mode) {
    case 1:
        if ($battSOC >= 80) {
            $available_kW = max(0, $pv - $house);
        }
        break;

    case 2:
        if ($battSOC >= 20) {
            $available_kW = max(0, $pv - $house + 5);
        }
        break;

    case 3:
        $available_kW = $MAX_HAUS_KW - $house;
        break;
}

$available_kW = max(0, min($available_kW, $MAX_HAUS_KW - $house));

// ---------------- Wärmepumpe ----------------
$pvUeberschuss = $pv - $house - $wb1Actual - $wb2Actual;
$heatpumpPower = 0.0;

if ($pvUeberschuss > $PV_MIN_UEBERSCHUSS && $battSOC >= $BATT_FULL) {
    $heatpumpPower = $HEATPUMP_POWER;
    RequestAction($VID_HEATPUMP, $heatpumpPower);
    $heatpumpState = 'Ein (' . number_format($heatpumpPower, 1, ',', '.') . ' kW)';
} else {
    RequestAction($VID_HEATPUMP, 0);
    $heatpumpState = 'Aus';
}

// ---------------- Fair-Share Wallboxen ----------------
$wbShares = ['wb1' => 0.0, 'wb2' => 0.0];
$activeWB = 0;

if ($wb1Connected) {
    $activeWB++;
}
if ($wb2Connected) {
    $activeWB++;
}

if ($activeWB > 0) {
    $share = $available_kW / $activeWB;

    if ($zeitAktiv && $hour >= $PRIO_START && $hour < $PRIO_END && $wb1Connected && $wb2Connected) {
        $wbShares['wb1'] = $share * 1.5;
        $wbShares['wb2'] = $share * 0.5;
    } else {
        $wbShares['wb1'] = $wb1Connected ? $share : 0.0;
        $wbShares['wb2'] = $wb2Connected ? $share : 0.0;
    }

    $wbShares['wb1'] = min($wbShares['wb1'], $WB_MAX['wb1']);
    $wbShares['wb2'] = min($wbShares['wb2'], $WB_MAX['wb2']);
}

// ---------------- Hausanschluss absichern ----------------
$totalPlanned = $house + $heatpumpPower + $wbShares['wb1'] + $wbShares['wb2'];

if ($totalPlanned > $MAX_HAUS_KW && ($wbShares['wb1'] + $wbShares['wb2']) > 0) {
    $factor = ($MAX_HAUS_KW - $house - $heatpumpPower) / ($wbShares['wb1'] + $wbShares['wb2']);
    $factor = max(0, min(1, $factor));
    $wbShares['wb1'] *= $factor;
    $wbShares['wb2'] *= $factor;
}

$totalPlanned = $house + $heatpumpPower + $wbShares['wb1'] + $wbShares['wb2'];

// ---------------- Wallboxen schreiben ----------------
RequestAction($VID_WB1_WR, $wb1Connected ? $wbShares['wb1'] : 0);
RequestAction($VID_WB2_WR, $wb2Connected ? $wbShares['wb2'] : 0);

// ---------------- Monitoring: Verbrauch berechnen ----------------
$verbrauchBerechnet = $battPower + $heatpumpPower + $house + $wb1Actual + $wb2Actual - $pv;
SetValueFloat($VID_RESULT, $verbrauchBerechnet);

$delta = $grid - $verbrauchBerechnet;

// ---------------- Prüfungen ----------------
$checks = [];

if ($house < 0 || $wb1Actual < 0 || $wb2Actual < 0 || $heatpumpPower < 0) {
    $checks[] = 'Negative Leistungswerte erkannt';
}
if ($pv > 0.5 && $battPower <= 0 && $grid <= 0) {
    $checks[] = 'PV aktiv, aber weder Netz- noch Batteriespeisung';
}
if ($house < 0.1) {
    $checks[] = 'Hausverbrauch extrem niedrig';
}
if ($totalPlanned > $MAX_HAUS_KW) {
    $checks[] = 'Gesamtlast überschreitet ' . number_format($MAX_HAUS_KW, 1, ',', '.') . ' kW';
}
if (!$wb1Connected && $wb1Actual > 0.1) {
    $checks[] = 'WB1 liefert Leistung, obwohl nicht verbunden';
}
if (!$wb2Connected && $wb2Actual > 0.1) {
    $checks[] = 'WB2 liefert Leistung, obwohl nicht verbunden';
}
if (abs($delta) > $TOLERANZ && empty($checks)) {
    $checks[] = 'Differenz zwischen Netzbezug und berechnetem Verbrauch: ' . number_format(abs($delta), 2, ',', '.') . ' kW';
}

// ---------------- Status bestimmen ----------------
if (abs($delta) <= $TOLERANZ) {
    $shortStatus  = 'OK';
    $statusColor  = 'green';
    $currentState = 0;
} else {
    $shortStatus  = 'Fehler';
    $statusColor  = 'red';
    $currentState = 1;
}

// ---------------- Verlauf aktualisieren ----------------
$entries = [];
$rawData = GetValueString($varHistoryData);

if ($rawData !== '') {
    $decoded = json_decode($rawData, true);
    if (is_array($decoded)) {
        $entries = $decoded;
    }
}

$detailText   = implode(' | ', $checks ?: ['✓']);
$prevState    = (int) GetValue($varState);
$updateNeeded = true;

if (!empty($entries)) {
    $lastEntry = $entries[0];
    $sameState = isset($lastEntry['status']) && $lastEntry['status'] === $shortStatus;
    $sameText  = isset($lastEntry['details']) && $lastEntry['details'] === $detailText;
    $sameFlag  = ($prevState === $currentState);

    if ($sameState && $sameText && $sameFlag) {
        $updateNeeded = false;
    }
}

$timestamp = date('d.m.Y H:i:s');

if ($updateNeeded) {
    SetValueInteger($varState, $currentState);

    array_unshift($entries, [
        'time'    => $timestamp,
        'status'  => $shortStatus,
        'color'   => $statusColor,
        'details' => $detailText
    ]);

    $entries = array_slice($entries, 0, $MAX_LOG_ENTRIES);
    SetValueString($varHistoryData, json_encode($entries));
}

// ---------------- Dashboard HTML ----------------
switch ($mode) {
    case 1:
        $modeText = 'PV nur (≥80 %)';
        $modeColor = '#4CAF50';
        break;
    case 2:
        $modeText = 'PV + Speicher (≥20 %)';
        $modeColor = '#FFD700';
        break;
    case 3:
        $modeText = 'Volllast (Netz erlaubt)';
        $modeColor = '#FF8C00';
        break;
    default:
        $modeText = 'Unbekannt';
        $modeColor = '#999999';
        break;
}

if (!$zeitAktiv) {
    $zeitText = 'Deaktiviert';
} elseif ($hour >= $PRIO_START && $hour < $PRIO_END) {
    $zeitText = 'Aktiv (08–18 Uhr)';
} else {
    $zeitText = 'Aktiv (außerhalb)';
}

$dashboard  = '<div style="font-family:Segoe UI, sans-serif; padding:10px;">';
$dashboard .= '<h2 style="margin-bottom:10px;">Lademanagement Übersicht</h2>';
$dashboard .= '<table style="width:100%; border-collapse:collapse;">';
$dashboard .= '<tr><td>Systemzeit:</td><td><b>' . date('H:i') . ' Uhr</b></td></tr>';
$dashboard .= '<tr><td>PV-Leistung:</td><td><b>' . number_format($pv, 1, ',', '.') . ' kW</b></td></tr>';
$dashboard .= '<tr><td>Hausverbrauch:</td><td><b>' . number_format($house, 1, ',', '.') . ' kW</b></td></tr>';
$dashboard .= '<tr><td>Batterie-SOC:</td><td><b>' . $battSOC . ' %</b></td></tr>';
$dashboard .= '<tr><td>Netzbezug:</td><td><b>' . number_format($grid, 2, ',', '.') . ' kW</b></td></tr>';
$dashboard .= '<tr><td>Berechneter Verbrauch:</td><td><b>' . number_format($verbrauchBerechnet, 2, ',', '.') . ' kW</b></td></tr>';
$dashboard .= '<tr><td>Wärmepumpe:</td><td><b>' . $heatpumpState . '</b></td></tr>';
$dashboard .= '<tr><td>Wallbox 1:</td><td><b>' . number_format($wbShares['wb1'], 1, ',', '.') . ' kW</b></td></tr>';
$dashboard .= '<tr><td>Wallbox 2:</td><td><b>' . number_format($wbShares['wb2'], 1, ',', '.') . ' kW</b></td></tr>';
$dashboard .= '<tr><td>Gesamtleistung geplant:</td><td><b>' . number_format($totalPlanned, 1, ',', '.') . ' kW</b></td></tr>';
$dashboard .= '<tr><td>Status:</td><td><b style="color:' . $statusColor . ';">' . $shortStatus . '</b></td></tr>';
$dashboard .= '<tr><td>Lademodus:</td><td><b style="color:' . $modeColor . ';">' . $modeText . '</b></td></tr>';
$dashboard .= '<tr><td>Zeitfenster:</td><td><b>' . $zeitText . '</b></td></tr>';
$dashboard .= '<tr><td>Prüfungen:</td><td><b>' . implode('<br>', $checks ?: ['✓']) . '</b></td></tr>';
$dashboard .= '</table>';
$dashboard .= '<div style="margin-top:6px; font-size:11px; color:gray;">Stand: ' . date('d.m.Y H:i') . ' Uhr</div>';
$dashboard .= '</div>';

SetValueString($varDashboard, $dashboard);

// ---------------- Verlauf HTML ----------------
$rows = '';
foreach ($entries as $entry) {
    $detailsHtml = nl2br(htmlEscape(str_replace(' | ', "\n", $entry['details'])));
    $rows .= '<tr>'
        . '<td style="padding:6px; border-bottom:1px solid #ddd;">' . htmlEscape($entry['time']) . '</td>'
        . '<td style="padding:6px; border-bottom:1px solid #ddd; color:' . htmlEscape($entry['color']) . ';"><b>' . htmlEscape($entry['status']) . '</b></td>'
        . '<td style="padding:6px; border-bottom:1px solid #ddd;">' . $detailsHtml . '</td>'
        . '</tr>';
}

$historyHTML  = '<div style="font-family:Segoe UI, sans-serif; padding:10px;">';
$historyHTML .= '<h3 style="margin:0 0 10px 0;">Energie-Verlauf</h3>';
$historyHTML .= '<table style="width:100%; border-collapse:collapse; table-layout:auto;">';
$historyHTML .= $rows;
$historyHTML .= '</table>';
$historyHTML .= '<div style="font-size:11px; color:gray;">Stand: ' . $timestamp . '</div>';
$historyHTML .= '</div>';

SetValueString($varHistoryHTML, $historyHTML);

echo 'Energiesystem aktualisiert um ' . date('H:i:s') . PHP_EOL;
?>