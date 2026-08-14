# Monitoring de sécurité

## Objectif

Conserver une trace exploitable des risques sans stocker des credentials.

## Activation et configuration

Activer `features.monitoring` et brancher `SecurityMonitoringStoreInterface`; définir rétention et lecteurs.

## Contrat et flux

`record()` délègue type, sévérité, détails et `occurred_at` ISO-8601.

## Exemple

```php
$monitoring->record($id, 'refresh_replay', 'high', ['route' => 'auth_refresh']);
```

## Sécurité et erreurs

Redacter tokens/mots de passe, borner détails, contrôler sévérités et accès.

## Validation

Tester événements de rejeu, échec, révocation, TOTP et appareil.
