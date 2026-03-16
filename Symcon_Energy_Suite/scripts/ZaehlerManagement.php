<?php
/*
    Zähler-Management
    - verwendet EASTRON-Zähler als Referenzvariable
    - skaliert anteilig auf Z1–Z10
    - archiviert automatisch alle Zähler
    - erzeugt Dashboard mit HTML-Tabelle
    - prüft Verteilung und Differenz
    Programmierer: Mike Dorr
    Projekt: HVG241 Meisterprüfung
*/

// ---------------- Konfiguration ----------------
$REF_VAR_ID = 42907;   // Referenz: Messwandler-Leistung gesamt
$PV_VAR_ID  = 37381;   // PV-Leistung
$catName    = "Messkonzept_Skalierung_Historie";
$parentID   = 0;

// Prozentuale Verteilung für Unterzähler
$distribution = [
    'Meter4' => 30,
    'Meter5' => 25,
    'Meter6' => 5,
    'Meter7' => 8,
    'Meter8' => 12,
    'Meter9' => 20
];

// Anzeigenamen
$labels = [
    'Meter1'  => 'Z1_Messwandler [kW]',
    'Meter2'  => 'Z2_Bezug_Einspeisung [kW]',
    'Meter3'  => 'Z3_PV_Einspeisung [kW]',
    'Meter4'  => 'Z4_Allgemeinverbrauch [kW]',
    'Meter5'  => 'Z5_Mieter1_EG [kW]',
    'Meter6'  => 'Z6_Mieter2_1.OG [kW]',
    'Meter7'  => 'Z7_Mieter3_1.OG [kW]',
    'Meter8'  => 'Z8_Mieter4_2.OG [kW]',
    'Meter9'  => 'Z9_Mieter5_2.OG [kW]',
    'Meter10' => 'Z10_Differenz [kW]'
];

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

function html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ---------------- Vorprüfungen ----------------
if (!IPS_VariableExists($REF_VAR_ID)) {
    echo "Referenzvariable nicht gefunden: $REF_VAR_ID\n";
    return;
}

if (!IPS_VariableExists($PV_VAR_ID)) {
    echo "PV-Variable nicht gefunden: $PV_VAR_ID\n";
    return;
}

// ---------------- Kategorie anlegen/suchen ----------------
$catID = ensureCategory($catName, $parentID);

// ---------------- Zählervariablen anlegen/suchen ----------------
$varIDs = [];
foreach ($labels as $key => $label) {
    $varIDs[$key] = ensureVariable($label, 2, $catID, '~Power');
}

// HTML-Dashboard anlegen
$htmlID = ensureVariable('Zähler_Dashboard', 3, $catID, '~HTMLBox');

// ---------------- Archivierung aktivieren ----------------
$archives = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}");
if (count($archives) > 0) {
    $archiveID = $archives[0];

    foreach ($varIDs as $vid) {
        if (!AC_GetLoggingStatus($archiveID, $vid)) {
            AC_SetLoggingStatus($archiveID, $vid, true);
        }

        if (AC_GetAggregationType($archiveID, $vid) != 1) {
            AC_SetAggregationType($archiveID, $vid, 1);
        }
    }

    IPS_ApplyChanges($archiveID);
} else {
    IPS_LogMessage('ZaehlerManagement', 'Keine Archivinstanz gefunden');
}

// ---------------- Werte einlesen ----------------
$refValue = (float) GetValue($REF_VAR_ID);
$pvValue  = (float) GetValue($PV_VAR_ID);

// ---------------- Verteilung prüfen ----------------
$distributionSum = array_sum($distribution);
$distributionOk  = abs($distributionSum - 100.0) < 0.001;

// ---------------- Skalierung durchführen ----------------
$sumSub = 0.0;

foreach ($distribution as $key => $pct) {
    $scaled = $refValue * ($pct / 100.0);
    SetValueFloat($varIDs[$key], $scaled);
    $sumSub += $scaled;
}

$balance = $refValue - $sumSub;
$bezug   = $refValue - $pvValue;

// Hauptzähler / Zusatzwerte setzen
SetValueFloat($varIDs['Meter1'], $refValue);
SetValueFloat($varIDs['Meter2'], $bezug);
SetValueFloat($varIDs['Meter3'], $pvValue);
SetValueFloat($varIDs['Meter10'], $balance);

