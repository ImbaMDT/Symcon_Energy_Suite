<?php
/*
    Sicherheitsstatus
    - Erstellt automatisch HTMLBox mit BMA/EMA Meldungen
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
function alarmRow(string $label, ?bool $state, string $colorOn, string $colorOff): string
{
    if ($state === null) {
        return "<tr style='background-color:#9E9E9E; color:white; font-weight:bold;'>
                    <td style='padding:12px;'>$label</td>
                    <td style='padding:12px; text-align:right;'>NICHT VERFÜGBAR</td>
                </tr>";
    }

    $bgColor = $state ? $colorOn : $colorOff;
    $text    = $state ? "ALARM!" : "OK";

    return "<tr style='background-color:$bgColor; color:white; font-weight:bold;'>
                <td style='padding:12px;'>$label</td>
                <td style='padding:12px; text-align:right;'>$text</td>
            </tr>";
}

function safeBoolValue(int $variableID): ?bool
{
    if (!IPS_VariableExists($variableID)) {
        return null;
    }
    return GetValueBoolean($variableID);
}

// ---------------- Sicherheitsvariablen ----------------
$VID_INTRUSION = 38398; // Einbruchmelder
$VID_WATER     = 58335; // Wassermelder
$VID_FIRE      = 36472; // Brandmelder

// ---------------- HTMLBox prüfen/erstellen ----------------
$htmlName = "Sicherheitsübersicht";
$VID_HTML = @IPS_GetVariableIDByName($htmlName, $parentID);
if ($VID_HTML === false) {
    $VID_HTML = IPS_CreateVariable(3); // String
    IPS_SetName($VID_HTML, $htmlName);
    IPS_SetParent($VID_HTML, $parentID);
    IPS_SetVariableCustomProfile($VID_HTML, "~HTMLBox");
    IPS_LogMessage("Sicherheit", "HTMLBox '$htmlName' wurde automatisch erstellt (ID: $VID_HTML)");
}

// ---------------- Statuswerte einlesen ----------------
$status_intrusion = safeBoolValue($VID_INTRUSION);
$status_water     = safeBoolValue($VID_WATER);
$status_fire      = safeBoolValue($VID_FIRE);

// ---------------- Zentralstatus ----------------
$states = [$status_intrusion, $status_water, $status_fire];
$availableStates = array_filter($states, function ($value) {
    return $value !== null;
});

$any_alarm = in_array(true, $availableStates, true);
$has_error = count($availableStates) !== count($states);

if ($any_alarm) {
    $central_bg  = "#B71C1C";
    $central_txt = "ALARM AKTIV";
} elseif ($has_error) {
    $central_bg  = "#F57C00";
    $central_txt = "STÖRUNG / SENSOR PRÜFEN";
} else {
    $central_bg  = "#2E7D32";
    $central_txt = "Alle Systeme OK";
}

// ---------------- HTML generieren ----------------
$html  = '<div style="font-family:Segoe UI, sans-serif; padding:10px;">';
$html .= "<div style='background-color:$central_bg; color:white; padding:14px; font-size:18px; font-weight:bold; text-align:center; border-radius:5px; margin-bottom:15px;'>$central_txt</div>";
$html .= '<h3 style="margin-bottom:10px;">Einzelmeldungen</h3>';
$html .= '<table style="width:100%; border-collapse:collapse; border-radius:4px; overflow:hidden;">';
$html .= alarmRow("Einbruchmeldeanlage", $status_intrusion, "#FF9800", "#4CAF50");
$html .= alarmRow("Wassermelder",        $status_water,     "#2196F3", "#4CAF50");
$html .= alarmRow("Brandmeldeanlage",    $status_fire,      "#F44336", "#4CAF50");
$html .= '</table>';
$html .= '<div style="margin-top:6px; font-size:11px; color:gray;">Stand: ' . date("d.m.Y H:i") . ' Uhr</div>';
$html .= '</div>';

SetValueString($VID_HTML, $html);
?>