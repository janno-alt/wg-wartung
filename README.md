# WG Wartung

Verbindung zwischen Kundenseiten und dem Servicebereich des WG-Digital-CRM. Das CRM fragt den Stand von WordPress, PHP und Plugins ab, spielt Plugin-Updates ein und installiert über die WG Suite freigegebene WG-Plugins wie WG Cache. Jede Anfrage ist mit dem Ed25519-Schlüssel des CRM signiert; die Seite kennt nur den öffentlichen Teil (`WG_WARTUNG_CRM_SCHLUESSEL`).

## Schnittstelle

Nur POST, nur mit gültiger Signatur, sonst antwortet die Seite mit 401.

| Route | Inhalt | Was passiert |
| --- | --- | --- |
| `/wp-json/wg-wartung/v1/status` | `{}` | Stand von WordPress, PHP, Theme und Plugins, dazu was diese Version kann (`kann`), der Stand der WG Suite und von WG Cache |
| `/wp-json/wg-wartung/v1/plugin-update` | `{"datei":"ordner/datei.php"}` | Update genau eines Plugins aus seiner eigenen Update-Quelle, danach wieder einschalten, wenn es vorher aktiv war |
| `/wp-json/wg-wartung/v1/plugin-install` | `{"ordner":"wg-cache"}` | Installiert und aktiviert ein WG-Plugin über die WG Suite der Seite; bei WG Cache folgt sofort dessen Selbsttest |
| `/wp-json/wg-wartung/v1/plugin-deactivate` | `{"ordner":"wg-cache"}` | Schaltet ein WG-Plugin aus dem Katalog der Suite ab, ohne es zu löschen |
| `/wp-json/wg-wartung/v1/cache-selbsttest` | `{}` | Selbsttest von WG Cache |

Ohne schöne Permalinks gilt jeweils `/?rest_route=/wg-wartung/v1/…`.

## Sicherheit

- Die Signatur deckt Methode, Host, Route, Zeit, Einmal-Kennung und den Inhalt ab. Sie gilt fünf Minuten und jede Einmal-Kennung nur einmal.
- Updates kommen nur aus der Update-Quelle, die WordPress für das Plugin selbst kennt. Ohne Paket (etwa ohne Lizenz) gibt es kein Update aus der Ferne.
- Installieren lässt sich nur, was die WG Suite für diese Seite freigibt, und nur als Paket vom eingestellten Suite-Server mit gültiger Signatur des Release-Schlüssels. Die Prüfung macht die Suite selbst, auch im Weg über die REST-Schnittstelle. WG Wartung lädt keinen Code selbst herunter.
- WG Wartung und die WG Suite lassen sich aus der Ferne weder installieren noch abschalten.

## Release

Wie bei allen WG-Plugins über `~/MCP/WG-Digital/wg-release.sh ~/Documents/wg-wartung <version>`. Bekommt das CRM einen neuen Schlüssel, braucht es eine neue Version mit dem neuen öffentlichen Schlüssel in `wg-wartung.php`.