// ---------------- Prüfungen ----------------
$checks = [];

if (!$distributionOk) {
    $checks[] = 'Prozentverteilung ergibt nicht 100 %, sondern ' . number_format($distributionSum, 2, ',', '.') . ' %';
}

if (abs($balance) > 0.01) {
    $checks[] = 'Differenzzähler ungleich 0: ' . number_format($balance, 3, ',', '.') . ' kW';
}

if ($pvValue < 0) {
    $checks[] = 'PV-Wert ist negativ';
}

$statusText  = empty($checks) ? 'OK' : 'Prüfen';
$statusColor = empty($checks) ? '#2E7D32' : '#F57C00';

// ---------------- HTML-Dashboard erzeugen ----------------
$htmlOut  = '<div style="font-family:Segoe UI, sans-serif; padding:10px;">';
$htmlOut .= '<div style="background-color:' . $statusColor . '; color:white; padding:12px; font-size:16px; font-weight:bold; text-align:center; border-radius:5px; margin-bottom:12px;">';
$htmlOut .= 'Zähler-Dashboard: ' . $statusText;
$htmlOut .= '</div>';

$htmlOut .= '<table style="width:100%; border-collapse:collapse;">';
$htmlOut .= '<tr>';
$htmlOut .= '<th style="text-align:left; padding:6px; border-bottom:1px solid #ccc;">Name</th>';
$htmlOut .= '<th style="text-align:right; padding:6px; border-bottom:1px solid #ccc;">Wert</th>';
$htmlOut .= '</tr>';

$sortedEntries = [];
foreach ($labels as $key => $name) {
    if (preg_match('/^Z(\d+)_/', $name, $m)) {
        $sortedEntries[(int)$m[1]] = ['name' => $name, 'id' => $varIDs[$key]];
    }
}
ksort($sortedEntries);

foreach ($sortedEntries as $entry) {
    $name  = html($entry['name']);
    $value = html(GetValueFormatted($entry['id']));

    $htmlOut .= '<tr>';
    $htmlOut .= '<td style="padding:6px; border-bottom:1px solid #eee;">' . $name . '</td>';
    $htmlOut .= '<td style="padding:6px; text-align:right; border-bottom:1px solid #eee;"><b>' . $value . '</b></td>';
    $htmlOut .= '</tr>';
}

$htmlOut .= '</table>';

$htmlOut .= '<h3 style="margin:14px 0 8px 0;">Zusatzinformationen</h3>';
$htmlOut .= '<table style="width:100%; border-collapse:collapse;">';
$htmlOut .= '<tr><td style="padding:4px 8px;">Referenzwert</td><td style="text-align:right;"><b>' . number_format($refValue, 2, ',', '.') . ' kW</b></td></tr>';
$htmlOut .= '<tr><td style="padding:4px 8px;">PV-Wert</td><td style="text-align:right;"><b>' . number_format($pvValue, 2, ',', '.') . ' kW</b></td></tr>';
$htmlOut .= '<tr><td style="padding:4px 8px;">Verteilungssumme</td><td style="text-align:right;"><b>' . number_format($distributionSum, 2, ',', '.') . ' %</b></td></tr>';
$htmlOut .= '<tr><td style="padding:4px 8px;">Differenz</td><td style="text-align:right;"><b>' . number_format($balance, 3, ',', '.') . ' kW</b></td></tr>';
$htmlOut .= '</table>';

$htmlOut .= '<h3 style="margin:14px 0 8px 0;">Prüfungen</h3>';
if (empty($checks)) {
    $htmlOut .= '<div style="color:green;"><b>✓ Keine Auffälligkeiten</b></div>';
} else {
    $htmlOut .= '<ul style="margin:0; padding-left:20px;">';
    foreach ($checks as $check) {
        $htmlOut .= '<li>' . html($check) . '</li>';
    }
    $htmlOut .= '</ul>';
}

$htmlOut .= '<div style="margin-top:10px; font-size:11px; color:gray;">Stand: ' . date('d.m.Y H:i:s') . '</div>';
$htmlOut .= '</div>';

SetValueString($htmlID, $htmlOut);

// ---------------- Ausgabe ----------------
echo 'Zähler-Management aktualisiert um ' . date('H:i:s') . ' (Referenz: ' . number_format($refValue, 2, ',', '.') . " kW)\n";
?>