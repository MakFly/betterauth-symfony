# Appareils

## Objectif

Enregistrer un appareil comme signal de risque et aide à la revue de sessions.

## Activation et configuration

Activer `features.device` et fournir `DeviceStoreInterface`.

## Contrat et flux

`record()` trie les attributs, calcule SHA-256 et transmet fingerprint, user-agent et IP au store.

## Exemple

```php
$fingerprint = $devices->record($id, $ua, $ip, ['platform' => 'web']);
```

## Sécurité et erreurs

Fingerprint jamais mot de passe/facteur unique ; minimiser rétention IP/UA et protéger revue/révocation.

## Validation

Tester stabilité, variation d’attributs, rétention et absence de credentials.
