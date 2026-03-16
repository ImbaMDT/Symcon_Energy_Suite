<?php
/*
Importiert Scripte und Bilder ohne den Modulstore oder das Internet benutzen zu müssen
*/
declare(strict_types=1);

class Symcon_Energy_Suite extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Timer ist vorbereitet, aber aktuell nicht aktiv (0ms = deaktiviert)
        $this->RegisterTimer('DeployScriptsTimer', 0, 'SDP_DeployScripts($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SendDebug('Symcon_Energy_Suite', 'Starte automatisches Skript-Deployment...', 0);

        // ---------------- Skripte importieren ----------------
        $moduleDir = __DIR__ . DIRECTORY_SEPARATOR . 'scripts';

        if (!is_dir($moduleDir)) {
            $this->SendDebug('Symcon_Energy_Suite', 'Scripts-Verzeichnis nicht gefunden: ' . $moduleDir, 0);
        } else {
            $files = scandir($moduleDir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $ident = pathinfo($file, PATHINFO_FILENAME);
                    $existingID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

                    if ($existingID === false) {
                        $scriptID = IPS_CreateScript(0);
                        IPS_SetName($scriptID, $ident);
                        IPS_SetIdent($scriptID, $ident);
                        IPS_SetParent($scriptID, $this->InstanceID);

                        $code = file_get_contents($moduleDir . DIRECTORY_SEPARATOR . $file);
						if ($code === false) {
							$this->SendDebug(...);
							continue;
						}
                        IPS_SetScriptContent($scriptID, $code);

                        $this->SendDebug('Symcon_Energy_Suite', "Skript '$ident' neu erstellt (ID: $scriptID)", 0);
                    } else {
                        $this->SendDebug('Symcon_Energy_Suite', "Skript '$ident' existiert bereits (ID: $existingID)", 0);
                    }
                }
            }
        }

        // ---------------- Medien (Bilder) importieren ----------------
        $mediaDir = __DIR__ . DIRECTORY_SEPARATOR . 'media';
        if (!is_dir($mediaDir)) {
            $this->SendDebug('Symcon_Energy_Suite', 'Medien-Verzeichnis nicht gefunden: ' . $mediaDir, 0);
            return;
        }

        $mediaFiles = scandir($mediaDir);
        foreach ($mediaFiles as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $ident = pathinfo($file, PATHINFO_FILENAME);
                $existingID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

                if ($existingID === false) {
                    $mediaID = IPS_CreateMedia(1); // 1 = Image
                    IPS_SetMediaFile($mediaID, $mediaDir . DIRECTORY_SEPARATOR . $file, true);
                    IPS_SetName($mediaID, $ident);
                    IPS_SetIdent($mediaID, $ident);
                    IPS_SetParent($mediaID, $this->InstanceID);

                    $this->SendDebug('Symcon_Energy_Suite', "Bild '$ident' importiert (ID: $mediaID)", 0);
                } else {
                    $this->SendDebug('Symcon_Energy_Suite', "Bild '$ident' bereits vorhanden (ID: $existingID)", 0);
                }
            }
        }
    }
}
