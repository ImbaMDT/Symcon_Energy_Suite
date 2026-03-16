<?php
/*
    Energiemonitoring mit Verlauf
    - Verbrauchsberechnung
    - Prüfungen
    - Verlauf mit Statuswechsel-Erkennung oder geänderten Details
*/

$vid_batt      = 53609;
$vid_heatpump  = 42994;
$vid_house     = 37163;
$vid_wb1       = 17849;
$vid_wb2       = 12904;
$vid_grid      = 55216;
$vid_pv        = 37381;
$vid_wb1_conn  = 11167;
$vid_wb2_conn  = 31450;
$vid_result    = 56622;

$anschluss_max = 60.0;
$toleranz      = 0.01;
$max_entries   = 50;

$monitorCatName = 'Energie-Monitoring';
$stateVarName   = 'Energie_StatusIntern';
$dataVarName    = 'Energie_Verlauf_Daten';
$htmlVarName    = 'Energie-Verlauf';

$selfID   = $_IPS['SELF'];
$parentID = IPS_GetObject($selfID)['ParentID'];

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

function ensureVariable(string $name, int $type, int $parentID, string $profile = ''): int
{
    $id = @IPS_GetVariableIDByName($name, $parentID);
    if ($id === false) {
        $id = IPS_CreateVariable($type);
        IPS_SetName($id, $name);
        IPS_SetParent($id, $parentID);
        if ($profile !== '') {
            IPS_SetVariableCustomProfile($id, $profile);
        }
    }
    return $id;
}

function readFloatSafe(int $id): float
{
    if (!IPS_VariableExists($id)) {
        throw new Exception('Variable fehlt: ' . $id);
    }
    return (float) GetValue($id);
}

function readBoolSafe(int $id): bool
{
    if (!IPS_VariableExists($id)) {
        throw new Exception('Variable fehlt: ' . $id);
    }
    return (bool) GetValue($id);
}

$monitorCatID = ensureCategory($monitorCatName, $parentID);
$stateID      = ensureVariable($stateVarName, 1, $monitorCatID);
$dataID       = ensureVariable($dataVarName, 3, $monitorCatID);
$logID        = ensureVariable($htmlVarName, 3, $monitorCatID, '~HTMLBox');

if (!IPS_VariableExists($vid_result)) {
    throw new Exception('Ergebnisvariable fehlt: ' . $vid_result);
}

// Werte lesen
$batterie = readFloatSafe($vid_batt);
$heatpump = readFloatSafe($vid_heatpump);
$house    = readFloatSafe($vid_house);
$wallbox1 = readFloatSafe($vid_wb1);
$wallbox2 = readFloatSafe($vid_wb2);
$grid     = readFloatSafe($vid_grid);
$pv       = readFloatSafe($vid_pv);
$wb1Conn  = readBoolSafe($vid_wb1_conn);
$wb2Conn  = readBoolSafe($vid_wb2_conn);

// Verbrauch berechnen
$verbrauch = $batterie + $heatpump + $house + $wallbox1 + $wallbox2 - $pv;
SetValueFloat($vid_result, $verbrauch);

$delta       = $grid - $verbrauch;
$gesamtLast  = $house + $heatpump + $wallbox1 + $wallbox2;
$aktZeit     = date('d.m.Y H:i:s');
$fDelta      = number_format(abs($delta), 2, ',', '.') . ' kW';

// Status bestimmen
if (abs($delta) <= $toleranz) {
    $shortStatus  = 'OK';
    $currentState = 0;
    $color        = 'green';
    $richtung     = '';
} else {
    $shortStatus  = 'Fehler';
    $currentState = 1;
    $color        = 'red';
    $richtung     = ($delta > 0) ? 'Weniger Verbrauch als Netzbezug' : 'Mehr Verbrauch als Netzbezug';
}

// Prüfungen
$checks = [];

if ($heatpump < 0 || $house < 0 || $wallbox1 < 0 || $wallbox2 < 0) {
    $checks[] = 'Negative Leistungswerte erkannt';
}
if ($pv > 0.5 && $batterie <= 0 && $grid <= 0) {
    $checks[] = 'PV aktiv, aber weder Netz- noch Batteriespeisung';
}
if ($house < 0.1) {
    $checks[] = 'Hausverbrauch extrem niedrig';
}
if ($gesamtLast > $anschluss_max) {
    $checks[] = 'Gesamtlast überschreitet ' . number_format($anschluss_max, 1, ',', '.') . ' kW';
}
if (!$wb1Conn && $wallbox1 > 0.1) {
    $checks[] = 'WB1 liefert Leistung, obwohl nicht verbunden';
}
if (!$wb2Conn && $wallbox2 > 0.1) {
    $checks[] = 'WB2 liefert Leistung, obwohl nicht verbunden';
}
if (empty($checks) && $currentState === 1) {
    $checks[] = 'Differenz überschreitet Toleranz (Δ = ' . $fDelta . ')';
}

// Vorhandene Verlaufdaten laden
$entries = [];
$raw = GetValueString($dataID);
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $entries = $decoded;
    }
}

$prevState   = GetValueInteger($stateID);
$detailText  = implode(' | ', $checks ?: ['✓']);
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

if ($updateNeeded) {
    SetValueInteger($stateID, $currentState);

    array_unshift($entries, [
        'time'    => $aktZeit,
        'status'  => $shortStatus,
        'color'   => $color,
        'details' => $detailText
    ]);

    $entries = array_slice($entries, 0, $max_entries);
    SetValueString($dataID, json_encode($entries));
}

// HTML erzeugen
$rows = '';
foreach ($entries as $entry) {
    $detailsHtml = htmlspecialchars($entry['details']);
    $detailsHtml = str_replace(' | ', '<br>', $detailsHtml);

    $rows .= '<tr>'
        . '<td style="padding:6px; border-bottom:1px solid #ddd;">' . htmlspecialchars($entry['time']) . '</td>'
        . '<td style="padding:6px; border-bottom:1px solid #ddd; color:' . htmlspecialchars($entry['color']) . ';"><b>' . htmlspecialchars($entry['status']) . '</b></td>'
        . '<td style="padding:6px; border-bottom:1px solid #ddd;">' . $detailsHtml . '</td>'
        . '</tr>';
}

$html = '<div style="font-family:Segoe UI, sans-serif; padding:10px;">'
    . '<table style="width:100%; border-collapse:collapse; table-layout:auto;">'
    . $rows
    . '</table>'
    . '<div style="font-size:11px; color:gray;">Stand: ' . $aktZeit . '</div>'
    . '</div>';

SetValueString($logID, $html);
?>