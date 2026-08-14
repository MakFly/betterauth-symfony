# Multi-tenancy

## Objectif

Centraliser l’appartenance sans relâcher l’isolation des données.

## Activation et configuration

Activer `features.multi_tenant` et brancher `TenantMembershipStoreInterface`.

## Contrat et flux

`allows($userId, $tenantId)` délègue à `hasMembership()` après résolution fiable du tenant.

## Exemple

```php
$tenant = $resolver->fromRequest($request);
if (!$tenants->allows($id, $tenant->id)) throw $denied;
```

## Sécurité et erreurs

Ne jamais faire confiance au seul claim client ; vérifier chaque lecture/écriture et les clés de cache.

## Validation

Tester tenant absent, membre, non-membre et claim croisé.
