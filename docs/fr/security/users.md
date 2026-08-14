# Utilisateurs

## Objectif

Brancher les utilisateurs de l’application à Symfony Security sans imposer d’ORM.

## Activation et configuration

Le provider implémente `loadUserByIdentifier()` pour `better_auth.user_id_claim` (par défaut `sub`). Les interfaces `AuthUserInterface` et `PasswordHolderInterface` sont optionnelles.

## Contrat et flux

Après vérification, l’authenticator transmet l’identifiant au provider et produit un passport auto-validé.

## Exemple

```php
final class User implements UserInterface, AuthUserInterface, PasswordHolderInterface
{
    public function getUserIdentifier(): string { return $this->id; }
}
```

## Sécurité et erreurs

Identifiants stables non sensibles ; jamais de mot de passe dans les claims. Utilisateur absent : refus non énumérant.

## Validation

Tester lookup, utilisateur supprimé, rôles, `eraseCredentials()` et identifiant absent.
