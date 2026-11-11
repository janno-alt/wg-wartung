# WG Wartung

Verbindung zwischen Kundenseiten und dem Servicebereich des WG-Digital-CRM. Das CRM fragt den Stand von WordPress, PHP und Plugins ab und spielt Plugin-Updates ein. Jede Anfrage ist mit dem Ed25519-Schlüssel des CRM signiert; die Seite kennt nur den öffentlichen Teil (`WG_WARTUNG_CRM_SCHLUESSEL`).

Schnittstelle (nur POST, nur mit Signatur): `/wp-json/wg-wartung/v1/status`, `/wp-json/wg-wartung/v1/plugin-update` mit `{"datei":"ordner/datei.php"}`.
